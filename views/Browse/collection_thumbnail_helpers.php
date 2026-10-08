<?php
require_once(__DIR__.'/../../helpers/thumbnail_focus.php');
require_once(__DIR__.'/../../helpers/record_access.php');

/** Native collection media first, followed by readable object images. */
if (!function_exists('tadlGetCollectionImages')) {
	function tadlGetCollectionImages($ids, $options = []) {
		$request = $options['request'] ?? ($GLOBALS['g_request'] ?? null);
		$access = (array)($options['checkAccess'] ?? []);
		$roots = tadlReadableIDs($request, 'ca_collections', (array)$ids, $access);
		$fallbackIDs = caGetBundleAccessLevel('ca_collections', 'ca_objects') >= __CA_BUNDLE_ACCESS_READONLY__ ? $roots : [];
		$ids = caGetBundleAccessLevel('ca_collections', 'ca_object_representations') >= __CA_BUNDLE_ACCESS_READONLY__ ? $roots : [];
		$images = []; $version = caGetOption('version', $options, 'small');
		if ($ids) {
			$media = (array)(new ca_collections())->getPrimaryMediaForIDs($ids, [$version], $options);
			$allowed = array_fill_keys(tadlReadableIDs($request, 'ca_object_representations', array_column($media, 'representation_id'), $access, 'media'), true);
			foreach ($media as $id => $item) {
				if (isset($allowed[$item['representation_id'] ?? 0]) && ($tag = $item['tags'][$version] ?? '')) {
					$images[$id] = tadlFocusThumbnail($tag);
				}
			}
		}
		// Collection media bundle restrictions must not hide otherwise readable linked objects.
		$missing = array_diff($fallbackIDs, array_keys($images));
		$images += tadlGetDescendantCollectionImages($missing, array_merge($options, ['directOnly' => true]));
		$images += tadlGetDescendantCollectionImages(array_diff($missing, array_keys($images)), $options);
		return $images;
	}
}

/** Read scalar candidates in batches; fetch a media blob only until each card has an image. */
if (!function_exists('tadlGetDescendantCollectionImages')) {
	function tadlGetDescendantCollectionImages($collectionIDs, $options = []) {
		$options = is_array($options) ? $options : [];
		$request = $options['request'] ?? ($GLOBALS['g_request'] ?? null);
		$access = (array)caGetOption('checkAccess', $options, []);
		$ids = tadlReadableIDs($request, 'ca_collections', (array)$collectionIDs, $access, 'ca_objects');
		if (!$ids) { return []; }
		$version = caGetOption('version', $options, 'small');
		$direct = caGetOption('directOnly', $options, false);
		$branches = $direct ? array_combine($ids, array_map(static fn($id) => [$id => true], $ids)) : tadlReadableCollectionBranches($request, $ids, $access);
		$db = (new ca_collections())->getDb();
		$pending = []; $labels = [];
		foreach (array_chunk($ids, 500) as $chunk) {
			$params = [$chunk, $access, $access, $access];
			$restriction = '';
			if ($direct) {
				foreach (['relationshipTypes' => ['ca_objects_x_collections', 'oxc'], 'objectTypes' => ['ca_objects', 'o']] as $key => [$table, $alias]) {
					if (!$types = caGetOption($key, $options, [])) { continue; }
					$types = $key === 'relationshipTypes' ? caMakeRelationshipTypeIDList($table, $types) : caMakeTypeIDList($table, $types);
					if ($types) { $restriction .= ' AND '.$alias.'.type_id IN (?)'; $params[] = $types; }
				}
			}
			$scope = $direct ? 'c.collection_id = p.collection_id' : 'c.collection_id = p.collection_id OR (
				p.hier_collection_id > 0 AND c.hier_collection_id = p.hier_collection_id
				AND p.hier_left < p.hier_right AND c.hier_left < c.hier_right
				AND c.hier_left >= p.hier_left AND c.hier_right <= p.hier_right)';
			$result = $db->query("SELECT p.collection_id parent_collection_id, pl.name collection_label,
				c.collection_id, o.object_id, r.representation_id
				FROM ca_collections p INNER JOIN ca_collections c ON ({$scope})
				INNER JOIN ca_objects_x_collections oxc ON oxc.collection_id = c.collection_id
				INNER JOIN ca_objects o ON o.object_id = oxc.object_id
				INNER JOIN ca_objects_x_object_representations oxor ON oxor.object_id = o.object_id
				INNER JOIN ca_object_representations r ON r.representation_id = oxor.representation_id
				LEFT JOIN ca_collection_labels pl ON pl.collection_id = p.collection_id AND pl.is_preferred = 1
				WHERE p.collection_id IN (?) AND c.deleted = 0 AND c.access IN (?)
				AND o.deleted = 0 AND o.access IN (?) AND r.deleted = 0 AND r.access IN (?) {$restriction}
				ORDER BY c.hier_left, o.idno_sort, oxor.is_primary DESC, oxor.rank, oxor.relation_id", $params);
			if (!$result) { throw new RuntimeException('Unable to load collection thumbnails.'); }
			$rows = [];
			while ($result->nextRow()) {
				$row = [];
				foreach (['parent_collection_id', 'collection_id', 'object_id', 'representation_id'] as $field) { $row[$field] = (int)$result->get($field); }
				if (!isset($branches[$row['parent_collection_id']][$row['collection_id']])) { continue; }
				$rows[] = $row; $labels[$row['parent_collection_id']] = (string)$result->get('collection_label');
			}
			foreach (tadlReadableMediaRows($request, $rows, $access) as $row) {
				$pending[$row['parent_collection_id']][$row['representation_id']] = $row['representation_id'];
			}
		}
		$images = []; $tags = []; $positions = array_fill_keys(array_keys($pending), 0);
		$pending = array_map('array_values', $pending);
		while ($pending) {
			$fetch = [];
			foreach ($pending as $id => $representations) {
				while (isset($representations[$positions[$id]])) {
					$rep = $representations[$positions[$id]];
					if (!array_key_exists($rep, $tags)) { $fetch[$rep] = $rep; break; }
					if ($tags[$rep]) { $images[$id] = tadlFocusThumbnail($tags[$rep][$id]); break; }
					$positions[$id]++;
				}
				if (isset($images[$id]) || !isset($representations[$positions[$id]])) { unset($pending[$id]); }
			}
			if (!$fetch) { break; }
			foreach (array_chunk(array_values($fetch), 500) as $chunk) {
				$result = $db->query('SELECT representation_id, media FROM ca_object_representations WHERE representation_id IN (?) AND deleted = 0 AND access IN (?)', [$chunk, $access]);
				if (!$result) { throw new RuntimeException('Unable to load collection image media.'); }
				foreach ($chunk as $id) { $tags[$id] = []; }
				while ($result->nextRow()) {
					$id = (int)$result->get('representation_id');
					$info = $result->getMediaInfo('media', $version);
					if (!is_array($info) || !empty($info['USE_ICON']) || !empty($info['QUEUED']) || !preg_match('!^image/!i', (string)($info['MIMETYPE'] ?? ''))) { continue; }
					foreach ($pending as $root => $representations) {
						if (in_array($id, $representations, true) && ($tag = $result->getMediaTag('media', $version, ['alt' => $labels[$root] ?: _t('Collection image')]))) { $tags[$id][$root] = $tag; }
					}
				}
			}
		}
		return $images;
	}
}
