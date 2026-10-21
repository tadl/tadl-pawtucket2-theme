(function () {
    'use strict';
    const name = 'tadlDisplayPreferences';
    const allowed = {view:['images', 'list'], collection_view:['flat', 'hierarchy'], filters:['open', 'closed'], images_per_page:Array.from({length:8}, (_, index) => String((index + 1) * 9)), collections_images_per_page:Array.from({length:10}, (_, index) => String((index + 1) * 9)), list_per_page:['25', '50', '100']};
    const facetPanels = new WeakMap();
    document.documentElement.classList.add('tadl-display-preferences-ready');
    function restoreFacetOpener(panel) {
        const opener = window.jQuery(panel).data('tadlOpener');
        if (opener?.isConnected && opener.getClientRects().length) { opener.focus(); }
        else { window.jQuery('#bRefineClose:visible, #bRefineButton:visible').first().focus(); }
    }
    function closeFacetChoices(panel) {
        if (panel.getAttribute('aria-hidden') === 'true') { return; }
        window.jQuery(panel).hide();
        const controller = facetPanels.get(panel);
        if (controller) { controller.finallyCallback(); }
        else { restoreFacetOpener(panel); }
    }
    function openFacetChoices(panel) {
        const list = panel.querySelector('.tadl-facet-browser');
        panel.classList.toggle('tadl-facet-list-panel', !!list);
        if (!list) {
            // Hierarchical facets retain their separate native viewer.
            panel.setAttribute('role', 'region');
            panel.removeAttribute('aria-modal');
            panel.removeAttribute('aria-labelledby');
            panel.removeAttribute('aria-hidden');
            window.jQuery(panel).show().focus();
            return;
        }
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        panel.setAttribute('aria-labelledby', 'bScrollListLabel');
        let controller = facetPanels.get(panel);
        if (!controller) {
            controller = {finallyCallback:() => restoreFacetOpener(panel)};
            window.tadlAccessiblePanel(controller, panel);
            facetPanels.set(panel, controller);
            // Close on keyup, so the same Escape cannot close the underlying
            // sidebar or invoke the native media viewer after focus is restored.
            ['keydown', 'keyup'].forEach(type => panel.addEventListener(type, event => {
                if (event.key !== 'Escape' || !panel.classList.contains('tadl-facet-list-panel')) { return; }
                event.preventDefault();
                event.stopPropagation();
                if (type === 'keyup') { closeFacetChoices(panel); }
            }, true));
        }
        window.jQuery(panel).show();
        panel.querySelector('#bScrollList').scrollTop = 0;
        controller.onOpenCallback();
    }
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
    function initializeFilters() {
        document.querySelectorAll('[data-tadl-filter-layout]').forEach(layout => {
            const panel = layout.querySelector('#bRefine');
            const sidebar = layout.querySelector('.tadl-filter-sidebar');
            const show = layout.querySelector('#bRefineButton');
            const hide = layout.querySelector('#bRefineClose');
            if (!panel || !sidebar || !show || !hide) { return; }
            const phone = window.matchMedia('(max-width: 767px)');
            let preference = allowed.filters.includes(layout.dataset.tadlFilterState) ? layout.dataset.tadlFilterState : null;
            function update(state, moveFocus) {
                const expanded = state === 'open';
                const focusInSidebar = sidebar.contains(document.activeElement);
                if (!expanded) { layout.querySelector('#bMorePanelClose')?.click(); }
                layout.dataset.tadlFilterState = state;
                sidebar.hidden = !expanded;
                show.hidden = expanded;
                hide.hidden = !expanded;
                show.setAttribute('aria-expanded', String(expanded));
                hide.setAttribute('aria-expanded', String(expanded));
                if (moveFocus === true || (moveFocus === null && !expanded && focusInSidebar)) {
                    (expanded ? hide : show).focus({preventScroll:!phone.matches});
                } else if (moveFocus === false && phone.matches) {
                    (expanded ? panel : show).scrollIntoView({block:expanded ? 'start' : 'nearest'});
                }
            }
            function choose(state, moveFocus) {
                preference = state;
                save({filters:state});
                update(state, moveFocus);
            }
            show.addEventListener('click', event => choose('open', event.detail === 0));
            hide.addEventListener('click', event => choose('closed', event.detail === 0));
            // Capture keyup before the native document-level viewer Escape handlers.
            panel.addEventListener('keyup', event => {
                if (event.key !== 'Escape') { return; }
                event.preventDefault();
                event.stopPropagation();
                choose('closed', true);
            }, true);
            phone.addEventListener('change', () => update(preference || (phone.matches ? 'closed' : 'open'), null));
            update(preference || (phone.matches ? 'closed' : 'open'), null);
        });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initializeFilters); }
    else { initializeFilters(); }
    document.addEventListener('click', event => {
        const facetClose = event.target.closest('[data-tadl-facet-close]');
        if (facetClose) { closeFacetChoices(facetClose.closest('#bMorePanel')); return; }
        const facetJump = event.target.closest('[data-tadl-facet-jump]');
        if (facetJump) {
            const heading = document.getElementById(facetJump.getAttribute('href').slice(1));
            const scroller = facetJump.closest('.tadl-facet-browser').querySelector('#bScrollList');
            if (!heading || !scroller.contains(heading)) { return; }
            event.preventDefault();
            scroller.scrollTop += heading.getBoundingClientRect().top - scroller.getBoundingClientRect().top - parseFloat(getComputedStyle(scroller).paddingTop);
            heading.focus({preventScroll:true});
            return;
        }
        const facetToggle = event.target.closest('[data-tadl-facet-toggle]');
        if (facetToggle) {
            const values = document.getElementById(facetToggle.getAttribute('aria-controls'));
            if (!values) { return; }
            const expanded = facetToggle.getAttribute('aria-expanded') !== 'true';
            values.dataset.tadlFacetExpanded = String(expanded);
            facetToggle.setAttribute('aria-expanded', String(expanded));
            facetToggle.setAttribute('aria-label', expanded ? facetToggle.dataset.labelLessAccessible : facetToggle.dataset.labelMoreAccessible);
            facetToggle.querySelector('span').textContent = expanded ? facetToggle.dataset.labelLess : facetToggle.dataset.labelMore;
            const icon = facetToggle.querySelector('i');
            icon.classList.toggle('fa-angle-up', expanded);
            icon.classList.toggle('fa-angle-down', !expanded);
            return;
        }
        const facetChoices = event.target.closest('[data-tadl-facet-choices]');
        if (facetChoices && event.button === 0 && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
            event.preventDefault();
            window.jQuery('#bMorePanel').data('tadlOpener', facetChoices).load(facetChoices.href, function (response, status) {
                if (status !== 'success' && status !== 'notmodified') { location.assign(facetChoices.href); return; }
                openFacetChoices(this);
            });
            return;
        }
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
