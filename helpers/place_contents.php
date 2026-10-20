<?php
require_once(__DIR__.'/record_access.php');

/** Resolve only readable branches; private/deleted intermediate places prune their descendants. */
function tadlPlaceScopes($request, array $placeIDs) {
	return array_map('array_keys', tadlReadableHierarchyBranches($request, 'ca_places', $placeIDs, (array)caGetUserAccessValues($request)));
}

/** Read indexed relationships in batches, not a Lucene OR term for every place. */
function tadlPlaceObjectIDs($request, array $placeIDs) {
	$placeIDs = array_values(array_unique(array_filter($placeIDs, static fn($id) => is_int($id) && $id > 0)));
	$access = array_map('intval', (array)caGetUserAccessValues($request));
	if (!$placeIDs || !$access) { return []; }
	$db = Datamodel::getInstanceByTableName('ca_objects', false)->getDb();
	$ids = [];
	foreach (array_chunk($placeIDs, 500) as $chunk) {
		$rows = $db->query('SELECT DISTINCT r.object_id
			FROM ca_objects_x_places r INNER JOIN ca_objects o ON o.object_id = r.object_id
			WHERE r.place_id IN (?) AND o.deleted = 0 AND o.access IN (?)', [$chunk, $access]);
		if (!$rows) { throw new RuntimeException('Unable to load place relationships.'); }
		while ($rows->nextRow()) { $ids[(int)$rows->get('object_id')] = true; }
	}
	return tadlReadableIDs($request, 'ca_objects', array_keys($ids), $access, 'ca_places');
}

/** Native object access, configured criteria and sorting without parsing a large query. */
function tadlPlaceContentsResult($request, array $info) {
	$id = (int)$request->getParameter('place_id', pInteger);
	$scope = tadlPlaceScopes($request, [$id])[$id] ?? [];
	$ids = tadlPlaceObjectIDs($request, $scope);
	return tadlScopedObjectContentsResult($request, $info, $ids, 'tadl_place_contents:'.$id);
}

/** Shared native object filtering for indexed place/collection relationship scopes. */
function tadlScopedObjectContentsResult($request, array $info, array $ids, $context) {
	$browse = caGetBrowseInstance('ca_objects');
	// Intrinsic result filters are not part of the native browse cache key.
	// Use a separate namespace and always recompute current permissions/membership.
	$browse->setContext($context.':'.(int)$request->getUserID().':'.hash('sha256', join(',', $ids)));
	if (!$browse->addCriteria('_search', ['*'])
		|| !$browse->addResultFilter('ca_objects.object_id', 'in', join(',', $ids ?: [0]))) {
		throw new RuntimeException('Unable to restrict place results.');
	}
	if ($types = ($info['restrictToTypes'] ?? [])) {
		$browse->setTypeRestrictions($types, ['dontExpandHierarchically' => $info['dontExpandTypesHierarchically'] ?? false]);
	}
	$base = (array)(caGetSearchConfig()->get('baseCriteria')['ca_objects'] ?? []);
	foreach (array_merge($base, (array)($info['baseCriteria'] ?? [])) as $facet => $values) {
		if (!$browse->addCriteria($facet, $values)) { throw new RuntimeException('Unable to apply place result criteria.'); }
	}
	if (!empty($info['dontShowChildren'])) { $browse->addResultFilter('ca_objects.parent_id', 'is', 'null'); }
	if (!$browse->execute([
		'checkAccess' => (array)caGetUserAccessValues($request), 'request' => $request,
		'noCache' => true, 'dontCheckFacetAvailability' => true,
		'rootRecordsOnly' => (bool)($info['omitChildRecords'] ?? false),
		'expandResultsHierarchically' => (bool)($info['expandResultsHierarchically'] ?? false),
		'expandToIncludeParents' => (bool)($info['expandToIncludeParents'] ?? false)
	])) { throw new RuntimeException('Unable to load place results.'); }
	$sorts = (array)($info['sortBy'] ?? []);
	$sort = (string)$request->getParameter('sort', pString);
	return $browse->getResults([
		'sort' => $sorts[$sort] ?? reset($sorts),
		'sort_direction' => $request->getParameter('direction', pString) === 'desc' ? 'desc' : 'asc'
	]);
}
