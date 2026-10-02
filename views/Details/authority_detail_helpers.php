<?php
require_once(__DIR__.'/detail_field_helpers.php');

function tadlAuthorityText($value) {
	return trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function tadlAuthorityEscape($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Keep each occupation and its optional date within the same attribute row. */
function tadlAuthorityOccupations($request, $item) {
	if (caGetBundleAccessLevel('ca_entities', 'occupation') < __CA_BUNDLE_ACCESS_READONLY__) { return ''; }
	$rows = $item->get('ca_entities.occupation', [
		'returnWithStructure' => true, 'convertCodesToDisplayText' => true,
		'checkAccess' => caGetUserAccessValues($request), 'dontReturnDefault' => true
	])[(int)$item->get('entity_id')] ?? [];
	$occupations = [];
	foreach ($rows as $row) {
		$name = $row['occupation_name'] ?? '';
		if (!tadlDetailHasContent($name)) { continue; }
		$value = tadlAuthorityEscape($name);
		$date = $row['occupation_date'] ?? '';
		if (tadlDetailHasContent($date)) { $value .= ' ('.tadlAuthorityEscape($date).')'; }
		$occupations[] = $value;
	}
	if (!$occupations) { return ''; }
	return "<div class='unit'><label>".tadlAuthorityEscape(_t('Occupation'))."</label>".join('<br/>', $occupations)."</div>\n";
}

/** Recognize configured entity subtypes without calling an unknown type a person. */
function tadlAuthorityEntityKind($item) {
	$type_id = (int)$item->get('type_id');
	$types = $item->getTypeList();
	$visited = [];
	while ($type_id && isset($types[$type_id]) && !isset($visited[$type_id])) {
		$visited[$type_id] = true;
		$code = $types[$type_id]['idno'] ?? '';
		if ($code === 'ind') { return ['kind' => _t('Person'), 'about' => _t('About this person'), 'browse' => 'people', 'browse_label' => _t('Browse people')]; }
		if ($code === 'org') { return ['kind' => _t('Organization'), 'about' => _t('About this organization'), 'browse' => 'organizations', 'browse_label' => _t('Browse organizations')]; }
		$type_id = (int)($types[$type_id]['parent_id'] ?? 0);
	}
	return ['kind' => tadlAuthorityText($item->getTypeName()) ?: _t('Person or organization'), 'about' => _t('About this record'), 'browse' => 'Index', 'browse_label' => _t('Browse the archive')];
}

function tadlAuthorityDetailInfo($request, $item, $table) {
	$types = [
		'ca_places' => ['kind' => _t('Place'), 'about' => _t('About this place'), 'browse' => 'places', 'browse_label' => _t('Browse places'), 'key' => 'place_id', 'label' => 'name'],
		'ca_entities' => ['key' => 'entity_id', 'label' => 'displayname'],
		'ca_occurrences' => ['kind' => _t('Event'), 'about' => _t('About this event'), 'browse' => 'occurrences', 'browse_label' => _t('Browse events'), 'key' => 'occurrence_id', 'label' => 'name']
	];
	$info = $types[$table];
	if ($table === 'ca_entities') { $info = array_merge($info, tadlAuthorityEntityKind($item)); }
	$options = ['convertCodesToDisplayText' => true, 'checkAccess' => caGetUserAccessValues($request)];
	$info['name'] = tadlAuthorityText($item->getWithTemplate('^'.$table.'.preferred_labels.'.$info['label'], $options));
	$info['type'] = tadlAuthorityText($item->getWithTemplate('^'.$table.'.type_id', $options));
	$info['identifier'] = tadlAuthorityText($item->getWithTemplate('^'.$table.'.idno', $options));
	$info['items_url'] = caNavUrl($request, '', 'Search', 'objects', [
		'search' => $info['key'].':'.(int)$item->get($info['key']),
		'view' => 'images', 's' => 0, '_advanced' => 0, 'clear' => 1
	]);
	return $info;
}

/** Native relationships retain record access, ACL, type/source and direction checks. */
function tadlAuthorityRelatedGroups($request, $item, $table) {
	$groups = [];
	$access = (array)caGetUserAccessValues($request);
	$definitions = [
		'ca_collections' => ['key' => 'collection_id', 'heading' => _t('Related collections'), 'more' => _t('Show %1 more collections')],
		'ca_entities' => ['key' => 'entity_id', 'heading' => _t('Related people and organizations'), 'more' => _t('Show %1 more people and organizations')],
		'ca_places' => ['key' => 'place_id', 'heading' => _t('Related places'), 'more' => _t('Show %1 more places')],
		'ca_occurrences' => ['key' => 'occurrence_id', 'heading' => _t('Related events'), 'more' => _t('Show %1 more events')]
	];
	foreach ($definitions as $related_table => $definition) {
		// getRelatedItems() checks record permissions, but not the relationship
		// bundle or preferred-label bundle. Check both before reading labels.
		if (caGetBundleAccessLevel($table, $related_table) < __CA_BUNDLE_ACCESS_READONLY__
			|| caGetBundleAccessLevel($related_table, 'preferred_labels') < __CA_BUNDLE_ACCESS_READONLY__) { continue; }
		$rows = (array)$item->getRelatedItems($related_table, ['checkAccess' => $access]);
		if ($rows && caACLIsEnabled($related_table, ['forPawtucket' => true])) {
			// Native relationships check back-end ACLs; also honor front-end-only ACLs.
			$target = Datamodel::getInstance($related_table, true);
			$browse = caGetBrowseInstance($related_table);
			$ids = array_column($rows, $definition['key']);
			$readable = array_fill_keys($target && $browse ? $browse->filterHitsByACL($ids, $target->tableNum(), $request->getUserID()) : [], true);
			$rows = array_filter($rows, function ($row) use ($definition, $readable) { return isset($readable[(int)($row[$definition['key']] ?? 0)]); });
		}
		if ($related_table === 'ca_collections' && tadlMediaPreference($request) === 'only') {
			$ids = array_column($rows, $definition['key']);
			$eligible = array_fill_keys(tadlMediaEligibleIDs($related_table, $ids, $access), true);
			$rows = array_filter($rows, function ($row) use ($definition, $eligible) { return isset($eligible[(int)($row[$definition['key']] ?? 0)]); });
		}
		$links = [];
		foreach ($rows as $row) {
			$id = (int)($row[$definition['key']] ?? 0);
			$label = trim((string)($row['label'] ?? ''));
			if (!$id || !$label || ($related_table === $table && $id === (int)$item->get($definition['key']))) { continue; }
			$role = trim((string)($row['relationship_typename'] ?? ''));
			$html = '<li><span class="tadl-authority-relationship-label">'.caDetailLink($request, tadlAuthorityEscape($label), '', $related_table, $id).'</span>';
			if ($role) { $html .= '<span class="tadl-authority-relationship-role">'.tadlAuthorityEscape($role).'</span>'; }
			$links[] = ['label' => $label, 'html' => $html.'</li>'];
		}
		if (!$links) { continue; }
		usort($links, function ($a, $b) { return strnatcasecmp($a['label'], $b['label']); });
		$groups[] = $definition + ['links' => array_column($links, 'html')];
	}
	return $groups;
}
