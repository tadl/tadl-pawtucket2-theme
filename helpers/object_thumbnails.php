<?php
require_once(__DIR__.'/record_access.php');

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
