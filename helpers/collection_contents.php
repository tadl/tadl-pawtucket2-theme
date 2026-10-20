<?php
require_once(__DIR__.'/record_access.php');

function tadlCollectionContentsMode($request) {
	return $request->getParameter('collection_view', pString) === 'hierarchy' ? 'hierarchy' : 'flat';
}

/** Keep collection traversal within native record, bundle and Pawtucket ACL access. */
function tadlCollectionContentsReadable($request, $collection, array $access) {
	return $collection && $collection->getPrimaryKey() && !$collection->get('deleted')
		&& in_array((int)$collection->get('access'), $access, true)
		&& $collection->isReadable($request)
		&& (!caACLIsEnabled($collection, ['forPawtucket' => true])
			|| $collection->checkACLAccessForUser($request->user) >= __CA_ACL_READONLY_ACCESS__);
}

/** Stop at the configured base collection, or below hidden administrative groups. */
function tadlCollectionContentsAncestry($request, $collection) {
	$access = array_map('intval', (array)caGetUserAccessValues($request));
	$root_type = (string)caGetCollectionsConfig()->get('landing_page_collection_type');
	$nodes = [];
	while (tadlCollectionContentsReadable($request, $collection, $access)) {
		$id = (int)$collection->getPrimaryKey();
		if (isset($nodes[$id])) { return []; }
		$nodes[$id] = $collection;
		if ($root_type !== '' && $collection->getTypeCode() === $root_type) { break; }
		$parent_id = (int)$collection->get('parent_id');
		if (!$parent_id) { break; }
		if (!$collection->isReadable($request, 'hierarchy')) { return []; }
		$parent = Datamodel::getInstance('ca_collections', false);
		if (!$parent || !$parent->load($parent_id)) { return []; }
		if (!tadlCollectionContentsReadable($request, $parent, $access)) { break; }
		if (!$parent->isReadable($request, 'hierarchy')) { return []; }
		$collection = $parent;
	}
	return array_reverse($nodes, true);
}

/** One scoped lookup per level/batch; never load thousands of object records. */
function tadlCollectionContentsChildren($request, array $parents, array $access) {
	if (!$parents || !$access) { return []; }
	$children = [];
	$db = new Db();
	foreach (array_chunk($parents, 500) as $chunk) {
		$rows = $db->query('SELECT collection_id FROM ca_collections WHERE parent_id IN (?) AND access IN (?) AND deleted = 0', [$chunk, $access]);
		if (!$rows) { throw new RuntimeException('Unable to load collection contents.'); }
		while ($rows->nextRow()) {
			// Retained nodes need separate models: the native instance cache shares mutable loaded state.
			$child = Datamodel::getInstance('ca_collections', false);
			if ($child && $child->load((int)$rows->get('collection_id')) && tadlCollectionContentsReadable($request, $child, $access)) {
				$children[(int)$child->getPrimaryKey()] = $child;
			}
		}
	}
	return $children;
}

/** Scalar child metadata and bulk native visibility; never load a model for every child. */
function tadlCollectionContentsChildRows($request, array $parents, array $access) {
    $rows = []; $db = new Db();
    foreach (array_chunk($parents, 500) as $chunk) {
        $result = $db->query('SELECT collection_id, parent_id FROM ca_collections WHERE parent_id IN (?) AND access IN (?) AND deleted = 0', [$chunk, $access]);
        if (!$result) { throw new RuntimeException('Unable to load collection hierarchy.'); }
        $batch = [];
        while ($result->nextRow()) { $batch[(int)$result->get('collection_id')] = (int)$result->get('parent_id'); }
        $allowed = array_fill_keys(tadlReadableIDs($request, 'ca_collections', array_keys($batch), $access), true);
        $rows += array_intersect_key($batch, $allowed);
    }
    return $rows;
}

function tadlCollectionHasHierarchy($request, $collection) {
    $access = array_map('intval', (array)caGetUserAccessValues($request));
    return $access && tadlCollectionContentsReadable($request, $collection, $access)
        && $collection->isReadable($request, 'hierarchy')
        && (bool)tadlCollectionContentsChildRows($request, [(int)$collection->getPrimaryKey()], $access);
}

/** Cycle-safe breadth-first scalar traversal, pruning inaccessible intermediate records. */
function tadlCollectionContentsRows($request, $collection, $descendants = true) {
    $access = array_map('intval', (array)caGetUserAccessValues($request));
    if (!$access || !tadlCollectionContentsReadable($request, $collection, $access)) { return []; }
    $level = [(int)$collection->getPrimaryKey() => (int)$collection->get('parent_id')]; $nodes = [];
    while ($level) {
        $level = array_diff_key($level, $nodes);
        if (!$level) { break; }
        $nodes += $level;
        $parents = $descendants ? tadlReadableIDs($request, 'ca_collections', array_keys($level), $access, 'hierarchy') : [];
        $level = $parents ? tadlCollectionContentsChildRows($request, $parents, $access) : [];
    }
    return $nodes;
}

/** Traverse the selected branch only, pruning inaccessible/deleted ancestors. */
function tadlCollectionContentsCollections($request, $collection, $includeDescendants = true) {
	$access = array_map('intval', (array)caGetUserAccessValues($request));
	if (!$access || !tadlCollectionContentsReadable($request, $collection, $access)) { return []; }
	$level = [(int)$collection->getPrimaryKey() => $collection];
	$nodes = [];
	while ($level) {
		$parents = [];
		foreach ($level as $id => $node) {
			if (isset($nodes[$id])) { continue; }
			$nodes[$id] = $node;
			if ($includeDescendants && $node->isReadable($request, 'hierarchy')) { $parents[] = $id; }
		}
		$level = $parents ? tadlCollectionContentsChildren($request, $parents, $access) : [];
	}
	return $nodes;
}

function tadlCollectionContentsIDs($request, $collection, $includeDescendants = true) {
    return tadlReadableIDs($request, 'ca_collections', array_keys(tadlCollectionContentsRows($request, $collection, $includeDescendants)),
        array_map('intval', (array)caGetUserAccessValues($request)), 'ca_objects');
}

/** Numeric collection terms keep the native search responsible for object visibility. */
function tadlCollectionContentsSearch(array $ids) {
	$ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
	return $ids ? join(' OR ', array_map(static fn($id) => 'collection_id:'.$id, $ids)) : 'ca_objects.object_id:0';
}

/** Digitized-media presence throughout each readable branch, independent of display preference. */
function tadlCollectionContentsMediaPresence($request, $collection) {
	$nodes = tadlCollectionContentsRows($request, $collection);
	$presence = array_fill_keys(array_keys($nodes), false);
	$scope = tadlReadableIDs($request, 'ca_collections', array_keys($nodes), (array)caGetUserAccessValues($request), 'ca_objects');
	if (!$scope) { return $presence; }
	$db = new Db();
	$memberships = []; $objects = [];
	foreach (array_chunk($scope, 500) as $chunk) {
		$rows = $db->query('SELECT DISTINCT object_id, collection_id FROM ca_objects_x_collections WHERE collection_id IN (?)', [$chunk]);
		if (!$rows) { throw new RuntimeException('Unable to check collection media.'); }
		while ($rows->nextRow()) {
			$id = (int)$rows->get('object_id');
			$objects[$id] = $id;
			$memberships[] = [$id, (int)$rows->get('collection_id')];
		}
	}
	$eligible = array_fill_keys(tadlMediaEligibleIDs('ca_objects', array_values($objects), (array)caGetUserAccessValues($request), $request), true);
	foreach ($memberships as [$object_id, $id]) {
		if (!isset($eligible[$object_id])) { continue; }
		// An earlier positive result already marked this node and all its ancestors.
		while (isset($nodes[$id]) && !$presence[$id]) {
			$presence[$id] = true;
			$id = (int)$nodes[$id];
		}
	}
	return $presence;
}

/** Unique visible objects, retained for the optional object-dependent link policy. */
function tadlCollectionContentsCounts($request, $collection) {
	$nodes = tadlCollectionContentsRows($request, $collection);
	$counts = array_fill_keys(array_keys($nodes), 0);
	$scope = tadlReadableIDs($request, 'ca_collections', array_keys($nodes), (array)caGetUserAccessValues($request), 'ca_objects');
	if (!$scope) { return $counts; }
	$browse = caGetBrowseInstance('ca_objects');
	if (!$browse) { throw new RuntimeException('Unable to count collection contents.'); }
	$browse->addCriteria('_search', [tadlCollectionContentsSearch($scope)]);
	$browse->execute(['checkAccess' => caGetUserAccessValues($request), 'request' => $request, 'noCache' => true]);
	$result = $browse->getResults();
	if (!$result) { throw new RuntimeException('Unable to count collection contents.'); }
	tadlFilterMediaResult($request, $result);
	$visible = array_fill_keys($result->getPrimaryKeyValues(PHP_INT_MAX), true);
	if (!$visible) { return $counts; }

	// Fetch memberships in batches, without the native related-item limit or per-object model loads.
	$db = new Db();
	$memberships = [];
	foreach (array_chunk($scope, 500) as $chunk) {
		$rows = $db->query('SELECT DISTINCT object_id, collection_id FROM ca_objects_x_collections WHERE collection_id IN (?)', [$chunk]);
		if (!$rows) { throw new RuntimeException('Unable to count collection contents.'); }
		while ($rows->nextRow()) {
			$objectID = (int)$rows->get('object_id');
			if (isset($visible[$objectID])) { $memberships[$objectID][] = (int)$rows->get('collection_id'); }
		}
	}
	foreach ($memberships as $collectionIDs) {
		$seen = [];
		foreach ($collectionIDs as $id) {
			// A shared object counts once in each ancestor, even across sibling memberships or cycles.
			while (isset($nodes[$id]) && !isset($seen[$id])) {
				$seen[$id] = true;
				$counts[$id]++;
				$id = (int)$nodes[$id];
			}
		}
	}
	return $counts;
}
