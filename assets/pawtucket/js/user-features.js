/* Bootstrap 3.0 handles the menu but does not update its accessible state. */
(function ($) {
    'use strict';
    $(document).on('shown.bs.dropdown hidden.bs.dropdown', '.dropdown, .btn-group', function (event) {
        $(this).children('[data-toggle="dropdown"]').attr('aria-expanded', event.type === 'shown' ? 'true' : 'false');
    });
    $(document).on('shown.bs.collapse hidden.bs.collapse', '#bs-main-navbar-collapse-1', function (event) {
        $('.tadl-header .navbar-toggle').attr('aria-expanded', event.type === 'shown' ? 'true' : 'false');
    });
    // Native navigation lists use normal Tab order rather than ARIA menu roles.
    $(document).on('keydown', '.dropdown-menu', function (event) {
        if (event.key !== 'Escape') { return; }
        var toggle = $(this).parent('.open').children('[data-toggle="dropdown"]');
        if (toggle.length) {
            event.preventDefault();
            event.stopPropagation();
            toggle.dropdown('toggle').trigger('focus');
        }
    });
    $(document).on('keydown', '#bMorePanel', function (event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            $('#bMorePanelClose').trigger('click');
        }
    });
})(jQuery);

/* Sorting remains a native disclosure with ordinary links when JavaScript is off. */
(function () {
    'use strict';
    function closeOutside(event) {
        document.querySelectorAll('.tadl-results-sort[open]').forEach(function (menu) {
            if (!menu.contains(event.target)) { menu.open = false; }
        });
    }
    document.addEventListener('click', closeOutside);
    document.addEventListener('focusin', closeOutside);
    // Own the keyup before Pawtucket's document-level viewer Escape handlers.
    document.addEventListener('keyup', function (event) {
        if (event.key !== 'Escape') { return; }
        var menu = event.target.closest('.tadl-results-sort[open]');
        if (!menu) { return; }
        event.preventDefault();
        event.stopPropagation();
        menu.open = false;
        menu.querySelector('summary').focus();
    }, true);
})();

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
            var target = panel.querySelector('[data-tadl-error-summary], .tadl-dialog-close, .close a, .close button, button.close, a.close');
            if (target && target.getClientRects().length) { target.focus(); }
            else { panel.focus(); }
        }
        controller.onOpenCallback = function () {
            if (!open) {
                open = true;
                opener = document.activeElement;
                // A panel may live inside main rather than directly under body.
                // Hide siblings along that path without hiding the dialog itself.
                var branch = panel, parent = panel.parentElement || document.body, siblings = [];
                while (parent) {
                    siblings = siblings.concat(Array.prototype.filter.call(parent.children, function (node) {
                        return node !== branch && !/^(SCRIPT|STYLE|LINK)$/.test(node.tagName);
                    }));
                    if (parent === document.body) { break; }
                    branch = parent;
                    parent = parent.parentElement;
                }
                background = siblings.map(function (node) {
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
            background.forEach(function (saved) {
                if (!saved.inert) { saved.node.removeAttribute('inert'); }
                if (saved.hidden === null) { saved.node.removeAttribute('aria-hidden'); }
                else { saved.node.setAttribute('aria-hidden', saved.hidden); }
            });
            background = [];
            if (opener && opener.isConnected && opener.getClientRects().length) { opener.focus(); }
            else {
                var main = document.querySelector('#main');
                if (main) { main.focus(); }
            }
            panel.setAttribute('aria-hidden', 'true');
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

/* Error summaries and motion preferences apply to normal pages as well as panels. */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var summary = document.querySelector('[data-tadl-error-summary]');
        if (summary) { summary.focus(); }
    });
    if (window.matchMedia && jQuery.fx) {
        var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        function updateMotion() { jQuery.fx.off = reducedMotion.matches; }
        updateMotion();
        if (reducedMotion.addEventListener) { reducedMotion.addEventListener('change', updateMotion); }
    }
})();
