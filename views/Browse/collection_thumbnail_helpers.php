<?php
if (!function_exists('tadlGetDescendantCollectionImages')) {
	/**
	 * Return representative thumbnails for collection records using media attached
	 * to objects in descendant collection hierarchy nodes.
	 */
	function tadlGetDescendantCollectionImages($pa_collection_ids, $pa_options = null) {
		if (!is_array($pa_collection_ids) || !sizeof($pa_collection_ids)) {
			return [];
		}

		$pa_collection_ids = array_values(array_filter(array_map('intval', $pa_collection_ids)));
		if (!sizeof($pa_collection_ids)) {
			return [];
		}

		if (!is_array($pa_options)) {
			$pa_options = [];
		}

		$version = caGetOption('version', $pa_options, 'small');
		$access_values = caGetOption('checkAccess', $pa_options, []);
		$access_sql = '';
		$params = [$pa_collection_ids];

		if (is_array($access_values) && sizeof($access_values)) {
			$access_sql = " AND c.access IN (?) AND o.access IN (?) AND orep.access IN (?)";
			$params[] = $access_values;
			$params[] = $access_values;
			$params[] = $access_values;
		}

		$t_collection = new ca_collections();
		$o_db = $t_collection->getDb();
		$qr_res = $o_db->query("
			SELECT DISTINCT
				p.collection_id parent_collection_id,
				pl.name collection_label,
				orep.media
			FROM ca_collections p
			INNER JOIN ca_collections c ON
				c.hier_collection_id = p.hier_collection_id
				AND c.hier_left > p.hier_left
				AND c.hier_right < p.hier_right
			INNER JOIN ca_objects_x_collections oxc ON oxc.collection_id = c.collection_id
			INNER JOIN ca_objects o ON o.object_id = oxc.object_id
			INNER JOIN ca_objects_x_object_representations oxor ON
				oxor.object_id = o.object_id
				AND oxor.is_primary = 1
			INNER JOIN ca_object_representations orep ON orep.representation_id = oxor.representation_id
			LEFT JOIN ca_collection_labels pl ON
				pl.collection_id = p.collection_id
				AND pl.is_preferred = 1
			WHERE
				p.collection_id IN (?)
				AND p.deleted = 0
				AND c.deleted = 0
				AND o.deleted = 0
				{$access_sql}
			ORDER BY p.collection_id, c.hier_left, o.idno_sort, oxor.rank
		", $params);

		$images = [];
		while ($qr_res->nextRow()) {
			$collection_id = (int)$qr_res->get('parent_collection_id');
			if (isset($images[$collection_id])) {
				continue;
			}

			$alt = trim((string)$qr_res->get('collection_label'));
			$images[$collection_id] = $qr_res->getMediaTag('media', $version, [
				'alt' => $alt ? $alt : _t('Collection image')
			]);
		}

		return $images;
	}
}
