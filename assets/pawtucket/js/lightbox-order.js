/* Reuse the native checked reorder endpoint for pointer and keyboard controls. */
(function ($) {
    'use strict';
    var before = new WeakMap();
    window.tadlRememberLightboxOrder = function (container) {
        before.set(container, $(container).children('[id^="row-"]').toArray());
    };
    window.tadlSaveLightboxOrder = function (container, opener) {
        var list = $(container), status = $('#tadl-lightbox-order-status');
        if (list.attr('aria-busy') === 'true' || !list.attr('data-order-url')) { return; }
        list.attr('aria-busy', 'true').find('.tadl-lightbox-move').prop('disabled', true);
        list.sortable('disable');
        function restore() {
            (before.get(container) || []).slice().reverse().forEach(function (node) { container.prepend(node); });
            status.text(list.attr('data-order-error'));
        }
        $.ajax({
            type: 'POST',
            url: list.attr('data-order-url') + '/row_ids/' + list.sortable('serialize'),
            dataType: 'json'
        }).done(function (data) {
            if (data && data.status === 'ok') { status.text(list.attr('data-order-success')); }
            else { restore(); }
        }).fail(restore).always(function () {
            list.attr('aria-busy', 'false').find('.tadl-lightbox-move').prop('disabled', false);
            list.sortable('enable');
            before.delete(container);
            if (opener && opener.isConnected && (document.activeElement === document.body || document.activeElement === opener)) { opener.focus(); }
        });
    };
    $(function () {
        $('#lbSetResultLoadContainer').on('click', '.tadl-lightbox-move', function () {
            var button = this, list = $(this).closest('#lbSetResultLoadContainer');
            if (list.attr('aria-busy') === 'true') { return; }
            var row = $(this).closest('[id^="row-"]');
            var earlier = $(this).attr('data-move') === 'earlier';
            var sibling = earlier ? row.prev('[id^="row-"]') : row.next('[id^="row-"]');
            if (!sibling.length) {
                $('#tadl-lightbox-order-status').text(list.attr('data-order-boundary'));
                return;
            }
            window.tadlRememberLightboxOrder(list[0]);
            if (earlier) { row.insertBefore(sibling); } else { row.insertAfter(sibling); }
            button.focus();
            window.tadlSaveLightboxOrder(list[0], button);
        });
    });
})(jQuery);
