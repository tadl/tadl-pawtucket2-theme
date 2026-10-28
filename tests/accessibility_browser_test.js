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
const objectPhotos = spawnSync(process.env.PHP_BINARY || 'php', [path.join(__dirname, 'object_detail_media_test.php'), '--browser-fixtures'], {encoding:'utf8'});
assert.equal(objectPhotos.status, 0, objectPhotos.stderr || objectPhotos.stdout);
Object.assign(fixtures, JSON.parse(objectPhotos.stdout));
const collectionHeaders = spawnSync(process.env.PHP_BINARY || 'php', [path.join(__dirname, 'collection_detail_scripts_test.php'), '--staff-shortcut-fixtures'], {encoding:'utf8'});
assert.equal(collectionHeaders.status, 0, collectionHeaders.stderr || collectionHeaders.stdout);
Object.assign(fixtures, JSON.parse(collectionHeaders.stdout));
const authorityNavigation = spawnSync(process.env.PHP_BINARY || 'php', [path.join(__dirname, 'authority_detail_test.php'), '--navigation-fixtures'], {encoding:'utf8'});
assert.equal(authorityNavigation.status, 0, authorityNavigation.stderr || authorityNavigation.stdout);
const authorityNavigationFixtures = JSON.parse(authorityNavigation.stdout);
Object.assign(fixtures, authorityNavigationFixtures);
const navigationFixtures = spawnSync(process.env.PHP_BINARY || 'php', [path.join(__dirname, 'browse_navigation_test.php'), '--fixtures'], {encoding:'utf8'});
assert.equal(navigationFixtures.status, 0, navigationFixtures.stderr || navigationFixtures.stdout);
for (const [name, navigation] of Object.entries(JSON.parse(navigationFixtures.stdout))) {
    fixtures[name] = '<div class="row tadl-object-detail"><div class="col-xs-12 tadl-object-main"><div class="container tadl-object-content"><div class="row">'
        + '<div class="col-sm-6 col-md-6 col-lg-6 tadl-object-media"><img src="/synthetic.svg" alt="Synthetic catalogue image" width="600" height="400" style="max-width:100%;height:auto"></div>'
        + '<div class="col-sm-6 col-md-6 col-lg-6 tadl-object-info tadl-detail-metadata">' + navigation + '<h1>Synthetic object</h1></div></div></div></div></div>';
}
const sortFixtures = spawnSync(process.env.PHP_BINARY || 'php', [path.join(__dirname, 'result_context_heading_test.php'), '--sort-fixtures'], {encoding:'utf8'});
assert.equal(sortFixtures.status, 0, sortFixtures.stderr || sortFixtures.stdout);
Object.assign(fixtures, JSON.parse(sortFixtures.stdout));
const citationFixtures = spawnSync(process.env.PHP_BINARY || 'php', [path.join(__dirname, 'user_features_test.php'), '--citation-fixtures'], {encoding:'utf8'});
assert.equal(citationFixtures.status, 0, citationFixtures.stderr || citationFixtures.stdout);
const objectCitations = JSON.parse(citationFixtures.stdout);
Object.assign(fixtures, objectCitations);
const documentTextFixture = spawnSync(process.env.PHP_BINARY || 'php', [path.join(__dirname, 'document_text_test.php'), '--lazy-fixtures'], {encoding:'utf8'});
assert.equal(documentTextFixture.status, 0, documentTextFixture.stderr || documentTextFixture.stdout);
const documentText = JSON.parse(documentTextFixture.stdout);
fixtures['document-text'] = '<h1>Synthetic document</h1>' + documentText.disclosure;
let documentTextRequests = 0;
const artifacts = process.env.TADL_A11Y_ARTIFACT_DIR;
if (artifacts) { fs.mkdirSync(artifacts, {recursive:true, mode:0o700}); }
const reorderRequests = [];
let failOrder = false;
const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost');
    const name = url.pathname.slice(1);
    if (url.pathname.startsWith('/filters/') || url.pathname === '/synthetic/objects') {
        const cookies={};
        for (const part of (request.headers.cookie || '').split('; ')) {
            const separator=part.indexOf('=');
            if (separator>0) { cookies[part.slice(0,separator)]=decodeURIComponent(part.slice(separator+1)); }
        }
        const rendered=spawnSync(process.env.PHP_BINARY || 'php',[path.join(__dirname,'result_context_heading_test.php'),'--filter-fixture'],
            {input:JSON.stringify({cookies,params:Object.fromEntries(url.searchParams),controller:url.pathname.endsWith('/browse')?'Browse':'Search'}),encoding:'utf8'});
        assert.equal(rendered.status,0,rendered.stderr || rendered.stdout);
        response.setHeader('Content-Type','text/html; charset=utf-8');
        response.end(url.searchParams.has('getFacet')?rendered.stdout:fixtures.header+rendered.stdout+fixtures.footer); return;
    }
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
        const header = name === 'featured' ? fixtures['front-header'] : name === 'gallery' ? fixtures['gallery-header'] : name === 'finding-aid-text' ? fixtures['finding-aid-header'] : fixtures.header;
        response.end(fragment ? fixtures[name] : header + fixtures[name] + fixtures.footer);
        return;
    }
    if (url.pathname === '/DocumentText/View') {
        documentTextRequests++; assert.equal(request.method, 'GET');
        response.setHeader('Content-Type', 'text/html; charset=utf-8'); response.end(documentText.content); return;
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
    const photo = url.pathname.match(/^\/synthetic-photo-([123])\.svg$/);
    if (photo) {
        const [width,height] = [[900,600],[900,540],[600,900]][Number(photo[1])-1];
        response.setHeader('Content-Type', 'image/svg+xml');
        response.end(`<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}"><rect width="100%" height="100%" fill="#49688e"/><circle cx="50%" cy="50%" r="160" fill="#e5ebef"/></svg>`);
        return;
    }
    let base, relative;
    if (url.pathname.startsWith('/native/')) { base = path.join(reference, 'assets'); relative = url.pathname.slice(8); }
    if (url.pathname.startsWith('/themes/tadl/assets/')) { base = path.join(theme, 'assets'); relative = url.pathname.slice('/themes/tadl/assets/'.length); }
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
        for (const name of ['contact', 'contact-errors', 'password-recovery', 'new-password', 'cookies', 'gallery', 'gallery-index', 'object-photos', 'lightbox-order', 'lightbox-menu', 'document-text', 'long-fields', 'finding-aid-text', 'featured', 'notifications', 'collection-staff', 'sort-results', 'object-return', 'object-return-only', 'back-to-top', ...Object.keys(authorityNavigationFixtures), ...Object.keys(objectCitations)]) {
            await page.goto(origin + '/' + name);
            if (name === 'gallery') { await page.locator('.galleryDetailNext').waitFor(); }
            await page.evaluate(async () => {
                await document.fonts.ready;
                // Hit-target checks need the new page painted, including after a resize.
                await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            });
            await page.evaluate(axeSource);
            const result = await page.evaluate(async () => {
                const result = await axe.run(document, {runOnly:{type:'tag', values:['wcag2a','wcag2aa','wcag21a','wcag21aa','wcag22aa','best-practice']}});
                return result.violations.map(v => ({id:v.id, impact:v.impact, nodes:v.nodes.map(n => ({html:n.html, summary:n.failureSummary}))}));
            });
            assert.deepEqual(result, [], `${name} at ${width}px: ${JSON.stringify(result)}`);
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${name}: horizontal page overflow at ${width}px`);
            if (name === 'finding-aid-text') {
                assert.equal(await page.title(), 'Finding aid: Synthetic "Archives" & Photograph Collection | TADL Local History Collection', 'Text finding aids need a descriptive, safely escaped browser title.');
                assert.deepEqual(await page.locator('.tadl-finding-aid-fields dd strong').allTextContents(), ['Abstract','History','Advertisements','Early History'], 'Recorded emphasis must survive inside public description and scope fields.');
                assert(await page.locator('.tadl-finding-aid-fields dd strong').evaluateAll(nodes => nodes.every(node => parseInt(getComputedStyle(node).fontWeight,10) >= 700)), 'Metadata emphasis must visibly render in bold.');
                const back = page.getByRole('link', {name:'Return to collection',exact:true});
                assert.equal(await back.getAttribute('href'), '/Detail/collections/42');
                await back.focus(); await page.keyboard.press('Tab');
                assert(await page.getByRole('link', {name:'Download PDF',exact:true}).evaluate(node => node === document.activeElement), 'Finding-aid return and download actions must follow ordinary link Tab order.');
            }
            if (name === 'notifications') {
                for (const notification of await page.locator('.notificationMessage').all()) {
                    const banner = await notification.boundingBox();
                    const close = await notification.getByRole('button', {name:'Dismiss notification'}).boundingBox();
                    assert(close.width >= 24 && close.width <= 32 && close.height >= 24 && close.height <= 32, 'Notification dismiss buttons must stay compact while retaining a usable pointer target.');
                    assert(close.y >= banner.y && close.y + close.height <= banner.y + banner.height, 'The dismiss button must remain inside its notification banner.');
                }
            }
            if (name.startsWith('authority-return-')) {
                const links = page.locator('.tadl-record-nav a');
                assert.equal(await links.count(), 3);
                assert((await links.first().textContent()).startsWith('Back to '));
                assert((await links.first().getAttribute('href')).includes('&s=18&per_page=18'));
                await links.first().focus();
                await page.keyboard.press('Tab'); assert(await links.nth(1).evaluate(node => node === document.activeElement));
                await page.keyboard.press('Tab'); assert(await links.nth(2).evaluate(node => node === document.activeElement));
            }
            if (name.startsWith('object-return')) {
                const links = page.locator('.tadl-record-nav a');
                assert.equal(await links.count(), name === 'object-return' ? 3 : 1);
                assert((await links.first().textContent()).startsWith('Back to '));
                const column = await page.locator('.tadl-object-info').boundingBox();
                const paddingRight = await page.locator('.tadl-object-info').evaluate(node => parseFloat(getComputedStyle(node).paddingRight));
                const rows = new Map();
                for (let index = 0; index < await links.count(); index++) {
                    const link = links.nth(index), box = await link.boundingBox();
                    const row = rows.get(box.y) || {left:box.x, right:box.x + box.width};
                    row.left = Math.min(row.left, box.x); row.right = Math.max(row.right, box.x + box.width); rows.set(box.y, row);
                    await link.focus();
                    assert(await link.evaluate(node => node === document.activeElement), 'Every navigation action must be keyboard focusable.');
                    assert.equal(await link.locator('.small').evaluate(node => getComputedStyle(node).textDecorationLine), 'underline');
                }
                for (const row of rows.values()) {
                    if (width < 768) { assert(Math.abs((row.left + row.right) / 2 - (column.x + column.width / 2)) < 2, 'Each wrapped object navigation row must remain centered on phones.'); }
                    else { assert(Math.abs(row.right - column.x - column.width + paddingRight) < 2, 'Object navigation must remain right-aligned on desktop.'); }
                }
                await links.first().focus();
                if (await links.count() > 1) {
                    await page.keyboard.press('Tab'); assert(await links.nth(1).evaluate(node => node === document.activeElement), 'Previous must follow Back in keyboard order.');
                    await page.keyboard.press('Tab'); assert(await links.nth(2).evaluate(node => node === document.activeElement), 'Next must follow Previous in keyboard order.');
                }
            }
            if (name === 'collection-staff') {
                const navigation = await page.locator('.tadl-record-nav').boundingBox();
                const editor = await page.locator('.tadl-providence-collection').boundingBox();
                assert(editor.y >= navigation.y + navigation.height, 'Collection editor shortcut must sit below navigation.');
                assert(Math.abs(editor.x + editor.width - navigation.x - navigation.width) < 2, 'Collection editor shortcut must align with the right edge of navigation.');
                // Object navigation also lives inside metadata, whose prose links are underlined.
                const actions = page.locator('.tadl-collection-header-actions');
                for (const insideMetadata of [false, true]) {
                    await actions.evaluate((node, enabled) => node.classList.toggle('tadl-detail-metadata', enabled), insideMetadata);
                    for (const direction of ['previous', 'next']) {
                        const link = page.getByRole('link', {name:`Go to ${direction} collection`, exact:true});
                        const icon = await link.locator('i').boundingBox();
                        const label = await link.locator('.small').boundingBox();
                        assert(direction === 'previous' ? icon.x + icon.width <= label.x : label.x + label.width <= icon.x, 'Detail arrows must point outward from their labels.');
                        for (const state of ['hover', 'focus']) {
                            await link[state]();
                            assert.deepEqual(await link.evaluate(node => [node, node.querySelector('i'), node.querySelector('.small')].map(element => getComputedStyle(element).textDecorationLine)), ['none', 'none', 'underline'], 'Only the label may underline, including inside object metadata.');
                        }
                    }
                }
                await actions.evaluate(node => node.classList.remove('tadl-detail-metadata'));
            }
            if (artifacts) { await page.screenshot({path:path.join(artifacts, `${name}-${width}.png`), fullPage:true}); }
            await page.evaluate(() => {
                const sizes = Array.from(document.querySelectorAll('#main *')).map(node => [node, parseFloat(getComputedStyle(node).fontSize)]);
                sizes.forEach(([node, size]) => { node.style.fontSize = (size * 2) + 'px'; });
            });
            await page.addStyleTag({content:'#main * {line-height:1.5;letter-spacing:.12em;word-spacing:.16em} #main p {margin-bottom:2em}'});
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${name}: overflow with enlarged/spaced text at ${width}px`);
            if (name === 'notifications') {
                const notices = page.locator('.notificationMessage');
                for (const notice of await notices.all()) {
                    // Text enlargement can reflow the page between separate browser calls.
                    const contained = await notice.evaluate(node => node.querySelector('button').getBoundingClientRect().bottom <= node.getBoundingClientRect().bottom);
                    assert(contained, 'Enlarged dismiss controls must remain inside the banner too.');
                }
                await notices.first().getByRole('button', {name:'Dismiss notification'}).focus();
                await page.keyboard.press('Enter');
                assert.equal(await notices.first().isVisible(), false, 'Keyboard activation must dismiss the selected notification.');
                assert(await notices.last().isVisible(), 'Dismissing one message must preserve other notifications.');
                await notices.last().getByRole('button').click();
                assert.equal(await notices.last().isVisible(), false, 'Pointer activation must still dismiss a notification.');
            }
            reports.push(`${name} ${width}px: axe, reflow, enlarged text and spacing passed`);
        }
    }
    // Long prose and linked metadata must put the collapse action last in both
    // visual and keyboard order, including fragments inserted after page load.
    for (const width of [1280,320]) {
        await page.setViewportSize({width,height:900});
        await page.goto(origin + '/long-fields');
        const fields = page.locator('.tadl-long-field');
        assert.equal(await fields.count(),2);
        for (const [index, field] of (await fields.all()).entries()) {
            const details = field.locator('details');
            const summary = details.locator('summary');
            const full = details.locator('.tadl-long-field-full');
            const collapse = details.locator('.tadl-long-field-collapse');
            const preview = field.locator('.tadl-long-field-preview');
            assert(await summary.isVisible() && await preview.isVisible() && await collapse.isHidden());
            await summary.focus(); await page.keyboard.press('Enter');
            await page.waitForFunction(() => document.activeElement.classList.contains('tadl-long-field-full'));
            assert(await summary.isHidden() && await preview.isHidden() && await collapse.isVisible(), 'Expanded metadata must expose only the ending collapse control.');
            assert.equal(await collapse.getAttribute('aria-expanded'),'true');
            const bodyBox = await full.boundingBox(), collapseBox = await collapse.boundingBox();
            assert(collapseBox.y >= bodyBox.y + bodyBox.height, 'Read less must follow the complete value in document flow.');
            // The prose fixture is taller than a screen; opening must not jump to
            // the ending button or strand focus on the now-hidden summary.
            if ((await full.innerText()).startsWith('Synthetic long description')) {
                assert(collapseBox.y > 900, 'Opening a long field must keep the reading position near its start.');
            }
            await page.evaluate(axeSource);
            const violations = await page.evaluate(async () => (await axe.run('#main', {runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa','wcag22aa']}})).violations.map(v => ({id:v.id,nodes:v.nodes.map(n => n.html)})));
            assert.deepEqual(violations,[], 'Expanded long fields must retain accessible control names and contrast.');
            await full.locator('a').last().focus(); await page.keyboard.press('Tab');
            assert(await collapse.evaluate(node => node === document.activeElement), 'Read less must follow the last content link in keyboard order.');
            if (artifacts) { await page.screenshot({path:path.join(artifacts,`read-less-${width}-${index}.png`)}); }
            await page.keyboard.press('Space');
            assert.equal(await details.evaluate(node => node.open),false);
            assert(await summary.isVisible() && await preview.isVisible() && await summary.evaluate(node => node === document.activeElement), 'Collapsing must restore the preview and focus to Read more.');
            assert(await collapse.isHidden());
            await summary.click();
            await collapse.waitFor({state:'visible'});
            await collapse.click();
            assert.equal(await details.evaluate(node => node.open),false, 'Pointer activation must collapse the same field.');
        }
        await fields.first().evaluate(node => document.querySelector('#main').append(node.cloneNode(true)));
        const inserted = fields.last();
        await inserted.locator('summary').click();
        await inserted.locator('.tadl-long-field-collapse').click();
        assert.equal(await inserted.locator('details').evaluate(node => node.open),false, 'Inserted gallery/metadata fragments must work without a separate initialization step.');
        reports.push(`Long metadata ${width}px: ending Read less, keyboard reading order, focus restoration, pointer controls, inserted fragments and expanded axe passed`);
    }
    // Exercise the shared return control with real scrolling and native history.
    for (const width of [1280,320]) {
        for (const motion of ['reduce','no-preference']) {
            await page.setViewportSize({width,height:900});
            await page.emulateMedia({reducedMotion:motion});
            const url = origin + '/back-to-top?synthetic=1&width=' + width + '&motion=' + motion + '#retained-context';
            await page.goto(url);
            const link = page.getByRole('link', {name:'Back to top',exact:true,includeHidden:true});
            const scroll = async top => page.evaluate(async top => {
                window.scrollTo({top,behavior:'instant'});
                await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            }, top);
            await scroll(0);
            assert(await link.isHidden(), 'Back to top must stay hidden and outside the Tab order at the page top.');
            await scroll(900);
            assert(await link.isHidden(), 'The first viewport must not show Back to top.');
            await scroll(920);
            assert(await link.isVisible(), 'Back to top must appear after the first viewport.');
            const box = await link.boundingBox();
            assert(box.width >= 44 && box.height >= 44 && Math.abs(width - box.x - box.width - 20) < 1 && Math.abs(900 - box.y - box.height - 20) < 1, 'The control needs a usable target at the viewport bottom right.');
            assert.equal(await link.locator('.tadl-back-to-top-label').isVisible(), width < 992, 'Small screens must show the text label beside the chevron.');
            await page.evaluate(axeSource);
            const result = await page.evaluate(() => axe.run(document, {runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa','wcag22aa']}}));
            assert.deepEqual(result.violations, [], 'The visible Back to top control must pass accessibility checks.');
            await page.setViewportSize({width,height:1000});
            await page.waitForFunction(() => document.querySelector('[data-tadl-back-to-top]').hidden);
            await page.setViewportSize({width,height:900});
            await page.waitForFunction(() => !document.querySelector('[data-tadl-back-to-top]').hidden);
            if (motion === 'reduce') {
                await page.goto(origin + '/contact');
                await page.goBack();
                await page.waitForFunction(() => scrollY > innerHeight && !document.querySelector('[data-tadl-back-to-top]').hidden);
            }
            await page.evaluate(() => {
                const scroll = window.scrollTo.bind(window);
                window.backToTopScrolls = [];
                window.scrollTo = (...args) => { if (typeof args[0] === 'object') window.backToTopScrolls.push(args[0]); scroll(...args); };
            });
            await link.focus();
            if (artifacts) { await page.screenshot({path:path.join(artifacts, `back-to-top-${width}-${motion}.png`)}); }
            await page.keyboard.press('Enter');
            await page.waitForFunction(() => scrollY === 0 && document.querySelector('[data-tadl-back-to-top]').hidden);
            assert.equal(await page.locator(':focus').getAttribute('id'), 'tadl-page-top', 'Activation must move keyboard focus to the header before hiding the trigger.');
            assert.equal(page.url(), url, 'Returning to the top must preserve the current URL and result context.');
            assert.deepEqual(await page.evaluate(() => window.backToTopScrolls), [{top:0,behavior:motion === 'reduce' ? 'auto' : 'smooth'}], 'Reduced-motion users must return without animated scrolling.');
            await scroll(920);
            await link.click();
            await page.waitForFunction(() => scrollY === 0 && document.querySelector('[data-tadl-back-to-top]').hidden);
            await scroll(100000);
            const clearFooter = await page.locator('.tadl-footer-bottom').evaluate(node => node.getBoundingClientRect().bottom <= document.querySelector('[data-tadl-back-to-top]').getBoundingClientRect().top + parseFloat(getComputedStyle(node).paddingBottom));
            assert(clearFooter, 'Footer content must have room above the floating control.');
            await page.evaluate(() => {
                const notice = document.createElement('div'); notice.id = 'cookieNotice'; document.body.append(notice);
            });
            assert(await link.isHidden(), 'The return control must not cover a cookie notice.');
            await page.locator('#cookieNotice').evaluate(node => node.remove());
            await page.emulateMedia({media:'print'});
            assert(await link.isHidden(), 'Printed pages must omit the floating control.');
            await page.emulateMedia({media:'screen'});
        }
    }
    await page.emulateMedia({reducedMotion:'reduce'});
    reports.push('Back to top: viewport threshold, resize/history restoration, bottom-right layout, keyboard/pointer activation, focus, motion preferences, URL preservation and print/cookie behavior passed.');
    // Stub the browser API so these tests cannot alter the user's system clipboard.
    const citationContext = await browser.newContext({reducedMotion:'reduce'});
    await citationContext.route('**/*', route => route.request().url().startsWith(origin) ? route.continue() : route.abort());
    await citationContext.addInitScript(() => {
        const parameters = new URL(location.href).searchParams;
        const mode = parameters.get('clipboard');
        if (parameters.has('citation_path')) {
            // Registered before application listeners so initialization sees the malformed input.
            document.addEventListener('DOMContentLoaded', () => document.querySelector('[data-tadl-citation]').setAttribute('data-citation-path', parameters.get('citation_path')));
        }
        window.citationWrites = [];
        Object.defineProperty(navigator, 'clipboard', {configurable:true, value:mode === 'unavailable' ? undefined : {
            writeText(text) {
                window.citationWrites.push(text);
                return mode === 'rejected' ? Promise.reject(new Error('Synthetic clipboard denial')) : Promise.resolve();
            }
        }});
    });
    const citationPage = await citationContext.newPage();
    citationPage.on('pageerror', error => errors.push(error.message));
    for (const width of [1280,320]) {
        await citationPage.setViewportSize({width,height:900});
        for (const [name, detailPath] of [['object-citation','/Detail/objects/42'], ['object-citation-subdirectory','/catalogue/index.php/Detail/objects/73']]) {
            const expected = 'Image courtesy of Traverse Area District Library Local History Collection, ' + origin + detailPath;
            for (const mode of ['success','rejected','unavailable']) {
                await citationPage.goto(origin + '/' + name + '?clipboard=' + mode + '&key=synthetic-result&s=18&per_page=72#metadata');
                const citation = citationPage.locator('[data-tadl-citation]');
                const copy = citation.locator('[data-tadl-copy-citation]');
                const text = citation.locator('[data-tadl-citation-text]');
                const status = citation.getByRole('status');
                assert(await citation.isVisible() && await text.isHidden(), 'Copy citation must appear after enhancement, with its manual fallback initially hidden.');
                assert.equal(await copy.getAttribute('title'), 'Copy citation for this object');
                assert.equal((await copy.innerText()).trim(), 'Copy citation');
                await copy.focus(); await citationPage.keyboard.press('Enter');
                await citationPage.waitForFunction(() => document.querySelector('[data-tadl-citation-status]').textContent.length > 0);
                assert.equal(await text.inputValue(), expected, 'Citation must contain only the FAQ credit and absolute object URL, excluding current query, paging and fragment state.');
                assert.deepEqual(await citationPage.evaluate(() => window.citationWrites), mode === 'unavailable' ? [] : [expected], 'Clipboard must receive exactly one plain text citation when available.');
                if (mode === 'success') {
                    assert.equal((await copy.innerText()).trim(), 'Copied!', 'Success must change the button label instead of displaying a neighboring message.');
                    assert.equal(await status.textContent(), 'Citation copied.');
                    assert(await status.evaluate(node => node.classList.contains('sr-only') && getComputedStyle(node).clip !== 'auto'), 'Success must remain available to screen readers without a visible adjacent message.');
                    assert(await text.isHidden() && await copy.evaluate(node => node === document.activeElement), 'Successful copying must retain keyboard focus and keep the fallback hidden.');
                    await citationPage.keyboard.press('Enter');
                    await citationPage.waitForFunction(() => window.citationWrites.length === 2 && document.querySelector('[data-tadl-citation-label]').textContent === 'Copied!');
                    assert.deepEqual(await citationPage.evaluate(() => window.citationWrites), [expected,expected], 'The success button must remain usable for copying again.');
                } else {
                    assert.equal((await copy.innerText()).trim(), 'Copy citation', 'Clipboard failure must not claim the citation was copied.');
                    assert.equal(await status.textContent(), 'Copy the selected citation.');
                    assert(await status.evaluate(node => !node.classList.contains('sr-only')), 'Manual-copy instructions must remain visible when clipboard access fails.');
                    assert(await text.isVisible(), 'Unavailable or rejected clipboard access must reveal the manual-copy text.');
                    assert.deepEqual(await text.evaluate(node => ({readonly:node.readOnly, focused:node === document.activeElement, selection:node.value.slice(node.selectionStart,node.selectionEnd)})),
                        {readonly:true,focused:true,selection:expected}, 'Fallback must focus and select the complete readonly citation for keyboard copying.');
                }
                assert(await citationPage.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${mode}: citation must not overflow at ${width}px.`);
                if (artifacts && mode !== 'rejected') { await citationPage.screenshot({path:path.join(artifacts, `${name}-${mode}-${width}.png`),fullPage:true}); }
            }
        }
    }
    // A path supplied by compromised markup must never become a copied external link.
    for (const unsafePath of ['https://untrusted.example.invalid/Detail/objects/42', '//untrusted.example.invalid/Detail/objects/42', 'javascript:alert(1)']) {
        await citationPage.goto(origin + '/object-citation?citation_path=' + encodeURIComponent(unsafePath));
        assert(await citationPage.locator('[data-tadl-citation]').isHidden(), 'Invalid or cross-origin citation paths must leave the action hidden.');
        assert.deepEqual(await citationPage.evaluate(() => window.citationWrites), [], 'Invalid or cross-origin citation paths must not reach the clipboard.');
    }
    await citationContext.close();
    reports.push('Copy citation: exact clean object URLs, subdirectory/script paths, keyboard feedback, rejected/unavailable clipboard fallback and phone reflow passed');
    for (const width of [320,1280]) {
        await page.setViewportSize({width,height:1000});
        const mediaSizes = [];
        for (const route of ['/featured','/filters/browse']) {
            await page.goto(origin + route);
            if (width === 320) { await page.locator('.navbar-toggle').click(); }
            const selector = page.locator('.tadl-media-preference-menu:visible');
            await page.evaluate(async () => { await document.fonts.ready; await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); });
            mediaSizes.push(await selector.locator('summary').evaluate(node => {
                const bounds = node.getBoundingClientRect();
                return {font:getComputedStyle(node).fontSize,width:bounds.width,height:bounds.height};
            }));
        }
        assert.deepEqual(mediaSizes[0], mediaSizes[1], 'Front and object Browse must show the same media selector size.');
        assert.equal(mediaSizes[0].font,'18px', 'Every page must use the larger media selector size.');
        await page.goto(origin + '/contact');
        if (width === 320) { await page.locator('.navbar-toggle').click(); }
        const mediaMenu = page.locator('.tadl-media-preference-menu:visible');
        const mediaToggle = mediaMenu.locator('summary');
        await page.evaluate(async () => { await document.fonts.ready; await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); });
        assert.equal(await mediaMenu.count(), 1, 'Only the visible header must expose its media selector.');
        await mediaToggle.focus(); await page.keyboard.press('Enter'); await page.keyboard.press('Tab');
        assert.equal(await page.locator(':focus').getAttribute('value'), 'only', 'Media options must use native button Tab order.');
        const bounds = await mediaMenu.locator('.tadl-media-preference-choices').boundingBox();
        assert(bounds.x >= 0 && bounds.x + bounds.width <= width + 1, 'Media choices must fit the phone and desktop viewport.');
        await page.addScriptTag({content:axeSource});
        const mediaAxe = await page.evaluate(() => axe.run(document.querySelector('.tadl-media-preference-menu'), {runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa','wcag22aa']}}));
        assert.deepEqual(mediaAxe.violations, [], 'Open media selector must pass axe.');
        if (artifacts) { await page.screenshot({path:path.join(artifacts, `media-selector-${width}.png`)}); }
        await page.keyboard.press('Escape');
        assert.equal(await mediaMenu.evaluate(node => node.open), false, 'Escape must close the media selector.');
        assert(await mediaToggle.evaluate(node => node === document.activeElement), 'Escape must restore the selector toggle.');
        await mediaToggle.click(); await page.locator('#main h1').first().click();
        assert.equal(await mediaMenu.evaluate(node => node.open), false, 'An outside click must dismiss the media selector.');
    }
    {
        const plainContext = await browser.newContext({javaScriptEnabled:false});
        await plainContext.route('**/*', route => route.request().url().startsWith(origin) ? route.continue() : route.abort());
        await plainContext.route('**/MediaPreference/Set', route => route.fulfill({contentType:'text/html',body:'Synthetic preference saved'}));
        const plainPage = await plainContext.newPage();
        await plainPage.goto(origin + '/object-citation');
        assert(await plainPage.locator('[data-tadl-citation]').isHidden(), 'Without JavaScript the clipboard action must stay hidden rather than exposing a broken button.');
        assert(await plainPage.locator('[data-tadl-back-to-top]').isHidden(), 'Without JavaScript the scroll-dependent control must stay hidden.');
        await plainPage.goto(origin + '/finding-aid-text');
        assert(await plainPage.getByRole('link', {name:'Return to collection',exact:true}).isVisible(), 'Text finding aids must provide a usable return link without JavaScript.');
        await plainPage.goto(origin + '/long-fields');
        const plainField = plainPage.locator('.tadl-long-field').first();
        await plainField.locator('summary').click();
        assert(await plainField.locator('.tadl-long-field-full').isVisible() && await plainField.locator('summary').isVisible(), 'Without JavaScript native disclosure must still reveal all content.');
        assert(await plainField.locator('.tadl-long-field-collapse').isHidden(), 'The enhanced ending button must stay hidden without JavaScript.');
        await plainField.locator('summary').click();
        assert.equal(await plainField.locator('details').evaluate(node => node.open),false, 'Native summary must also collapse without JavaScript.');
        await plainPage.goto(origin + '/contact');
        const plainMenu = plainPage.locator('.tadl-media-preference-menu:visible');
        await plainMenu.locator('summary').click();
        const [submission] = await Promise.all([
            plainPage.waitForRequest(request => request.method() === 'POST' && request.url().endsWith('/MediaPreference/Set')),
            plainMenu.locator('button[value="only"]').click()
        ]);
        const fields = new URLSearchParams(submission.postData());
        assert.equal(fields.get('tadlMediaPreference'),'only', 'Media selector must submit the chosen mode without JavaScript.');
        assert(fields.get('csrfToken') && fields.get('returnTo'), 'Media selector must preserve CSRF protection and its return route.');
        await plainContext.close();
    }
    for (const width of [320,390,768,1024,1600]) {
        await page.setViewportSize({width,height:1000});
        await page.goto(origin + '/sort-results');
        const disclosure = page.locator('.tadl-results-sort'), toggle = disclosure.locator('summary');
        assert.equal(await toggle.locator('span').first().textContent(),'Sort by Title');
        assert.deepEqual(await toggle.evaluate(node=>{
            const neighbor=document.querySelector('.tadl-result-view-toggle:not(.active)');
            return [getComputedStyle(node).backgroundColor,getComputedStyle(neighbor).backgroundColor];
        }),['rgb(255, 255, 255)','rgb(255, 255, 255)'], 'Sort by must use the same white button surface as the neighboring view controls.');
        await toggle.focus(); await page.keyboard.press('Enter');
        assert(await disclosure.evaluate(node=>node.open), 'Sort by must open from the keyboard.');
        await page.keyboard.press('Tab');
        assert.equal(await page.locator(':focus').getAttribute('aria-label'), 'Sort by Identifier, Ascending', 'Direction choices must use normal Tab order.');
        const menu = await disclosure.locator('ul').boundingBox();
        assert(menu.x >= 0 && menu.x + menu.width <= width + 1, `${width}px: the open sort dropdown must fit the viewport.`);
        const rows=await disclosure.locator('.tadl-sort-choice').evaluateAll(nodes=>nodes.map(node=>{
            const box=node.getBoundingClientRect();return {y:box.y,height:box.height};
        }));
        assert(rows.every(row=>Math.abs(row.height-Math.round(row.height))<.01
            && Math.abs(row.y-rows[0].y-Math.round(row.y-rows[0].y))<.01),
            'Sort rows must share a pixel alignment so Title and Relevance do not paint the same arrow at different vertical offsets.');
        assert.equal(await disclosure.locator('[aria-current=true]').getAttribute('aria-label'), 'Sort by Title. Currently Descending; activate for Ascending.');
        assert.equal(await disclosure.locator('a').count(),5,'Each sort field must have one full-row link.');
        assert.equal(await disclosure.locator('.tadl-sort-direction .fa-arrow-down').count(),1);
        const arrowBox=await disclosure.locator('.tadl-sort-direction .fa').boundingBox();
        const badgeBox=await disclosure.locator('.tadl-sort-direction').boundingBox();
        assert(Math.abs(arrowBox.y+arrowBox.height/2-badgeBox.y-badgeBox.height/2)<=1,
            'The solid arrow must be centered within its badge.');
        const indicator = await disclosure.locator('.tadl-sort-direction').evaluate(node=>{
            const box=node.getBoundingClientRect(), row=node.parentElement.getBoundingClientRect();
            const labelNode=node.previousElementSibling, label=labelNode.getBoundingClientRect(), style=getComputedStyle(node);
            return {right:row.right-box.right, center:Math.abs(box.y+box.height/2-label.y-label.height/2),
                gap:box.x-label.right, width:box.width, height:box.height, border:style.borderTopWidth,
                font:parseFloat(style.fontSize), labelFont:parseFloat(getComputedStyle(labelNode).fontSize),
                background:style.backgroundColor, color:style.color};
        });
        assert(Math.abs(indicator.right-12)<=1 && indicator.center<=1 && indicator.gap>=7,
            `${width}px: the direction tag must sit at the row's right edge, vertically centered with the label.`);
        assert(Math.abs(indicator.width-indicator.height)<=1 && indicator.border==='0px' && indicator.font<indicator.labelFont,
            'The direction badge must be smaller than the label, with no outline.');
        assert.equal(indicator.background,'rgba(255, 255, 255, 0.88)');
        assert.equal(indicator.color,'rgb(11, 47, 87)', 'The inverse badge must use a navy arrow on a pale background.');
        for (const link of await disclosure.locator('a').all()) {
            const box = await link.boundingBox();
            assert(box.width >= menu.width - 12 && box.height >= 24, 'The whole sort row must be a usable touch target.');
        }
        await page.locator('#main').evaluate(node=>node.classList.add('detail'));
        const currentChoice=disclosure.locator('[aria-current=true]');
        for (const state of ['hover','focus']) {
            await currentChoice[state]();
            assert.deepEqual(await currentChoice.evaluate(node=>[node,node.querySelector('.tadl-sort-direction'),node.querySelector('.fa')]
                .map(element=>getComputedStyle(element).textDecorationLine)),['none','none','none'],
                'Detail-page link rules must not underline the direction badge on hover or focus.');
            assert.equal(await currentChoice.evaluate(node=>getComputedStyle(node).color),'rgb(255, 255, 255)',
                'The selected sort row must keep its contrasting label when hovered or focused in a detail page.');
        }
        await page.locator('#main').evaluate(node=>node.classList.remove('detail'));
        await page.evaluate(axeSource);
        // The floating list temporarily covers page controls; the closed-page
        // scan above checks those targets, and this scan checks the open list.
        const violations = await page.evaluate(async ()=>(await axe.run('.tadl-results-sort',{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa','wcag22aa']}})).violations.map(item=>({id:item.id,nodes:item.nodes.map(node=>node.html)})));
        assert.deepEqual(violations,[], `${width}px: the open Sort by control must retain accessible names, contrast and target sizes.`);
        if (artifacts) {
            await page.screenshot({path:path.join(artifacts,`sort-open-${width}.png`)});
            const heading = await toggle.boundingBox();
            await page.screenshot({path:path.join(artifacts,`sort-control-${width}.png`),clip:{x:Math.max(0,menu.x-8),y:heading.y-8,width:menu.width+16,height:menu.y+menu.height-heading.y+16}});
        }
        await disclosure.locator('a').first().focus();
        await page.keyboard.press('Escape');
        assert.equal(await disclosure.evaluate(node=>node.open),false);
        assert(await toggle.evaluate(node=>node===document.activeElement), 'Escape must restore focus to Sort by.');
        assert.equal(await page.evaluate(()=>caUI.panelCount),0, 'Closing Sort by must not invoke the native media-panel close lifecycle.');
        await toggle.click(); await page.locator('h1').click();
        assert.equal(await disclosure.evaluate(node=>node.open),false, 'An outside click must dismiss the sort dropdown.');
        await toggle.focus(); await page.keyboard.press('Enter');
        await disclosure.locator('a').last().focus(); await page.keyboard.press('Tab');
        assert.equal(await disclosure.evaluate(node=>node.open),false, 'Tabbing to a neighboring control must dismiss the overlay so that control stays visible.');
    }
    const filterContext=await browser.newContext();
    await filterContext.route('**/*',route=>route.request().url().startsWith(origin)?route.continue():route.abort());
    const filterPage=await filterContext.newPage();
    const filterRequests=[];
    filterPage.on('request',request=>{if(request.url().startsWith(origin) && ['xhr','fetch'].includes(request.resourceType()))filterRequests.push(request.url());});
    filterPage.on('pageerror',error=>errors.push(error.message));
    for (const width of [320,390,768,1024,1600]) {
        await filterContext.clearCookies();
        await filterPage.setViewportSize({width,height:1000});
        await filterPage.goto(origin+'/filters/search');
        const layout=filterPage.locator('[data-tadl-filter-layout]');
        const sidebar=filterPage.locator('.tadl-filter-sidebar');
        const content=filterPage.locator('.tadl-results-content');
        const show=filterPage.getByRole('button',{name:'Show filters',exact:true});
        const hide=filterPage.getByRole('button',{name:'Hide filters',exact:true});
        if (width<768) {
            assert(await sidebar.isHidden(),'Phone filters must start collapsed.');
            await show.click();
        }
        assert(await sidebar.isVisible(),'Desktop filters must start open; phone filters must reopen.');
        assert.equal(await hide.getAttribute('aria-expanded'),'true');
        const expanded=await content.boundingBox();
        const sidebarBox=await sidebar.boundingBox();
        assert.equal(await filterPage.locator('#bRefineButton svg[fill="none"]').count(),1,'Filters must use an outlined funnel icon.');
        const more=filterPage.getByRole('button',{name:'People and organizations: View 5 more',exact:true});
        const extra=filterPage.locator('#entity_facet_more');
        assert(await extra.isHidden(),'Additional facet values must start collapsed.');
        await more.click();
        const less=filterPage.getByRole('button',{name:'People and organizations: View less',exact:true});
        assert.equal(await less.getAttribute('aria-expanded'),'true');
        assert(await extra.isVisible() && await extra.locator('a').count()===5,'View more must reveal the remaining values locally.');
        await less.click();
        assert(await extra.isHidden() && await more.getAttribute('aria-expanded')==='false','View less must restore the short list.');
        const tilesBox=await filterPage.locator('.tadl-result-view-toggle.active').boundingBox();
        assert(tilesBox.height<48,'At the normal text size each view icon and label must stay on one line.');
        if (width>=768) {
            assert(sidebarBox.x>=expanded.x+expanded.width-1,'Expanded filters must sit to the right of results.');
        } else {
            assert(sidebarBox.y+sidebarBox.height<=expanded.y+1,'Phone filters must open above the results, without a modal overlay.');
        }
        await filterPage.evaluate(axeSource);
        const expandedIssues=await filterPage.evaluate(async()=>(await axe.run('#main',{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa','wcag22aa']}})).violations.map(item=>({id:item.id,nodes:item.nodes.map(node=>node.html)})));
        assert.deepEqual(expandedIssues,[],`${width}px: expanded filters must pass accessibility checks.`);
        if (artifacts) await filterPage.screenshot({path:path.join(artifacts,`filters-expanded-${width}.png`)});
        if (width===1600) {
            await filterPage.evaluate(()=>scrollTo(0,600));
            assert(Math.abs((await filterPage.locator('#bRefine').boundingBox()).y-16)<2,'The expanded sidebar must remain reachable while results scroll.');
            await filterPage.evaluate(()=>scrollTo(0,0));
        }
        await hide.click();
        assert(await sidebar.isHidden(),'Collapsing filters must hide the entire sidebar column.');
        assert(await show.evaluate(node=>node!==document.activeElement),'Pointer collapse must not move focus to the different Show filters button.');
        assert.equal(await show.getAttribute('aria-expanded'),'false');
        const collapsed=await content.boundingBox(), whole=await layout.boundingBox();
        assert(Math.abs(collapsed.width-whole.width)<2,'Collapsed results must reclaim the complete layout width.');
        if (width>=768) assert(collapsed.width>expanded.width,'Desktop cards must gain the sidebar space.');
        assert(await filterPage.locator('.tadl-filter-chip').isVisible(),'Selected filters must remain visible when the sidebar is closed.');
        assert(await filterPage.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`${width}px: collapsed controls must reflow.`);
        const closedIssues=await filterPage.evaluate(async()=>(await axe.run('#main',{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa','wcag22aa']}})).violations.map(item=>({id:item.id,nodes:item.nodes.map(node=>node.html)})));
        assert.deepEqual(closedIssues,[],`${width}px: collapsed filters must pass accessibility checks.`);
        if (artifacts) await filterPage.screenshot({path:path.join(artifacts,`filters-collapsed-${width}.png`)});
        await show.click();
        assert(await hide.evaluate(node=>node!==document.activeElement),'Pointer expansion must not move focus to the different Hide filters button.');
        await hide.click();
        await show.focus(); await filterPage.keyboard.press('Enter');
        assert(await hide.evaluate(node=>node===document.activeElement),'Opening must move focus to the sidebar control.');
        await filterPage.keyboard.press('Escape');
        assert(await sidebar.isHidden(),'Escape in filters must collapse the panel.');
        assert(await show.evaluate(node=>node===document.activeElement),'Keyboard Escape must restore focus to Show filters.');
        assert.equal(await filterPage.evaluate(()=>caUI.panelCount),0,'Filter Escape must not invoke native media-panel closing.');
        await filterPage.goto(origin+'/filters/browse');
        assert(await sidebar.isHidden(),'The sidebar preference must survive Search-to-Browse navigation.');
        assert((await filterPage.content()).includes('data-tadl-filter-state="closed"'),'Initial server HTML must retain the saved preference.');
        await filterPage.getByRole('link',{name:'List',exact:true}).click();
        await filterPage.waitForURL('**/synthetic/objects?**');
        assert(await sidebar.isHidden(),'Changing view must preserve filter visibility.');
        const preferences=JSON.parse(decodeURIComponent((await filterContext.cookies()).find(cookie=>cookie.name==='tadlDisplayPreferences').value));
        assert.equal(preferences.filters,'closed'); assert.equal(preferences.view,'list');
        if (width===320) {
            await filterPage.evaluate(()=>{
                const sizes=Array.from(document.querySelectorAll('#main *')).map(node=>[node,parseFloat(getComputedStyle(node).fontSize)]);
                sizes.forEach(([node,size])=>node.style.fontSize=(size*2)+'px');
            });
            assert(await filterPage.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Collapsed filters and view controls must reflow with enlarged text.');
        }
    }
    await filterContext.clearCookies();
    await filterPage.setViewportSize({width:1600,height:1000});
    await filterPage.goto(origin+'/filters/search');
    await filterPage.setViewportSize({width:390,height:1000});
    await filterPage.waitForFunction(()=>document.querySelector('.tadl-filter-sidebar').hidden);
    await filterPage.setViewportSize({width:1600,height:1000});
    await filterPage.waitForFunction(()=>!document.querySelector('.tadl-filter-sidebar').hidden);
    assert.equal((await filterContext.cookies()).length,0,'Responsive defaults must not become an explicit preference.');
    assert.deepEqual(filterRequests,[],'Expanding or collapsing filters must not fetch results or facets again.');
    await filterPage.getByRole('link',{name:'Object type: View all 70',exact:true}).click();
    await filterPage.getByRole('button',{name:'Close filter choices',exact:true}).waitFor();
    await filterPage.evaluate(()=>jQuery('#bMorePanel').data('tadlOpener',null));
    await filterPage.getByRole('button',{name:'Close filter choices',exact:true}).click();
    assert(await filterPage.getByRole('button',{name:'Hide filters',exact:true}).evaluate(node=>node===document.activeElement),
        'A closed More choices panel without its original opener must focus the visible sidebar control.');
    await filterContext.close();
    // Exercise the native alphabetical chooser at realistic scale, not a one-item stub.
    const facetContext=await browser.newContext({reducedMotion:'reduce'});
    await facetContext.route('**/*',route=>route.request().url().startsWith(origin)?route.continue():route.abort());
    const facetPage=await facetContext.newPage();
    facetPage.on('pageerror',error=>errors.push(error.message));
    for (const viewport of [{width:1600,height:1000},{width:768,height:1000},{width:390,height:844},{width:320,height:568},{width:844,height:390}]) {
        await facetContext.clearCookies();
        await facetPage.setViewportSize(viewport);
        await facetPage.goto(origin+'/filters/search?large=1');
        if (await facetPage.locator('#bRefineButton').isVisible()) { await facetPage.locator('#bRefineButton').click(); }
        const opener=facetPage.getByRole('link',{name:'People and organizations: View all 4015',exact:true});
        await opener.focus(); await facetPage.keyboard.press('Enter');
        const dialog=facetPage.getByRole('dialog'), close=dialog.getByRole('button',{name:'Close filter choices',exact:true});
        await close.waitFor();
        await facetPage.evaluate(async()=>{await document.fonts.ready;await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));});
        assert.equal(await dialog.getAttribute('aria-labelledby'),'bScrollListLabel');
        assert.equal(await dialog.getAttribute('aria-modal'),'true');
        assert(await close.evaluate(node=>node===document.activeElement),'Opening full filter choices must focus its close button.');
        assert.equal(await dialog.locator('#bScrollList a').count(),4015,'Large chooser must retain every filter value.');
        const dimensions=await dialog.evaluate(panel=>{
            const index=panel.querySelector('#bLetterBar'),list=panel.querySelector('#bScrollList');
            const rect=node=>node.getBoundingClientRect().toJSON();
            return {panel:rect(panel),index:rect(index),list:rect(list),panelScroll:panel.scrollHeight-panel.clientHeight,
                listScroll:list.scrollHeight-list.clientHeight,indexScroll:index.scrollHeight-index.clientHeight};
        });
        assert(dimensions.panel.x>=0 && dimensions.panel.right<=viewport.width+1 && dimensions.panel.bottom<=viewport.height+1,'Filter drawer must fit the viewport.');
        assert(dimensions.list.y>=dimensions.index.bottom-1 && dimensions.list.height>=120,'Choices must start below the alphabet and have usable space, including in landscape.');
        assert(dimensions.panelScroll<=1 && dimensions.indexScroll<=1 && dimensions.listScroll>1000,'Only the choice list may scroll vertically.');
        assert.equal(await facetPage.evaluate(()=>getComputedStyle(document.documentElement).overflowY),'hidden','The open chooser must lock background scrolling.');
        assert(await facetPage.locator('header.tadl-header').evaluate(node=>node.hasAttribute('inert')),'Background controls must be inert while choosing a filter.');
        const pageScroll=await facetPage.evaluate(()=>scrollY);
        const scrollTop=()=>dialog.locator('#bScrollList').evaluate(node=>node.scrollTop);
        for (const letter of ['Z','É','<','2','(']) {
            const jump=dialog.locator('#bLetterBar').getByRole('link',{name:letter,exact:true});
            const target=await jump.getAttribute('href');
            await jump.click();
            assert.equal(await facetPage.locator(':focus').getAttribute('id'),target.slice(1),'Letter jumps must focus their matching heading, including punctuation and Unicode.');
            const heading=await dialog.locator(target).boundingBox();
            const list=await dialog.locator('#bScrollList').boundingBox();
            assert(heading.y>=list.y && heading.y<list.y+40,'Letter navigation must scroll the matching group to the top of the choices.');
            assert.equal(await facetPage.evaluate(()=>scrollY),pageScroll,'Letter jumps must not scroll the document.');
        }
        assert.equal(await scrollTop(),0,'The first letter must return to the beginning of the choices.');
        // Tab wraps without moving the drawer/header off screen, even at the final value.
        await close.focus(); await facetPage.keyboard.press('Shift+Tab');
        assert(await dialog.locator('#bScrollList a').last().evaluate(node=>node===document.activeElement),'Shift+Tab must reach the final choice instead of background controls.');
        await facetPage.keyboard.press('Tab');
        assert(await close.evaluate(node=>node===document.activeElement),'Tab after the final choice must wrap to Close.');
        assert((await close.boundingBox()).y>=0,'Close must remain visible after scrolling to the end.');
        if (viewport.width===1600 || viewport.width===320) {
            await facetPage.evaluate(axeSource);
            const failures=await facetPage.evaluate(async()=> (await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21a','wcag21aa','wcag22aa','best-practice']}})).violations.map(item=>({id:item.id,nodes:item.nodes.map(node=>node.html)})));
            assert.deepEqual(failures,[],`Large filter chooser axe failures at ${viewport.width}px`);
        }
        if (artifacts) { await facetPage.screenshot({path:path.join(artifacts,`facet-browser-${viewport.width}x${viewport.height}.png`)}); }
        await facetPage.keyboard.press('Escape');
        assert(await dialog.isHidden(),'Escape must close the large chooser.');
        assert(await opener.evaluate(node=>node===document.activeElement),'Closing must return focus to the exact View all launcher.');
        assert(await facetPage.locator('#bRefine').isVisible(),'Closing the chooser must not collapse the underlying filters.');
        assert.equal(await facetPage.evaluate(()=>caUI.panelCount),0,'Chooser Escape must not invoke a native viewer close.');
        assert.equal(await facetPage.locator('header.tadl-header').getAttribute('inert'),null,'Closing must restore background controls.');
        assert.notEqual(await facetPage.evaluate(()=>getComputedStyle(document.documentElement).overflowY),'hidden','Closing must release background scrolling.');
        await opener.click(); await close.waitFor();
        assert.equal(await scrollTop(),0,'Reopening choices must reset the inner scroll.');
        await close.click();
        reports.push(`4015-choice filter browser ${viewport.width}x${viewport.height}: bounded layout, scrolling, letter jumps, keyboard and focus restoration passed`);
    }
    await facetPage.setViewportSize({width:320,height:1000});
    await facetPage.goto(origin+'/filters/search?large=1');
    if (await facetPage.locator('#bRefineButton').isVisible()) { await facetPage.locator('#bRefineButton').click(); }
    await facetPage.getByRole('link',{name:'People and organizations: View all 4015',exact:true}).click();
    await facetPage.getByRole('dialog').waitFor();
    await facetPage.evaluate(()=>{
        const nodes=Array.from(document.querySelectorAll('#bMorePanel *')).map(node=>[node,parseFloat(getComputedStyle(node).fontSize)]);
        nodes.forEach(([node,size])=>node.style.fontSize=(size*2)+'px');
    });
    await facetPage.addStyleTag({content:'#bMorePanel * {line-height:1.5;letter-spacing:.12em;word-spacing:.16em}'});
    const reflow=await facetPage.locator('#bMorePanel').evaluate(panel=>{
        const list=panel.querySelector('#bScrollList');
        return {page:document.documentElement.scrollWidth<=innerWidth+1,panel:panel.scrollWidth<=panel.clientWidth+1,
            list:list.scrollWidth<=list.clientWidth+1,height:list.clientHeight,close:panel.querySelector('#bMorePanelClose').getBoundingClientRect().right};
    });
    assert(reflow.page && reflow.panel && reflow.list && reflow.height>=120 && reflow.close<=320,'The large chooser must retain readable choices and Close with enlarged/spaced text.');
    if (artifacts) { await facetPage.screenshot({path:path.join(artifacts,'facet-browser-enlarged.png')}); }
    // Selecting a value still uses the native GET route and preserves the browse key/view.
    const finalChoice=facetPage.locator('#bScrollList a').last();
    const chosen=new URL(await finalChoice.getAttribute('href'),origin);
    assert.equal(chosen.searchParams.get('id'),'4015'); assert.equal(chosen.searchParams.get('facet'),'entity_facet');
    assert.equal(chosen.searchParams.get('key'),'synthetic-key'); assert.equal(chosen.searchParams.get('view'),'images');
    await finalChoice.click(); await facetPage.waitForURL(chosen.href);
    await facetContext.close();
    for (const width of [320,1600]) {
        const noScript=await browser.newContext({javaScriptEnabled:false,viewport:{width,height:1000}});
        await noScript.route('**/*',route=>route.request().url().startsWith(origin)?route.continue():route.abort());
        const fallback=await noScript.newPage();
        await fallback.goto(origin+'/filters/search');
        assert(await fallback.locator('#bRefine').isVisible(),'Filters must remain usable without JavaScript, including on phones.');
        assert(await fallback.locator('#bRefineButton').isHidden() && await fallback.locator('#bRefineClose').isHidden(),'No-script pages must not expose nonfunctional disclosure controls.');
        assert(await fallback.locator('#bRefine a').count()>0);
        assert(await fallback.locator('#entity_facet_more').isVisible(),'Without JavaScript, all short-list facet values must remain available.');
        assert(await fallback.locator('[data-tadl-facet-toggle]').isHidden(),'No-script pages must hide the inactive View more button.');
        assert((await fallback.locator('[data-tadl-facet-choices]').getAttribute('href')).includes('getFacet=1'),'Long-list choices need a native GET fallback.');
        assert(await fallback.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
        await noScript.close();
    }
    reports.push('Filter sidebar passed: full-width collapse, Search/Browse persistence, responsive defaults, keyboard focus/Escape, no-script access and no result requests.');
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
    // Actual object bundle: different photo proportions must not move the arrow
    // or the page's scroll anchor, even when navigation starts on the short photo.
    for (const width of [1280, 768, 390, 320]) {
        await page.setViewportSize({width,height:900});
        await page.goto(origin + '/object-photos');
        await page.evaluate(async () => {
            await document.fonts.ready;
            await Promise.all(Array.from(document.images, image => image.decode().catch(() => {})));
            window.scrollTo(0, 100);
        });
        const next = page.locator('#detailRepNavNext'), previous = page.locator('#detailRepNavPrev');
        await page.locator('#main').focus(); await page.keyboard.press('ArrowLeft');
        assert.equal(await page.locator('#detailRepCounter').innerText(), 'Media 1 of 3');
        const arrow = await next.boundingBox();
        const stage = await page.locator('.tadl-representation-stage').boundingBox();
        assert(await page.locator('#repViewerItemDisplay').evaluate(frame => {
            const stage = frame.parentElement, styles = getComputedStyle(stage);
            return Math.abs(frame.getBoundingClientRect().width - (stage.clientWidth - parseFloat(styles.paddingLeft) - parseFloat(styles.paddingRight))) < 1;
        }), 'The reserved frame must keep the full available photo width even when its height is capped.');
        const scroll = await page.evaluate(() => window.scrollY);
        assert(await previous.isDisabled());
        assert.equal(await next.getAttribute('aria-keyshortcuts'), 'ArrowRight');
        for (const position of [2, 3]) {
            await next.click();
            await page.locator('#repViewerItemDisplay img').evaluate(image => image.decode());
            assert.equal(await page.locator('#detailRepCounter').innerText(), `Media ${position} of 3`);
            const current = await next.boundingBox();
            assert(Math.abs(current.y - arrow.y) < 1, `Photo ${position}: arrow jumped at ${width}px.`);
            assert.equal(await page.evaluate(() => window.scrollY), scroll, 'Photo changes must not move the page scroll anchor.');
            const image = await page.locator('#repViewerItemDisplay img').boundingBox();
            assert(image.height <= stage.height + 1 && image.y >= stage.y - 1 && image.y + image.height <= stage.y + stage.height + 1, 'Full photo must fit inside the reserved frame.');
        }
        assert(await next.isDisabled());
        await page.locator('#main').focus(); await page.keyboard.press('ArrowRight');
        assert.equal(await page.locator('#detailRepCounter').innerText(), 'Media 3 of 3');
        await previous.focus(); await page.keyboard.press('ArrowLeft');
        assert.equal(await page.locator('#detailRepCounter').innerText(), 'Media 2 of 3');
        await page.keyboard.press('ArrowRight');
        assert.equal(await page.locator('#detailRepCounter').innerText(), 'Media 3 of 3');
        await page.locator('#repThumb_0').click();
        assert.equal(await page.locator('#repThumb_0').getAttribute('aria-current'), 'true');
        await page.locator('#main').focus(); await page.keyboard.press('ArrowRight');
        assert.equal(await page.locator('#detailRepCounter').innerText(), 'Media 2 of 3');
        if (artifacts) { await page.screenshot({path:path.join(artifacts, `object-photos-${width}.png`),fullPage:true}); }
    }
    // Keyboard arrows remain available to text entry, native players, sliders,
    // menus and modal viewers. Modifier shortcuts and key repeat do not page.
    await page.evaluate(() => {
        const probes = document.createElement('div'); probes.id = 'keyboard-probes';
        probes.innerHTML = '<input aria-label="Synthetic input" value="Text"><textarea aria-label="Synthetic text">Text</textarea><select aria-label="Synthetic select"><option>One</option><option>Two</option></select><div contenteditable="true" tabindex="0">Edit</div><input type="range" aria-label="Synthetic range"><button>Other button</button><details><summary>Other disclosure</summary>Text</details><video controls tabindex="0"></video><canvas tabindex="0"></canvas><div role="slider" tabindex="0" aria-label="Synthetic slider" aria-valuenow="1"></div>';
        document.querySelector('#main').append(probes);
    });
    for (const probe of await page.locator('#keyboard-probes > *').all()) {
        const control = await probe.evaluate(node => node.tagName);
        await (control === 'DETAILS' ? probe.locator('summary') : probe).focus();
        await page.keyboard.press('ArrowRight');
        assert.equal(await page.locator('#detailRepCounter').innerText(), 'Media 2 of 3', `${control} must keep its own arrow keys.`);
    }
    await page.locator('#main').focus();
    for (const key of ['Alt+ArrowRight', 'Shift+ArrowRight', 'Control+ArrowRight', 'Meta+ArrowRight']) {
        // Alt+Right is browser history on some platforms: cancel that native
        // default after the production handler, while still exercising its guard.
        await page.evaluate(() => document.addEventListener('keydown', function stopHistory(event) {
            if (event.key !== 'ArrowRight') { return; }
            event.preventDefault(); document.removeEventListener('keydown', stopHistory);
        }));
        await page.keyboard.press(key);
        assert.equal(await page.locator('#detailRepCounter').innerText(), 'Media 2 of 3');
    }
    await page.evaluate(() => {
        document.dispatchEvent(new KeyboardEvent('keydown', {key:'ArrowRight',repeat:true,bubbles:true}));
        const dialog = document.createElement('div'); dialog.id = 'synthetic-photo-dialog';
        dialog.setAttribute('aria-modal', 'true'); dialog.setAttribute('aria-hidden', 'false');
        document.body.append(dialog);
    });
    await page.keyboard.press('ArrowRight');
    assert.equal(await page.locator('#detailRepCounter').innerText(), 'Media 2 of 3', 'Modal viewers must own arrow keys.');
    await page.locator('#synthetic-photo-dialog').evaluate(node => node.remove());
    await page.keyboard.press('ArrowRight');
    assert.equal(await page.locator('#detailRepCounter').innerText(), 'Media 3 of 3');
    console.log('Object photos: stable arrows and scroll anchors at desktop/tablet/phone widths; keyboard, thumbnails, bounds and control/dialog exclusions passed.');
    await page.setViewportSize({width:1280,height:900});
    await page.goto(origin + '/gallery');
    await page.locator('.galleryDetailNext').waitFor();
    await page.locator('.galleryDetailNext').focus();
    await page.keyboard.press('ArrowRight');
    await page.waitForFunction(() => document.querySelector('#galleryIcon2').getAttribute('aria-current') === 'true' && document.querySelector('#galleryDetailImageArea').getAttribute('aria-busy') === 'false' && document.activeElement.classList.contains('galleryDetailNext'));
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => document.querySelector('#galleryIcon3').getAttribute('aria-current') === 'true' && document.activeElement.classList.contains('galleryDetailPrevious'));
    await page.keyboard.press('ArrowLeft');
    await page.waitForFunction(() => document.querySelector('#galleryIcon2').getAttribute('aria-current') === 'true' && document.querySelector('#galleryDetailImageArea').getAttribute('aria-busy') === 'false');
    await page.locator('#moreLink').click();
    assert.equal(await page.locator(':focus').getAttribute('id'), 'galleryIcon13', 'Revealing items must move focus to the first new thumbnail.');
    await page.route('**/Gallery/getSetItem*', async route => {
        if (new URL(route.request().url()).searchParams.get('item_id') === '2') { await new Promise(resolve => setTimeout(resolve, 250)); }
        await route.continue().catch(() => {});
    });
    await page.locator('#galleryIcon2').click();
    await page.locator('#galleryIcon3').click();
    await page.waitForFunction(() => document.querySelector('#galleryIcon3').getAttribute('aria-current') === 'true' && document.querySelector('#galleryDetailImageArea').getAttribute('aria-busy') === 'false');
    await page.waitForTimeout(350);
    assert(await page.locator('.galleryDetailNext').isDisabled(), 'A delayed older response must not replace the selected final item.');
    assert.equal(await page.locator('#galleryIcon3').getAttribute('aria-current'), 'true');
    await page.unroute('**/Gallery/getSetItem*');
    await page.goto(origin + '/gallery'); await page.locator('.galleryDetailNext').waitFor();
    await page.route('**/Gallery/getSetItemInfo?*', async route => {
        if (new URL(route.request().url()).searchParams.get('item_id') === '2') { await route.fulfill({status:503,body:'Synthetic failure'}); }
        else { await route.continue(); }
    });
    await page.locator('#galleryIcon2').click();
    await page.waitForFunction(() => document.querySelector('#tadl-gallery-status').textContent.includes('could not be loaded'));
    assert.equal(await page.locator('#galleryIcon1').getAttribute('aria-current'), 'true', 'A failed selection must preserve the previously matched media/information.');
    assert.equal(await page.locator('#galleryDetailImageArea').getAttribute('aria-busy'), 'false');
    await page.unroute('**/Gallery/getSetItemInfo?*');
    await page.goto(origin + '/lightbox-menu');
    await page.getByRole('button', {name:'Options', exact:true}).focus(); await page.keyboard.press('Enter');
    assert.equal(await page.getByRole('button', {name:'Options', exact:true}).getAttribute('aria-expanded'), 'true');
    await page.keyboard.press('Tab');
    assert(await page.locator('#main .dropdown-menu').evaluate(node => node.contains(document.activeElement)), 'Lightbox menu choices must be keyboard reachable.');
    await page.evaluate(axeSource);
    const openMenuFailures = await page.evaluate(async () => (await axe.run('#main', {runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21a','wcag21aa','wcag22aa']}})).violations.map(item => ({id:item.id, nodes:item.nodes.map(node => node.html)})));
    assert.deepEqual(openMenuFailures, [], 'An open Lightbox menu must retain valid semantics.');
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
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).overflowY), 'hidden', 'An open login dialog must lock background scrolling.');
    await page.evaluate(axeSource);
    const dialogFailures = await page.evaluate(async () => (await axe.run('#caMediaPanel', {runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21a','wcag21aa','wcag22aa']}})).violations.map(v=>v.id));
    assert.deepEqual(dialogFailures, []);
    await page.keyboard.press('Shift+Tab');
    assert(await page.locator('#caMediaPanel').evaluate(node => node.contains(document.activeElement)));
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => document.activeElement.id === 'synthetic-opener');
    assert.equal(await page.locator('header.tadl-header').getAttribute('inert'), null);
    assert.notEqual(await page.evaluate(() => getComputedStyle(document.documentElement).overflowY), 'hidden', 'Escape must release background scrolling.');
    await page.evaluate(() => caMediaPanel.showPanel('/media-dialog'));
    await page.waitForFunction(() => document.activeElement.getAttribute('aria-label') === 'Close media viewer');
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).overflowY), 'hidden', 'Other native media dialogs must also lock background scrolling.');
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => document.querySelector('#caMediaPanel').getAttribute('aria-hidden') === 'true');
    assert.equal(await page.evaluate(() => caUI.panelCount), 0, 'Closing a media dialog must invoke the native lifecycle exactly once.');
    assert.notEqual(await page.evaluate(() => getComputedStyle(document.documentElement).overflowY), 'hidden', 'Closing the media viewer must release background scrolling.');
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
    await page.goto(origin + '/document-text');
    assert.equal(documentTextRequests, 0, 'A closed disclosure must not request full text.');
    await page.locator('.tadl-document-text summary').focus(); await page.keyboard.press('Enter');
    await page.waitForFunction(() => document.querySelector('[data-document-text-loaded]'));
    assert.equal(documentTextRequests, 1);
    assert((await page.locator('[data-document-text-body]').textContent()).includes('Synthetic long OCR text.'));
    await page.keyboard.press('Enter'); await page.keyboard.press('Enter');
    assert.equal(documentTextRequests, 1, 'Reopening a loaded disclosure must reuse its existing text.');
    // Execute the actual adapted registration block with native jQuery hotkeys.
    await page.goto(origin + '/contact');
    await page.addScriptTag({url:origin + '/native/jquery/js/jquery.hotkeys.js'});
    const viewerSource = fs.readFileSync(path.join(theme, 'assets/pawtucket/vendor/jquery.tileviewer.js'), 'utf8');
    const keyboardBlock = viewerSource.slice(viewerSource.indexOf('// Scope shortcuts'), viewerSource.indexOf('// Touch events'));
    await page.evaluate(block => {
        const canvas = document.createElement('canvas');
        const after = document.createElement('button'); after.id = 'after-canvas'; after.textContent = 'After image';
        document.querySelector('#main').append(canvas, after);
        window.viewerCalls = 0;
        const view = {canvas, change_zoom() {window.viewerCalls++;}, get_destination_from_center_with_rotation() {return {x:0,y:0};}, pan() {window.viewerCalls++;}};
        const options = {id:'synthetic'}; const layer = {level:0};
        eval(block);
    }, keyboardBlock);
    await page.locator('canvas').focus();
    await page.keyboard.press('ArrowRight');
    assert.equal(await page.evaluate(() => window.viewerCalls), 1);
    await page.keyboard.press('Tab');
    assert.equal(await page.locator(':focus').getAttribute('id'), 'after-canvas');
    await page.keyboard.press('ArrowRight');
    await page.keyboard.press('n');
    assert.equal(await page.evaluate(() => window.viewerCalls), 1, 'Viewer shortcuts must be inactive outside its canvas.');
    assert.deepEqual(errors, [], 'Unexpected browser errors');
    console.log(reports.join('\n'));
    console.log('Keyboard checks passed: skip link, errors, cookies, gallery AJAX, modal focus/Tab/Escape/restoration, mobile navigation and lightbox reorder/rollback.');
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (browser) { await browser.close(); }
    server.close();
});
