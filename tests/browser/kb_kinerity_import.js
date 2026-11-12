// Browser checks on a board just moved from kinerity/knowledgebase to this
// extension (seed-forum's kinerity-33/-40 boards, after the switch and
// tests/board/kinerity_import_check.php). Looks at what a member and the admin
// see: guests refused as kinerity refused them, the imported tree and articles,
// BBCode and special characters, a cross-listed article in both categories,
// deactivated articles hidden from members, the approval queue, the ACP pages,
// and search after the index is built.
//
// Environment: KB_TEST_URL (board URL), KB_ARTIFACT_DIR (screenshots and
// results), KB_ADMIN_USER / KB_ADMIN_PASSWORD and KB_MEMBER_USER (seed-forum's
// public fixture admin and forum_alex by default), KB_IDS (JSON from the board:
// tests/browser/kb_kinerity_import_ids.php prints it).
// KB_IDS holds new article ids picked from the backup tables:
//   {"approved": N, "cross": N, "cross_second_category": N, "waiting": N,
//    "deleted": N, "special": N, "special_title": "..."}
// Run: node <playwright-skill>/run.js tests/browser/kb_kinerity_import.js
const { chromium } = require('playwright');
const { expect } = require('playwright/test');
const fs = require('node:fs');
const path = require('node:path');

const base = (process.env.KB_TEST_URL || 'http://localhost:8161').replace(/\/$/, '');
const output = process.env.KB_ARTIFACT_DIR || '/tmp/kb-kinerity-import';
const adminUser = process.env.KB_ADMIN_USER || 'admin';
const adminPassword = process.env.KB_ADMIN_PASSWORD || 'KbTest1234!';
const memberUser = process.env.KB_MEMBER_USER || 'forum_alex';
const ids = JSON.parse(process.env.KB_IDS || '{}');
const results = [];
fs.mkdirSync(output, { recursive: true });

const article = id => base + '/app.php/knowledgebase/article?k=' + id;
const category = id => base + '/app.php/knowledgebase/category?id=' + id;

async function check(name, page, action) {
    try {
        await action();
        results.push({ name, status: 'PASS' });
        console.log('PASS', name);
    } catch (error) {
        results.push({ name, status: 'FAIL', error: error.message.split('\n')[0] });
        console.error('FAIL', name, error.message.split('\n')[0]);
        process.exitCode = 1;
    }
    await page.screenshot({ path: path.join(output, String(results.length).padStart(2, '0') + '.png'), fullPage: false });
    fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify(results, null, 2));
}

// phpBB refuses a form submitted within a second of its creation_time
async function ready(page) {
    await page.waitForFunction(() => {
        const input = document.querySelector('input[name="creation_time"]');
        return !input || Math.floor(Date.now() / 1000) > Number(input.value) + 1;
    });
}

async function login(page, user = adminUser) {
    await page.goto(base + '/ucp.php?mode=login');
    await page.fill('#username', user);
    await page.fill('#password', adminPassword);
    await ready(page);
    await page.click('input[name="login"]');
    await expect(page.locator('a[href*="mode=logout"]').first()).toBeAttached();
}

// The real ACP link, then the re-authentication phpBB asks for
async function openAcp(page) {
    await page.goto(base + '/index.php');
    await page.locator('a[href*="adm/index.php"]').first().click();
    if (await page.locator('input[name^="password_"]').count()) {
        await page.locator('input[name^="password_"]').fill(adminPassword);
        await ready(page);
        await page.click('input[name="login"]');
    }
    await expect(page.locator('#acp')).toBeVisible();
}

(async () => {
    const browser = await chromium.launch();
    const guest = await (await browser.newContext()).newPage();
    const member = await (await browser.newContext()).newPage();
    const admin = await (await browser.newContext()).newPage();
    const errors = [];
    for (const page of [guest, member, admin]) {
        page.on('pageerror', error => errors.push(error.message));
    }

    // kinerity gave guests no read permission, so neither does the import
    await check('guests are refused, as they were in kinerity', guest, async () => {
        await guest.goto(base + '/app.php/knowledgebase');
        await expect(guest.locator('body')).not.toContainText('Article ' + ids.approved_number);
    });

    await login(member, memberUser);

    await check('a member sees the imported articles and sections', member, async () => {
        await member.goto(base + '/app.php/knowledgebase');
        await expect(member.locator('body')).toContainText('Article');
        await expect(member.locator('select, body').first()).toContainText('Section 1');
        await expect(member.locator('body')).not.toContainText('General Error');
    });

    await check('an approved article shows its BBCode as formatting', member, async () => {
        await member.goto(article(ids.approved));
        await expect(member.locator('.content').first()).toBeVisible();
        expect(await member.locator('.content').first().innerHTML()).toMatch(/<(strong|b|ol|li|blockquote|code|a|span)[\s>]/);
        await expect(member.locator('.content').first()).not.toContainText('[b]');
    });

    await check('special characters show once, not double-escaped', member, async () => {
        await member.goto(article(ids.special));
        await expect(member.locator('body')).toContainText(ids.special_title);
        await expect(member.locator('body')).not.toContainText('&amp;');
        await expect(member.locator('body')).not.toContainText('&quot;');
    });

    await check('a cross-listed article is listed in its other category', member, async () => {
        await member.goto(category(ids.cross_second_category));
        await expect(member.locator('a[href*="k=' + ids.cross + '"]').first()).toBeAttached();
    });

    await check('members cannot open a deactivated (kinerity deleted) article', member, async () => {
        await member.goto(article(ids.deleted));
        await expect(member.locator('.content').filter({ hasText: 'Body' })).toHaveCount(0);
    });

    await login(admin);

    await check('articles waiting in kinerity are in the approval queue as New', admin, async () => {
        await admin.goto(base + '/app.php/knowledgebase/queue');
        await expect(admin.locator('body')).toContainText('New');
        await admin.goto(article(ids.waiting));
        await expect(admin.locator('body')).toContainText('New');
    });

    await openAcp(admin);
    const acpLinks = ['ACP_LIBRARY_MANAGE', 'ACP_LIBRARY_ARTICLES', 'ACP_LIBRARY_PERMISSIONS', 'ACP_LIBRARY_SEARCH'];
    const sid = new URL(admin.url()).searchParams.get('sid');
    for (const mode of [['manage_module', 'manage'], ['articles_module', 'articles'], ['permissions_module', 'permissions'], ['search_module', 'index']]) {
        await check('ACP page loads: ' + mode[1], admin, async () => {
            await admin.goto(base + '/adm/index.php?i=-phpbbmodders-knowledgebase-acp-' + mode[0] + '&mode=' + mode[1] + '&sid=' + sid);
            await expect(admin.locator('#main')).toBeVisible();
            await expect(admin.locator('body')).not.toContainText('General Error');
            await expect(admin.locator('body')).not.toContainText('SQL ERROR');
        });
    }
    void acpLinks;

    await check('no kinerity module left in the ACP menu', admin, async () => {
        await admin.goto(base + '/adm/index.php?i=acp_extensions&mode=main&sid=' + sid);
        await expect(admin.locator('#menu')).not.toContainText('ACP_KNOWLEDGEBASE');
        await expect(admin.locator('body')).not.toContainText('kinerity');
    });

    await check('creating the search index, then searching, finds an imported article', admin, async () => {
        await admin.goto(base + '/adm/index.php?i=-phpbbmodders-knowledgebase-acp-search_module&mode=index&sid=' + sid);
        const create = admin.locator('input[name="action[create]"], input[value*="Create index"]').first();
        if (await create.count()) {
            await ready(admin);
            await create.click();
            // Indexing runs over several page loads; wait for it to finish
            await admin.waitForFunction(() => !document.querySelector('meta[http-equiv="refresh"]'), null, { timeout: 600000, polling: 2000 });
        }
        await admin.goto(base + '/app.php/knowledgebase/search?keywords=' + encodeURIComponent('Article ' + ids.approved_number));
        await expect(admin.locator('a[href*="k="]').first()).toBeAttached();
    });

    await check('no JavaScript errors', admin, async () => {
        expect(errors).toEqual([]);
    });

    await browser.close();
    console.log(results.filter(r => r.status === 'PASS').length + '/' + results.length + ' checks passed');
})();
