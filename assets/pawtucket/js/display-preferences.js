(function () {
    'use strict';
    const name = 'tadlDisplayPreferences';
    const allowed = {view:['images', 'list'], collection_view:['flat', 'hierarchy'], images_per_page:Array.from({length:8}, (_, index) => String((index + 1) * 9)), collections_images_per_page:Array.from({length:10}, (_, index) => String((index + 1) * 9)), list_per_page:['25', '50', '100']};
    document.documentElement.classList.add('tadl-display-preferences-ready');
    function save(changes) {
        let preferences = {};
        try {
            const cookie = document.cookie.split('; ').find(value => value.startsWith(name + '='));
            if (cookie) { preferences = JSON.parse(decodeURIComponent(cookie.slice(name.length + 1))); }
        } catch (_) { /* Invalid or unavailable cookies fall back to ordinary links. */ }
        const valid = {};
        Object.keys(allowed).forEach(key => {
            const value = String(changes[key] ?? preferences?.[key] ?? '');
            if (allowed[key].includes(value)) { valid[key] = value; }
        });
        const cookiePath = document.documentElement.getAttribute('data-tadl-cookie-path') || '/';
        try {
            document.cookie = name + '=' + encodeURIComponent(JSON.stringify(valid)) + '; Path=' + cookiePath + '; Max-Age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
        } catch (_) { /* Navigation still works when preference storage is blocked. */ }
    }
    document.addEventListener('click', event => {
        const link = event.target.closest('a[data-tadl-view], a[data-tadl-collection-view]');
        if (!link || event.button !== 0) { return; }
        const changes = {};
        if (link.dataset.tadlView) { changes.view = link.dataset.tadlView; }
        if (link.dataset.tadlCollectionView) { changes.collection_view = link.dataset.tadlCollectionView; }
        save(changes);
    });
    document.addEventListener('submit', event => {
        const form = event.target.closest('[data-tadl-per-page-form]');
        if (!form) { return; }
        const choice = form.elements.per_page.value;
        const list = form.elements.view?.value === 'list';
        const key = form.dataset.tadlPageSizeKey || (list ? 'list_per_page' : 'images_per_page');
        if (!['images_per_page', 'collections_images_per_page', 'list_per_page'].includes(key)) { return; }
        // Keep Pawtucket's native numeric preload/context parameter bounded too.
        form.elements.n.value = choice === 'all' ? allowed[key][allowed[key].length - 1] : choice;
        // All belongs to this result set; remember the safe default for its view.
        save({[key]:choice === 'all' ? (list ? '25' : '18') : choice});
    });
    document.addEventListener('change', event => {
        const form = event.target.closest('[data-tadl-per-page-form]');
        if (form && event.target.name === 'per_page') { form.requestSubmit(); }
    });
}());
