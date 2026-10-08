/* Bootstrap 3.0 handles the menu but does not update its accessible state. */
(function ($) {
    'use strict';
    $(document).on('shown.bs.dropdown hidden.bs.dropdown', '.tadl-main-nav .dropdown', function (event) {
        $(this).children('.dropdown-toggle').attr('aria-expanded', event.type === 'shown' ? 'true' : 'false');
    });
    $(document).on('shown.bs.collapse hidden.bs.collapse', '#bs-main-navbar-collapse-1', function (event) {
        $('.tadl-header .navbar-toggle').attr('aria-expanded', event.type === 'shown' ? 'true' : 'false');
    });
})(jQuery);

/* Keep the native AJAX viewer/panel lifecycle, with keyboard and modal semantics. */
(function () {
    'use strict';
    window.tadlAccessiblePanel = function (controller, panel) {
        if (!panel) { return; }
        var opener, background = [], open = false;
        var onOpen = controller.onOpenCallback, onFinally = controller.finallyCallback;
        function controls() {
            return Array.prototype.filter.call(panel.querySelectorAll('a[href],button,input:not([type="hidden"]),select,textarea,summary,[tabindex]'), function (node) {
                return !node.disabled && node.tabIndex >= 0 && node.getClientRects().length && !node.closest('[inert]');
            }).sort(function (a, b) {
                // Positive tabindex values precede the normal DOM tab order.
                return (a.tabIndex || Infinity) - (b.tabIndex || Infinity);
            });
        }
        function focusContent() {
            if (!open || (document.activeElement !== panel && panel.contains(document.activeElement))) { return; }
            var target = panel.querySelector('.close');
            if (target && target.getClientRects().length) { target.focus(); }
            else { panel.focus(); }
        }
        controller.onOpenCallback = function () {
            if (!open) {
                open = true;
                opener = document.activeElement;
                background = Array.prototype.filter.call(document.body.children, function (node) {
                    return node !== panel && !node.contains(panel) && !/^(SCRIPT|STYLE|LINK)$/.test(node.tagName);
                }).map(function (node) {
                    var saved = {node: node, inert: node.hasAttribute('inert'), hidden: node.getAttribute('aria-hidden')};
                    node.setAttribute('inert', ''); node.setAttribute('aria-hidden', 'true');
                    return saved;
                });
                panel.setAttribute('aria-hidden', 'false');
                panel.focus();
            }
            if (onOpen) { onOpen.apply(controller, arguments); }
            focusContent();
        };
        controller.finallyCallback = function () {
            open = false;
            panel.setAttribute('aria-hidden', 'true');
            background.forEach(function (saved) {
                if (!saved.inert) { saved.node.removeAttribute('inert'); }
                if (saved.hidden === null) { saved.node.removeAttribute('aria-hidden'); }
                else { saved.node.setAttribute('aria-hidden', saved.hidden); }
            });
            background = [];
            if (opener && opener.isConnected && opener.getClientRects().length) { opener.focus(); }
            if (onFinally) { onFinally.apply(controller, arguments); }
        };
        // The native onOpen callback precedes the .load() completion. Focus the
        // close control after AJAX content arrives, without stealing focus on slides.
        new MutationObserver(focusContent).observe(panel, {childList: true, subtree: true});
        document.addEventListener('keydown', function (event) {
            if (!open || event.key !== 'Tab') { return; }
            var items = controls(), index = items.indexOf(document.activeElement);
            if (!items.length) { event.preventDefault(); panel.focus(); return; }
            if (index < 0 || (event.shiftKey && index === 0) || (!event.shiftKey && index === items.length - 1)) {
                event.preventDefault(); items[event.shiftKey ? items.length - 1 : 0].focus();
            }
        }, true);
        // Browsers without inert still need programmatic focus kept inside the dialog.
        document.addEventListener('focusin', function (event) {
            if (open && !panel.contains(event.target)) { panel.focus(); }
        });
    };
})();
