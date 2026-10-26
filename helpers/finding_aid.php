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
	$value = str_replace(["\r\n", "\r"], "\n", $value);
	$value = preg_replace('/[\p{Z}\t]+/u', ' ', $value);
	// Empty editor blocks and source formatting must not become stacked blank lines.
	$value = trim(preg_replace(['/ *\n */', '/\n{3,}/'], ["\n", "\n\n"], $value));
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

/** Intrinsic rows are fetched together; type-specific metadata applicability is retained. */
function tadlFindingAidBatchRows($model, array $ids) {
	$rows = [];
	$type_field = $model->getTypeFieldName();
	foreach (array_chunk($ids, 500) as $chunk) {
		$rows += (array)$model->getFieldValuesForIDs($chunk, [$type_field, 'rank']);
	}
	return $rows;
}

/** Fresh presence only: avoid expanding empty attributes for every archival unit. */
function tadlFindingAidBatchAttributeRows($request, $model, array $rows, array $bundles) {
	$roots = []; $table = $model->tableName(); $type_field = $model->getTypeFieldName();
	foreach ($rows as &$row) { $row['_finding_aid_attributes'] = []; }
	unset($row);
	foreach ($bundles as $bundle) {
		if (!preg_match('/\A'.preg_quote($table, '/').'\.([a-zA-Z0-9_]+)(?:\.[a-zA-Z0-9_]+)*\z/', (string)$bundle, $match)) { continue; }
		$root = $match[1];
		if (isset($roots[$root]) || $root === 'preferred_labels' || $model->hasField($root)
			|| $request->user->getBundleAccessLevel($table, $root) < __CA_BUNDLE_ACCESS_READONLY__) { continue; }
		foreach (array_unique(array_column($rows, $type_field)) as $type_id) {
			if ($model->hasElement($root, (int)$type_id) && ($element_id = (int)ca_metadata_elements::getElementID($root)) > 0) {
				$roots[$root] = $element_id; break;
			}
		}
	}
	$codes = [];
	foreach ($roots as $root => $element_id) { $codes[$element_id][] = $root; }
	foreach (array_chunk(array_keys($rows), 500) as $ids) {
		foreach (array_chunk(array_keys($codes), 500) as $elements) {
			$result = $model->getDb()->query('SELECT DISTINCT row_id, element_id FROM ca_attributes WHERE table_num = ? AND row_id IN (?) AND element_id IN (?)', [$model->tableNum(), $ids, $elements]);
			if (!$result) { throw new RuntimeException('Unable to read finding aid metadata presence.'); }
			while ($result->nextRow()) {
				$id = (int)$result->get('row_id'); $element = (int)$result->get('element_id');
				if (!isset($rows[$id], $codes[$element])) { continue; }
				foreach ($codes[$element] as $root) { $rows[$id]['_finding_aid_attributes'][$root] = true; }
			}
		}
	}
	return $rows;
}

/** Native multi-record templates retain formatting without one template query per unit. */
function tadlFindingAidBatchValue($request, $model, array $rows, $bundles, $display_language_labels = false) {
	$values = [];
	$table = $model->tableName();
	$type_field = $model->getTypeFieldName();
	foreach ((array)$bundles as $bundle) {
		if (!preg_match('/\A'.preg_quote($table, '/').'\.([a-zA-Z0-9_]+)(?:\.[a-zA-Z0-9_]+)*\z/', (string)$bundle, $match)) { continue; }
		$root = $match[1];
		if ($request->user->getBundleAccessLevel($table, $root) < __CA_BUNDLE_ACCESS_READONLY__) { continue; }
		$ids = []; $applicable = [];
		foreach ($rows as $id => $row) {
			if (isset($values[$id])) { continue; }
			if ($root !== 'preferred_labels' && !$model->hasField($root)
				&& isset($row['_finding_aid_attributes']) && !isset($row['_finding_aid_attributes'][$root])) { continue; }
			$type_id = (int)($row[$type_field] ?? 0);
			if (!array_key_exists($type_id, $applicable)) {
				$applicable[$type_id] = $root === 'preferred_labels' || $model->hasField($root) || $model->hasElement($root, $type_id);
			}
			if ($applicable[$type_id]) { $ids[] = (int)$id; }
		}
		$access = caGetUserAccessValues($request);
		if ($display_language_labels && $bundle === 'ca_collections.language'
			&& (int)ca_metadata_elements::getElementDatatype($root) === 3) { $access = []; }
		foreach (array_chunk($ids, 500) as $chunk) {
			$result = caProcessTemplateForIDs('^'.$bundle, $table, $chunk, [
				'checkAccess' => $access, 'convertCodesToDisplayText' => true,
				'makeLink' => false, 'delimiter' => "\n", 'dontReturnDefault' => true,
				'returnAsArray' => true, 'indexWithIDs' => true, 'includeBlankValuesInTopLevelForPrefetch' => true
			]);
			if (!is_array($result)) { throw new RuntimeException('Unable to read finding aid metadata.'); }
			foreach ($chunk as $id) {
				$value = tadlFindingAidText($result[$id] ?? '');
				if ($value !== '') { $values[$id] = $value; }
			}
		}
	}
	return $values;
}

/** Batch creator memberships, but independently validate each creator and displayed label. */
function tadlFindingAidBatchRelatedValue($request, $model, array $rows, array $field) {
	if (($field['related_table'] ?? '') !== 'ca_entities'
		|| $request->user->getBundleAccessLevel($model->tableName(), 'ca_entities') < __CA_BUNDLE_ACCESS_READONLY__) { return []; }
	$types = caMakeRelationshipTypeIDList('ca_entities_x_collections', (array)($field['relationship_types'] ?? []));
	if (!$types) { return []; }
	$owners = []; $ids = [];
	foreach (array_chunk(array_keys($rows), 500) as $chunk) {
		$relationships = $model->getRelatedItems('ca_entities', [
			'row_ids' => $chunk, 'checkAccess' => caGetUserAccessValues($request), 'user_id' => $request->getUserID(),
			'restrictToRelationshipTypes' => $types, 'limit' => PHP_INT_MAX
		]);
		foreach ((array)$relationships as $relationship) {
			$owner = (int)($relationship['row_id'] ?? 0); $id = (int)($relationship['entity_id'] ?? 0);
			if (!isset($rows[$owner]) || $id < 1) { continue; }
			$owners[$owner][$id] = $id; $ids[$id] = $id;
		}
	}
	$ids = tadlReadableIDs($request, 'ca_entities', array_values($ids), (array)caGetUserAccessValues($request));
	$entity = Datamodel::getInstance('ca_entities', false);
	if (!$ids || !$entity) { return []; }
	$entity_rows = tadlFindingAidBatchAttributeRows($request, $entity, tadlFindingAidBatchRows($entity, $ids), (array)($field['bundles'] ?? []));
	$names = tadlFindingAidBatchValue($request, $entity, $entity_rows, $field['bundles'] ?? []);
	$result = [];
	foreach ($owners as $owner => $entity_ids) {
		$values = [];
		foreach ($entity_ids as $id) { if (isset($names[$id])) { $values[] = $names[$id]; } }
		if ($values) { $result[$owner] = join("\n", $values); }
	}
	return $result;
}

/** Build every descriptive level using bounded hierarchy and metadata reads. */
function tadlFindingAidBatchData($request, $collection, $config) {
	$parents = tadlCollectionContentsRows($request, $collection);
	if (!$parents) { return null; }
	$root_id = (int)$collection->getPrimaryKey();
	$model = Datamodel::getInstance('ca_collections', false);
	$rows = tadlFindingAidBatchRows($model, array_keys($parents));
	if (!isset($rows[$root_id])) { return null; }
	$configured_fields = (array)$config->getAssoc('collection_fields'); $bundles = [];
	foreach ($configured_fields as $field) {
		if (is_array($field) && !isset($field['related_table'])) { $bundles = array_merge($bundles, (array)($field['bundles'] ?? [])); }
	}
	$rows = tadlFindingAidBatchAttributeRows($request, $model, $rows, $bundles);
	$titles = tadlFindingAidBatchValue($request, $model, $rows, ['ca_collections.preferred_labels.name']);
	$identifiers = tadlFindingAidBatchValue($request, $model, $rows, ['ca_collections.idno']);
	$fields = [];
	foreach ($configured_fields as $key => $field) {
		if (!is_array($field)) { continue; }
		$values = isset($field['related_table']) ? tadlFindingAidBatchRelatedValue($request, $model, $rows, $field)
			: tadlFindingAidBatchValue($request, $model, $rows, $field['bundles'] ?? [], (int)($field['display_language_labels'] ?? 0) === 1);
		foreach ($values as $id => $value) { $fields[$id][$key] = ['label' => (string)($field['label'] ?? $key), 'value' => $value]; }
	}
	$data = [
		'title' => $titles[$root_id] ?? _t('Untitled archival unit'), 'identifier' => $identifiers[$root_id] ?? '',
		'fields' => $fields[$root_id] ?? [], 'collections' => [], 'generated' => gmdate('Y-m-d H:i').' UTC'
	];
	$rank_readable = $request->user->getBundleAccessLevel('ca_collections', 'rank') >= __CA_BUNDLE_ACCESS_READONLY__;
	$children = [];
	foreach ($parents as $id => $parent_id) {
		if (!isset($rows[$id])) { continue; }
		$parent_id = $id === $root_id ? null : $parent_id;
		// A unit deleted between traversal and metadata reads also prunes its branch.
		if ($parent_id !== null && !isset($data['collections'][$parent_id])) { continue; }
		$data['collections'][$id] = [
			'title' => $titles[$id] ?? _t('Untitled archival unit'), 'identifier' => $identifiers[$id] ?? '',
			'fields' => $id === $root_id ? [] : ($fields[$id] ?? []), 'parent_id' => $parent_id,
			'depth' => $parent_id === null ? 0 : ($data['collections'][$parent_id]['depth'] + 1),
			'rank' => $rank_readable ? (int)($rows[$id]['rank'] ?? 0) : 0
		];
		if ($parent_id !== null) { $children[$parent_id][] = $id; }
	}
	return tadlFindingAidOrderData($data, $children, $root_id, $config);
}

/** Descriptive metadata for the selected collection and every readable descendant; no object queries. */
function tadlFindingAidData($request, $collection, $config) {
	if (!tadlFindingAidReadable($request, $collection)) { return null; }
	if (function_exists('caProcessTemplateForIDs') && method_exists($collection, 'getFieldValuesForIDs')) {
		return tadlFindingAidBatchData($request, $collection, $config);
	}
	return tadlFindingAidSingleData($request, $collection, $config);
}

/** Compatibility path for native runtimes without the multi-record template API. */
function tadlFindingAidSingleData($request, $collection, $config) {
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
	return tadlFindingAidOrderData($data, $children, $root_id, $config);
}

function tadlFindingAidOrderData(array $data, array $children, $root_id, $config) {
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
 $key = hash('sha256', serialize(['schema' => 2, 'renderer' => $rendererKey, 'description' => $data]));
 $valid = static fn($bytes) => is_string($bytes) && strlen($bytes) > 1024 && str_starts_with($bytes, '%PDF-') && str_ends_with(rtrim($bytes), '%%EOF');
 $max_bytes = 8 * 1024 * 1024;
 $enabled = $timeout > 0 && class_exists('ExternalCache');
 if ($enabled) {
  try {
   $entry = ExternalCache::fetch($key, 'tadlFindingAidPDF');
   if (is_array($entry) && ($entry['schema'] ?? null) === 2 && is_string($entry['pdf'] ?? null)
     && strlen($entry['pdf']) <= 4 * intdiv($max_bytes + 2, 3)) {
    $bytes = base64_decode($entry['pdf'], true);
    if ($valid($bytes) && strlen($bytes) <= $max_bytes) { return $bytes; }
   }
  }
  catch (Throwable $error) { /* Cache availability must not prevent document generation. */ }
 }
 $bytes = $generate();
 if (!$valid($bytes)) { return null; }
 if ($enabled && strlen($bytes) <= $max_bytes) {
  // Native file caches JSON-encode values; raw PDF bytes are not valid UTF-8.
  try { ExternalCache::save($key, ['schema' => 2, 'pdf' => base64_encode($bytes)], 'tadlFindingAidPDF', min(86400, (int)$timeout)); }
  catch (Throwable $error) { /* The validated response can still be served. */ }
 }
 return $bytes;
}
