/* Actual preference helpers, picker, links, shared CSS/JS; synthetic results only. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const {spawnSync} = require('node:child_process');
const {chromium} = require('playwright');
const theme = path.resolve(__dirname, '..');
const reference = path.resolve(process.env.TADL_REFERENCE_ROOT || path.join(theme, '../../pawtucket2'));
const shellResult = spawnSync('php', [path.join(__dirname, 'accessibility_test.php'), '--fixtures'], {encoding:'utf8'});
assert.equal(shellResult.status, 0, shellResult.stderr || shellResult.stdout);
const shell = JSON.parse(shellResult.stdout);
const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost');
    let base, relative;
    if (url.pathname.startsWith('/native/')) { base = path.join(reference, 'assets'); relative = url.pathname.slice(8); }
    if (url.pathname.startsWith('/themes/tadl/assets/')) { base = path.join(theme, 'assets'); relative = url.pathname.slice('/themes/tadl/assets/'.length); }
    if (base) {
        const file = path.resolve(base, relative);
        if (file.startsWith(base + path.sep) && fs.existsSync(file) && fs.statSync(file).isFile()) {
            const types = {'.css':'text/css', '.js':'application/javascript', '.svg':'image/svg+xml', '.png':'image/png', '.woff':'font/woff', '.woff2':'font/woff2'};
            response.setHeader('Content-Type', types[path.extname(file)] || 'application/octet-stream');
            fs.createReadStream(file).pipe(response); return;
        }
        response.writeHead(404); response.end(); return;
    }
    const params = Object.fromEntries(url.searchParams);
    const cookies = {};
    for (const pair of (request.headers.cookie || '').split('; ')) {
        const separator = pair.indexOf('=');
        if (separator > 0) { cookies[pair.slice(0, separator)] = decodeURIComponent(pair.slice(separator + 1)); }
    }
    const total = url.pathname === '/small' ? 14 : Number(/^synthetic-(\d+)$/.exec(params.key || '')?.[1] || 144);
    const result = spawnSync('php', [path.join(__dirname, 'display_preferences_test.php'), '--render'], {input:JSON.stringify({params, cookies, total}), encoding:'utf8'});
    assert.equal(result.status, 0, result.stderr || result.stdout);
    response.setHeader('Content-Type', 'text/html; charset=utf-8');
    response.end(shell.header + '<h1>Synthetic collection</h1>' + result.stdout + shell.footer);
});
let browser;
(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const origin = 'http://127.0.0.1:' + server.address().port;
    browser = await chromium.launch({headless:true, ...(process.env.TADL_BROWSER_CHANNEL ? {channel:process.env.TADL_BROWSER_CHANNEL} : {})});
    const context = await browser.newContext();
    await context.route('**/*', route => route.request().url().startsWith(origin) ? route.continue() : route.abort());
    const page = await context.newPage(), errors = [];
    page.on('pageerror', error => errors.push(error.message));
    for (const width of [320, 390, 768, 1024, 1200, 1280, 1600]) {
        await page.setViewportSize({width, height:1000});
        await page.goto(origin);
        assert.equal(await page.locator('[data-result]').count(), 18, 'Eighteen is the default at every width.');
        assert.deepEqual(await page.locator('select[name=per_page] option').evaluateAll(nodes => nodes.map(node => node.value)), ['9','18','27','36','45','54','63','72']);
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${width}px: result controls must not overflow.`);
        const size = await page.getByRole('combobox', {name:'Items per page'}).boundingBox();
        assert(size.height >= 24 && size.width >= 24, 'Picker needs a usable pointer target.');
        if (process.env.TADL_A11Y_ARTIFACT_DIR) {
            fs.mkdirSync(process.env.TADL_A11Y_ARTIFACT_DIR, {recursive:true});
            await page.screenshot({path:path.join(process.env.TADL_A11Y_ARTIFACT_DIR, `per-page-${width}.png`)});
        }
    }
    await page.addScriptTag({path:process.env.TADL_AXE_PATH || require.resolve('axe-core/axe.min.js')});
    const accessibility = await page.evaluate(() => axe.run('.tadl-collection-results-toolbar', {runOnly:{type:'tag', values:['wcag2a','wcag2aa','wcag21aa','wcag22aa']}}));
    assert.deepEqual(accessibility.violations, [], 'Result controls must have no automated A/AA violations.');
    assert.equal(await page.getByRole('button', {name:'Apply', exact:true}).isVisible(), false, 'JavaScript users should not need Apply.');
    assert.equal(await page.getByText('Updates results automatically.', {exact:true}).count(), 0, 'The selector needs no extra instruction text.');
    await Promise.all([page.waitForURL('**/Search/objects?**'), page.getByRole('combobox', {name:'Items per page'}).selectOption('27')]);
    assert.equal(await page.locator('[data-result]').count(), 27);
    assert.equal(new URL(page.url()).searchParams.get('s'), '0');
    await page.getByRole('link', {name:'Next', exact:true}).click();
    await page.waitForURL('**s=27**');
    assert.equal(await page.locator('[data-result]').first().innerText(), 'Synthetic item 28');
    await page.getByRole('link', {name:'List', exact:true}).click();
    await page.waitForFunction(() => document.querySelector('[data-view]')?.dataset.view === 'list');
    assert.equal(await page.locator('[data-result]').count(), 25, 'List view has its own 25-item default.');
    assert.deepEqual(await page.locator('select[name=per_page] option').evaluateAll(nodes => nodes.map(node => node.value)), ['25','50','100']);
    await Promise.all([page.waitForURL('**per_page=50**'), page.getByRole('combobox', {name:'Items per page'}).selectOption('50')]);
    await page.getByRole('link', {name:'Switch collection view'}).click();
    await page.waitForFunction(() => document.querySelector('[data-collection-view]')?.dataset.collectionView === 'hierarchy');
    await page.getByRole('link', {name:'Another collection'}).click();
    await page.waitForURL('**/Detail/collections/43');
    assert.equal(await page.locator('[data-view]').getAttribute('data-view'), 'list');
    assert.equal(await page.locator('[data-collection-view]').getAttribute('data-collection-view'), 'hierarchy');
    assert.equal(await page.locator('[data-result]').count(), 50);
    const cookie = (await context.cookies()).find(value => value.name === 'tadlDisplayPreferences');
    assert.equal(cookie.sameSite, 'Lax'); assert.equal(cookie.path, '/');
    assert(cookie.expires > Date.now()/1000 + 300*86400, 'Preference cookie must survive browser sessions.');
    await page.getByRole('link', {name:'Tiles', exact:true}).click();
    await page.waitForFunction(() => document.querySelector('[data-view]')?.dataset.view === 'images');
    assert.equal(await page.locator('[data-result]').count(), 27, 'Switching back to tiles must restore the tile size.');
    await page.getByRole('link', {name:'List', exact:true}).click();
    await page.waitForFunction(() => document.querySelector('[data-view]')?.dataset.view === 'list');
    assert.equal(await page.locator('[data-result]').count(), 50, 'Switching back to list must restore its separate size.');
    await page.goto(origin + '/?view=list&per_page=25&key=synthetic-88');
    assert.deepEqual(await page.locator('select[name=per_page] option').evaluateAll(nodes => nodes.map(node => node.value)), ['25','50','all']);
    await Promise.all([page.waitForURL('**per_page=all**'), page.getByRole('combobox', {name:'Items per page'}).selectOption('all')]);
    assert.equal(await page.locator('[data-result]').count(), 88);
    await page.getByRole('link', {name:'Another collection'}).click();
    await page.waitForURL('**/Detail/collections/43');
    assert.equal(await page.locator('[data-result]').count(), 25, 'All in List must reset the next collection to 25.');
    assert.equal(await page.locator('select[name=per_page]').inputValue(), '25');
    assert.equal(await page.locator('option[value=all]').count(), 0);
    await page.goto(origin + '/?per_page=all&key=synthetic-1000');
    assert.equal(await page.locator('[data-result]').count(), 25, 'A crafted List All link must fall back to 25 in a large collection.');
    await page.goto(origin + '/?view=list&per_page=all&key=synthetic-88');
    assert.equal(await page.locator('[data-result]').count(), 88, 'List All may show at most 100 records.');
    await page.goto(origin + '/?view=images&per_page=all&key=synthetic-1000');
    assert.equal(await page.locator('[data-result]').count(), 18, 'A crafted Tiles All link must fall back to 18 in a large collection.');
    const plain = await browser.newContext({javaScriptEnabled:false});
    await plain.route('**/*', route => route.request().url().startsWith(origin) ? route.continue() : route.abort());
    const plainPage = await plain.newPage();
    await plainPage.goto(origin);
    await plainPage.getByRole('combobox', {name:'Items per page'}).selectOption('27');
    await plainPage.getByRole('button', {name:'Apply', exact:true}).click();
    await plainPage.waitForURL('**per_page=27**');
    assert.equal(await plainPage.locator('[data-result]').count(), 27, 'GET selection must work without JavaScript.');
    assert.deepEqual(errors, []);
    await plain.close();
    console.log('Display preference browser passed: 320–1600px controls, bounded All, paging, cross-page cookies, view/mode persistence and no-JavaScript GET.');
})().catch(error => { console.error(error); process.exitCode=1; }).finally(async () => {
    if (browser) { await browser.close(); } server.close();
});
