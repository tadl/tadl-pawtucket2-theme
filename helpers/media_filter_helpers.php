<?php

/**
 * Return eligible public records in their existing result order. A representation
 * must have a usable original or native embed; metadata-only records and icons do not
 * make an item eligible. Collections qualify through objects in the collection
 * itself or in accessible descendant collections.
 */
function tadlMediaEligibleIDs($table, array $candidateIDs, array $accessValues) {
	static $eligibilityCache = [];
	if (!in_array($table, ['ca_objects', 'ca_collections'], true)) {
		return [];
	}

	$ids = [];
	foreach ($candidateIDs as $id) {
		if ((is_int($id) || (is_string($id) && ctype_digit($id))) && ((int)$id > 0)) {
			$ids[(int)$id] = (int)$id;
		}
	}
	$access = [];
	foreach ($accessValues as $value) {
		if ((is_int($value) || (is_string($value) && ctype_digit($value))) && ((int)$value >= 0)) {
			$access[(int)$value] = (int)$value;
		}
	}
	if (!$ids || !$access) {
		return [];
	}
	$access = array_values($access);
	sort($access, SORT_NUMERIC);
	$cacheKey = $table.':'.join(',', $access);
	if (!isset($eligibilityCache[$cacheKey])) {
		$eligibilityCache[$cacheKey] = [];
	}
	// This cache lasts only for the request and is scoped by the access mask.
	$uncachedIDs = array_filter($ids, function ($id) use ($eligibilityCache, $cacheKey) {
		return !array_key_exists($id, $eligibilityCache[$cacheKey]);
	});
	$db = Datamodel::getInstanceByTableName($table, true)->getDb();
	$eligible = [];
	$representationHasMedia = [];
	$mediaURL = null;

	foreach (array_chunk(array_values($uncachedIDs), 500) as $chunk) {
		if ($table === 'ca_objects') {
			$sql = "
				SELECT DISTINCT o.object_id candidate_id, r.representation_id, r.media
				FROM ca_objects o
				INNER JOIN ca_objects_x_object_representations oxr ON oxr.object_id = o.object_id
				INNER JOIN ca_object_representations r ON r.representation_id = oxr.representation_id
				WHERE o.object_id IN (?)
					AND o.deleted = 0 AND o.access IN (?)
					AND r.deleted = 0 AND r.access IN (?)
					AND r.media IS NOT NULL AND r.media <> ''
			";
			$params = [$chunk, $access, $access];
		} else {
			// Bounds alone are insufficient: separate collection hierarchies can
			// share the same bounds. Include self even before its bounds are set.
			$collectionJoin = "
				INNER JOIN ca_collections c ON c.collection_id = p.collection_id OR (
					c.hier_collection_id = p.hier_collection_id
					AND p.hier_collection_id > 0
					AND p.hier_left < p.hier_right
					AND c.hier_left < c.hier_right
					AND c.hier_left >= p.hier_left
					AND c.hier_right <= p.hier_right
				)
			";
			$sql = "
				SELECT DISTINCT p.collection_id candidate_id, r.representation_id
				FROM ca_collections p
				{$collectionJoin}
				INNER JOIN ca_objects_x_collections oc ON oc.collection_id = c.collection_id
				INNER JOIN ca_objects o ON o.object_id = oc.object_id
				INNER JOIN ca_objects_x_object_representations oxr ON oxr.object_id = o.object_id
				INNER JOIN ca_object_representations r ON r.representation_id = oxr.representation_id
				WHERE p.collection_id IN (?)
					AND p.deleted = 0 AND p.access IN (?)
					AND c.deleted = 0 AND c.access IN (?)
					AND o.deleted = 0 AND o.access IN (?)
					AND r.deleted = 0 AND r.access IN (?)
					AND r.media IS NOT NULL AND r.media <> ''
			";
			$params = [$chunk, $access, $access, $access, $access];
		}

		$result = $db->query($sql, $params);
		if (!$result) {
			throw new RuntimeException('Unable to check media availability.');
		}
		if ($table === 'ca_collections') {
			$eligible += tadlCollectionMediaCandidates($db, $result, $access, $representationHasMedia);
		} else {
			while ($result->nextRow()) {
				$id = (int)$result->get('candidate_id');
				if (isset($eligible[$id])) { continue; }
				$representationID = (int)$result->get('representation_id');
				if (!isset($representationHasMedia[$representationID])) {
					$representationHasMedia[$representationID] = tadlRepresentationHasUsableMedia($result, $mediaURL);
				}
				if ($representationHasMedia[$representationID]) { $eligible[$id] = true; }
			}
		}
		foreach ($chunk as $id) {
			$eligibilityCache[$cacheKey][$id] = isset($eligible[$id]);
		}
	}

	return array_values(array_filter($ids, function ($id) use ($eligibilityCache, $cacheKey) {
		return $eligibilityCache[$cacheKey][$id];
	}));
}

/** Use native media decoding for originals and supported embeds, including legacy descriptors. */
function tadlRepresentationHasUsableMedia($result, &$mediaURL) {
	$original = $result->getMediaInfo('media', 'original');
	$hasMedia = $result->hasMedia('media');
	if ($hasMedia && is_array($original)
		&& (!empty($original['FILENAME']) || !empty($original['EXTERNAL_URL']))
		&& (bool)$result->getMediaUrl('media', 'original')) { return true; }
	if ($hasMedia) {
		$mediaInfo = $result->getMediaInfo('media');
		$source = $mediaInfo['INPUT']['FETCHED_FROM'] ?? null;
		if (!empty($mediaInfo['IS_EMBEDDED']) && is_string($source) && strlen(trim($source))) {
			if (!$mediaURL) {
				if (!class_exists('\\CA\\MediaUrl')) { require_once(__CA_LIB_DIR__.'/MediaUrl.php'); }
				$mediaURL = new \CA\MediaUrl();
			}
			return (bool)$mediaURL->embedTag($source);
		}
	}
	return false;
}

/**
 * The hierarchy query returns only ID pairs, not thousands of repeated media
 * blobs. Fetch one untested descriptor per unresolved collection at a time and
 * stop as soon as it qualifies. Shared representations are decoded only once.
 */
function tadlCollectionMediaCandidates($db, $relationships, array $access, array &$hasMedia) {
	$pending = []; $positions = []; $eligible = []; $mediaURL = null;
	while ($relationships->nextRow()) {
		$id = (int)$relationships->get('candidate_id');
		$pending[$id][] = (int)$relationships->get('representation_id');
		$positions[$id] = 0;
	}
	while ($pending) {
		$fetch = [];
		foreach ($pending as $id => $representationIDs) {
			while (isset($representationIDs[$positions[$id]])) {
				$representationID = $representationIDs[$positions[$id]];
				if (!array_key_exists($representationID, $hasMedia)) {
					$fetch[$representationID] = $representationID;
					break;
				}
				if ($hasMedia[$representationID]) { $eligible[$id] = true; break; }
				$positions[$id]++;
			}
			if (isset($eligible[$id]) || !isset($representationIDs[$positions[$id]])) { unset($pending[$id]); }
		}
		if (!$fetch) { break; }
		$result = $db->query("SELECT representation_id, media FROM ca_object_representations
			WHERE representation_id IN (?) AND deleted = 0 AND access IN (?)", [array_values($fetch), $access]);
		if (!$result) { throw new RuntimeException('Unable to check collection media availability.'); }
		foreach ($fetch as $representationID) { $hasMedia[$representationID] = false; }
		while ($result->nextRow()) {
			$hasMedia[(int)$result->get('representation_id')] = tadlRepresentationHasUsableMedia($result, $mediaURL);
		}
	}
	return $eligible;
}

/**
 * Mutate the original result so callers retaining it also receive filtered IDs.
 * Native browse caches remain untouched; this runs before theme pagination.
 */
function tadlFilterMediaResult($request, $result) {
	if (!($result instanceof SearchResult) || (tadlMediaPreference($request) !== 'only')) {
		return $result;
	}
	$table = $result->tableName();
	if (!in_array($table, ['ca_objects', 'ca_collections'], true)) {
		return $result;
	}

	$ids = tadlMediaEligibleIDs($table, $result->getPrimaryKeyValues(), (array)caGetUserAccessValues($request));
	if (!class_exists('WLPlugSearchEngineBrowseEngine')) {
		require_once(__CA_LIB_DIR__.'/Browse/BrowseResult.php');
	}
	$result->init(new WLPlugSearchEngineBrowseEngine($ids, [], $result->tableNum()), [], ['db' => $result->getDb()]);
	return $result;
}

/** Keep catalogue facet choices, without displaying unfiltered hit counts. */
function tadlMediaFacetItems($request, $items, $info) {
	if (!is_array($items) || (tadlMediaPreference($request) !== 'only')) {
		return $items;
	}
	$subjectTable = $info['tadl_subject_table'] ?? null;
	if (!$subjectTable) {
		$browseType = $request->getParameter('browseType', pString) ?: $request->getAction();
		$browseInfo = caGetInfoForBrowseType($browseType);
		$subjectTable = $browseInfo['table'] ?? null;
	}
	if ($subjectTable && !in_array($subjectTable, ['ca_objects', 'ca_collections'], true)) {
		return $items;
	}
	$table = $info['table'] ?? (in_array($info['type'] ?? null, ['label', 'hierarchy'], true) ? $subjectTable : null);
	$restrict = in_array($table, ['ca_objects', 'ca_collections'], true)
		&& in_array($info['type'] ?? null, ['authority', 'label', 'hierarchy'], true);
	$ids = [];
	foreach ($items as $key => &$item) {
		if (!is_array($item)) { continue; }
		unset($item['content_count']);
		$id = $item['id'] ?? $item['item_id'] ?? $key;
		if ($restrict && (is_int($id) || (is_string($id) && ctype_digit($id)))) {
			$ids[$key] = (int)$id;
		}
	}
	unset($item);
	if (!$restrict || !$ids) { return $items; }
	$eligible = array_fill_keys(tadlMediaEligibleIDs($table, array_values($ids), (array)caGetUserAccessValues($request)), true);
	foreach ($ids as $key => $id) {
		if (!isset($eligible[$id])) { unset($items[$key]); }
	}
	return $items;
}

/** Hierarchy endpoints do not pass facet configuration into their views. */
function tadlMediaHierarchyFacetInfo($request, $browseType, $facetName) {
	$browseInfo = ($browseType === 'caLightbox') ? ['table' => 'ca_objects'] : caGetInfoForBrowseType($browseType);
	if (!is_array($browseInfo) || empty($browseInfo['table'])) { return []; }
	$browse = caGetBrowseInstance($browseInfo['table']);
	if (!$browse) { return []; }
	$key = $request->getParameter('key', pString);
	if ($key && ($key !== 'null')) { $browse->reload($key); }
	$info = $browse->getInfoForFacet($facetName);
	if (!is_array($info)) { return []; }
	$info['tadl_subject_table'] = $browseInfo['table'];
	if (in_array($info['type'] ?? null, ['label', 'hierarchy'], true) && empty($info['table'])) {
		$info['table'] = $browseInfo['table'];
	}
	return $info;
}
