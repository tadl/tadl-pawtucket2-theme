<?php
require_once(__DIR__.'/public_errors.php');

/** Gallery is for published presentations; user item sets must not appear here. */
function tadlGuardGalleryRequest($request) {
	// ResultContext includes the Gallery controller for static navigation helpers
	// while rendering Detail pages. Validate only a dispatched Gallery request.
	if (strcasecmp((string)$request->getController(), 'Gallery') !== 0) { return; }
	$action = strtolower((string)$request->getAction());
	if ($action === 'index') { return; }
	// Numeric gallery pages and every native AJAX action resolve the same set.
	$id = ctype_digit($action) ? (int)$action : (int)$request->getParameter('set_id', pInteger);
	$code = caGetGalleryConfig()->get('gallery_set_type');
	$type = $code ? (int)(new ca_lists())->getItemIDFromList('set_types', $code) : 0;
	$set = new ca_sets();
	if ($id < 1 || !$type || !$set->load($id) || $set->get('deleted')
		|| (int)$set->get('type_id') !== $type || (int)$set->get('access') !== 1
		|| !in_array(1, array_map('intval', (array)caGetUserAccessValues($request)), true)
		|| !$set->isReadable($request)) {
		throw new TadlPublicNotFoundException('Page not found');
	}
}
