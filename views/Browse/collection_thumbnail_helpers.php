<?php
require_once(__DIR__.'/../../helpers/thumbnail_focus.php');
if (!function_exists('tadlGetCollectionImages')) {
	/** Native collection media first; fetch object/descendant fallbacks only where needed. */
	function tadlGetCollectionImages($ids, $options = []) {
		$ids = array_values(array_unique(array_filter(array_map('intval', (array)$ids), static fn($id) => $id > 0)));
		if (!$ids) { return []; }
		$version = caGetOption('version', $options, 'small');
		$collection = new ca_collections();
		$images = [];
		foreach ($collection->getPrimaryMediaForIDs($ids, [$version], $options) as $id => $media) {
			if ($tag = $media['tags'][$version] ?? '') { $images[$id] = tadlFocusThumbnail($tag); }
		}
		$missing = array_values(array_diff($ids, array_keys($images)));
		// Avoid the native authority fallback: it renders every linked object image
		// and repeatedly loads the collection record, then keeps just the last tag.
		$images += tadlGetDescendantCollectionImages($missing, array_merge($options, ['directOnly' => true]));
		$missing = array_values(array_diff($ids, array_keys($images)));
		$images += tadlGetDescendantCollectionImages($missing, $options);
		return $images;
	}
}
if (!function_exists('tadlGetDescendantCollectionImages')) {
	/** Return at most one primary object thumbnail per collection, using stable order. */
	function tadlGetDescendantCollectionImages($pa_collection_ids, $pa_options = null) {
		$ids = array_values(array_unique(array_filter(array_map('intval', (array)$pa_collection_ids), static fn($id) => $id > 0)));
		if (!$ids) { return []; }
		$options = is_array($pa_options) ? $pa_options : [];
		$version = caGetOption('version', $options, 'small');
		$access = caGetOption('checkAccess', $options, []);
		$params = [];
		$access_sql = $parent_access_sql = '';
		if (is_array($access) && $access) {
			$access_sql = ' AND c.access IN (?) AND o.access IN (?) AND orep.access IN (?)';
			array_push($params, $access, $access, $access);
			$parent_access_sql = ' AND p.access IN (?)';
		}
		$direct = caGetOption('directOnly', $options, false);
		$scope = $direct ? 'c.collection_id = p.collection_id' : '(c.collection_id = p.collection_id OR (
			c.hier_collection_id = p.hier_collection_id AND p.hier_collection_id > 0
			AND p.hier_left < p.hier_right AND c.hier_left < c.hier_right
			AND c.hier_left >= p.hier_left AND c.hier_right <= p.hier_right))';
		$restrictions = '';
		if ($direct) {
			// Preserve the configured selectors for directly related object media.
			if ($types = caGetOption('relationshipTypes', $options, [])) {
				if ($types = caMakeRelationshipTypeIDList('ca_objects_x_collections', $types)) {
					$restrictions .= ' AND oxc.type_id IN (?)'; $params[] = $types;
				}
			}
			if ($types = caGetOption('objectTypes', $options, [])) {
				if ($types = caMakeTypeIDList('ca_objects', $types)) {
					$restrictions .= ' AND o.type_id IN (?)'; $params[] = $types;
				}
			}
		}
		$params[] = $ids;
		if ($parent_access_sql) { $params[] = $access; }
		$db = (new ca_collections())->getDb();
		// Select the relation ID before fetching its media blob. LIMIT belongs in
		// the correlated lookup, so each card returns one row rather than all images.
		$result = $db->query("SELECT p.collection_id parent_collection_id, pl.name collection_label, r.media
			FROM ca_collections p
			INNER JOIN ca_objects_x_object_representations selected ON selected.relation_id = (
				SELECT oxor.relation_id FROM ca_collections c
				INNER JOIN ca_objects_x_collections oxc ON oxc.collection_id = c.collection_id
				INNER JOIN ca_objects o ON o.object_id = oxc.object_id
				INNER JOIN ca_objects_x_object_representations oxor ON oxor.object_id = o.object_id AND oxor.is_primary = 1
				INNER JOIN ca_object_representations orep ON orep.representation_id = oxor.representation_id
				WHERE {$scope} AND c.deleted = 0 AND o.deleted = 0 AND orep.deleted = 0
					{$access_sql} {$restrictions}
				ORDER BY c.hier_left, o.idno_sort, oxor.rank, oxor.relation_id LIMIT 1
			)
			INNER JOIN ca_object_representations r ON r.representation_id = selected.representation_id
			LEFT JOIN ca_collection_labels pl ON pl.collection_id = p.collection_id AND pl.is_preferred = 1
			WHERE p.collection_id IN (?) AND p.deleted = 0 {$parent_access_sql}", $params);
		if (!$result) { throw new RuntimeException('Unable to load collection thumbnails.'); }
		$images = [];
		while ($result->nextRow()) {
			$id = (int)$result->get('parent_collection_id');
			if (isset($images[$id])) { continue; }
			$alt = trim((string)$result->get('collection_label'));
			if ($tag = $result->getMediaTag('media', $version, ['alt' => $alt ?: _t('Collection image')])) {
				$images[$id] = tadlFocusThumbnail($tag);
			}
		}
		return $images;
	}
}
