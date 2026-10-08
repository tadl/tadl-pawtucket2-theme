<?php

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

function tadlCollectionHasHierarchy($request, $collection) {
	$access = array_map('intval', (array)caGetUserAccessValues($request));
	return $access && tadlCollectionContentsReadable($request, $collection, $access)
		&& $collection->isReadable($request, 'hierarchy')
		&& (bool)tadlCollectionContentsChildren($request, [(int)$collection->getPrimaryKey()], $access);
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
	$ids = [];
	foreach (tadlCollectionContentsCollections($request, $collection, $includeDescendants) as $id => $node) {
		if ($node->isReadable($request, 'ca_objects')) { $ids[] = $id; }
	}
	return $ids;
}

/** Numeric collection terms keep the native search responsible for object visibility. */
function tadlCollectionContentsSearch(array $ids) {
	$ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
	return $ids ? join(' OR ', array_map(static fn($id) => 'collection_id:'.$id, $ids)) : 'ca_objects.object_id:0';
}

/** Unique visible objects in each whole branch, with one native object search per browser response. */
function tadlCollectionContentsCounts($request, $collection) {
	$nodes = tadlCollectionContentsCollections($request, $collection);
	$counts = array_fill_keys(array_keys($nodes), 0);
	$scope = [];
	foreach ($nodes as $id => $node) {
		if ($node->isReadable($request, 'ca_objects')) { $scope[] = $id; }
	}
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
				$id = (int)$nodes[$id]->get('parent_id');
			}
		}
	}
	return $counts;
}
