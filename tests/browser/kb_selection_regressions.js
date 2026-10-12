// Run against the disposable expanded fixture board with fresh role states.
// Requires Playwright; KB_STATE_DIR contains the walkthrough's storage states.
const { chromium } = require('playwright');
const { expect } = require('playwright/test');
const fs = require('node:fs');
const path = require('node:path');

const base = process.env.KB_TEST_URL || 'http://localhost:8092';
const stateDir = process.env.KB_STATE_DIR || '/tmp';
const output = process.env.KB_ARTIFACT_DIR || '/tmp/kb-selection-regressions';
const results = [];
fs.mkdirSync(output, { recursive: true });

async function ready(page) {
    await page.waitForFunction(() => {
        const input = document.querySelector('input[name="creation_time"]');
        return !input || Math.floor(Date.now() / 1000) > Number(input.value) + 1;
    });
}

async function check(name, page, action) {
    try {
        await action();
        results.push({ name, status: 'PASS' });
        console.log('PASS', name);
    } catch (error) {
        results.push({ name, status: 'FAIL', error: error.message });
        console.error('FAIL', name, error.message);
        process.exitCode = 1;
    }
    await page.screenshot({ path: path.join(output, 'selection-' + results.length + '.png'), fullPage: true });
    fs.writeFileSync(path.join(output, 'selection-results.json'), JSON.stringify(results, null, 2));
}

async function selectFields(page) {
    await page.locator('input[name="field_values[1][]"]').check();
    await page.locator('input[name="field_values[3][]"][value="8"]').check();
    await page.locator('select[name="field_values[2][]"]').selectOption('4');
    for (const value of ['9', '10']) {
        await page.locator('input[name="field_values[4][]"][value="' + value + '"]').check();
    }
    await page.locator('#cross_categories').selectOption(['18', '26']);
}

async function preview(page) {
    await ready(page);
    await page.locator('input[name="preview"]').click();
    await expect(page.locator('#preview')).toBeVisible();
}

async function assertFields(page) {
    await expect(page.locator('input[name="field_values[1][]"]')).toBeChecked();
    await expect(page.locator('input[name="field_values[3][]"][value="8"]')).toBeChecked();
    await expect(page.locator('select[name="field_values[2][]"]')).toHaveValue('4');
    for (const value of ['9', '10']) {
        await expect(page.locator('input[name="field_values[4][]"][value="' + value + '"]')).toBeChecked();
    }
    expect(await page.locator('#cross_categories').evaluate(element =>
        Array.from(element.selectedOptions).map(option => option.value))).toEqual(['18', '26']);
}

(async () => {
    const browser = await chromium.launch({ headless: false });
    try {
        const author = await browser.newContext({ storageState: path.join(stateDir, 'kb_author1-state.json') });
        const reader = await browser.newContext({ storageState: path.join(stateDir, 'kb_reader-state.json') });
        const guest = await browser.newContext();
        const a = await author.newPage();
        const r = await reader.newPage();
        const g = await guest.newPage();
        a.setDefaultTimeout(10000);
        r.setDefaultTimeout(10000);

        await check('New article preview preserves all widgets and cross-listing', a, async () => {
            await a.goto(base + '/knowledgebase/posting?id=8');
            await a.locator('#subject').fill('Selection regression preview');
            await a.locator('#descr').fill('Preview only; no article is created.');
            await a.locator('#message').fill('# Selection regression\n\nKeep the selected values.');
            await a.locator('#article_markdown').check();
            await selectFields(a);
            await preview(a);
            await assertFields(a);
        });
        await check('Existing article preview keeps changed selections', a, async () => {
            await a.goto(base + '/knowledgebase/article?k=18');
            await a.locator('a[title="Edit"]').click();
            for (const checkbox of await a.locator('input[name="field_values[4][]"]').all()) {
                await checkbox.uncheck();
            }
            await selectFields(a);
            await preview(a);
            await assertFields(a);
        });
        await check('Preview preserves deliberately cleared optional fields and categories', a, async () => {
            await a.locator('#cross_categories').selectOption([]);
            await a.locator('select[name="field_values[2][]"]').selectOption('0');
            for (const checkbox of await a.locator('input[name="field_values[4][]"]').all()) {
                await checkbox.uncheck();
            }
            await preview(a);
            expect(await a.locator('#cross_categories').evaluate(element => element.selectedOptions.length)).toBe(0);
            await expect(a.locator('select[name="field_values[2][]"]')).toHaveValue('0');
            expect(await a.locator('input[name="field_values[4][]"]:checked').count()).toBe(0);
        });
        await check('Reader can select every visible category in Advanced Search', r, async () => {
            await r.goto(base + '/knowledgebase/search');
            expect(await r.locator('#search_cat option:disabled').count()).toBe(0);
            expect(await r.locator('#search_cat option').count()).toBe(37);
            await r.locator('#search_cat').selectOption('8');
            await r.locator('input#keywords[type="text"]').fill('branchwalk');
            await r.locator('#author').fill('kb_author1');
            await r.locator('input[name="submit"]').click();
            await expect(r.locator('body')).toContainText('Clean URL Troubleshooting: a practical guide');
        });
        await check('Reader category choices remain usable on a phone', r, async () => {
            await r.setViewportSize({ width: 390, height: 844 });
            await r.goto(base + '/knowledgebase/search');
            await r.locator('#search_cat').selectOption(['8', '18']);
            expect(await r.locator('#search_cat').evaluate(element => element.selectedOptions.length)).toBe(2);
        });
        await check('Search fix does not grant reader posting permission', r, async () => {
            await r.goto(base + '/knowledgebase/posting?id=8');
            await expect(r.locator('body')).toContainText('You cannot add articles');
        });
        await check('Search fix does not grant guest Knowledgebase access', g, async () => {
            await g.goto(base + '/knowledgebase/search');
            await expect(g.locator('body')).toContainText('not authorised');
        });
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
