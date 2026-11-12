// Check the Knowledge Base forms work for people without JavaScript and
// carry one label per field, as the seeded administrator:
// - the ACP introduction settings: with JavaScript, the text box shows only
//   while the introduction is ticked; without it, the text box is always there;
// - the custom-field filter and the "drafts only" box have a Go button when
//   JavaScript is off (both normally submit on change);
// - no field on the add-article form, nor the ACP field type menu, has more than one label;
// - the inactive tabs' text passes WCAG AA contrast (4.5:1) on its background;
// - article title links and the queue's buttons and menus are at least 24px
//   high on a phone.
// Read-only: it submits nothing but the login forms.
//
// KB_TEST_URL is the board's base URL, without a trailing slash; routes go
// through app.php, so the board needs no clean-URL rewriting.
const {chromium} = require('playwright');

const base = process.env.KB_TEST_URL;
if (!/^http:\/\/(localhost|127\.0\.0\.1):\d+$/.test(base || '')) throw new Error('Set KB_TEST_URL to a disposable local board, for example http://localhost:8171');
const password = 'KbTest1234!';
const ready = {waitUntil: 'domcontentloaded'};
const results = [];

function record(label, ok, detail = '') {
	results.push({label, ok, detail});
}

// phpBB refuses a form submitted within two seconds of it being made
async function waitForFormAge(page) {
	await page.waitForFunction(() => {
		const field = document.querySelector('[name="creation_time"]');
		return field && Date.now() / 1000 > Number(field.value) + 2;
	});
}

// Log in to the board, then unlock the ACP, in a fresh browser context
async function adminPage(browser, javaScriptEnabled) {
	const context = await browser.newContext({javaScriptEnabled, viewport: {width: 1280, height: 900}});
	const page = await context.newPage();
	page.setDefaultTimeout(120000);
	await page.goto(`${base}/ucp.php?mode=login`, ready);
	await page.locator('#username').fill('admin');
	await page.locator('#password').fill(password);
	if (javaScriptEnabled) await waitForFormAge(page); else await page.waitForTimeout(2500);
	await Promise.all([page.waitForNavigation(ready), page.locator('[name="login"][type="submit"]').click()]);
	return page;
}

async function openAcp(page, path) {
	const sid = (await page.context().cookies()).find(item => item.name.endsWith('_sid'));
	await page.goto(`${base}/adm/index.php?${path}&sid=${sid ? sid.value : ''}`, ready);
	if (await page.locator('input[type="password"]').count()) {
		await page.locator('input[type="password"]').fill(password);
		await page.waitForTimeout(2500);
		await Promise.all([page.waitForNavigation(ready), page.locator('[name="login"][type="submit"]').click()]);
		const now = (await page.context().cookies()).find(item => item.name.endsWith('_sid'));
		await page.goto(`${base}/adm/index.php?${path}&sid=${now ? now.value : ''}`, ready);
	}
}

// Fields that more than one <label> names
async function doubleLabelled(page) {
	return page.evaluate(() => [...document.querySelectorAll('input, select, textarea')]
		.filter(field => field.labels && field.labels.length > 1)
		.map(field => field.name || field.id));
}

// WCAG relative luminance contrast of two computed rgb() colours
function contrast(fore, back) {
	const lum = rgb => {
		const [r, g, b] = rgb.match(/\d+(\.\d+)?/g).slice(0, 3).map(Number).map(value => {
			const c = value / 255;
			return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
		});
		return 0.2126 * r + 0.7152 * g + 0.0722 * b;
	};
	const [light, dark] = [lum(fore), lum(back)].sort((a, b) => b - a);
	return (light + 0.05) / (dark + 0.05);
}

(async () => {
	const browser = await chromium.launch({headless: true});
	const config = 'i=-phpbbmodders-knowledgebase-acp-config_module&mode=settings';
	try {
		// ACP introduction settings, with JavaScript
		const js = await adminPage(browser, true);
		await openAcp(js, config);
		const ticked = await js.locator('#intro_enabled').isChecked();
		record('intro options follow the switch on load', await js.locator('#intro_text').isVisible() === ticked);
		await js.locator('#intro_enabled').setChecked(!ticked);
		record('intro options follow the switch when changed', await js.locator('#intro_text').isVisible() === !ticked);

		// The field type menu in the ACP custom fields
		await openAcp(js, 'i=-phpbbmodders-knowledgebase-acp-fields_module&mode=fields');
		const acpDouble = await doubleLabelled(js);
		const hasType = await js.locator('#field_type').count() > 0;
		record('ACP field form: one label per field', hasType && acpDouble.length === 0, hasType ? acpDouble.join(', ') : 'no field type menu found');

		// The add-article form
		await js.goto(`${base}/app.php/knowledgebase/posting?mode=add&id=1`, ready);
		const postDouble = await doubleLabelled(js);
		record('add-article form: one label per field', postDouble.length === 0, postDouble.join(', '));

		// Inactive tab contrast
		await js.goto(`${base}/app.php/knowledgebase/my_articles`, ready);
		const tabs = await js.locator('.kb-tabs .kb-tab:not(.kb-tab-active) > a').evaluateAll(links => links.map(link => {
			const style = getComputedStyle(link);
			return [link.textContent.trim(), style.color, style.backgroundColor];
		}));
		const weak = tabs.filter(([, fore, back]) => !/rgba\(0, 0, 0, 0\)/.test(back) && contrast(fore, back) < 4.5);
		record('inactive tabs pass 4.5:1 contrast', tabs.length > 0 && weak.length === 0, weak.map(([text]) => text).join(', '));

		// Touch targets on a phone: article title links, and the queue's buttons and menus
		await js.setViewportSize({width: 375, height: 812});
		const small = [];
		for (const path of ['category?id=1', 'queue']) {
			await js.goto(`${base}/app.php/knowledgebase/${path}`, ready);
			const sizes = await js.locator('table.kb-index-table td:first-child > a, table.kb-index-table .button2, table.kb-index-table select')
				.evaluateAll(items => items.map(item => [item.textContent.trim().slice(0, 30), Math.round(item.getBoundingClientRect().height)]));
			if (!sizes.length) small.push(`${path}: nothing to measure`);
			small.push(...sizes.filter(([, height]) => height < 24).map(([text, height]) => `${path}: ${text} (${height}px)`));
		}
		record('list links and queue controls are at least 24px high', small.length === 0, small.slice(0, 5).join(', '));

		// Without JavaScript
		const plain = await adminPage(browser, false);
		await openAcp(plain, config);
		record('intro text box present without JavaScript', await plain.locator('#intro_text').isVisible());
		await plain.goto(`${base}/app.php/knowledgebase/my_articles`, ready);
		record('drafts-only box has a Go button without JavaScript',
			await plain.locator('form:has([name="drafts_only"]) button[type="submit"]').isVisible());
		await plain.goto(`${base}/app.php/knowledgebase/category?id=1`, ready);
		if (await plain.locator('[name="field_value_id"]').count()) {
			record('field filter has a Go button without JavaScript',
				await plain.locator('form:has([name="field_value_id"]) button[type="submit"]').isVisible());
		} else {
			record('field filter has a Go button without JavaScript', false, 'category 1 shows no field filter; seed the board first');
		}
	} finally {
		await browser.close();
	}

	for (const result of results) console.log(`${result.ok ? 'ok  ' : 'FAIL'} ${result.label}${result.detail ? ': ' + result.detail : ''}`);
	const failed = results.filter(result => !result.ok).length;
	console.log(`${results.length - failed} of ${results.length} checks passed`);
	process.exitCode = failed ? 1 : 0;
})();
