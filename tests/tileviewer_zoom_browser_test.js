/* Actual native image engine/widget; synthetic tiles only, no catalogue or database. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const {chromium} = require('playwright');
const theme = path.resolve(__dirname, '..');
const reference = path.resolve(process.env.TADL_REFERENCE_ROOT || path.join(theme, '../../pawtucket2'));
const artifacts = process.env.TADL_A11Y_ARTIFACT_DIR;
if (artifacts) { fs.mkdirSync(artifacts, {recursive:true, mode:0o700}); }
const images = {
    small: {width:640,height:480,tilesize:256,levels:4},
    large: {width:4096,height:3072,tilesize:256,levels:6},
    tall: {width:640,height:3000,tilesize:256,levels:6}
};
function fixture(image, maximum) {
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Synthetic image viewer</title>'
        + ['/native/bootstrap/css/bootstrap.css','/native/jquery/jquery-ui/jquery-ui.min.css','/native/fontawesome/css/font-awesome.css',
            '/native/jquery/jquery-tileviewer/jquery.tileviewer.css','/theme/pawtucket/css/main.css','/theme/pawtucket/css/theme.css','/theme/pawtucket/css/accessibility.css']
            .map(url => '<link rel="stylesheet" href="' + url + '">').join('')
        + '<style>body{margin:0;overflow:hidden;background:#000}.synthetic-title{height:40px;background:#5d6269;color:white;padding:6px 10px}#synthetic{width:100vw;height:calc(100vh - 40px)}</style>'
        + ['/native/jquery/js/jquery-3.7.1.min.js','/native/jquery/jquery-ui/jquery-ui.min.js','/native/jquery/js/jquery.hotkeys.js',
            '/native/jquery/js/jquery.mousewheel.js','/native/jquery/circular-slider/circular-slider.js','/theme/pawtucket/vendor/jquery.tileviewer.js']
            .map(url => '<script src="' + url + '"></script>').join('')
        + '</head><body class="tadl-interior"><div class="synthetic-title">Synthetic archival image</div><div id="synthetic"></div><script>'
        + 'jQuery("#synthetic").tileviewer(' + JSON.stringify({id:'synthetic',src:'/tile?number=',info:image,maximumPixelsize:maximum,
            toolbar:['pan','overview','expand'],useAnnotations:false,displayAnnotations:false,lockAnnotations:true,
            enableMeasurements:false,allowRotation:false,allowAnnotationList:false}) + ');</script></body></html>';
}
const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost');
    if (url.pathname === '/') {
        response.setHeader('Content-Type','text/html; charset=utf-8');
        response.end(fixture(images[url.searchParams.get('image')] || images.small, url.searchParams.get('maximum') === '1' ? 1 : 4));
        return;
    }
    if (url.pathname === '/tile') {
        response.setHeader('Content-Type','image/svg+xml');
        response.end('<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="#aaa"/><path d="M0 130L70 55L150 130L205 90L256 150V256H0Z" fill="#747474"/></svg>');
        return;
    }
    let base, relative;
    if (url.pathname.startsWith('/native/')) { base = path.join(reference,'assets'); relative = url.pathname.slice(8); }
    if (url.pathname.startsWith('/theme/')) { base = path.join(theme,'assets'); relative = url.pathname.slice(7); }
    if (base) {
        const file = path.resolve(base,relative);
        if (file.startsWith(base + path.sep) && fs.existsSync(file) && fs.statSync(file).isFile()) {
            response.setHeader('Content-Type', ({'.css':'text/css','.js':'application/javascript','.woff':'font/woff','.woff2':'font/woff2','.svg':'image/svg+xml','.jpg':'image/jpeg'})[path.extname(file)] || 'application/octet-stream');
            fs.createReadStream(file).pipe(response); return;
        }
    }
    response.statusCode = 404; response.end('Synthetic resource not found');
});
let browser;
async function readZoom(page) {
    return page.evaluate(() => {
        const view = jQuery('#synthetic').data('view');
        const slider = jQuery('#syntheticZoomSlider');
        return {actual:view.current_zoom(),center:view.center_pixelpos(),minimum:slider.slider('option','min') / 100,
            maximum:slider.slider('option','max') / 100,value:slider.slider('value') / 100};
    });
}
function synchronized(state, phase) {
    assert(state.minimum < state.maximum, phase + ': slider bounds must remain ordered: ' + JSON.stringify(state));
    assert(Math.abs(state.actual - state.value) < .00011, phase + ': handle must represent the actual zoom: ' + JSON.stringify(state));
}
async function dragHandle(page, fraction) {
    const slider = await page.locator('#syntheticZoomSlider').boundingBox();
    const handle = await page.locator('#syntheticZoomSlider .ui-slider-handle').boundingBox();
    await page.mouse.move(handle.x + handle.width / 2, handle.y + handle.height / 2);
    await page.mouse.down();
    await page.mouse.move(slider.x + slider.width * fraction, handle.y + handle.height / 2, {steps:12});
    await page.mouse.up();
}
(async () => {
    await new Promise(resolve => server.listen(0,'127.0.0.1',resolve));
    const origin = 'http://127.0.0.1:' + server.address().port;
    browser = await chromium.launch({headless:true, ...(process.env.TADL_BROWSER_CHANNEL ? {channel:process.env.TADL_BROWSER_CHANNEL} : {})});
    const context = await browser.newContext();
    await context.route('**/*', route => route.request().url().startsWith(origin) ? route.continue() : route.abort());
    const page = await context.newPage();
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    for (const test of [
        {width:1600,height:1100,image:'small',maximum:4},
        {width:1280,height:900,image:'large',maximum:4},
        {width:390,height:844,image:'small',maximum:4},
        {width:320,height:700,image:'tall',maximum:4},
        {width:1600,height:1100,image:'small',maximum:1}
    ]) {
        const label = `${test.image} ${test.width}px, configured maximum ${test.maximum}`;
        await page.setViewportSize({width:test.width,height:test.height});
        await page.goto(origin + '/?image=' + test.image + '&maximum=' + test.maximum);
        await page.locator('#syntheticZoomSlider .ui-slider-handle').waitFor();
        const fitted = await readZoom(page);
        const sliderWidth = (await page.locator('#syntheticZoomSlider').boundingBox()).width;
        // Grab the handle itself, not the track: reversed native limits used to
        // throw in jQuery UI's _mouseCapture before any movement was possible.
        await dragHandle(page,.7);
        assert.deepEqual(errors,[],label + ': native dragging must not throw');
        let state = await readZoom(page);
        synchronized(state,label + ' dragged');
        assert(Math.abs(state.center.x - fitted.center.x) <= 1 && Math.abs(state.center.y - fitted.center.y) <= 1,
            label + ': slider zoom must keep the same image point centered');
        assert(Math.abs(state.actual - fitted.actual) > .01,label + ': handle dragging must change the image scale');
        assert(Math.abs(state.actual - (state.minimum + .7 * (state.maximum - state.minimum))) < (state.maximum - state.minimum) / sliderWidth + .00011,
            label + ': actual zoom must follow the dragged position, not only the handle');
        assert(state.maximum >= test.maximum && state.maximum >= fitted.actual,
            label + ': the slider must cover the full engine range and an already larger fitted view');
        for (const fraction of [1,0,.5]) {
            await dragHandle(page,fraction);
            state = await readZoom(page); synchronized(state,label + ' endpoint ' + fraction);
            assert(Math.abs(state.actual - (state.minimum + fraction * (state.maximum - state.minimum))) < (state.maximum - state.minimum) / sliderWidth + .00011,
                label + ': repeated handle drags must reach either endpoint');
        }
        await page.getByRole('link',{name:'Fit image to screen',exact:true}).click();
        state = await readZoom(page); synchronized(state,label + ' fitted');
        assert(Math.abs(state.actual - fitted.actual) < .00011,label + ': Fit must restore the original view');
        if (test.maximum === 4) {
            await page.getByRole('link',{name:'Zoom in',exact:true}).hover(); await page.mouse.down();
            await page.waitForFunction(zoom => jQuery('#synthetic').data('view').current_zoom() > zoom + .02, state.actual);
            await page.mouse.up();
            // Native press-and-hold controls finish on their next 50ms tick.
            await page.waitForTimeout(80);
            state = await readZoom(page); synchronized(state,label + ' plus');
            assert(state.value < state.maximum - .01,label + ': one plus press must not pin the handle to the maximum');
            await dragHandle(page,.8);
            const beforeMinus = await readZoom(page);
            await page.getByRole('link',{name:'Zoom out',exact:true}).hover(); await page.mouse.down();
            await page.waitForFunction(zoom => jQuery('#synthetic').data('view').current_zoom() < zoom - .02, beforeMinus.actual);
            await page.mouse.up(); await page.waitForTimeout(80);
            synchronized(await readZoom(page),label + ' minus');
            await dragHandle(page,.3); synchronized(await readZoom(page),label + ' dragged after plus');
        }
        await dragHandle(page,.3);
        await page.locator('canvas').first().focus();
        const keyboard = await readZoom(page); await page.keyboard.press('=');
        state = await readZoom(page); synchronized(state,label + ' keyboard');
        assert(state.actual > keyboard.actual,label + ': keyboard zoom must still update the handle');
        const strip = await page.locator('.tileviewerToolbarZoom').boundingBox();
        assert(strip.x >= 36 && strip.x + strip.width <= test.width,label + ': zoom controls must fit the screen and clear side tools');
        if (artifacts) { await page.screenshot({path:path.join(artifacts,`tileviewer-${test.image}-${test.width}-${test.maximum}.png`)}); }
        if (test.image === 'small' && test.width === 1600 && test.maximum === 4) {
            for (const viewport of [{width:3200,height:2600},{width:390,height:844}]) {
                await page.setViewportSize(viewport);
                await page.getByRole('link',{name:'Fit image to screen',exact:true}).click();
                const resized = await readZoom(page); synchronized(resized,'Resized Fit');
                assert(resized.minimum <= resized.actual && resized.maximum + .00011 >= resized.actual,
                    'A larger or smaller fitted image must remain inside valid slider bounds');
                await dragHandle(page,.6); synchronized(await readZoom(page),'Resized handle drag');
            }
        }
        console.log(label + ': full range, actual handle drags, both endpoints, repeated zoom/Fit, synchronization and layout passed');
    }
    // A rapid double-click used to overwrite the shared interval ID. The older
    // timer then kept zooming in after release, pinning subsequent actions at max.
    await page.setViewportSize({width:1280,height:900});
    await page.goto(origin + '/?image=small');
    await page.locator('#syntheticZoomSlider .ui-slider-handle').waitFor();
    await dragHandle(page,1);
    await page.getByRole('link',{name:'Zoom in',exact:true}).dblclick({delay:5});
    await page.getByRole('link',{name:'Zoom out',exact:true}).click();
    await page.waitForTimeout(180);
    let released = await readZoom(page);
    assert(released.actual < released.maximum - .02,'Minus must leave maximum zoom after rapid plus clicks: ' + JSON.stringify(released));
    await page.waitForTimeout(180);
    assert(Math.abs((await readZoom(page)).actual - released.actual) < .00011,'Zoom must stop changing after mouse release');
    await dragHandle(page,.4); released = await readZoom(page);
    await page.waitForTimeout(180);
    assert(Math.abs((await readZoom(page)).actual - released.actual) < .00011,'A released zoom-in timer must not pull a dragged handle back to maximum');
    synchronized(released,'Drag away from maximum after rapid clicks');
    await page.getByRole('link',{name:'Zoom out',exact:true}).dblclick({delay:5});
    released = await readZoom(page); await page.waitForTimeout(180);
    assert(Math.abs((await readZoom(page)).actual - released.actual) < .00011,'Rapid minus clicks must not leave a zoom-out timer running either');
    await page.getByRole('link',{name:'Zoom in',exact:true}).hover(); await page.mouse.down();
    await page.waitForFunction(() => {
        const view = jQuery('#synthetic').data('view');
        return view.current_zoom() >= view.maxZoom - .1;
    });
    const strip = await page.locator('.tileviewerToolbarZoom').boundingBox();
    await page.mouse.move(strip.x + strip.width / 2,strip.y + strip.height / 2);
    await page.mouse.up(); released = await readZoom(page); await page.waitForTimeout(180);
    assert(Math.abs((await readZoom(page)).actual - released.actual) < .00011,'Releasing a held plus button outside the button must stop zooming immediately');
    await dragHandle(page,.4); released = await readZoom(page); await page.waitForTimeout(180);
    assert(Math.abs((await readZoom(page)).actual - released.actual) < .00011,'Dragging away from maximum after a held press must remain stable');
    console.log('Maximum zoom recovery: rapid plus/minus clicks, held plus, release outside the button, minus, actual handle drag and stable scale after release passed');
    assert.deepEqual(errors,[], 'Native image-viewer errors');
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (browser) { await browser.close(); }
    server.close();
});
