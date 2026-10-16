<?php
require_once(__DIR__.'/detail_field_helpers.php');
require_once(__DIR__.'/../../helpers/family_contents.php');

function tadlAuthorityText($value) {
	return trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function tadlAuthorityEscape($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Keep repeatable historical addresses, types and dates within their own row. */
function tadlAuthorityAddresses($request, $item) {
	if (caGetBundleAccessLevel('ca_entities', 'address') < __CA_BUNDLE_ACCESS_READONLY__) { return ''; }
	$rows = $item->get('ca_entities.address', [
		'returnWithStructure' => true, 'convertCodesToDisplayText' => true,
		'checkAccess' => caGetUserAccessValues($request), 'dontReturnDefault' => true,
		'convertLineBreaks' => false
	])[(int)$item->get('entity_id')] ?? [];
	$addresses = [];
	foreach ($rows as $row) {
		$values = [];
		foreach (['address1', 'address2', 'city', 'stateprovince', 'postalcode', 'country', 'address_type', 'address_date'] as $code) {
			$values[$code] = tadlDetailHasContent($row[$code] ?? '') ? trim((string)$row[$code]) : '';
		}
		$lines = array_filter([$values['address1'], $values['address2']], static fn($value) => $value !== '');
		$locality = $values['city'];
		if ($values['stateprovince'] !== '') { $locality .= ($locality !== '' ? ', ' : '').$values['stateprovince']; }
		if ($values['postalcode'] !== '') { $locality .= ($locality !== '' ? ' ' : '').$values['postalcode']; }
		if ($locality !== '') { $lines[] = $locality; }
		if ($values['country'] !== '') { $lines[] = $values['country']; }
		if (!$lines) { continue; }
		$context = $values['address_type'];
		if ($values['address_date'] !== '') { $context .= ($context !== '' ? ' (' : '').$values['address_date'].($context !== '' ? ')' : ''); }
		if ($context !== '') { $lines[] = $context; }
		$addresses[] = join('<br/>', array_map(static fn($line) => nl2br(tadlAuthorityEscape($line), false), $lines));
	}
	if (!$addresses) { return ''; }
	return "<div class='unit'><label>".tadlAuthorityEscape(_t('Address'))."</label>"
		.tadlDetailExpandableValue(join('<br/><br/>', $addresses), _t('Address'))."</div>\n";
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
	$code = tadlEntityBaseType($item);
	if ($code === 'ind') { return ['kind' => _t('Person'), 'about' => _t('About this person'), 'browse' => 'people', 'browse_label' => _t('Browse people')]; }
	if ($code === 'org') { return ['kind' => _t('Organization'), 'about' => _t('About this organization'), 'browse' => 'organizations', 'browse_label' => _t('Browse organizations')]; }
	if ($code === 'fam') { return ['kind' => _t('Family'), 'about' => _t('About this family'), 'browse' => 'families', 'browse_label' => _t('Browse families')]; }
	return ['kind' => tadlAuthorityText($item->getTypeName()) ?: _t('Entity'), 'about' => _t('About this record'), 'browse' => null, 'browse_label' => ''];
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
	if ($table === 'ca_entities' && $info['browse'] === 'families') {
		$info['items_url'] = caNavUrl($request, '', 'FamilyContents', 'Objects', ['entity_id' => (int)$item->get('entity_id'), 'view' => 'images']);
		$info['items_note'] = _t('Includes items linked to this family and its directly related people and organizations.');
	}
	if ($table === 'ca_places') {
		$info['items_url'] = caNavUrl($request, '', 'PlaceContents', 'Objects', ['place_id' => (int)$item->get('place_id'), 'view' => 'images']);
		$info['items_note'] = _t('Includes items linked to this place and its descendant places.');
	}
	return $info;
}

/** Native relationships retain record access, ACL, type/source and direction checks. */
function tadlAuthorityRelatedGroups($request, $item, $table) {
	$groups = [];
	$access = (array)caGetUserAccessValues($request);
	$definitions = [
		'ca_collections' => ['key' => 'collection_id', 'heading' => _t('Related collections'), 'more' => _t('Show %1 more collections')],
		'ca_entities' => ['key' => 'entity_id', 'heading' => _t('Related people, organizations and families'), 'more' => _t('Show %1 more people, organizations and families')],
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
			$eligible = array_fill_keys(tadlMediaEligibleIDs($related_table, $ids, $access, $request), true);
			$rows = array_filter($rows, function ($row) use ($definition, $eligible) { return isset($eligible[(int)($row[$definition['key']] ?? 0)]); });
		}
		$links = [];
		foreach ($rows as $row) {
			$id = (int)($row[$definition['key']] ?? 0);
			$label = trim((string)($row['label'] ?? ''));
			if (!$id || !$label || ($related_table === $table && $id === (int)$item->get($definition['key']))) { continue; }
			$role = trim((string)($row['relationship_typename'] ?? ''));
			$html = '<li><span class="tadl-authority-relationship-label">'.caDetailLink($request, tadlAuthorityEscape($label), '', $related_table, $id).'</span>';
			if ($role) { $html .= ' <span class="tadl-authority-relationship-role">('.tadlAuthorityEscape($role).')</span>'; }
			$links[] = ['label' => $label, 'html' => $html.'</li>'];
		}
		if (!$links) { continue; }
		usort($links, function ($a, $b) { return strnatcasecmp($a['label'], $b['label']); });
		$groups[] = $definition + ['links' => array_column($links, 'html')];
	}
	return $groups;
}
