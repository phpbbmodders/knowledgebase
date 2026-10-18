// Run only on a disposable seed-forum board prepared for author-change checks.
// KB_TEST_URL and KB_TEST_BOARD_ROOT are explicit; fixture passwords are public.
const { chromium } = require('playwright');
const { expect } = require('playwright/test');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const fs = require('node:fs');
const base = process.env.KB_TEST_URL;
const root = process.env.KB_TEST_BOARD_ROOT;
if (!base || !root || !root.includes('/seeded-board/kb-author-change-')) {
    throw new Error('Set the disposable author-change board URL and root');
}
const fixture = path.join(__dirname, 'kb_author_change_fixture.py');
function data(operation) {
    return JSON.parse(execFileSync('python3', [fixture, root, operation], { encoding: 'utf8' }));
}
async function ready(page) {
    await page.waitForFunction(() => {
        const input = document.querySelector('input[name="creation_time"]');
        return !input || Math.floor(Date.now() / 1000) > Number(input.value) + 1;
    });
}
(async () => {
    const before = data('prepare');
    const browser = await chromium.launch({ headless: !process.env.DISPLAY });
    const contexts = [];
    let checks = 0;
    const errors = [];
    const url = id => `${base}/knowledgebase/change_author?k=${id}`;
    async function login(name) {
        const context = await browser.newContext();
        contexts.push(context);
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await page.goto(`${base}/ucp.php?mode=login`);
        await page.locator('#username').fill(name);
        await page.locator('#password').fill('KbTest1234!');
        await ready(page);
        await page.locator('[name="login"][type="submit"]').click();
        await page.waitForURL(value => !value.search.includes('mode=login'), { timeout: 10000 }).catch(async error => { console.error(name, await page.locator('body').innerText()); throw error; });
        return page;
    }
    async function status(page, id, expected) {
        expect((await page.goto(url(id))).status()).toBe(expected);
        checks++;
    }
    async function transfer(page, type, value) {
        await page.goto(url(2));
        await page.locator('#author_type').selectOption(String(type));
        if (type === 0) await page.locator('#username').fill(value);
        else await page.locator('#group_id').selectOption(String(value));
        await ready(page);
        await page.getByRole('button', { name: 'Change author', exact: true }).click();
        await expect(page).toHaveURL(/knowledgebase\/article\?k=2/);
        checks++;
    }
    try {
        const guest = await browser.newPage();
        await status(guest, 2, 403);
        const reader = await login('kb_reader');
        await status(reader, 2, 403);
        const original = await login('kb_author1');
        await status(original, 2, 403);
        const moderator = await login('kb_transfer_moderator');
        await status(moderator, 2, 200);
        await status(moderator, 4, 403);
        const admin = await login('admin');
        await status(admin, 11, 404);
        await status(admin, 1, 409);
        await expect(admin.locator('.kb-notice')).toContainText('Authorship cannot be transferred');
        await expect(admin.locator('.kb-tabs')).toContainText('Notice');
        checks++;
        await admin.goto(`${base}/knowledgebase/article?k=1`);
        await expect(admin.locator('.kb-article-status')).toContainText('Has draft');
        await reader.goto(`${base}/knowledgebase/article?k=1`);
        await expect(reader.locator('.kb-article-status')).not.toContainText('Has draft');
        checks++;
        await admin.goto(`${base}/knowledgebase/category?id=${before.article.article_category_id}`);
        await expect(admin.locator('tr[id="2"] .kb-article-status')).toContainText('Revision pending');
        checks++;

        await admin.goto(url(2));
        await expect(admin.getByRole('heading', { name: 'Change author', exact: true })).toBeVisible();
        checks++;
        const form = await admin.locator('form[action*="change_author"]').evaluate(form => Object.fromEntries(new FormData(form)));
        const forged = await admin.request.post(url(2), { form: { ...form, submit: 'Change author', username: 'kb_author2', form_token: 'invalid' } });
        expect(await forged.text()).toContain('The submitted form was invalid');
        expect(data('snapshot').article.author_id).toBe(before.article.author_id);
        checks++;
        await admin.locator('#username').fill('nonexistent_fixture_account');
        await ready(admin);
        await admin.getByRole('button', { name: 'Change author', exact: true }).click();
        await expect(admin.getByText('Choose an active member or a configured Knowledge Base team.', { exact: true })).toBeVisible();
        checks++;
        await admin.goto(url(2));
        await admin.locator('#username').fill('kb_author2');
        await admin.locator('[name="old_author_id"]').evaluate(input => input.value = '999999');
        await ready(admin);
        await admin.getByRole('button', { name: 'Change author', exact: true }).click();
        await expect(admin.getByText(/The author or article status changed/)).toBeVisible();
        checks++;
        await transfer(moderator, 0, 'kb_author2');
        let after = data('snapshot');
        expect(after.article.author_id).toBe(before.target_user);
        expect(after.article.author_type).toBe(0);
        expect(after.revisions).toEqual(before.revisions);
        expect(after.coauthors).toEqual(before.coauthors.filter(row => !(row.author_type === 0 && row.author_ref_id === before.target_user)));
        for (const key of Object.keys(before.article).filter(key => !['author_id', 'author_type', 'author', 'views'].includes(key))) {
            expect(after.article[key], key).toEqual(before.article[key]);
        }
        checks++;
        await original.goto(`${base}/knowledgebase/article?k=2`);
        await expect(original.getByRole('link', { name: 'Edit', exact: true })).toHaveCount(0);
        const newAuthor = await login('kb_author2');
        await newAuthor.goto(`${base}/knowledgebase/article?k=2`);
        await expect(newAuthor.getByRole('link', { name: 'Edit', exact: true })).toBeVisible();
        checks++;
        await transfer(admin, 1, before.team_id);
        expect(data('snapshot').article.author_type).toBe(1);
        checks++;
        const output = process.env.KB_ARTIFACT_DIR;
        if (output) {
            fs.mkdirSync(output, { recursive: true });
            await admin.goto(url(2));
            await admin.screenshot({ path: path.join(output, 'change-author.png'), fullPage: true });
        }
        await transfer(admin, 0, 'kb_author1');
        after = data('snapshot');
        expect(after.article.author_id).toBe(before.article.author_id);
        expect(after.revisions).toEqual(before.revisions);
        expect(after.logs).toBe(before.logs + 3);
        expect(errors).toEqual([]);
        checks++;
        console.log(JSON.stringify({ base, checks, result: 'PASS' }));
    } finally {
        data('restore');
        for (const context of contexts) await context.close();
        await browser.close();
    }
})();
