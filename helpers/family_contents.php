<?php
require_once(__DIR__.'/record_access.php');

/** Resolve entity subtypes without treating an unknown type as an individual. */
function tadlEntityBaseType($item) {
	$types = $item->getTypeList();
	$typeID = (int)$item->get('type_id');
	$visited = [];
	while ($typeID && isset($types[$typeID]) && !isset($visited[$typeID])) {
		$visited[$typeID] = true;
		$code = $types[$typeID]['idno'] ?? '';
		if (in_array($code, ['ind', 'org', 'fam'], true)) { return $code; }
		$typeID = (int)($types[$typeID]['parent_id'] ?? 0);
	}
	return null;
}

/** One-hop family/member scopes, with native record, bundle and Pawtucket ACL checks. */
function tadlFamilyEntityScopes($request, array $familyIDs) {
	$access = array_map('intval', (array)caGetUserAccessValues($request));
	$familyIDs = tadlReadableIDs($request, 'ca_entities', $familyIDs, $access, 'ca_objects');
	if (!$familyIDs) { return []; }
	$familyTypes = (array)caMakeTypeIDList('ca_entities', ['fam']);
	if (!$familyTypes) { return []; }
	$memberTypes = (array)caMakeTypeIDList('ca_entities', ['ind', 'org']);
	$readMembers = $memberTypes && caGetBundleAccessLevel('ca_entities', 'ca_entities') >= __CA_BUNDLE_ACCESS_READONLY__;
	$db = Datamodel::getInstanceByTableName('ca_entities', false)->getDb();
	$scopes = [];
	foreach (array_chunk($familyIDs, 500) as $chunk) {
		$roots = $db->query('SELECT entity_id FROM ca_entities WHERE entity_id IN (?) AND type_id IN (?)', [$chunk, $familyTypes]);
		if (!$roots) { throw new RuntimeException('Unable to load families.'); }
		$families = [];
		while ($roots->nextRow()) {
			$id = (int)$roots->get('entity_id');
			$families[] = $id; $scopes[$id] = [$id => $id];
		}
		if (!$families || !$readMembers) { continue; }
		$rows = $db->query('SELECT r.entity_left_id family_id, e.entity_id
			FROM ca_entities_x_entities r INNER JOIN ca_entities e ON e.entity_id = r.entity_right_id
			WHERE r.entity_left_id IN (?) AND e.type_id IN (?) AND e.access IN (?) AND e.deleted = 0
			UNION
			SELECT r.entity_right_id family_id, e.entity_id
			FROM ca_entities_x_entities r INNER JOIN ca_entities e ON e.entity_id = r.entity_left_id
			WHERE r.entity_right_id IN (?) AND e.type_id IN (?) AND e.access IN (?) AND e.deleted = 0',
			[$families, $memberTypes, $access, $families, $memberTypes, $access]);
		if (!$rows) { throw new RuntimeException('Unable to load family relationships.'); }
		$members = []; $links = [];
		while ($rows->nextRow()) {
			$id = (int)$rows->get('entity_id'); $members[$id] = $id;
			$links[] = [(int)$rows->get('family_id'), $id];
		}
		$readable = array_fill_keys(tadlReadableIDs($request, 'ca_entities', array_values($members), $access, 'ca_objects'), true);
		foreach ($links as [$family, $id]) { if (isset($readable[$id])) { $scopes[$family][$id] = $id; } }
	}
	return array_map('array_values', $scopes);
}

/** Numeric terms retain native search sorting, deduplication and object access. */
function tadlFamilyContentsSearch(array $entityIDs) {
	$entityIDs = array_values(array_unique(array_filter(array_map('intval', $entityIDs), static fn($id) => $id > 0)));
	return $entityIDs ? join(' OR ', array_map(static fn($id) => 'entity_id:'.$id, $entityIDs)) : 'ca_objects.object_id:0';
}
