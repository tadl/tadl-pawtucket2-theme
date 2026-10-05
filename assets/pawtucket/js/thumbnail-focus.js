(function () {
    'use strict';
    function positionAxis(focal, visible) {
        return visible >= 1 - 1e-8 ? 50 : Math.max(0, Math.min(100, (focal - visible / 2) / (1 - visible) * 100));
    }
    function cropPosition(width, height, boxWidth, boxHeight, focus) {
        if (![width, height, boxWidth, boxHeight].every(v => Number.isFinite(v) && v > 0)) return null;
        if (!focus || typeof focus !== 'object') return null;
        const scale = Math.max(boxWidth / width, boxHeight / height);
        const visibleX = boxWidth / (width * scale), visibleY = boxHeight / (height * scale);
        let point = focus.point;
        if (!point && focus.faces && focus.faces.length) {
            // Keep the group together; a little headroom is preferable to centering on noses.
            const left = Math.min(...focus.faces.map(f => f[0]));
            const top = Math.min(...focus.faces.map(f => f[1]));
            const right = Math.max(...focus.faces.map(f => f[0] + f[2]));
            const bottom = Math.max(...focus.faces.map(f => f[1] + f[3]));
            point = {x: (left + right) / 2, y: Math.max(0, (top + bottom) / 2 - (bottom - top) * 0.1)};
            // Keep every face inside the crop when the group fits this aspect ratio.
            if (right - left <= visibleX) point.x = Math.max(right - visibleX / 2, Math.min(left + visibleX / 2, point.x));
            if (bottom - top <= visibleY) point.y = Math.max(bottom - visibleY / 2, Math.min(top + visibleY / 2, point.y));
        }
        if (!point || ![point.x, point.y].every(v => Number.isFinite(v) && v >= 0 && v <= 1)) return null;
        return [positionAxis(point.x, visibleX), positionAxis(point.y, visibleY)];
    }
    // Export only the geometry for portable tests. No dependency in the browser.
    if (typeof module !== 'undefined' && module.exports) { module.exports = {cropPosition}; return; }
    const selector = 'img[data-tadl-focus]';
    const observed = new WeakSet();
    function update(img) {
        if (getComputedStyle(img).objectFit !== 'cover') return;
        let focus;
        try { focus = JSON.parse(img.dataset.tadlFocus); } catch (_) { return; }
        const position = cropPosition(img.naturalWidth, img.naturalHeight, img.clientWidth, img.clientHeight, focus);
        if (position) img.style.objectPosition = position.map(v => v + '%').join(' ');
    }
    const resize = typeof ResizeObserver === 'function' ? new ResizeObserver(entries => entries.forEach(e => update(e.target))) : null;
    function scan(root) {
        const images = Array.from(root.querySelectorAll ? root.querySelectorAll(selector) : []);
        if (root.matches && root.matches(selector)) images.push(root);
        images.forEach(img => {
            if (!observed.has(img)) {
                observed.add(img);
                img.addEventListener('load', () => update(img));
                if (resize) resize.observe(img);
            }
            update(img);
        });
    }
    function start() {
        scan(document);
        new MutationObserver(records => records.forEach(record => {
            record.addedNodes.forEach(node => { if (node.nodeType === 1) scan(node); });
            record.removedNodes.forEach(node => {
                if (!resize || node.nodeType !== 1) return;
                const removed = Array.from(node.querySelectorAll(selector));
                if (node.matches(selector)) removed.push(node);
                removed.forEach(img => { resize.unobserve(img); observed.delete(img); });
            });
        })).observe(document.body, {childList: true, subtree: true});
        if (!resize) window.addEventListener('resize', () => scan(document));
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
}());
