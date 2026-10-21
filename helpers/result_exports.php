<?php
require_once(__DIR__.'/public_errors.php');

/** Reject retired catalogue exports before constructing result sets. */
function tadlGuardResultExportRequest($request) {
	if (!in_array(strtolower((string)$request->getController()),
		['search', 'browse', 'collectioncontents', 'placecontents', 'familycontents'], true)) { return; }
	$view = $request->getParameter('view', pString);
	$format = $request->getParameter('export_format', pString);
	if (in_array($view, ['pdf', 'xlsx', 'pptx'], true)
		|| (is_string($format) && trim($format) !== '')
		|| $request->getParameter('download', pInteger)) {
		throw new TadlResultExportDisabledException('Catalogue result exports are disabled');
	}
}
