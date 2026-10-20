/* Commit matching media/information together; newer selections supersede pending requests. */
(function ($) {
    'use strict';
    let revision = 0;
    let pending = [];
    window.tadlSelectGalleryItem = function (target, focusSelector) {
        const current = ++revision;
        pending.forEach(request => request.abort());
        const area = $('#galleryDetailImageArea');
        const status = $('#tadl-gallery-status');
        area.attr('aria-busy', 'true');
        const media = $.ajax({url:target.representation, dataType:'html'});
        const information = $.ajax({url:target.information, dataType:'html'});
        pending = [media, information];
        $.when(media, information).done(function (representation, details) {
            if (current !== revision) { return; }
            area.html(representation[0]).attr('aria-busy', 'false');
            $('#galleryDetailObjectInfo').html(details[0]);
            galleryHighlightThumbnail(target.thumbnail);
            status.addClass('sr-only');
            if (focusSelector) {
                let control = area.find(focusSelector + ':not(:disabled)').first();
                if (!control.length) { control = area.find('button:not(:disabled), a[href]').first(); }
                control.trigger('focus');
            }
            if (target.overlay && $('#caMediaPanelContentArea:visible').length) {
                const overlay = $.ajax({url:target.overlay, dataType:'html'}).done(function (html) {
                    if (current === revision) { $('#caMediaPanelContentArea:visible').html(html); }
                });
                pending.push(overlay);
            }
        }).fail(function (_, reason) {
            if (current !== revision || reason === 'abort') { return; }
            area.attr('aria-busy', 'false');
            status.removeClass('sr-only').text(area.attr('data-load-error'));
        });
    };
})(jQuery);
