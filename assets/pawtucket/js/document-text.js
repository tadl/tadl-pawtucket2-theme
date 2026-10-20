/* Full document text is requested only when its native disclosure opens. */
document.addEventListener('toggle', function (event) {
    const section = event.target;
    if (!section.matches('details[data-document-text-url]') || !section.open || section.dataset.documentTextLoaded || section.dataset.documentTextLoading) { return; }
    section.dataset.documentTextLoading = 'true';
    const body = section.querySelector('[data-document-text-body]');
    const status = section.querySelector('[data-document-text-status]');
    body.setAttribute('aria-busy', 'true');
    status.textContent = 'Loading document text…';
    jQuery.ajax({url:section.dataset.documentTextUrl, dataType:'html'}).done(function (html) {
        jQuery(body).html(html);
        section.dataset.documentTextLoaded = 'true';
        status.textContent = 'Document text loaded.';
    }).fail(function () {
        status.classList.remove('sr-only');
        status.textContent = 'Document text could not be loaded. Try opening the text link, or close and reopen this section to retry.';
    }).always(function () {
        body.setAttribute('aria-busy', 'false');
        delete section.dataset.documentTextLoading;
    });
}, true);
