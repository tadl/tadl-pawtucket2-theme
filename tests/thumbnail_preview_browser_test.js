/* Actual grid markup and thumbnail scripts; synthetic images and local assets only. */
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
const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost');
    if (['/wide', '/sidebar', '/list'].includes(url.pathname)) {
        const scripts = ['thumbnail-focus', 'thumbnail-preview'].map(name => `<script src="/themes/tadl/assets/pawtucket/js/${name}.js"></script>`).join('');
        const header = shell.header.replace('</head>', scripts + '</head>');
        const tiles = '<section class="tadl-authority-items"><h2>Related items</h2><div class="row">' + cards.tiles + '</div></section>';
        const content = url.pathname === '/list' ? cards.list : url.pathname === '/sidebar'
            ? '<div class="tadl-authority-layout">' + tiles + '<aside class="tadl-authority-sidebar"><h2>Record information</h2><p>Synthetic metadata.</p></aside></div>'
            : tiles.replace(' class="tadl-authority-items"', '');
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        response.end(header + '<h1>Synthetic thumbnail previews</h1>' + content + shell.footer);
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
async function closed(card) { await card.evaluate(node => new Promise((resolve, reject) => {
    if (!node.classList.contains('tadl-thumbnail-preview-active')) { resolve(); return; }
    const timer = setTimeout(() => { observer.disconnect(); reject(new Error('Thumbnail preview did not close.')); }, 5000);
    const observer = new MutationObserver(() => { if (!node.classList.contains('tadl-thumbnail-preview-active')) { clearTimeout(timer); observer.disconnect(); resolve(); } });
    observer.observe(node, {attributes:true, attributeFilter:['class']});
})); }
async function expanded(page, card) {
    await page.waitForFunction(id => {
        const node = document.getElementById(id), box = node.querySelector('.bResultItemImg'), image = box.querySelector('img');
        const height = Math.max(box.clientWidth / 1.5, Math.min(box.clientWidth * image.naturalHeight / image.naturalWidth, innerHeight * .75));
        const preview = node.querySelector('.tadl-thumbnail-preview-image');
        return node.classList.contains('tadl-thumbnail-preview-open') && preview && preview.complete
            && Math.abs(box.getBoundingClientRect().height - height) < 1 && getComputedStyle(preview).opacity === '1';
    }, await card.getAttribute('id'));
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
            const card = page.locator('#row100'), box = card.locator('.bResultItemImg');
            const base = await box.boundingBox();
            assert(Math.abs(base.width / base.height - 1.5) < .01);
            const nextRow = await page.locator('#row104').evaluate(node => node.getBoundingClientRect().top + scrollY);
            const links = await card.locator('a').count();
            await box.hover(); await expanded(page, card);
            assert((await box.boundingBox()).height > base.height, 'Portrait preview must expand downward.');
            const fullBox = await box.boundingBox();
            await page.mouse.move(fullBox.x + 20, fullBox.y + fullBox.height - 10);
            assert((await card.getAttribute('class')).includes('tadl-thumbnail-preview-open'), 'Moving onto the revealed image must keep the preview open.');
            assert.equal(await page.locator('#row104').evaluate(node => node.getBoundingClientRect().top + scrollY), nextRow, 'Expanded previews must not move the grid rows.');
            assert.equal(await card.locator('a').count(), links, 'Preview must retain original links without duplicate controls.');
            assert.equal(await card.locator('.tadl-thumbnail-preview-image').getAttribute('aria-hidden'), 'true');
            assert.equal(await card.locator('.tadl-thumbnail-preview-image').getAttribute('src'), await card.locator('img:not(.tadl-thumbnail-preview-image)').evaluate(node => node.currentSrc), 'Preview must reuse the selected derivative.');
            assert.equal(await card.locator('.tadl-thumbnail-preview-image').evaluate(node => getComputedStyle(node).objectFit), 'contain');
            if (process.env.TADL_A11Y_ARTIFACT_DIR) {
                fs.mkdirSync(process.env.TADL_A11Y_ARTIFACT_DIR, {recursive:true});
                await page.screenshot({path:path.join(process.env.TADL_A11Y_ARTIFACT_DIR, `thumbnail-preview-${name}-${width}.png`)});
            }
            await page.mouse.move(1, 1);
            assert(await box.evaluate(node => node.getAnimations().some(animation => animation.effect.getTiming().duration > 0)), 'Contraction should animate.');
            await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
            await page.mouse.move(base.x + 20, base.y + 20); await expanded(page, card);
            await page.mouse.move(1, 1);
            await closed(card);
            assert(Math.abs((await box.boundingBox()).height - base.height) < 1, 'Leaving must restore the normal crop.');
            assert(await card.evaluate(node => !node.style.minHeight && !node.parentElement.style.height && !node.querySelector('.bResultItemImg').style.height), 'Closing must release temporary layout dimensions.');
            await box.hover(); await expanded(page, card); await page.keyboard.press('Escape'); await closed(card);
            assert(!(await card.getAttribute('class')).includes('tadl-thumbnail-preview-open'));
            await page.mouse.move(1, 1);
            await card.locator('.bResultItemImg a').focus(); await expanded(page, card);
            await page.locator('.tadl-logo-link').focus(); await closed(card);
            const wide = page.locator('#row103'); await wide.locator('.bResultItemImg').hover(); await expanded(page, wide);
            assert(Math.abs((await wide.locator('.bResultItemImg').boundingBox()).width / (await wide.locator('.bResultItemImg').boundingBox()).height - 1.5) < .01, 'Landscape preview can reveal the full image without enlarging the box.');
            await page.mouse.move(1, 1); await closed(wide);
            const tall = page.locator('#row104'); await tall.locator('.bResultItemImg').hover(); await expanded(page, tall);
            assert((await tall.locator('.bResultItemImg').boundingBox()).height <= 751, 'Very tall previews must fit their full image inside a viewport-bounded box.');
            await page.mouse.move(1, 1); await closed(tall);
            await page.locator('#row102 .bResultItemImg').hover();
            assert.equal(await page.locator('#row102.tadl-thumbnail-preview-active').count(), 0, 'Placeholder cards must not expand.');
            console.log(`${name} ${width}px: hover/leave animation, full portrait/landscape, stable grid, focus/Escape and placeholders passed`);
        }
    }
    await page.goto(origin + '/wide'); await page.mouse.move(1, 1); await page.keyboard.press('Tab');
    await page.locator('#row100 .bResultItemImg a').focus(); await expanded(page, page.locator('#row100'));
    await page.setViewportSize({width:1024, height:800}); await expanded(page, page.locator('#row100'));
    await page.locator('.tadl-logo-link').focus(); await closed(page.locator('#row100'));
    await page.evaluate(html => {
        const row = document.createElement('div'); row.id = 'ajax-results'; row.innerHTML = html.replace(/id='row/g, "id='ajax-row"); document.querySelector('#main').append(row);
    }, cards.tiles);
    await page.locator('#ajax-row100 .bResultItemImg').hover(); await expanded(page, page.locator('#ajax-row100'));
    await page.locator('#ajax-results').evaluate(node => { window.syntheticRemovedResults = node; node.remove(); });
    await page.mouse.move(1, 1);
    await page.evaluate(() => { document.querySelector('#main').append(window.syntheticRemovedResults); delete window.syntheticRemovedResults; });
    await page.locator('#ajax-row100 .bResultItemImg a').focus(); await expanded(page, page.locator('#ajax-row100'));
    await page.locator('.tadl-logo-link').focus(); await closed(page.locator('#ajax-row100'));
    await page.locator('#ajax-results').evaluate(node => node.remove());
    await page.emulateMedia({reducedMotion:'reduce'}); await page.mouse.move(1, 1);
    await page.locator('#row100 .bResultItemImg').hover(); await expanded(page, page.locator('#row100'));
    assert(await page.locator('#row100 .bResultItemImg').evaluate(node => node.getAnimations().every(animation => animation.effect.getTiming().duration < 1)), 'Reduced motion must disable expansion animation.');
    await page.mouse.move(1, 1); await closed(page.locator('#row100'));
    await page.goto(origin + '/list'); await page.locator('.bResultListItemImg').first().hover();
    assert.equal(await page.locator('.tadl-thumbnail-preview-active').count(), 0, 'List view must remain unchanged.');
    const touch = await browser.newContext({viewport:{width:390,height:844}, isMobile:true, hasTouch:true});
    await touch.route('**/*', route => route.request().url().startsWith(origin) ? route.continue() : route.abort());
    const touchPage = await touch.newPage(); await touchPage.goto(origin + '/wide');
    await touchPage.locator('#row100 .bResultItemImg a').tap();
    await touchPage.waitForURL(origin + '/Detail/objects/100');
    assert.deepEqual(errors, [], 'Thumbnail preview browser errors.');
    console.log('Resize, AJAX insertion/removal, reduced motion, List view and direct touch navigation passed.');
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (browser) await browser.close(); server.close();
});
