// Load every main Knowledge Base page and every Knowledge Base ACP module as
// the seeded administrator, plus the ACP permission trace, and fail on a
// server error, a PHP error or
// phpBB's SQL error page. Then check two refusals: an ACP category move link
// without its hash, and the approve page for the seeded kb_reader. Read-only:
// it opens pages and submits nothing but the login forms, so it suits any
// seeded seed-forum board on any database.
//
// KB_TEST_URL is the board's base URL, without a trailing slash. Routes go
// through app.php, so the board needs no clean-URL rewriting.
// KB_ARTIFACT_DIR optionally saves a screenshot of each page that fails.
const {chromium} = require('playwright');
const fs = require('node:fs');

const base = process.env.KB_TEST_URL;
if (!/^http:\/\/(localhost|127\.0\.0\.1):\d+$/.test(base || '')) throw new Error('Set KB_TEST_URL to a disposable local board, for example http://localhost:8171');
const out = process.env.KB_ARTIFACT_DIR;
const password = 'KbTest1234!';

// Text that only shows up when a page broke
const ERROR_MARKERS = ['SQL ERROR', 'Fatal error', 'Parse error', 'Uncaught', 'General Error', '[phpBB Debug]', 'Warning</b>:', 'Notice</b>:', 'Deprecated</b>:'];

// phpBB's English messages for a refused form or page (seed-forum boards are English)
const FORM_INVALID = 'The submitted form was invalid';
const NOT_AUTHORISED = 'You are not authorised to access this area';

// phpBB refuses a form submitted within two seconds of it being made
async function waitForFormAge(page) {
	await page.waitForFunction(() => {
		const field = document.querySelector('[name="creation_time"]');
		return field && Date.now() / 1000 > Number(field.value) + 2;
	});
}

(async () => {
	const browser = await chromium.launch({headless: true});
	const page = await browser.newPage({viewport: {width: 1280, height: 900}});
	// PHP's built-in server (MSSQL boards) serves styles and scripts slowly, so
	// pages are checked once their HTML is in, without waiting for every asset
	page.setDefaultTimeout(120000);
	const ready = {waitUntil: 'domcontentloaded'};
	// Only the HTML is checked, and scripts would hold up domcontentloaded
	await page.route(/\.(css|js|png|gif|jpe?g|svg|ico|woff2?|ttf)(\?|$)/, route => route.abort());
	const results = [];

	async function check(label, url) {
		const response = await page.goto(url, ready);
		const body = await page.content();
		const problems = [];
		if (!response || response.status() >= 500) problems.push(`HTTP ${response ? response.status() : 'no response'}`);
		for (const marker of ERROR_MARKERS) {
			if (body.includes(marker)) problems.push(marker);
		}
		if (problems.length) {
			// phpBB's error page message, for the SQL error and query
			const text = await page.locator('body').innerText();
			const at = Math.max(0, text.search(/SQL ERROR|Fatal error|General Error|Warning|Notice/));
			problems.push(JSON.stringify(text.slice(at, at + 600)));
		}
		if (problems.length && out) {
			fs.mkdirSync(out, {recursive: true});
			await page.screenshot({path: `${out}/${label.replace(/[^a-z0-9]+/gi, '-')}.png`, fullPage: true});
		}
		results.push({label, status: response ? response.status() : 0, problems});
		return body;
	}

	// A page that must be refused, showing phpBB's message for that
	async function expectText(label, url, text, tab = page) {
		const response = await tab.goto(url, ready);
		const shown = await tab.locator('body').innerText();
		results.push({label, status: response ? response.status() : 0, problems: shown.includes(text) ? [] : [`expected "${text}"`]});
	}

	try {
		// Board login
		await page.goto(`${base}/ucp.php?mode=login`, ready);
		await page.locator('#username').fill('admin');
		await page.locator('#password').fill(password);
		await waitForFormAge(page);
		await page.locator('[name="login"][type="submit"]').click();
		await page.waitForURL(url => !url.search.includes('mode=login'), ready);

		// Front end: the index, then the first category and article it links to
		await check('index', `${base}/app.php/knowledgebase`);
		const category = await firstLink(page, 'knowledgebase/category?id=');
		const article = await firstLink(page, 'knowledgebase/article?k=');
		if (!category || !article) throw new Error('The index links to no category or article; seed the board first');
		await check('category', category);
		await check('article', article);
		await check('search', `${base}/app.php/knowledgebase/search?keywords=guide&sf=all&cid=0`);
		await check('queue', `${base}/app.php/knowledgebase/queue`);
		await check('my articles', `${base}/app.php/knowledgebase/my_articles`);
		await check('recent changes', `${base}/app.php/knowledgebase/recent_changes`);
		await check('recent changes rss', `${base}/app.php/knowledgebase/recent_changes.rss`);
		await check('posting form', `${base}/app.php/knowledgebase/posting?mode=add`);
		await check('edit form', `${base}/app.php/knowledgebase/posting?mode=edit&id=${new URL(article).searchParams.get('k')}`);

		// ACP: it needs the session id in the address, and the password again
		const settings = '/adm/index.php?i=-phpbbmodders-knowledgebase-acp-config_module&mode=settings';
		await page.goto(`${base}${settings}&sid=${await sessionCookie(page)}`, ready);
		if (await page.locator('input[type="password"]').count()) {
			await page.locator('input[type="password"]').fill(password);
			await waitForFormAge(page);
			// phpBB 4.0 keeps the ACP session in the cookie, so the address may not change
			await Promise.all([page.waitForNavigation(ready), page.locator('[name="login"][type="submit"]').click()]);
		}
		await check('acp settings', `${base}${settings}&sid=${await sessionCookie(page)}`);
		const modules = await page.locator('#menu a[href*="i=-phpbbmodders-knowledgebase-acp-"]').evaluateAll(links => links.map(link => [link.textContent.trim(), link.href]));
		if (!modules.length) throw new Error('No Knowledge Base modules in the ACP menu');
		for (const [label, href] of modules) await check(`acp ${label}`, href);
		// The permission trace pop-up for the admin (user 2) in the first category
		await check('acp permission trace', `${base}/adm/index.php?i=-phpbbmodders-knowledgebase-acp-permissions_module&mode=permissions&action=trace&user_id=2&category_id=1&auth=kb_u_add&sid=${await sessionCookie(page)}`);

		// Refusals: a category move link without its hash, and the approve
		// page for a member who is neither a moderator nor on the article's team
		const manage = '/adm/index.php?i=-phpbbmodders-knowledgebase-acp-manage_module&mode=manage';
		await expectText('acp move without hash refused', `${base}${manage}&action=move_up&f=1&sid=${await sessionCookie(page)}`, FORM_INVALID);
		const reader = await browser.newPage();
		reader.setDefaultTimeout(120000);
		await reader.route(/\.(css|js|png|gif|jpe?g|svg|ico|woff2?|ttf)(\?|$)/, route => route.abort());
		await reader.goto(`${base}/ucp.php?mode=login`, ready);
		await reader.locator('#username').fill('kb_reader');
		await reader.locator('#password').fill(password);
		await waitForFormAge(reader);
		await reader.locator('[name="login"][type="submit"]').click();
		await reader.waitForURL(url => !url.search.includes('mode=login'), ready);
		await expectText('approve page refused for kb_reader', `${base}/app.php/knowledgebase/approve?id=${new URL(article).searchParams.get('k')}`, NOT_AUTHORISED, reader);
	} finally {
		await browser.close();
	}

	const failed = results.filter(result => result.problems.length);
	for (const result of results) console.log(`${result.problems.length ? 'FAIL' : 'ok  '} ${result.status} ${result.label}${result.problems.length ? ': ' + result.problems.join(', ') : ''}`);
	console.log(`${results.length - failed.length} of ${results.length} pages loaded cleanly`);
	process.exitCode = failed.length ? 1 : 0;
})();

// The first link on the page whose address contains the text, or null
async function firstLink(page, text) {
	return page.locator('a').evaluateAll((links, part) => {
		// Skip same-page anchors, which load nothing
		const link = links.find(a => a.href.includes(part) && !a.href.includes('#'));
		return link ? link.href : null;
	}, text);
}

// The session id from phpBB's cookie, which every ACP address must carry
async function sessionCookie(page) {
	const cookie = (await page.context().cookies()).find(item => item.name.endsWith('_sid'));
	return cookie ? cookie.value : '';
}
