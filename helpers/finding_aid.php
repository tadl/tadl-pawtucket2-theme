<?php
require_once(__DIR__.'/record_access.php');
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

/** Selected collection and readable descendants; unique object counts, never a media/page filter. */
function tadlFindingAidData($request, $collection, $config) {
	if (!tadlFindingAidReadable($request, $collection)) { return null; }
	$root_id = (int)$collection->getPrimaryKey();
	$title = tadlFindingAidValue($request, $collection, ['ca_collections.preferred_labels.name']) ?: _t('Collection');
	$data = [
		'title' => $title, 'identifier' => tadlFindingAidValue($request, $collection, ['ca_collections.idno']),
		'fields' => tadlFindingAidFields($request, $collection, $config->getAssoc('collection_fields')),
		'collections' => [], 'object_count' => 0, 'generated' => gmdate('Y-m-d H:i').' UTC'
	];
	$pending = [[$root_id, null, 0]]; $visited = []; $memberships = []; $children = [];
	while ($pending) {
		[$id, $parent_id, $depth] = array_pop($pending);
		if (isset($visited[$id])) { continue; }
		$visited[$id] = true;
		$node = $id === $root_id ? $collection : Datamodel::getInstance('ca_collections', false);
		if (!$node || ($id !== $root_id && !$node->load($id)) || !tadlFindingAidReadable($request, $node)) { continue; }
		$data['collections'][$id] = [
			'title' => $id === $root_id ? $title : (tadlFindingAidValue($request, $node, ['ca_collections.preferred_labels.name']) ?: _t('Collection')),
			'identifier' => $id === $root_id ? $data['identifier'] : tadlFindingAidValue($request, $node, ['ca_collections.idno']),
			'parent_id' => $parent_id, 'depth' => $depth, 'count' => 0
		];
		if ($parent_id !== null) { $children[$parent_id][] = $id; }
		if ($node->isReadable($request, 'ca_objects')) {
			// Native related-item APIs have a default cap. Explicitly remove it:
			// counts must include every linked record, not just the first page.
			foreach ((array)$node->getRelatedItems('ca_objects', [
				'idsOnly' => true, 'checkAccess' => caGetUserAccessValues($request), 'limit' => PHP_INT_MAX
			]) as $object_id) {
				$object_id = (int)$object_id;
				if ($object_id > 0) { $memberships[$object_id][$id] = true; }
			}
		}
		if ($node->isReadable($request, 'hierarchy')) {
			foreach ((array)$node->getHierarchyChildren($id, ['idsOnly' => true]) as $child_id) {
				if ((int)$child_id > 0) { $pending[] = [(int)$child_id, $id, $depth + 1]; }
			}
		}
	}
	// Count IDs in permission-filtered batches; no object media or metadata is needed.
	foreach (tadlReadableIDs($request, 'ca_objects', array_keys($memberships), (array)caGetUserAccessValues($request)) as $id) {
		$collection_ids = $memberships[$id];
		$data['object_count']++;
		foreach (array_keys($collection_ids) as $collection_id) { $data['collections'][$collection_id]['count']++; }
	}
	// Sort siblings, then walk each whole branch before the next sibling. Sorting
	// flattened label paths can interleave distinct branches with identical names.
	foreach ($children as &$siblings) {
		usort($siblings, static function ($a, $b) use ($data) {
			return strnatcasecmp($data['collections'][$a]['title'], $data['collections'][$b]['title']) ?: ($a <=> $b);
		});
	}
	unset($siblings);
	$ordered = []; $pending = [$root_id];
	while ($pending) {
		$id = array_pop($pending);
		$ordered[$id] = $data['collections'][$id];
		foreach (array_reverse($children[$id] ?? []) as $child_id) { $pending[] = $child_id; }
	}
	$data['collections'] = $ordered;
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
