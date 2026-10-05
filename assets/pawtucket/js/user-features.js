/* Bootstrap 3.0 handles the menu but does not update its accessible state. */
(function ($) {
    'use strict';
    $(document).on('shown.bs.dropdown hidden.bs.dropdown', '.tadl-account-menu', function (event) {
        $(this).children('.dropdown-toggle').attr('aria-expanded', event.type === 'shown' ? 'true' : 'false');
    });
})(jQuery);
