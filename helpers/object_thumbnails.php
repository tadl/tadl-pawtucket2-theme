<?php

/** Preserve the primary thumbnail; use native access-filtered media when it is unavailable. */
function tadlObjectThumbnail($object_id, $version, array $access, $primary_tag = '') {
	if (!$access || (int)$object_id <= 0) { return ''; }
	if ($primary_tag) { return $primary_tag; }

	// A paired JPEG can remain primary after being made private. Keep catalogue
	// links untouched and let the native model enforce access, ACLs and bundles.
	$object = new ca_objects((int)$object_id);
	if (!$object->getPrimaryKey()) { return ''; }
	$representations = (array)$object->getRepresentations([$version], null, ['checkAccess' => $access]);
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
