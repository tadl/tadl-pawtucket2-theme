<?php
require_once(__DIR__.'/record_access.php');

/** Fetch primary media once per page, rather than through each result's relationship expansion. */
function tadlObjectThumbnails($request, array $ids, $version, array $access) {
	if (!$request || !$ids || !$access) { return []; }
	if (caGetBundleAccessLevel('ca_objects', 'ca_object_representations') < __CA_BUNDLE_ACCESS_READONLY__
		|| caGetBundleAccessLevel('ca_object_representations', 'media') < __CA_BUNDLE_ACCESS_READONLY__) { return []; }
	$ids = tadlReadableIDs($request, 'ca_objects', $ids, $access, 'ca_object_representations');
	$images = []; $db = Datamodel::getInstanceByTableName('ca_objects', false)->getDb();
	foreach (array_chunk($ids, 500) as $chunk) {
		$candidates = $db->query('SELECT oxr.object_id, r.representation_id
			FROM ca_objects_x_object_representations oxr
			INNER JOIN ca_objects o ON o.object_id = oxr.object_id
			INNER JOIN ca_object_representations r ON r.representation_id = oxr.representation_id
			WHERE oxr.object_id IN (?) AND oxr.is_primary = 1
				AND o.deleted = 0 AND o.access IN (?) AND r.deleted = 0 AND r.access IN (?)
			ORDER BY oxr.object_id, r.representation_id', [$chunk, $access, $access]);
		if (!$candidates) { throw new RuntimeException('Unable to read primary thumbnail relationships.'); }
		$rows = [];
		while ($candidates->nextRow()) { $rows[] = ['object_id'=>(int)$candidates->get('object_id'), 'representation_id'=>(int)$candidates->get('representation_id')]; }
		$primary = []; $tags = [];
		foreach (tadlReadableMediaRows($request, $rows, $access, true) as $row) { $primary[$row['object_id']] ??= $row['representation_id']; }
		if ($primary) {
			$media = $db->query('SELECT representation_id, media FROM ca_object_representations WHERE representation_id IN (?) AND deleted = 0 AND access IN (?)', [array_values(array_unique($primary)), $access]);
			if (!$media) { throw new RuntimeException('Unable to read primary thumbnails.'); }
			while ($media->nextRow()) {
				// The surrounding link supplies the record's accessible name. Avoid
				// native alt templates expanding all representation labels per card.
				$tags[(int)$media->get('representation_id')] = $media->getMediaTag('ca_object_representations.media', $version, ['alt'=>'']);
			}
		}
		foreach ($chunk as $id) {
			$images[$id] = tadlObjectThumbnail($id, $version, $access, $tags[$primary[$id] ?? 0] ?? '', $request);
		}
	}
	return $images;
}

/** Preserve the primary thumbnail; use native access-filtered media when it is unavailable. */
function tadlObjectThumbnail($object_id, $version, array $access, $primary_tag = '', $request = null) {
	if (!$access || (int)$object_id <= 0) { return ''; }
	$request = $request ?? ($GLOBALS['g_request'] ?? null);
	if (!$request || caGetBundleAccessLevel('ca_objects', 'ca_object_representations') < __CA_BUNDLE_ACCESS_READONLY__
		|| caGetBundleAccessLevel('ca_object_representations', 'media') < __CA_BUNDLE_ACCESS_READONLY__) { return ''; }
	$model = Datamodel::getInstanceByTableName('ca_object_representations', false);
	$restricted = caACLIsEnabled('ca_object_representations', ['forPawtucket' => true])
		|| $model->getAppConfig()->get('perform_type_access_checking') || caSourceAccessControlIsEnabled($model)
		|| is_array(caGetTypeRestrictionsForUser('ca_object_representations'))
		|| is_array(caGetSourceRestrictionsForUser('ca_object_representations'));
	if ($primary_tag && !$restricted) { return $primary_tag; }

	// A paired JPEG can remain primary after being made private. Keep catalogue
	// links untouched and let the native model enforce access, ACLs and bundles.
	$object = new ca_objects((int)$object_id);
	if (!$object->getPrimaryKey()) { return ''; }
	$representations = (array)$object->getRepresentations([$version], null, ['checkAccess' => $access]);
	if ($restricted) {
		$allowed = array_fill_keys(tadlReadableIDs($request, 'ca_object_representations', array_column($representations, 'representation_id'), $access, 'media'), true);
		$representations = array_filter($representations, static fn($row) => isset($allowed[$row['representation_id']]));
	}
	usort($representations, static function ($a, $b) {
		return [(int)!($a['is_primary'] ?? false), (int)($a['rank'] ?? 0), (int)$a['representation_id']]
			<=> [(int)!($b['is_primary'] ?? false), (int)($b['rank'] ?? 0), (int)$b['representation_id']];
	});
	foreach ($representations as $representation) {
		$info = $representation['info'][$version] ?? [];
		if (!is_array($info) || !empty($info['USE_ICON']) || !empty($info['QUEUED'])
			|| !preg_match('!^image/!i', (string)($info['MIMETYPE'] ?? ''))) { continue; }
		if ($tag = $representation['tags'][$version] ?? '') { return $tag; }
	}
	return '';
}
