/* Browser dependencies are development tools; no application/database is bootstrapped. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const {spawnSync} = require('node:child_process');
const {chromium} = require('playwright');
const theme = path.resolve(__dirname, '..');
const reference = path.resolve(process.env.TADL_REFERENCE_ROOT || path.join(theme, '../../pawtucket2'));
const axePath = process.env.TADL_AXE_PATH || require.resolve('axe-core/axe.min.js');
const axeSource = fs.readFileSync(axePath, 'utf8');
const rendered = spawnSync(process.env.PHP_BINARY || 'php', [path.join(__dirname, 'accessibility_test.php'), '--fixtures'], {encoding:'utf8'});
assert.equal(rendered.status, 0, rendered.stderr || rendered.stdout);
const fixtures = JSON.parse(rendered.stdout);
const artifacts = process.env.TADL_A11Y_ARTIFACT_DIR;
if (artifacts) { fs.mkdirSync(artifacts, {recursive:true, mode:0o700}); }
const reorderRequests = [];
let failOrder = false;
const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost');
    const name = url.pathname.slice(1);
    if (url.pathname.startsWith('/Lightbox/AjaxReorderItems/')) {
        assert.equal(request.method, 'POST');
        assert(url.pathname.includes('/csrfToken/synthetic-token/row_ids/'));
        reorderRequests.push(url.pathname);
        response.setHeader('Content-Type', 'application/json');
        response.end(JSON.stringify({status:failOrder ? 'error' : 'ok'}));
        return;
    }
    if (Object.hasOwn(fixtures, name)) {
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        const fragment = name === 'login-dialog' || name === 'media-dialog';
        const header = name === 'featured' ? fixtures['front-header'] : name === 'gallery' ? fixtures['gallery-header'] : fixtures.header;
        response.end(fragment ? fixtures[name] : header + fixtures[name] + fixtures.footer);
        return;
    }
    if (url.pathname === '/Gallery/getSetItemRep') {
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        response.end(fixtures['gallery-item-' + url.searchParams.get('item_id')]);
        return;
    }
    if (url.pathname === '/Gallery/getSetItemInfo') { response.end('<h2>Synthetic gallery item</h2>'); return; }
    if (url.pathname === '/synthetic.svg') {
        response.setHeader('Content-Type', 'image/svg+xml');
        response.end('<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400"><rect width="600" height="400" fill="#49688e"/></svg>');
        return;
    }
    let base, relative;
    if (url.pathname.startsWith('/native/')) { base = path.join(reference, 'assets'); relative = url.pathname.slice(8); }
    if (url.pathname.startsWith('/themes/tadl/')) { base = theme; relative = url.pathname.slice(13); }
    if (base) {
        const file = path.resolve(base, relative);
        if (file.startsWith(base + path.sep) && fs.existsSync(file) && fs.statSync(file).isFile()) {
            const types = {'.css':'text/css', '.js':'application/javascript', '.svg':'image/svg+xml', '.png':'image/png', '.jpg':'image/jpeg', '.woff':'font/woff', '.woff2':'font/woff2'};
            response.setHeader('Content-Type', types[path.extname(file)] || 'application/octet-stream');
            fs.createReadStream(file).pipe(response);
            return;
        }
    }
    response.statusCode = 404;
    response.end('Synthetic fixture resource not found');
});
let browser;
(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const origin = 'http://127.0.0.1:' + server.address().port;
    browser = await chromium.launch({headless:true, ...(process.env.TADL_BROWSER_CHANNEL ? {channel:process.env.TADL_BROWSER_CHANNEL} : {})});
    const context = await browser.newContext({reducedMotion:'reduce'});
    // Synthetic fixtures never contact external applications, submit mail, or log in.
    await context.route('**/*', route => route.request().url().startsWith(origin) ? route.continue() : route.abort());
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const reports = [];
    for (const width of [1280, 320]) {
        await page.setViewportSize({width, height:900});
        for (const name of ['contact', 'contact-errors', 'password-recovery', 'new-password', 'cookies', 'gallery', 'lightbox-order', 'featured', 'notifications']) {
            await page.goto(origin + '/' + name);
            if (name === 'gallery') { await page.locator('.galleryDetailNext').waitFor(); }
            await page.evaluate(axeSource);
            const result = await page.evaluate(async () => {
                const result = await axe.run(document, {runOnly:{type:'tag', values:['wcag2a','wcag2aa','wcag21a','wcag21aa','wcag22aa','best-practice']}});
                return result.violations.map(v => ({id:v.id, impact:v.impact, nodes:v.nodes.map(n => ({html:n.html, summary:n.failureSummary}))}));
            });
            assert.deepEqual(result, [], `${name} at ${width}px: ${JSON.stringify(result)}`);
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${name}: horizontal page overflow at ${width}px`);
            if (artifacts) { await page.screenshot({path:path.join(artifacts, `${name}-${width}.png`), fullPage:true}); }
            await page.evaluate(() => {
                const sizes = Array.from(document.querySelectorAll('#main *')).map(node => [node, parseFloat(getComputedStyle(node).fontSize)]);
                sizes.forEach(([node, size]) => { node.style.fontSize = (size * 2) + 'px'; });
            });
            await page.addStyleTag({content:'#main * {line-height:1.5;letter-spacing:.12em;word-spacing:.16em} #main p {margin-bottom:2em}'});
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${name}: overflow with enlarged/spaced text at ${width}px`);
            reports.push(`${name} ${width}px: axe, reflow, enlarged text and spacing passed`);
        }
    }
    await page.setViewportSize({width:1280, height:900});
    await page.goto(origin + '/contact');
    await page.keyboard.press('Tab');
    assert.equal(await page.locator(':focus').textContent(), 'Skip to main content');
    await page.keyboard.press('Enter');
    assert.equal(await page.locator(':focus').getAttribute('id'), 'main');
    await page.goto(origin + '/contact-errors');
    assert.equal(await page.locator(':focus').getAttribute('data-tadl-error-summary'), '');
    await page.goto(origin + '/cookies');
    const on = page.locator('input[name="cookie_options_analytics"][value="1"]');
    await on.focus();
    await page.keyboard.press('ArrowRight');
    assert(await page.locator('input[name="cookie_options_analytics"][value="0"]').isChecked());
    await page.locator('summary.cookieCount').first().focus();
    await page.keyboard.press('Enter');
    assert(await page.locator('details.cookieByCategory').first().getAttribute('open') !== null);
    await page.goto(origin + '/gallery');
    await page.locator('.galleryDetailNext').waitFor();
    await page.locator('.galleryDetailNext').focus();
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => document.querySelector('#galleryIcon2').getAttribute('aria-current') === 'true' && document.querySelector('#galleryDetailImageArea').getAttribute('aria-busy') === 'false' && document.activeElement.classList.contains('galleryDetailNext'));
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => document.querySelector('#galleryIcon3').getAttribute('aria-current') === 'true' && document.activeElement.classList.contains('galleryDetailPrevious'));
    // Exercise the actual native panel, the changed dialog template and its focus adapter.
    await page.goto(origin + '/contact');
    await page.evaluate(() => {
        const opener = document.createElement('button'); opener.id = 'synthetic-opener'; opener.textContent = 'Open login dialog';
        opener.onclick = () => caMediaPanel.showPanel('/login-dialog');
        document.querySelector('#main').append(opener);
    });
    await page.locator('#synthetic-opener').focus();
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => document.activeElement.classList.contains('tadl-dialog-close'));
    await page.evaluate(axeSource);
    const dialogFailures = await page.evaluate(async () => (await axe.run('#caMediaPanel', {runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21a','wcag21aa','wcag22aa']}})).violations.map(v=>v.id));
    assert.deepEqual(dialogFailures, []);
    await page.keyboard.press('Shift+Tab');
    assert(await page.locator('#caMediaPanel').evaluate(node => node.contains(document.activeElement)));
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => document.activeElement.id === 'synthetic-opener');
    assert.equal(await page.locator('header.tadl-header').getAttribute('inert'), null);
    await page.evaluate(() => caMediaPanel.showPanel('/media-dialog'));
    await page.waitForFunction(() => document.activeElement.getAttribute('aria-label') === 'Close media viewer');
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => document.querySelector('#caMediaPanel').getAttribute('aria-hidden') === 'true');
    assert.equal(await page.evaluate(() => caUI.panelCount), 0, 'Closing a media dialog must invoke the native lifecycle exactly once.');
    await page.setViewportSize({width:320, height:900});
    await page.goto(origin + '/contact');
    await page.locator('.navbar-toggle').focus();
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => document.querySelector('.navbar-toggle').getAttribute('aria-expanded') === 'true');
    await page.locator('.tadl-main-nav button.dropdown-toggle').focus();
    await page.keyboard.press('Space');
    await page.waitForFunction(() => document.querySelector('.tadl-main-nav button.dropdown-toggle').getAttribute('aria-expanded') === 'true');
    await page.keyboard.press('Tab');
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('.tadl-main-nav button.dropdown-toggle').getAttribute('aria-expanded'), 'false');
    await page.goto(origin + '/lightbox-order');
    const move = page.locator('#row-1 [data-move="later"]');
    await move.focus();
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => document.querySelector('#tadl-lightbox-order-status').textContent === 'Item order saved.');
    assert.deepEqual(await page.locator('#lbSetResultLoadContainer > [id]').evaluateAll(nodes => nodes.map(n => n.id)), ['row-2','row-1','row-3']);
    assert.equal(await page.locator(':focus').getAttribute('data-move'), 'later');
    assert(reorderRequests[0].includes('row[]=2&row[]=1&row[]=3'));
    failOrder = true;
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => document.querySelector('#tadl-lightbox-order-status').textContent.startsWith('Could not save'));
    assert.deepEqual(await page.locator('#lbSetResultLoadContainer > [id]').evaluateAll(nodes => nodes.map(n => n.id)), ['row-2','row-1','row-3']);
    await page.goto(origin + '/featured');
    await page.locator('[data-tadl-next]').focus();
    await page.keyboard.press('Enter');
    assert.equal(await page.locator('[data-tadl-slide="1"]').getAttribute('aria-hidden'), 'false');
    assert.equal(await page.locator('[data-tadl-slide="0"] a').getAttribute('tabindex'), '-1');
    assert((await page.locator('[data-tadl-slide-status]').textContent()).includes('2 through 2 of 4'));
    assert.deepEqual(errors, [], 'Unexpected browser errors');
    console.log(reports.join('\n'));
    console.log('Keyboard checks passed: skip link, errors, cookies, gallery AJAX, modal focus/Tab/Escape/restoration, mobile navigation and lightbox reorder/rollback.');
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (browser) { await browser.close(); }
    server.close();
});
