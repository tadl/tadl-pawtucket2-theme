/* Reveal the selected derivative without moving the surrounding result grid. */
(function () {
    'use strict';
    const cards = new WeakMap(), active = new Set();
    const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    function restore(state) {
        clearTimeout(state.timer);
        cancelAnimationFrame(state.frame);
        state.card.classList.remove('tadl-thumbnail-preview-active', 'tadl-thumbnail-preview-open');
        (state.saved || []).forEach(([node, name, value, priority]) => {
            if (value) node.style.setProperty(name, value, priority);
            else node.style.removeProperty(name);
        });
        state.saved = null;
        active.delete(state);
    }

    function expand(state) {
        if (!state.open || !state.image.naturalWidth || !state.image.naturalHeight || !state.slot.isConnected) return;
        clearTimeout(state.timer);
        cancelAnimationFrame(state.frame);
        if (!active.has(state)) {
            const height = state.card.getBoundingClientRect().height;
            state.baseHeight = state.box.getBoundingClientRect().height;
            state.saved = [[state.slot, 'height'], [state.card, 'min-height'], [state.box, 'height']].map(([node, name]) =>
                [node, name, node.style.getPropertyValue(name), node.style.getPropertyPriority(name)]);
            state.slot.style.height = height + 'px';
            state.card.style.minHeight = height + 'px';
            state.box.style.height = state.baseHeight + 'px';
            if (!state.preview) {
                state.preview = document.createElement('img');
                state.preview.className = 'tadl-thumbnail-preview-image';
                state.preview.alt = '';
                state.preview.setAttribute('aria-hidden', 'true');
                state.preview.loading = 'eager';
                state.preview.referrerPolicy = state.image.referrerPolicy;
                if (state.image.hasAttribute('crossorigin')) state.preview.setAttribute('crossorigin', state.image.getAttribute('crossorigin'));
                state.box.append(state.preview);
            }
            // Reuse exactly the already selected image; never request an original or another representation.
            state.preview.src = state.image.currentSrc || state.image.src;
            state.card.classList.add('tadl-thumbnail-preview-active');
            active.add(state);
            state.box.getBoundingClientRect(); // Establish the pixel start height before transitioning.
        }
        const fullHeight = state.box.clientWidth * state.image.naturalHeight / state.image.naturalWidth;
        const height = Math.max(state.baseHeight, Math.min(fullHeight, window.innerHeight * 0.75));
        state.frame = requestAnimationFrame(() => {
            state.box.style.height = height + 'px';
            state.card.classList.add('tadl-thumbnail-preview-open');
        });
    }

    function update(state) {
        state.open = !state.dismissed && (state.hovered || state.focused);
        if (state.open) { expand(state); return; }
        if (!active.has(state)) return;
        cancelAnimationFrame(state.frame);
        clearTimeout(state.timer);
        state.card.classList.remove('tadl-thumbnail-preview-open');
        state.box.style.height = state.baseHeight + 'px';
        // Retain the floating card until contraction finishes, including when interrupted.
        if (reducedMotion.matches) restore(state);
        else state.timer = setTimeout(() => { if (!state.open) restore(state); }, 260);
    }

    function attach(card) {
        const existing = cards.get(card);
        if (existing) { if (resize) resize.observe(existing.slot); return; }
        const slot = card.parentElement;
        const box = card.querySelector('.bResultItemImg');
        const image = box && box.querySelector('img');
        if (!image || !slot.matches('.tadl-results-grid > .bResultItemCol')) return;
        const state = {card, slot, box, image, hovered: false, focused: false, dismissed: false, open: false};
        cards.set(card, state);
        card.addEventListener('pointerenter', event => {
            if (event.pointerType !== 'mouse' || !finePointer.matches) return;
            state.hovered = true; state.dismissed = false; update(state);
        });
        card.addEventListener('pointerleave', () => {
            state.hovered = false;
            if (!state.focused) state.dismissed = false;
            update(state);
        });
        card.addEventListener('focusin', event => {
            state.focused = event.target.matches(':focus-visible');
            state.dismissed = false; update(state);
        });
        card.addEventListener('focusout', event => {
            if (card.contains(event.relatedTarget)) return;
            state.focused = false;
            if (!state.hovered) state.dismissed = false;
            update(state);
        });
        image.addEventListener('load', () => { if (state.open) expand(state); });
        image.addEventListener('error', () => { state.open = false; restore(state); });
        if (resize) resize.observe(slot);
    }

    function scan(root, callback) {
        if (root.matches && root.matches('.tadl-results-grid .bResultItem')) callback(root);
        if (root.querySelectorAll) root.querySelectorAll('.tadl-results-grid .bResultItem').forEach(callback);
    }

    function resized(state) {
        if (!active.has(state)) return;
        restore(state);
        if (state.open) state.frame = requestAnimationFrame(() => expand(state));
    }
    const resize = typeof ResizeObserver === 'function' ? new ResizeObserver(entries => {
        entries.forEach(entry => {
            const state = cards.get(entry.target.querySelector('.bResultItem'));
            if (!state) return;
            // Expansion changes height only; watch width to avoid a resize feedback loop.
            const width = entry.contentRect.width;
            if (state.width !== undefined && Math.abs(width - state.width) > 0.5) resized(state);
            state.width = width;
        });
    }) : null;

    function start() {
        scan(document, attach);
        new MutationObserver(records => records.forEach(record => {
            record.addedNodes.forEach(node => { if (node.nodeType === 1) scan(node, attach); });
            record.removedNodes.forEach(node => {
                if (node.nodeType !== 1) return;
                // Removed cards no longer match a grid ancestor, so inspect them directly.
                const removed = Array.from(node.querySelectorAll('.bResultItem'));
                if (node.matches('.bResultItem')) removed.push(node);
                removed.forEach(card => {
                    const state = cards.get(card);
                    if (!state || card.isConnected) return;
                    state.hovered = false; state.focused = false; state.dismissed = false;
                    state.open = false; restore(state);
                    if (resize) resize.unobserve(state.slot);
                });
            });
        })).observe(document.body, {childList: true, subtree: true});
        document.addEventListener('keydown', event => {
            if (event.key !== 'Escape' || !active.size) return;
            active.forEach(state => { state.dismissed = true; update(state); });
            event.preventDefault();
        });
        window.addEventListener('resize', () => active.forEach(resized), {passive: true});
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
}());
