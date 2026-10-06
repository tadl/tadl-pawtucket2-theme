<?php
// Example for a view located in views/Browse/ or views/Search/.
require_once(__DIR__.'/../../helpers/thumbnail_focus.php');

// $native_thumbnail_html MUST already come from your normal access/ACL-filtered
// native media selection. Preserve its representation, version, alt and link.
// Decorate the image before adding the existing record link:
$focused_thumbnail_html = tadlFocusThumbnail($native_thumbnail_html);
// Use $focused_thumbnail_html wherever this view currently prints the thumbnail.
