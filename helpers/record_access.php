<?php

/** Bulk visibility checks for theme SQL, using the native restriction and ACL APIs. */
function tadlReadableIDs($request, $table, array $ids, array $access, $bundle = null) {
	$keys = ['ca_objects' => 'object_id', 'ca_collections' => 'collection_id',
		'ca_object_representations' => 'representation_id', 'ca_entities' => 'entity_id',
		'ca_places' => 'place_id', 'ca_occurrences' => 'occurrence_id'];
	if (!isset($keys[$table]) || !$request || !$access) { return []; }
	$ids = array_values(array_unique(array_map('intval', array_filter($ids, static fn($id) => (is_int($id) || (is_string($id) && ctype_digit($id))) && (int)$id > 0))));
	if (!$ids || ($bundle && caGetBundleAccessLevel($table, $bundle) < __CA_BUNDLE_ACCESS_READONLY__)) { return []; }
	$model = Datamodel::getInstanceByTableName($table, false);
	if (!$model) { throw new RuntimeException('Unable to check record permissions.'); }
	$permissionFields = [];
	if ($model->getAppConfig()->get('perform_type_access_checking')) { $permissionFields['type'] = $model->getTypeFieldName(); }
	if (caSourceAccessControlIsEnabled($model)) { $permissionFields['source'] = $model->getSourceFieldName(); }
	$select = $keys[$table];
	foreach ($permissionFields as $kind => $field) {
		if (!$field || !preg_match('/\A[a-zA-Z0-9_]+\z/', $field)) { return []; }
		$select .= ', '.$field.' permission_'.$kind;
	}
	$conditions = ['deleted = 0', 'access IN (?)'];
	$params = [array_map('intval', $access)];
	foreach (['type' => caGetTypeRestrictionsForUser($table), 'source' => caGetSourceRestrictionsForUser($table)] as $kind => $allowed) {
		if (!is_array($allowed)) { continue; }
		if (!$allowed) { return []; }
		$field = $kind === 'type' ? $model->getTypeFieldName() : $model->getSourceFieldName();
		if (!$field || !preg_match('/\A[a-zA-Z0-9_]+\z/', $field)) { return []; }
		$conditions[] = $field.' IN (?)'; $params[] = $allowed;
	}
	$readable = [];
	foreach (array_chunk($ids, 500) as $chunk) {
		$rows = $model->getDb()->query('SELECT '.$select.' FROM '.$table.' WHERE '.$keys[$table].' IN (?) AND '.join(' AND ', $conditions), array_merge([$chunk], $params));
		if (!$rows) { throw new RuntimeException('Unable to check record permissions.'); }
		while ($rows->nextRow()) {
			foreach ($permissionFields as $kind => $field) {
				$level = $kind === 'type' ? $request->user->getTypeAccessLevel($table, $rows->get('permission_type'))
					: $request->user->getSourceAccessLevel($table, $rows->get('permission_source'));
				if ($level < __CA_BUNDLE_ACCESS_READONLY__) { continue 2; }
			}
			$readable[] = (int)$rows->get($keys[$table]);
		}
	}
	if ($readable && caACLIsEnabled($model, ['forPawtucket' => true])) {
		$browse = caGetBrowseInstance($table);
		if (!$browse) { throw new RuntimeException('Unable to check record ACLs.'); }
		$readable = $browse->filterHitsByACL($readable, $model->tableNum(), $request->getUserID());
	}
	$allowed = array_fill_keys($readable, true);
	return array_values(array_filter($ids, static fn($id) => isset($allowed[$id])));
}

/** Media selection and eligibility share the same object/representation boundary. */
function tadlReadableMediaRows($request, array $rows, array $access) {
	foreach (['ca_objects' => ['object_id', 'ca_object_representations'], 'ca_object_representations' => ['representation_id', 'media']] as $table => [$field, $bundle]) {
		if (!$rows) { break; }
		$allowed = array_fill_keys(tadlReadableIDs($request, $table, array_column($rows, $field), $access, $bundle), true);
		$rows = array_filter($rows, static fn($row) => isset($allowed[$row[$field]]));
	}
	return $rows;
}

/** Scalar hierarchy metadata only; reject branches below unreadable ancestors. */
function tadlReadableCollectionBranches($request, array $roots, array $access) {
	$roots = tadlReadableIDs($request, 'ca_collections', $roots, $access, 'ca_objects');
	$branches = [];
	if (!$roots) { return []; }
	foreach ($roots as $id) { $branches[$id][$id] = true; }
	if (caGetBundleAccessLevel('ca_collections', 'hierarchy') < __CA_BUNDLE_ACCESS_READONLY__) { return $branches; }
	$db = Datamodel::getInstanceByTableName('ca_collections', false)->getDb();
	foreach (array_chunk($roots, 500) as $chunk) {
		$result = $db->query('SELECT DISTINCT p.collection_id root_id, c.collection_id, c.parent_id
			FROM ca_collections p INNER JOIN ca_collections c ON c.collection_id = p.collection_id OR (
				p.hier_collection_id > 0 AND c.hier_collection_id = p.hier_collection_id
				AND p.hier_left < p.hier_right AND c.hier_left < c.hier_right
				AND c.hier_left >= p.hier_left AND c.hier_right <= p.hier_right)
			WHERE p.collection_id IN (?)', [$chunk]);
		if (!$result) { throw new RuntimeException('Unable to check collection branches.'); }
		$trees = []; $ids = [];
		while ($result->nextRow()) {
			$id = (int)$result->get('collection_id'); $ids[$id] = $id;
			$trees[(int)$result->get('root_id')][$id] = (int)$result->get('parent_id');
		}
		$allowed = array_fill_keys(tadlReadableIDs($request, 'ca_collections', array_values($ids), $access), true);
		foreach ($trees as $root => $parents) {
			foreach ($parents as $id => $parent) {
				$path = []; $cursor = $id;
				while ($cursor !== $root && isset($allowed[$cursor], $parents[$cursor]) && !isset($path[$cursor])) {
					$path[$cursor] = true; $cursor = $parents[$cursor];
				}
				if ($cursor === $root) { $branches[$root][$id] = true; }
			}
		}
	}
	return $branches;
}
