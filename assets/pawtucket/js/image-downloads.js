// Delegate dismissal so menus loaded by gallery/viewer AJAX behave the same way.
document.addEventListener('click', function (event) {
    document.querySelectorAll('details.tadl-image-downloads[open]').forEach(function (menu) {
        if (!menu.contains(event.target)) { menu.open = false; }
    });
}, true);

document.addEventListener('keydown', function (event) {
    // TileViewer's Tab shortcut must not block keyboard use of a download menu.
    if (event.key === 'Tab' && event.target.closest('details.tadl-image-downloads')) {
        event.stopPropagation();
        return;
    }
    if (event.key !== 'Escape') { return; }
    const menus = document.querySelectorAll('details.tadl-image-downloads[open]');
    if (!menus.length) { return; }
    menus.forEach(function (menu) {
        if (menu.contains(document.activeElement)) { menu.querySelector('summary').focus(); }
        menu.open = false;
    });
    // Close the menu first, without also closing the media viewer behind it.
    event.preventDefault();
    event.stopPropagation();
}, true);

function tadlPlaceViewerDownload(overlay) {
    if (!overlay) { return; }
    const menu = overlay.querySelector('.tadl-viewer-downloads');
    const column = overlay.querySelector('.tileviewerToolbarCol');
    if (!menu || !column || column.contains(menu)) { return; }
    const rotation = column.querySelector('[id$="ControlRotation"]');
    if (rotation) { column.insertBefore(menu, rotation); }
    else { column.prepend(menu); }
}
