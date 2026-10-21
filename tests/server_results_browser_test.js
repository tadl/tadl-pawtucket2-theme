/* Real server-composed collection fragments; local synthetic native boundaries only. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const {spawnSync} = require('node:child_process');
const {chromium} = require('playwright');
const theme = path.resolve(__dirname, '..');
const reference = path.resolve(process.env.TADL_REFERENCE_ROOT || path.join(theme, '../../pawtucket2'));
function php(file, args = [], input) {
    const result = spawnSync('php', [path.join(__dirname, file), ...args], {input, encoding:'utf8'});
    assert.equal(result.status, 0, result.stderr || result.stdout);
    return result.stdout;
}
const shell = JSON.parse(php('accessibility_test.php', ['--fixtures']));
const hierarchy = JSON.parse(php('collection_hierarchy_test.php', ['--fixtures']));
const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost');
    for (const [prefix, base] of [['/native/', path.join(reference, 'assets')], ['/themes/tadl/assets/', path.join(theme, 'assets')]]) {
        if (!url.pathname.startsWith(prefix)) continue;
        const file = path.resolve(base, url.pathname.slice(prefix.length));
        if (!file.startsWith(base + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) { response.writeHead(404); response.end(); return; }
        response.setHeader('Content-Type', ({'.css':'text/css','.js':'application/javascript','.woff':'font/woff','.woff2':'font/woff2'})[path.extname(file)] || 'application/octet-stream');
        fs.createReadStream(file).pipe(response); return;
    }
    if (/\.(jpg|svg)$/.test(url.pathname)) { response.setHeader('Content-Type','image/svg+xml'); response.end('<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400"/>'); return; }
    const bits = url.pathname.split('/').filter(Boolean), params = {};
    for (let i = 3; i + 1 < bits.length; i += 2) params[bits[i]] = decodeURIComponent(bits[i + 1]);
    Object.assign(params, Object.fromEntries(url.searchParams));
    const cookies = {};
    for (const pair of (request.headers.cookie || '').split('; ')) {
        const split = pair.indexOf('=');
        if (split > 0) cookies[pair.slice(0, split)] = decodeURIComponent(pair.slice(split + 1));
    }
    const html = url.pathname === '/hierarchy' || params.collection_view === 'hierarchy'
        ? '<div id="collectionHierarchy">' + hierarchy.branches[params.collection_branch === '60' ? '60' : '20'] + '</div>'
        : php('media_preferences_test.php', ['--server-render'], JSON.stringify({params,cookies}));
    response.setHeader('Content-Type','text/html; charset=utf-8');
    response.end(shell.header + '<h1>Synthetic collection</h1><div id="browseResultsContainer">' + html + '</div>' + shell.footer);
});
let browser;
(async () => {
    await new Promise(resolve => server.listen(0,'127.0.0.1',resolve));
    const origin = 'http://127.0.0.1:' + server.address().port;
    browser = await chromium.launch({headless:true, ...(process.env.TADL_BROWSER_CHANNEL ? {channel:process.env.TADL_BROWSER_CHANNEL} : {})});
    for (const javaScriptEnabled of [false,true]) {
        const context = await browser.newContext({javaScriptEnabled});
        const ajax = [], errors = [];
        await context.route('**/*', route => route.request().url().startsWith(origin) ? route.continue() : route.abort());
        const page = await context.newPage();
        page.on('request', request => { if (['xhr','fetch'].includes(request.resourceType())) ajax.push(request.url()); });
        page.on('pageerror', error => errors.push(error.message));
        await page.goto(origin + '/Detail/collections/888888');
        assert.equal(await page.locator('[id^=row]').count(),18, 'Cards must exist without JavaScript or a follow-up request.');
        assert.equal(await page.locator('#browseResultsContainer').getByText(/Loading/).count(),0);
        const firstResponse = await context.request.get(origin + '/Detail/collections/888888');
        assert(/id=['"]row1000['"]/.test(await firstResponse.text()), 'The initial HTTP response must contain the actual result cards.');
        for (const width of [320,768,1200,1280,1600]) {
            await page.setViewportSize({width,height:1000});
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${width}px: server result controls must reflow.`);
        }
        await page.locator('.tadl-results-sort > summary').focus(); await page.keyboard.press('Enter');
        await page.getByRole('link',{name:'Sort by Title, Ascending',exact:true}).click();
        await page.waitForURL('**/sort/Title/direction/asc/**');
        assert(page.url().includes('/s/0/') && page.url().includes('/per_page/18'), 'Sort changes must preserve size and reset paging.');
        assert.equal(await page.locator('.tadl-sort-choice[aria-current=true]').getAttribute('aria-label'),'Sort by Title. Currently Ascending; activate for Descending.');
        assert.equal(await page.locator('.tadl-sort-toggle > span').first().textContent(),'Sort by Title');
        assert.equal(await page.locator('.tadl-sort-direction').textContent(),'↑');
        assert.equal(await page.locator('[id^=row]').count(),18);
        for (const [current,next,arrow] of [['Ascending','Descending','↓'],['Descending','Ascending','↑']]) {
            await page.locator('.tadl-results-sort > summary').click();
            await page.getByRole('link',{name:`Sort by Title. Currently ${current}; activate for ${next}.`,exact:true}).click();
            await page.waitForURL(`**/sort/Title/direction/${next === 'Descending' ? 'desc' : 'asc'}/**`);
            assert.equal(await page.locator('.tadl-sort-direction').textContent(),arrow,'Clicking the current field must reverse the order and update its indicator.');
        }
        await page.locator('.tadl-results-sort > summary').click();
        await page.getByRole('link',{name:'Sort by Identifier, Ascending',exact:true}).click();
        await page.waitForURL('**/sort/Identifier/direction/asc/**');
        assert.equal(await page.locator('[id^=row]').first().getAttribute('id'),'row1000');
        await page.getByRole('link',{name:'Next',exact:true}).first().click();
        await page.waitForFunction(() => document.querySelector('[id^=row]')?.id === 'row1018');
        await page.goBack();
        await page.waitForFunction(() => document.querySelector('[id^=row]')?.id === 'row1000');
        await page.getByRole('link',{name:'List',exact:true}).click();
        await page.waitForFunction(() => document.querySelectorAll('[id^=row]').length === 25);
        await page.getByRole('combobox',{name:'Items per page'}).selectOption('50');
        if (!javaScriptEnabled) await page.getByRole('button',{name:'Apply',exact:true}).click();
        await page.waitForFunction(() => document.querySelectorAll('[id^=row]').length === 50);
        await page.getByRole('link',{name:'Next',exact:true}).first().click();
        await page.waitForFunction(() => document.querySelector('[id^=row]')?.id === 'row1050');
        await page.goto(origin + '/hierarchy');
        assert.equal(await page.locator('#collectionLoad').getByText('Volume 1', {exact:true}).count(),1);
        await page.locator('[data-collection-branch="60"]').click();
        await page.waitForURL('**collection_branch=60**');
        assert.equal(await page.locator('#collectionLoad').getByText('Synthetic second branch folder', {exact:true}).count(),1);
        await page.goBack();
        assert.equal(await page.locator('#collectionLoad').getByText('Volume 1', {exact:true}).count(),1);
        if (javaScriptEnabled) {
            await page.goto(origin + '/hierarchy#collection-60');
            await page.waitForURL('**collection_branch=60**');
            assert.equal(await page.locator('#collectionLoad').getByText('Synthetic second branch folder',{exact:true}).count(),1, 'Legacy branch bookmarks must use normal navigation to the selected branch.');
        }
        assert.deepEqual(ajax,[], 'Result navigation must not fetch or append fragments.');
        assert.deepEqual(errors,[], 'Server result views must have no browser script errors.');
        await context.close();
    }
    process.stdout.write('Server result browser checks passed: initial HTML, no-script paging/Back/view/size controls, responsive layout, no result XHR.\n');
})().catch(error => { console.error(error); process.exitCode=1; }).finally(async () => { if (browser) await browser.close(); server.close(); });
