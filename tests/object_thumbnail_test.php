<?php
/** Synthetic native model boundaries and actual result-view rendering. */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('__CA_BUNDLE_ACCESS_READONLY__', 1);
function caGetBundleAccessLevel($table, $bundle) { return 1; }
function caACLIsEnabled($model, $options) { return false; }
function caGetTypeRestrictionsForUser($table) { return null; }
function caGetSourceRestrictionsForUser($table) { return null; }
function caSourceAccessControlIsEnabled($model) { return false; }
class ThumbnailPermissionConfig { function get($key) { return false; } }
class Datamodel {
	static function getInstanceByTableName($table, $cached) { return new self(); }
	function getAppConfig() { return new ThumbnailPermissionConfig(); }
}
$GLOBALS['g_request'] = new stdClass();
$assertions = 0;
function checkObjectThumbnail($condition, $message) {
	global $assertions; $assertions++;
	if (!$condition) { throw new RuntimeException($message); }
}
class ca_objects {
	static public array $media = [], $calls = [];
	function __construct(private int $id = 0) {}
	function getPrimaryKey() { return isset(self::$media[$this->id]) ? $this->id : null; }
	function getRepresentations($versions, $sizes, $options) {
		self::$calls[] = [$this->id, $versions, $sizes, $options];
		// Access, ACL and deletion filtering belong to the native model.
		return array_filter(self::$media[$this->id], static fn($rep) => !$rep['deleted']
			&& $rep['readable'] && in_array($rep['access'], $options['checkAccess'], true));
	}
	function getPrimaryMediaForIDs($ids, $versions, $options) { return []; }
}
function objectThumbnailMedia($id, $rank = 0, $primary = false, $access = 1, $overrides = []) {
	$info = ['MIMETYPE' => 'image/jpeg', 'FILENAME' => 'synthetic-'.$id.'.jpg'];
	return array_replace([
		'representation_id' => $id, 'rank' => $rank, 'is_primary' => $primary,
		'access' => $access, 'deleted' => false, 'readable' => true,
		'info' => ['medium' => $info, 'small' => $info],
		'tags' => ['medium' => '<img src="/synthetic/'.$id.'-medium.jpg" alt="Synthetic &amp; image">',
			'small' => '<img src="/synthetic/'.$id.'-small.jpg" alt="Synthetic &amp; image">']
	], $overrides);
}
require dirname(__DIR__).'/helpers/object_thumbnails.php';
checkObjectThumbnail(tadlObjectThumbnail(1, 'medium', [1], '<img src="/synthetic/primary.jpg">') === '<img src="/synthetic/primary.jpg">', 'Visible primary tag was replaced.');
checkObjectThumbnail(ca_objects::$calls === [], 'Visible primary incurred fallback model queries.');
ca_objects::$media[1] = [objectThumbnailMedia(10, 0, true, 0), objectThumbnailMedia(11, 1)];
checkObjectThumbnail(str_contains(tadlObjectThumbnail(1, 'medium', [1]), '11-medium.jpg'), 'Private primary did not fall back to accessible TIFF derivative.');
checkObjectThumbnail(end(ca_objects::$calls) === [1, ['medium'], null, ['checkAccess' => [1]]], 'Native representation access mask or requested version was lost.');
checkObjectThumbnail(str_contains(tadlObjectThumbnail(1, 'small', [1]), '11-small.jpg'), 'List fallback did not use the small version.');
ca_objects::$media[2] = [objectThumbnailMedia(20, 0, true, 0)];
checkObjectThumbnail(tadlObjectThumbnail(2, 'medium', [1]) === '', 'Private-only media returned a thumbnail.');
ca_objects::$media[3] = [objectThumbnailMedia(30, 0, false, 1, ['readable' => false]), objectThumbnailMedia(31, 0, false, 1, ['deleted' => true])];
checkObjectThumbnail(tadlObjectThumbnail(3, 'medium', [1]) === '', 'Native ACL/deletion filtering was bypassed.');
ca_objects::$media[4] = [objectThumbnailMedia(43, 2), objectThumbnailMedia(42, 1), objectThumbnailMedia(41, 1)];
checkObjectThumbnail(str_contains(tadlObjectThumbnail(4, 'medium', [1]), '41-medium.jpg'), 'Fallback rank and ID ties were not deterministic.');
ca_objects::$media[4][] = objectThumbnailMedia(44, 9, true);
checkObjectThumbnail(str_contains(tadlObjectThumbnail(4, 'medium', [1]), '44-medium.jpg'), 'Accessible primary lost precedence in fallback inventory.');
ca_objects::$media[5] = [
	objectThumbnailMedia(50, 0, false, 1, ['info' => ['medium' => ['MIMETYPE' => 'image/jpeg', 'USE_ICON' => true]]]),
	objectThumbnailMedia(51, 1, false, 1, ['info' => ['medium' => ['MIMETYPE' => 'image/jpeg', 'QUEUED' => true]]]),
	objectThumbnailMedia(52, 2, false, 1, ['info' => ['medium' => ['MIMETYPE' => 'video/mp4']]]),
	objectThumbnailMedia(53, 3, false, 1, ['tags' => ['medium' => '']]), objectThumbnailMedia(54, 4)
];
checkObjectThumbnail(str_contains(tadlObjectThumbnail(5, 'medium', [1]), '54-medium.jpg'), 'Unusable derivatives prevented safe fallback.');
checkObjectThumbnail(tadlObjectThumbnail(999, 'medium', [1]) === '', 'Missing object returned media.');
$calls = count(ca_objects::$calls);
checkObjectThumbnail(tadlObjectThumbnail(1, 'medium', []) === '' && tadlObjectThumbnail(0, 'medium', [1]) === '', 'Empty access mask or invalid object was accepted.');
checkObjectThumbnail(count(ca_objects::$calls) === $calls, 'Invalid inputs reached native media lookup.');

function _t($text) { return $text; }
function caGetOption($key, $options, $default = null) { return $options[$key] ?? $default; }
function caGetUserAccessValues($request) { return [1]; }
function caDisplayLightbox($request) { return false; }
function caGetIconsConfig() { return new ObjectThumbnailConfig(); }
function caDetailLink($request, $label, $class, $table, $id) { return '<a href="/Detail/objects/'.$id.'">'.$label.'</a>'; }
function caDetailUrl($request, $table, $id) { return '/Detail/objects/'.$id; }
function caNavUrl($request, $module, $controller, $action, $params, $options) { return '/Search/objects'; }
function tadlFilterMediaResult($request, $result) {}
class ca_list_items {}
class ExternalCache { static function save($key, $value, $group, $timeout) {} }
class ObjectThumbnailConfig {
	function get($key) { return $key === 'cache_timeout' ? 0 : null; }
	function getAssoc($key) { return []; }
}
class ObjectThumbnailRequest { function isAjax() { return false; } }
class ObjectThumbnailResult {
	private bool $done = false;
	function numHits() { return 1; }
	function seek($start) { $this->done = false; }
	function nextHit() { if ($this->done) { return false; } $this->done = true; return true; }
	function getPrimaryKey() { return 1; }
	function get($key, $options = []) {
		if (str_contains($key, '.media.')) { return ''; }
		return str_contains($key, 'preferred_labels') ? 'Synthetic object' : 1;
	}
	function getMediaTag($field, $version, $options) { return ''; }
	function getWithTemplate($template) { return ''; }
}
class ObjectThumbnailView {
	public ObjectThumbnailRequest $request;
	function __construct() { $this->request = new ObjectThumbnailRequest(); }
	function getVar($key) {
		return match ($key) {
			'result' => new ObjectThumbnailResult(), 'table' => 'ca_objects', 'primaryKey' => 'object_id', 'access_values' => [1],
			'facets', 'criteria', 'options', 'views' => [], 'config' => new ObjectThumbnailConfig(),
			'start', 'row_id' => 0, 'itemsPerPage' => 6, 'block' => 'objects',
			'blockInfo' => ['table' => 'ca_objects', 'displayName' => 'Objects'], default => null
		};
	}
	function render($view) {
		ob_start();
		try { include dirname(__DIR__).'/views/'.$view; return ob_get_clean(); }
		catch (Throwable $error) { ob_end_clean(); throw $error; }
	}
}
foreach (['Browse/browse_results_images_html.php' => 'medium', 'Browse/browse_results_list_html.php' => 'small', 'Search/tadl_search_results_subview_html.php' => 'medium'] as $view => $version) {
	$html = (new ObjectThumbnailView())->render($view);
	checkObjectThumbnail(str_contains($html, '11-'.$version.'.jpg') && !str_contains($html, '10-'.$version.'.jpg'), $view.': private primary prevented safe fallback.');
	checkObjectThumbnail(str_contains($html, 'Synthetic &amp; image'), $view.': native escaped tag was changed.');
}
echo 'Object thumbnails passed: '.$assertions.' assertions (primary preference, access-aware fallback, deterministic selection and actual Tiles/List/search views).'.PHP_EOL;
