<?php
require_once(__DIR__.'/collection_contents.php');

/** Public finding aids never bypass native record, type, source or bundle access. */
function tadlFindingAidReadable($request, $record) {
	$access = array_map('intval', (array)caGetUserAccessValues($request));
	return $record && $record->getPrimaryKey() && !$record->get('deleted')
		&& in_array((int)$record->get('access'), $access, true)
		&& $record->isReadable($request)
		&& (!caACLIsEnabled($record, ['forPawtucket' => true])
			|| $record->checkACLAccessForUser($request->user) >= __CA_ACL_READONLY_ACCESS__);
}

/** The public root may sit below private administrative grouping records. */
function tadlFindingAidRoot($request, $collection) {
	$ancestry = tadlCollectionContentsAncestry($request, $collection);
	return $ancestry ? reset($ancestry) : null;
}

/** Catalog text becomes escaped text in the PDF, never executable HTML. */
function tadlFindingAidText($value) {
	$value = preg_replace('~<(script|style)\b[^>]*>.*?</\1\s*>~is', '', (string)$value);
	$value = preg_replace('~<br\s*/?>|</(?:p|div|li|h[1-6]|tr|section)>~i', "\n", $value);
	$value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$value = trim(preg_replace('/[\p{Z}\t]+/u', ' ', $value));
	return preg_match('/[\p{L}\p{N}]/u', $value) ? $value : '';
}

/** Only read a configured, existing field when its root bundle is readable. */
function tadlFindingAidValue($request, $record, $bundles, $display_language_labels = false) {
	foreach ((array)$bundles as $bundle) {
		if (!preg_match('/\A'.preg_quote($record->tableName(), '/').'\.([a-zA-Z0-9_]+)(?:\.[a-zA-Z0-9_]+)*\z/', (string)$bundle, $match)) { continue; }
		$root = $match[1];
		if ($root !== 'preferred_labels' && !$record->hasField($root) && !$record->hasElement($root)) { continue; }
		if (!$record->isReadable($request, $root)) { continue; }
		$access = caGetUserAccessValues($request);
		// Match the collection detail's language labels. Only this exact List
		// attribute may display selected terms irrespective of term access; the
		// collection and language bundle still require their normal permissions.
		if ($display_language_labels && $bundle === 'ca_collections.language'
			&& (int)ca_metadata_elements::getElementDatatype($root) === 3) { $access = []; }
		$value = tadlFindingAidText($record->getWithTemplate('^'.$bundle, [
			'checkAccess' => $access, 'convertCodesToDisplayText' => true,
			'makeLink' => false, 'delimiter' => "\n", 'dontReturnDefault' => true
		]));
		if ($value !== '') { return $value; }
	}
	return '';
}

/** Creator names need their own record, label and ACL checks, not just a readable relationship. */
function tadlFindingAidRelatedValue($request, $record, $field) {
	if (($field['related_table'] ?? '') !== 'ca_entities' || !$record->isReadable($request, 'ca_entities')) { return ''; }
	$types = caMakeRelationshipTypeIDList('ca_entities_x_collections', (array)($field['relationship_types'] ?? []));
	if (!$types) { return ''; }
	$values = [];
	$ids = $record->getRelatedItems('ca_entities', [
		'idsOnly' => true, 'checkAccess' => caGetUserAccessValues($request), 'user_id' => $request->getUserID(),
		'restrictToRelationshipTypes' => $types, 'limit' => PHP_INT_MAX
	]);
	foreach (array_unique(array_map('intval', (array)$ids)) as $id) {
		if ($id < 1) { continue; }
		$entity = Datamodel::getInstance('ca_entities', false);
		if (!$entity || !$entity->load($id) || !tadlFindingAidReadable($request, $entity)) { continue; }
		$value = tadlFindingAidValue($request, $entity, $field['bundles'] ?? []);
		if ($value !== '') { $values[] = $value; }
	}
	return join("\n", $values);
}

function tadlFindingAidFields($request, $record, $fields) {
	$result = [];
	foreach ((array)$fields as $key => $field) {
		if (!is_array($field)) { continue; }
		$value = isset($field['related_table']) ? tadlFindingAidRelatedValue($request, $record, $field)
			: tadlFindingAidValue($request, $record, $field['bundles'] ?? [], (int)($field['display_language_labels'] ?? 0) === 1);
		if ($value !== '') { $result[$key] = ['label' => (string)($field['label'] ?? $key), 'value' => $value]; }
	}
	return $result;
}

/** Descriptive metadata for the selected collection and every readable descendant; no object queries. */
function tadlFindingAidData($request, $collection, $config) {
	if (!tadlFindingAidReadable($request, $collection)) { return null; }
	$root_id = (int)$collection->getPrimaryKey();
	$title = tadlFindingAidValue($request, $collection, ['ca_collections.preferred_labels.name']) ?: _t('Untitled archival unit');
	$fields = $config->getAssoc('collection_fields');
	$data = [
		'title' => $title, 'identifier' => tadlFindingAidValue($request, $collection, ['ca_collections.idno']),
		'fields' => tadlFindingAidFields($request, $collection, $fields),
		'collections' => [], 'generated' => gmdate('Y-m-d H:i').' UTC'
	];
	$pending = [[$root_id, null, 0]]; $visited = []; $children = [];
	while ($pending) {
		[$id, $parent_id, $depth] = array_pop($pending);
		if (isset($visited[$id])) { continue; }
		$visited[$id] = true;
		$node = $id === $root_id ? $collection : Datamodel::getInstance('ca_collections', false);
		if (!$node || ($id !== $root_id && !$node->load($id)) || !tadlFindingAidReadable($request, $node)) { continue; }
		$data['collections'][$id] = [
			'title' => $id === $root_id ? $title : (tadlFindingAidValue($request, $node, ['ca_collections.preferred_labels.name']) ?: _t('Untitled archival unit')),
			'identifier' => $id === $root_id ? $data['identifier'] : tadlFindingAidValue($request, $node, ['ca_collections.idno']),
			// The selected root's metadata is already included in the overview.
			'fields' => $id === $root_id ? [] : tadlFindingAidFields($request, $node, $fields),
			'parent_id' => $parent_id, 'depth' => $depth,
			'rank' => $node->isReadable($request, 'rank') ? (int)$node->get('rank') : 0
		];
		if ($parent_id !== null) { $children[$parent_id][] = $id; }
		if ($node->isReadable($request, 'hierarchy')) {
			foreach ((array)$node->getHierarchyChildren($id, ['idsOnly' => true]) as $child_id) {
				if ((int)$child_id > 0) { $pending[] = [(int)$child_id, $id, $depth + 1]; }
			}
		}
	}
	// Respect recorded arrangement, with natural names as a tie-breaker. The
	// complete branch stays together even when different siblings share a title.
	$sort_by_name = $config->get('hierarchy_sort') === 'name';
	foreach ($children as &$siblings) {
		usort($siblings, static function ($a, $b) use ($data, $sort_by_name) {
			return (!$sort_by_name ? ($data['collections'][$a]['rank'] <=> $data['collections'][$b]['rank']) : 0)
				?: strnatcasecmp($data['collections'][$a]['title'], $data['collections'][$b]['title']) ?: ($a <=> $b);
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

/** Cache only immutable PDF bytes after rebuilding the current access-filtered description. */
function tadlFindingAidCachedPDF(array $data, $rendererKey, callable $generate, $timeout = 86400) {
 unset($data['generated']); // A reused PDF retains its actual generation timestamp.
 $key = hash('sha256', serialize(['schema' => 1, 'renderer' => $rendererKey, 'description' => $data]));
 $valid = static fn($bytes) => is_string($bytes) && strlen($bytes) > 1024 && str_starts_with($bytes, '%PDF-') && str_ends_with(rtrim($bytes), '%%EOF');
 $enabled = $timeout > 0 && class_exists('ExternalCache');
 if ($enabled) {
  try { $bytes = ExternalCache::fetch($key, 'tadlFindingAidPDF'); if ($valid($bytes)) { return $bytes; } }
  catch (Throwable $error) { /* Cache availability must not prevent document generation. */ }
 }
 $bytes = $generate();
 if (!$valid($bytes)) { return null; }
 if ($enabled && strlen($bytes) <= 8 * 1024 * 1024) {
  try { ExternalCache::save($key, $bytes, 'tadlFindingAidPDF', min(86400, (int)$timeout)); }
  catch (Throwable $error) { /* The validated response can still be served. */ }
 }
 return $bytes;
}
