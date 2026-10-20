/* Actual grid markup and focal-point script; synthetic images and local assets only. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const {spawnSync} = require('node:child_process');
const {chromium} = require('playwright');
const theme = path.resolve(__dirname, '..');
const reference = path.resolve(process.env.TADL_REFERENCE_ROOT || path.join(theme, '../../pawtucket2'));
function fixture(file, argument) {
    const output = spawnSync(process.env.PHP_BINARY || 'php', [path.join(__dirname, file), argument], {encoding:'utf8'});
    assert.equal(output.status, 0, output.stderr || output.stdout);
    return JSON.parse(output.stdout);
}
const shell = fixture('accessibility_test.php', '--fixtures');
const cards = fixture('object_thumbnail_test.php', '--grid-fixtures');
const formatCards = fixture('representation_formats_test.php', '--fixtures');
const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost');
    if (['/wide', '/sidebar', '/list', '/formats', '/formats-list'].includes(url.pathname)) {
        const scripts = '<script src="/themes/tadl/assets/pawtucket/js/thumbnail-focus.js"></script>';
        const header = shell.header.replace('</head>', scripts + '</head>');
        const tiles = '<section class="tadl-authority-items"><h2>Related items</h2><div class="row">' + cards.tiles + '</div></section>';
        const content = url.pathname === '/formats' ? formatCards.tiles : url.pathname === '/formats-list' ? formatCards.list
            : url.pathname === '/list' ? cards.list : url.pathname === '/sidebar'
            ? '<div class="tadl-authority-layout">' + tiles + '<aside class="tadl-authority-sidebar"><h2>Record information</h2><p>Synthetic metadata.</p></aside></div>'
            : tiles.replace(' class="tadl-authority-items"', '');
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        response.end(header + '<h1>Synthetic thumbnail grid</h1>' + content + shell.footer);
        return;
    }
    if (['/portrait.svg', '/landscape.svg', '/tall.svg'].includes(url.pathname)) {
        const width = url.pathname === '/portrait.svg' ? 332 : url.pathname === '/tall.svg' ? 160 : 900;
        const height = url.pathname === '/tall.svg' ? 1200 : 400;
        response.setHeader('Content-Type', 'image/svg+xml');
        response.end(`<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 332 400" preserveAspectRatio="none"><rect width="332" height="400" fill="#b9cbd1"/><path d="M0 200H332V400H0Z" fill="#627c84"/><g fill="#be8171" stroke="#233e49" stroke-width="4"><circle cx="66" cy="56" r="20"/><circle cx="249" cy="60" r="20"/><circle cx="149" cy="88" r="20"/></g><path d="M40 125h52v120H40M220 127h58v120h-58M120 157h58v160h-58" fill="#426578"/></svg>`);
        return;
    }
    if (url.pathname.startsWith('/Detail/objects/')) { response.end('Synthetic object destination'); return; }
    let base, relative;
    if (url.pathname.startsWith('/native/')) { base = path.join(reference, 'assets'); relative = url.pathname.slice(8); }
    if (url.pathname.startsWith('/themes/tadl/assets/')) { base = path.join(theme, 'assets'); relative = url.pathname.slice('/themes/tadl/assets/'.length); }
    if (base) {
        const file = path.resolve(base, relative);
        if (file.startsWith(base + path.sep) && fs.existsSync(file) && fs.statSync(file).isFile()) {
            const types = {'.css':'text/css', '.js':'application/javascript', '.svg':'image/svg+xml', '.png':'image/png', '.woff':'font/woff', '.woff2':'font/woff2'};
            response.setHeader('Content-Type', types[path.extname(file)] || 'application/octet-stream');
            fs.createReadStream(file).pipe(response);
            return;
        }
    }
    response.statusCode = 404; response.end('Synthetic resource not found');
});
let browser;
async function fixedCrop(card) {
    const box = card.locator('.bResultItemImg'), before = await box.boundingBox();
    await box.hover();
    await card.locator('.bResultItemImg a').focus();
    const after = await box.boundingBox();
    assert(Math.abs(after.height - before.height) < 1 && Math.abs(after.width / after.height - 1.5) < .01, 'Hover/focus must keep the 3:2 crop.');
    assert.equal(await card.locator('.bResultItemImg img').count(), 1, 'Hover/focus must not add a full-image layer.');
    assert.equal(await card.locator('img').evaluate(node => getComputedStyle(node).objectFit), 'cover');
    assert.notEqual(await card.evaluate(node => getComputedStyle(node).position), 'absolute', 'The card must remain in the grid.');
}
(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const origin = 'http://127.0.0.1:' + server.address().port;
    browser = await chromium.launch({headless:true, ...(process.env.TADL_BROWSER_CHANNEL ? {channel:process.env.TADL_BROWSER_CHANNEL} : {})});
    const context = await browser.newContext({reducedMotion:'no-preference'});
    await context.route('**/*', route => route.request().url().startsWith(origin) ? route.continue() : route.abort());
    const page = await context.newPage(), errors = [];
    page.on('pageerror', error => errors.push(error.message));
    for (const width of [1600, 1280]) {
        await page.setViewportSize({width, height:1000});
        for (const name of ['wide', 'sidebar']) {
            await page.goto(origin + '/' + name); await page.mouse.move(1, 1);
            await page.waitForFunction(() => document.querySelector('#row100 img').naturalWidth > 0);
            const card = page.locator('#row100');
            const nextRow = await page.locator('#row104').evaluate(node => node.getBoundingClientRect().top + scrollY);
            const links = await card.locator('a').count();
            await fixedCrop(card);
            assert.equal(await page.locator('#row104').evaluate(node => node.getBoundingClientRect().top + scrollY), nextRow, 'Hover/focus must not move the grid rows.');
            assert.equal(await card.locator('a').count(), links, 'Native links must remain intact.');
            if (process.env.TADL_A11Y_ARTIFACT_DIR) {
                fs.mkdirSync(process.env.TADL_A11Y_ARTIFACT_DIR, {recursive:true});
                await page.screenshot({path:path.join(process.env.TADL_A11Y_ARTIFACT_DIR, `thumbnail-grid-${name}-${width}.png`)});
            }
            await fixedCrop(page.locator('#row103'));
            await fixedCrop(page.locator('#row104'));
            const placeholder = page.locator('#row102 .bResultItemImg');
            await placeholder.hover();
            const bounds = await placeholder.boundingBox();
            assert(Math.abs(bounds.width / bounds.height - 1.5) < .01, 'Placeholders retain the grid aspect ratio.');
            await page.mouse.move(1, 1);
            console.log(`${name} ${width}px: fixed portrait/landscape/tall crops, stable grid, focus and placeholders passed`);
        }
    }
    await page.goto(origin + '/wide'); await page.mouse.move(1, 1); await page.keyboard.press('Tab');
    await page.setViewportSize({width:1024, height:800}); await fixedCrop(page.locator('#row100'));
    await page.evaluate(html => {
        const row = document.createElement('div'); row.id = 'ajax-results'; row.innerHTML = html.replace(/id='row/g, "id='ajax-row"); document.querySelector('#main').append(row);
    }, cards.tiles);
    await fixedCrop(page.locator('#ajax-row100'));
    await page.locator('#ajax-results').evaluate(node => node.remove());
    await page.emulateMedia({reducedMotion:'reduce'}); await page.mouse.move(1, 1);
    await fixedCrop(page.locator('#row100'));
    await page.goto(origin + '/list'); await page.locator('.bResultListItemImg').first().hover();
    const listBox = await page.locator('.bResultListItemImg').first().boundingBox();
    assert.equal(listBox.width, 112); assert.equal(listBox.height, 88, 'List view dimensions must remain unchanged.');
    for (const width of [320, 390, 768, 1280, 1600]) {
        await page.setViewportSize({width, height:1000});
        for (const name of ['formats', 'formats-list']) {
            await page.goto(origin + '/' + name); await page.mouse.move(1, 1);
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${name} ${width}px must not overflow.`);
            const tags = page.locator('#row1 .tadl-representation-format');
            assert.equal(await tags.count(), 6, 'Each original format appears once.');
            assert.deepEqual(await tags.first().evaluate(node => {
                const style = getComputedStyle(node);
                return [style.backgroundColor, style.color, style.borderRadius];
            }), ['rgb(34, 34, 34)', 'rgb(255, 255, 255)', '0px'], 'Tags should be white on black with square corners.');
            assert(await tags.evaluateAll(nodes => nodes.every(node => {
                const tag = node.getBoundingClientRect(), row = node.parentElement.getBoundingClientRect();
                return tag.left >= row.left - 1 && tag.right <= row.right + 1;
            })), 'Format tags must wrap inside their caption.');
            assert.equal(await page.locator('#row3 .tadl-representation-formats, #row3 small').count(), 0, 'Metadata-only items need no empty format or identifier row.');
            await page.addScriptTag({path:process.env.TADL_AXE_PATH || require.resolve('axe-core/axe.min.js')});
            const violations = await page.evaluate(async () => (await axe.run('#main', {runOnly:{type:'tag', values:['wcag2a','wcag2aa','wcag21a','wcag21aa','wcag22aa']}})).violations);
            assert.deepEqual(violations.map(item => item.id), [], `${name} ${width}px accessibility violations.`);
            if (process.env.TADL_A11Y_ARTIFACT_DIR) {
                fs.mkdirSync(process.env.TADL_A11Y_ARTIFACT_DIR, {recursive:true});
                await page.screenshot({path:path.join(process.env.TADL_A11Y_ARTIFACT_DIR, `collection-${name}-${width}.png`)});
            }
        }
    }
    await page.goto(origin + '/formats');
    await fixedCrop(page.locator('#row1'));
    assert.equal(await page.locator('#row1 .tadl-representation-format').count(), 6, 'Hovered cards must retain their format tags.');
    console.log('Collection formats: square black/white tags, wrapping, metadata-only items and axe passed at 320–1600px.');
    const touch = await browser.newContext({viewport:{width:390,height:844}, isMobile:true, hasTouch:true});
    await touch.route('**/*', route => route.request().url().startsWith(origin) ? route.continue() : route.abort());
    const touchPage = await touch.newPage(); await touchPage.goto(origin + '/wide');
    await touchPage.locator('#row100 .bResultItemImg a').tap();
    await touchPage.waitForURL(origin + '/Detail/objects/100');
    assert.deepEqual(errors, [], 'Thumbnail grid browser errors.');
    console.log('Resize, AJAX insertion, reduced motion, List view and direct touch navigation passed.');
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (browser) await browser.close(); server.close();
});
