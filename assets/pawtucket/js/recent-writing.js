(function () {
    'use strict';

    function writingURL(value, prefix) {
        if (typeof value !== 'string' || !value.trim()) { return null; }
        try {
            const url = new URL(value, 'https://www.tadl.org/');
            return url.protocol === 'https:' && url.host === 'www.tadl.org'
                && !url.username && !url.password && url.pathname.startsWith(prefix) ? url.href : null;
        } catch (_) { return null; }
    }

    function writingCard(post) {
        const title = post?.title?.rendered;
        const href = writingURL(post?.post_url, '/posts/');
        if (typeof title !== 'string' || !title.trim() || !href) { return null; }

        const card = document.createElement('a');
        card.className = 'tadl-blog-card';
        card.href = href;
        const image = document.createElement('span');
        image.className = 'tadl-blog-image';
        const src = writingURL(post?.featured_image_urls?.thumbnail, '/sites/');
        if (src) {
            const img = document.createElement('img');
            img.src = src;
            img.alt = '';
            img.loading = 'lazy';
            img.decoding = 'async';
            image.append(img);
        }
        const label = document.createElement('span');
        label.className = 'tadl-blog-title';
        label.textContent = title.trim();
        card.append(image, label);
        return card;
    }

    async function refreshWriting(grid, mayRetry) {
        const controller = new AbortController();
        const timeout = setTimeout(function () { controller.abort(); }, 8000);
        try {
            const response = await fetch('https://feeds.tools.tadl.org/local_history_posts.json?limit=2', {
                credentials: 'omit', referrerPolicy: 'no-referrer', signal: controller.signal
            });
            // A cold feed enqueues a background refresh; keep the fallback and try once.
            if (response.status === 503 && mayRetry) {
                setTimeout(function () { refreshWriting(grid, false); }, 60000);
                return;
            }
            if (!response.ok) { return; }
            const posts = await response.json();
            if (!Array.isArray(posts)) { return; }
            const cards = posts.map(writingCard).filter(Boolean).slice(0, 2);
            if (cards.length) { grid.replaceChildren(...cards); }
        } catch (_) {
            // Existing cards and View More remain usable during network/feed failures.
        } finally {
            clearTimeout(timeout);
        }
    }

    function start() {
        const grid = document.getElementById('tadl-recent-writing');
        if (grid) { refreshWriting(grid, true); }
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start, {once: true}); }
    else { start(); }
})();
