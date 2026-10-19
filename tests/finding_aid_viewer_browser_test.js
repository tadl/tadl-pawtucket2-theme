/* Optional native PDF.js integration; synthetic catalogue and locally served assets only. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const {spawnSync} = require('node:child_process');
const {chromium} = require('playwright');
const theme = path.resolve(__dirname, '..');
const reference = path.resolve(process.env.TADL_REFERENCE_ROOT || path.join(theme, '../../pawtucket2'));
assert(process.env.TADL_TEST_COMPOSER_AUTOLOAD, 'Set TADL_TEST_COMPOSER_AUTOLOAD to the reference Pawtucket vendor/autoload.php.');
function fixture(file, argument) {
    const result = spawnSync(process.env.PHP_BINARY || 'php', [path.join(__dirname, file), argument], {encoding:'utf8'});
    assert.equal(result.status, 0, result.stderr || result.stdout);
    return JSON.parse(result.stdout);
}
const pageParts = fixture('accessibility_test.php', '--fixtures');
const links = fixture('collection_detail_scripts_test.php', '--finding-aid-fixture').links;
const aid = fixture('finding_aid_test.php', '--viewer-fixture');
const pdf = Buffer.from(aid.pdf, 'base64');
assert(pdf.subarray(0, 5).equals(Buffer.from('%PDF-')));
let pdfRequests = 0;
const viewerMethods = [];
const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost');
    if (url.pathname === '/') {
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        const header = pageParts.header.replace('</head>', '<link rel="stylesheet" href="/native/pdfjs/viewer/viewer.css"><script src="/native/pdfjs/pdf.js"></script></head>');
        response.end(header + '<h1>Synthetic collection</h1>' + links + pageParts.footer);
        return;
    }
    if (url.pathname === '/synthetic/CollectionFindingAid/Viewer/collection_id/42') {
        viewerMethods.push(request.method);
        const viewer = aid.viewers[request.method];
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        response.writeHead(viewer ? viewer.status : 405, viewer ? viewer.headers : {Allow:'GET, POST'});
        response.end(viewer ? viewer.body : 'Method Not Allowed');
        return;
    }
    if (/^\/synthetic\/CollectionFindingAid\/(?:View|Download)\/collection_id\/42$/.test(url.pathname)) {
        const attachment = url.pathname.includes('/Download/');
        if (!attachment) { pdfRequests++; }
        response.setHeader('Content-Type', 'application/pdf');
        response.setHeader('Content-Disposition', (attachment ? 'attachment' : 'inline') + '; filename="SYN-42-finding-aid.pdf"');
        response.setHeader('Cache-Control', 'private, no-store');
        response.end(pdf);
        return;
    }
    let base, relative;
    if (url.pathname.startsWith('/native/')) { base = path.join(reference, 'assets'); relative = url.pathname.slice(8); }
    if (url.pathname.startsWith('/synthetic/assets/')) { base = path.join(reference, 'assets'); relative = url.pathname.slice('/synthetic/assets/'.length); }
    if (url.pathname.startsWith('/themes/tadl/assets/')) { base = path.join(theme, 'assets'); relative = url.pathname.slice('/themes/tadl/assets/'.length); }
    if (base) {
        const file = path.resolve(base, relative);
        if (file.startsWith(base + path.sep) && fs.existsSync(file) && fs.statSync(file).isFile()) {
            const types = {'.css':'text/css', '.js':'application/javascript', '.svg':'image/svg+xml', '.png':'image/png', '.jpg':'image/jpeg', '.woff':'font/woff', '.woff2':'font/woff2', '.properties':'text/plain'};
            response.setHeader('Content-Type', types[path.extname(file)] || 'application/octet-stream');
            fs.createReadStream(file).pipe(response);
            return;
        }
    }
    response.statusCode = 404;
    response.end('Synthetic resource not found');
});
let browser;
(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const origin = 'http://127.0.0.1:' + server.address().port;
    browser = await chromium.launch({headless:true, ...(process.env.TADL_BROWSER_CHANNEL ? {channel:process.env.TADL_BROWSER_CHANNEL} : {})});
    const context = await browser.newContext({reducedMotion:'reduce'});
    await context.route('**/*', route => route.request().url().startsWith(origin) ? route.continue() : route.abort());
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    for (const width of [1280, 390]) {
        await page.setViewportSize({width, height:900});
        await page.goto(origin);
        const viewerResponse = page.waitForResponse(response => response.url() === origin + '/synthetic/CollectionFindingAid/Viewer/collection_id/42');
        await page.getByRole('link', {name:'View finding aid (PDF)', exact:true}).click();
        assert.equal((await viewerResponse).status(), 200, 'Native media-panel POST must pass the actual controller method guard.');
        await page.locator('#viewer .page canvas').first().waitFor({state:'visible'});
        assert.equal(page.url(), origin + '/');
        assert.equal(await page.locator('#caMediaPanel').getAttribute('aria-hidden'), 'false');
        assert(await page.locator('#caMediaPanel').evaluate(node => node.contains(document.activeElement)));
        const pages = await page.evaluate(() => PDFViewerApplication.pdfDocument.numPages);
        assert(pages >= 2);
        await page.locator('#pageNumber').fill('2');
        await page.locator('#pageNumber').press('Enter');
        await page.waitForFunction(() => PDFViewerApplication.page === 2);
        await page.locator('#viewFind').click();
        await page.locator('#findInput').fill('clippings');
        await page.locator('#findInput').press('Enter');
        await page.waitForFunction(() => PDFViewerApplication.findController.pageMatches.some(matches => matches.length > 0));
        const downloadPromise = page.waitForEvent('download');
        await page.getByRole('link', {name:'Download PDF', exact:true}).click();
        assert.equal((await downloadPromise).suggestedFilename(), 'SYN-42-finding-aid.pdf');
        if (process.env.TADL_A11Y_ARTIFACT_DIR) {
            fs.mkdirSync(process.env.TADL_A11Y_ARTIFACT_DIR, {recursive:true});
            await page.screenshot({path:path.join(process.env.TADL_A11Y_ARTIFACT_DIR, `finding-aid-viewer-${width}.png`)});
        }
        await page.getByRole('button', {name:'Close media viewer', exact:true}).click();
        await page.waitForFunction(() => document.querySelector('#caMediaPanel').getAttribute('aria-hidden') === 'true');
        assert.equal(await page.evaluate(() => document.activeElement.textContent.replace(/\s+/g, ' ').trim()), 'View finding aid (PDF)');
        await page.getByRole('link', {name:'View finding aid (PDF)', exact:true}).click();
        await page.locator('#viewer .page canvas').first().waitFor({state:'visible'});
        await page.getByRole('button', {name:'Close media viewer', exact:true}).click();
        await page.waitForFunction(() => document.querySelector('#caMediaPanel').getAttribute('aria-hidden') === 'true');
    }
    assert.deepEqual(errors, [], 'Native document viewer JavaScript errors.');
    assert.deepEqual(viewerMethods, ['POST', 'POST', 'POST', 'POST'], 'Exercise the native media-panel transport on every opening.');
    assert.equal(pdfRequests, 4, 'Each opening should request the PDF once without pre-generating or caching it.');
    console.log('Finding-aid browser passed: native PDF.js rendering, paging, text search, download, close/reopen and focus restoration at 1280/390px.');
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (browser) { await browser.close(); }
    server.close();
});
