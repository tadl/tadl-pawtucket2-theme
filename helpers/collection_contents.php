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
			$child = Datamodel::getInstance('ca_collections', true);
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
function tadlCollectionContentsIDs($request, $collection, $includeDescendants = true) {
	$access = array_map('intval', (array)caGetUserAccessValues($request));
	if (!$access || !tadlCollectionContentsReadable($request, $collection, $access)) { return []; }
	$level = [(int)$collection->getPrimaryKey() => $collection];
	$visited = []; $ids = [];
	while ($level) {
		$parents = [];
		foreach ($level as $id => $node) {
			if (isset($visited[$id])) { continue; }
			$visited[$id] = true;
			if ($node->isReadable($request, 'ca_objects')) { $ids[] = $id; }
			if ($includeDescendants && $node->isReadable($request, 'hierarchy')) { $parents[] = $id; }
		}
		$level = $parents ? tadlCollectionContentsChildren($request, $parents, $access) : [];
	}
	return $ids;
}

/** Numeric collection terms keep the native search responsible for object visibility. */
function tadlCollectionContentsSearch(array $ids) {
	$ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
	return $ids ? join(' OR ', array_map(static fn($id) => 'collection_id:'.$id, $ids)) : 'ca_objects.object_id:0';
}
