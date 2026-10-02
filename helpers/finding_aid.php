<?php
/** Public finding aids never bypass native record, type, source or bundle access. */
function tadlFindingAidReadable($request, $record) {
	$access = array_map('intval', (array)caGetUserAccessValues($request));
	return $record && $record->getPrimaryKey() && !$record->get('deleted')
		&& in_array((int)$record->get('access'), $access, true)
		&& $record->isReadable($request)
		&& (!caACLIsEnabled($record, ['forPawtucket' => true])
			|| $record->checkACLAccessForUser($request->user) >= __CA_ACL_READONLY_ACCESS__);
}

/** Catalog text becomes escaped text in the PDF, never executable HTML. */
function tadlFindingAidText($value) {
	$value = preg_replace('~<(script|style)\b[^>]*>.*?</\1\s*>~is', '', (string)$value);
	$value = preg_replace('~<br\s*/?>|</(?:p|div|li)>~i', "\n", $value);
	$value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$value = trim(preg_replace('/[\p{Z}\t]+/u', ' ', $value));
	return preg_match('/[\p{L}\p{N}]/u', $value) ? $value : '';
}

/** Only read a configured, existing field when its root bundle is readable. */
function tadlFindingAidValue($request, $record, $bundles) {
	foreach ((array)$bundles as $bundle) {
		if (!preg_match('/\A'.preg_quote($record->tableName(), '/').'\.([a-zA-Z0-9_]+)(?:\.[a-zA-Z0-9_]+)*\z/', (string)$bundle, $match)) { continue; }
		$root = $match[1];
		if ($root !== 'preferred_labels' && !$record->hasField($root) && !$record->hasElement($root)) { continue; }
		if (!$record->isReadable($request, $root)) { continue; }
		$value = tadlFindingAidText($record->getWithTemplate('^'.$bundle, [
			'checkAccess' => caGetUserAccessValues($request), 'convertCodesToDisplayText' => true,
			'makeLink' => false, 'delimiter' => '; ', 'dontReturnDefault' => true
		]));
		if ($value !== '') { return $value; }
	}
	return '';
}

function tadlFindingAidFields($request, $record, $fields) {
	$result = [];
	foreach ((array)$fields as $key => $field) {
		if (!is_array($field)) { continue; }
		$value = tadlFindingAidValue($request, $record, $field['bundles'] ?? []);
		if ($value !== '') { $result[$key] = ['label' => (string)($field['label'] ?? $key), 'value' => $value]; }
	}
	return $result;
}

/** Cache repeated storage labels only within this export and its access context. */
function tadlFindingAidLocations($request, $object, &$cache) {
	if (!$object->isReadable($request, 'ca_storage_locations')) { return ''; }
	$ids = [];
	if ($object->hasField('home_location_id') && $object->isReadable($request, 'home_location_id')) {
		$home = (int)$object->get('home_location_id');
		if ($home > 0) { $ids[$home] = $home; }
	}
	$related = (array)$object->getRelatedItems('ca_storage_locations', [
		'idsOnly' => true, 'checkAccess' => caGetUserAccessValues($request), 'limit' => PHP_INT_MAX
	]);
	// Prefer the home location when readable; unrelated historical locations do
	// not override it. If it is unavailable, show the accessible recorded relations.
	$groups = $ids ? [$ids, $related] : [$related];
	foreach ($groups as $group) {
		$labels = [];
		foreach (array_unique(array_map('intval', $group)) as $id) {
			if ($id < 1) { continue; }
			if (!array_key_exists($id, $cache)) {
				$location = Datamodel::getInstance('ca_storage_locations', true);
				$cache[$id] = '';
				if ($location && $location->load($id) && tadlFindingAidReadable($request, $location)) {
					$name = tadlFindingAidValue($request, $location, ['ca_storage_locations.preferred_labels.name']);
					$identifier = tadlFindingAidValue($request, $location, ['ca_storage_locations.idno']);
					if ($name !== '') { $cache[$id] = $name.($identifier !== '' ? ' ('.$identifier.')' : ''); }
				}
			}
			if ($cache[$id] !== '') { $labels[$id] = $cache[$id]; }
		}
		if ($labels) { natcasesort($labels); return implode('; ', $labels); }
	}
	return '';
}

/** Selected collection and readable descendants; unique objects, never a media/page filter. */
function tadlFindingAidData($request, $collection, $config) {
	if (!tadlFindingAidReadable($request, $collection)) { return null; }
	$root_id = (int)$collection->getPrimaryKey();
	$title = tadlFindingAidValue($request, $collection, ['ca_collections.preferred_labels.name']) ?: _t('Collection');
	$data = [
		'title' => $title, 'identifier' => tadlFindingAidValue($request, $collection, ['ca_collections.idno']),
		'fields' => tadlFindingAidFields($request, $collection, $config->getAssoc('collection_fields')),
		'collections' => [], 'objects' => [], 'generated' => gmdate('Y-m-d H:i').' UTC'
	];
	$pending = [[$root_id, [$title]]]; $visited = []; $memberships = [];
	while ($pending) {
		[$id, $path] = array_pop($pending);
		if (isset($visited[$id])) { continue; }
		$visited[$id] = true;
		$node = $id === $root_id ? $collection : Datamodel::getInstance('ca_collections', true);
		if (!$node || ($id !== $root_id && !$node->load($id)) || !tadlFindingAidReadable($request, $node)) { continue; }
		if ($id !== $root_id) { $path[] = tadlFindingAidValue($request, $node, ['ca_collections.preferred_labels.name']) ?: _t('Collection'); }
		$data['collections'][$id] = ['path' => implode(' > ', $path), 'count' => 0];
		if ($node->isReadable($request, 'ca_objects')) {
			// Native related-item APIs have a default cap. Explicitly remove it:
			// an inventory must include every linked record, not just the first page.
			foreach ((array)$node->getRelatedItems('ca_objects', [
				'idsOnly' => true, 'checkAccess' => caGetUserAccessValues($request), 'limit' => PHP_INT_MAX
			]) as $object_id) {
				$object_id = (int)$object_id;
				if ($object_id > 0) { $memberships[$object_id][$id] = $data['collections'][$id]['path']; }
			}
		}
		if ($node->isReadable($request, 'hierarchy')) {
			foreach ((array)$node->getHierarchyChildren($id, ['idsOnly' => true]) as $child_id) {
				if ((int)$child_id > 0) { $pending[] = [(int)$child_id, $path]; }
			}
		}
	}
	$location_cache = [];
	foreach ($memberships as $id => $paths) {
		$object = Datamodel::getInstance('ca_objects', true);
		if (!$object || !$object->load($id) || !tadlFindingAidReadable($request, $object)) { continue; }
		$fields = tadlFindingAidFields($request, $object, $config->getAssoc('inventory_fields'));
		$location = $config->get('include_storage_locations') ? tadlFindingAidLocations($request, $object, $location_cache) : '';
		if ($location !== '') { $fields['location'] = ['label' => _t('Recorded storage location'), 'value' => $location]; }
		natcasesort($paths);
		$data['objects'][] = [
			'id' => $id, 'title' => tadlFindingAidValue($request, $object, ['ca_objects.preferred_labels.name']) ?: _t('Object'),
			'fields' => $fields, 'collections' => array_values($paths)
		];
		foreach (array_keys($paths) as $collection_id) { $data['collections'][$collection_id]['count']++; }
	}
	usort($data['objects'], static function ($a, $b) {
		$a_identifier = $a['fields']['identifier']['value'] ?? '';
		$b_identifier = $b['fields']['identifier']['value'] ?? '';
		return ($a_identifier === '') <=> ($b_identifier === '')
			?: strnatcasecmp($a_identifier, $b_identifier)
			?: strnatcasecmp($a['title'], $b['title']) ?: ($a['id'] <=> $b['id']);
	});
	uasort($data['collections'], static function ($a, $b) { return strnatcasecmp($a['path'], $b['path']); });
	return $data;
}

/** Pawtucket supplies Dompdf; no remote resources or embedded PHP/JS are allowed. */
function tadlFindingAidPDF($html) {
	if (!class_exists('Dompdf\\Dompdf') || !class_exists('Dompdf\\Options')) { return null; }
	$options = new \Dompdf\Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false]);
	$pdf = new \Dompdf\Dompdf($options);
	$pdf->setPaper('letter', 'portrait');
	$pdf->loadHtml($html, 'UTF-8');
	$pdf->render();
	$canvas = $pdf->getCanvas();
	$canvas->page_text(480, 753, '{PAGE_NUM} / {PAGE_COUNT}', $pdf->getFontMetrics()->getFont('DejaVu Sans'), 8, [0.35, 0.35, 0.35]);
	$bytes = $pdf->output();
	return is_string($bytes) && strlen($bytes) > 1024 && strncmp($bytes, '%PDF-', 5) === 0
		&& substr(rtrim($bytes), -5) === '%%EOF' ? $bytes : null;
}
