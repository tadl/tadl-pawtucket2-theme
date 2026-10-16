<?php
require_once(__DIR__.'/record_access.php');

/** Resolve only readable branches; private/deleted intermediate places prune their descendants. */
function tadlPlaceScopes($request, array $placeIDs) {
	return array_map('array_keys', tadlReadableHierarchyBranches($request, 'ca_places', $placeIDs, (array)caGetUserAccessValues($request)));
}

function tadlPlaceContentsSearch(array $placeIDs) {
	$placeIDs = array_values(array_unique(array_filter(array_map('intval', $placeIDs), static fn($id) => $id > 0)));
	return $placeIDs ? join(' OR ', array_map(static fn($id) => 'place_id:'.$id, $placeIDs)) : 'ca_objects.object_id:0';
}
