<?php
/** Synthetic models and media-plugin boundary; no application bootstrap or database. */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('pInteger', 1);
define('pString', 2);
$directory = sys_get_temp_dir().'/tadl-download-test-'.bin2hex(random_bytes(8));
mkdir($directory.'/lib/pawtucket', 0700, true);
mkdir($directory.'/lib/Logging', 0700, true);
foreach (['pawtucket/BasePawtucketController.php', 'Media.php', 'Logging/Downloadlog.php'] as $file) {
	file_put_contents($directory.'/lib/'.$file, '<?php');
}
define('__CA_LIB_DIR__', $directory.'/lib');
$fixtures = [
	'png' => 'iVBORw0KGgoAAAANSUhEUgAAAA0AAAAHEAIAAAAMgowOAAAAR0lEQVQoz2NUUcnImDGDgUqA0eVo9Z8FCtQyjkVxgchk9naqua6NbbHyen2quU5hgTA1XXfc/ZTXPjuqGff8+d27R49SyzgAxTUT4P6pWkYAAAAASUVORK5CYII=',
	'jpg' => '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/2wBDAQMDAwQDBAgEBAgQCwkLEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBD/wAARCAAHAA0DAREAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAZEAABBQAAAAAAAAAAAAAAAAAEAAIYVJH/xAAWAQEBAQAAAAAAAAAAAAAAAAAABQb/xAAYEQADAQEAAAAAAAAAAAAAAAAAAhZRAf/aAAwDAQACEQMRAD8Ar8RgK4+tVWubemdlVwRGArj61K5t6JVcP//Z',
	'tiff' => 'SUkqACoCAAAkJGhomJgkJGhomJgkJGhomJgkJGhomJgkJGhomJgkJGhomJgkJGhomJgkJGhomJgkJGhomJgkJGhomJgkJGhomJgkJGhomJgkJGhomJjFRPx7IKDFRPx7IKDFRPx7IKDFRPx7IKDFRPx7IKDFRPx7IKDFRPx7IKDFRPx7IKDFRPx7IKDFRPx7IKDFRPx7IKDFRPx7IKDFRPx7IKBlZY+Pp6dlZY+Pp6dlZY+Pp6dlZY+Pp6dlZY+Pp6dlZY+Pp6dlZY+Pp6dlZY+Pp6dlZY+Pp6dlZY+Pp6dlZY+Pp6dlZY+Pp6dlZY+Pp6cGhiOjL68GhiOjL68GhiOjL68GhiOjL68GhiOjL68GhiOjL68GhiOjL68GhiOjL68GhiOjL68GhiOjL68GhiOjL68GhiOjL68GhiOjL6+mpra2trampra2trampra2trampra2trampra2trampra2trampra2trampra2trampra2trampra2trampra2trampra2trampra2trZHx0rKPr5Hx0rKPr5Hx0rKPr5Hx0rKPr5Hx0rKPr5Hx0rKPr5Hx0rKPr5Hx0rKPr5Hx0rKPr5Hx0rKPr5Hx0rKPr5Hx0rKPr5Hx0rKPr7n593dxcXn593dxcXn593dxcXn593dxcXn593dxcXn593dxcXn593dxcXn593dxcXn593dxcXn593dxcXn593dxcXn593dxcXn593dxcUPAAABAwABAAAADQAAAAEBAwABAAAABwAAAAIBAwADAAAA5AIAAAMBAwABAAAAAQAAAAYBAwABAAAAAgAAAAoBAwABAAAAAQAAABEBBAABAAAACAAAABIBAwABAAAAAQAAABUBAwABAAAAAwAAABYBAwABAAAABwAAABcBBAABAAAAIgIAABwBAwABAAAAAQAAACkBAwACAAAAAAABAD4BBQACAAAAGgMAAD8BBQAGAAAA6gIAAAAAAAAQABAAEACF61EAAACAAMP1qAAAAAACzcxMAAAAAAHNzEwAAACAAM3MTAAAAAACj8L1AAAAABA3GqAAAAAAAiuHCgAAACAA'
];
foreach ($fixtures as $format => $bytes) { file_put_contents($directory.'/image.'.$format, base64_decode($bytes, true)); }
file_put_contents($directory.'/not-image', 'Synthetic non-image content');
register_shutdown_function(function () use ($directory) {
	foreach (glob($directory.'/*') as $file) { if (is_file($file)) { unlink($file); } }
	foreach (['pawtucket/BasePawtucketController.php', 'Media.php', 'Logging/Downloadlog.php'] as $file) { unlink($directory.'/lib/'.$file); }
	rmdir($directory.'/lib/pawtucket'); rmdir($directory.'/lib/Logging'); rmdir($directory.'/lib'); rmdir($directory);
});
$assertions = 0;
function checkDownload($condition, $message) {
	$GLOBALS['assertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function _t($text) { return $text; }
function caGetUserAccessValues($request) { return [1]; }
function caACLIsEnabled($object, $options) { return $object->values['acl'] ?? false; }
function caObjectsDisplayDownloadLink($request, $objectID, $representation) { return $request->allowed; }
function caGetAvailableDownloadVersions($request, $mime) { return $request->versions; }
function caNavUrl($request, $module, $controller, $action, $params = []) { return '/'.$controller.'/'.$action.'?'.http_build_query($params); }
class DownloadConfig { function get($key) { return $GLOBALS['requiresLogin'] ?? false; } }
class DownloadRequest {
	public bool $allowed = true;
	public array $versions = ['original'];
	public DownloadConfig $config;
	function __construct(public array $params = [], public string $method = 'GET', public bool $loggedIn = false) { $this->config = new DownloadConfig(); }
	function getParameter($key, $type) { return $this->params[$key] ?? null; }
	function parameterExists($key) { return array_key_exists($key, $this->params) ? true : null; }
	function getRequestMethod() { return $this->method; }
	function getUserID() { return $this->loggedIn ? 7 : null; }
	function isLoggedIn() { return $this->loggedIn; }
	function getController() { return 'Detail'; }
}
class DownloadModel {
	public array $values = ['access' => 1, 'deleted' => 0, 'original_filename' => 'Synthetic scan.tif'];
	public array $rows = [];
	public bool $readable = true;
	public bool $loadable = true;
	public array $info = [];
	public string $path = '';
	function __construct(public string $table, public int $id) {}
	function tableName() { return $this->table; }
	function tableNum() { return 57; }
	function getPrimaryKey() { return $this->id; }
	function get($key) { return $this->values[$key] ?? null; }
	function load($id) { return $this->loadable && $this->id === $id; }
	function isReadable($request) { return $this->readable; }
	function getRepresentations($versions, $sizes, $options) {
		checkDownload($options === ['simple' => true, 'checkAccess' => [1]], 'Native representation filtering changed.');
		return $this->rows;
	}
	function getMediaInfo($field, $version, $key = null) {
		return $key ? ($this->info[$key] ?? null) : $this->info;
	}
	function getMediaPath($field, $version) { return $this->path; }
}
class Datamodel { static function getInstance($table, $initialize) { return $GLOBALS['models'][$table] ?? null; } }
class Media {
	static public array $calls = [];
	static public string $failure = '';
	private string $source;
	function __construct() { self::$calls[] = ['new']; }
	function read($path) { $this->source = $path; self::$calls[] = ['read', $path]; return self::$failure !== 'read'; }
	function get($key) { return $key === 'width' ? (self::$failure === 'dimensions' ? 14 : 13) : 7; }
	function set($key, $value) { self::$calls[] = ['set', $key, $value]; }
	function write($path, $mime) {
		self::$calls[] = ['write', $mime];
		if (self::$failure === 'write') { return false; }
		if (!self::$failure && ($magick = getenv('TADL_TEST_MAGICK'))) {
			$process = proc_open([$magick, $this->source.'[0]', '-quality', '90', '-background', '#ffffff', $path.'.jpg'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
			fclose($pipes[0]); stream_get_contents($pipes[1]); fclose($pipes[1]);
			$error = stream_get_contents($pipes[2]); fclose($pipes[2]);
			if (proc_close($process) !== 0) { throw new RuntimeException($error); }
			return $path.'.jpg';
		}
		$bytes = $GLOBALS['fixtures'][self::$failure === 'format' ? 'png' : 'jpg'];
		file_put_contents($path.'.jpg', base64_decode($bytes)); return $path.'.jpg';
	}
	function cleanup() { self::$calls[] = ['cleanup']; }
}
class Downloadlog { static public array $entries = []; function log($entry) { self::$entries[] = $entry; } }
class DownloadView {
	public array $values = [];
	function __construct(public ?DownloadRequest $request = null) {}
	function getVar($key) { return $this->values[$key] ?? null; }
	function setVar($key, $value) { $this->values[$key] = $value; }
	function render($file) { return '<div>Native synthetic viewer</div>'; }
}
class DownloadResponse {
	public int $status = 200;
	public array $headers = [];
	public string $content = '';
	function setHTTPResponseCode($code, $message) { $this->status = $code; }
	function addHeader($key, $value) { $this->headers[$key] = $value; }
	function addContent($content) { $this->content .= $content; }
}
class BasePawtucketController {
	public DownloadView $view;
	public bool $rendered = false;
	function __construct(public DownloadRequest $request, public DownloadResponse $response) { $this->view = new DownloadView(); }
	function render($file, $direct) { checkDownload($file === 'Details/image_download_binary.php' && $direct, 'Wrong streaming view.'); $this->rendered = true; }
}
require dirname(__DIR__).'/controllers/ImageDownloadController.php';
function resetDownload($format = 'tiff') {
	$object = new DownloadModel('ca_objects', 42);
	$rep = new DownloadModel('ca_object_representations', 101);
	$mime = $format === 'jpg' ? 'image/jpeg' : 'image/'.$format;
	$object->rows = [101 => ['mimetype' => $mime]];
	$rep->info = ['MIMETYPE' => $mime]; $rep->path = $GLOBALS['directory'].'/image.'.$format;
	$GLOBALS['models'] = ['ca_objects' => $object, 'ca_object_representations' => $rep];
	$GLOBALS['requiresLogin'] = false; Media::$calls = []; Media::$failure = ''; Downloadlog::$entries = [];
	return [$object, $rep, new DownloadRequest(['object_id' => 42, 'representation_id' => 101, 'format' => 'jpg'])];
}
function runDownload($request) {
	$controller = new ImageDownloadController($request, new DownloadResponse()); $controller->Download(); return $controller;
}
foreach (['png', 'tiff', 'jpg'] as $format) {
	[$object, $rep, $request] = resetDownload($format);
	$originalHash = hash_file('sha256', $rep->path);
	$html = tadlImageDownloadLinks($request, $object, 101);
	checkDownload(str_contains($html, '<details') && str_contains($html, '<summary') && str_contains($html, 'JPG (to share)'), 'Native dropdown/JPG missing.');
	checkDownload(str_contains($html, 'TIFF (to print)') === ($format === 'tiff'), 'TIFF offered without a TIFF original.');
	$controller = runDownload($request);
	$download = $controller->view->getVar('image_download');
	checkDownload($controller->rendered && $download['mime'] === 'image/jpeg', 'JPG download failed.');
	$dimensions = getimagesize($download['path']);
	checkDownload($dimensions[0] === 13 && $dimensions[1] === 7 && $dimensions[2] === IMAGETYPE_JPEG, 'Download was resized or was not JPEG bytes.');
	checkDownload(str_ends_with($controller->view->getVar('image_download_name'), '.jpg'), 'JPG filename missing.');
	checkDownload(hash_file('sha256', $rep->path) === $originalHash, 'Original was changed.');
	checkDownload(count(Downloadlog::$entries) === 1, 'Successful download was not logged.');
	if ($format === 'jpg') { checkDownload(!Media::$calls, 'JPEG original should stream without re-encoding.'); }
	else { checkDownload(Media::$calls[1] === ['read', $rep->path] && end(Media::$calls) === ['cleanup'], 'Conversion did not read/clean up native media.'); }
}
[$object, $rep, $request] = resetDownload(); $request->params['format'] = 'tiff';
$controller = runDownload($request); $download = $controller->view->getVar('image_download');
checkDownload($controller->rendered && $download['path'] === $rep->path && $download['mime'] === 'image/tiff' && !Media::$calls, 'TIFF must stream the original unchanged.');
foreach (['method', 'format', 'objectID', 'repID', 'load', 'objectAccess', 'objectACL', 'objectDeleted', 'repACL', 'repDeleted', 'unattached', 'bundle', 'policy', 'version', 'missing', 'queued', 'icon', 'nonimage', 'login'] as $case) {
	[$object, $rep, $request] = resetDownload();
	switch ($case) {
		case 'method': $request->method = 'POST'; break;
		case 'format': $request->params['format'] = 'original'; break;
		case 'objectID': $request->params['object_id'] = -1; break;
		case 'repID': $request->params['representation_id'] = 0; break;
		case 'load': $object->loadable = false; break;
		case 'objectAccess': $object->values['access'] = 0; break;
		case 'objectACL': $object->readable = false; break;
		case 'objectDeleted': $object->values['deleted'] = 1; break;
		case 'repACL': $rep->readable = false; break;
		case 'repDeleted': $rep->values['deleted'] = 1; break;
		case 'unattached': unset($object->rows[101]); break;
		case 'bundle': $object->rows = []; break;
		case 'policy': $request->allowed = false; break;
		case 'version': $request->versions = ['large']; break;
		case 'missing': $rep->path .= '.missing'; break;
		case 'queued': $rep->info['QUEUED'] = true; break;
		case 'icon': $rep->info['USE_ICON'] = true; break;
		case 'nonimage': $object->rows[101]['mimetype'] = 'video/mp4'; break;
		case 'login': $GLOBALS['requiresLogin'] = true; break;
	}
	$controller = runDownload($request);
	checkDownload($controller->response->status >= 400 && !$controller->rendered && !Media::$calls && !Downloadlog::$entries, $case.': rejected request reached conversion/download.');
}
foreach (['read', 'write', 'dimensions', 'format'] as $failure) {
	[$object, $rep, $request] = resetDownload(); Media::$failure = $failure;
	$controller = runDownload($request);
	checkDownload($controller->response->status === 503 && !$controller->rendered && !Downloadlog::$entries, $failure.': conversion failed open.');
}
[$object, $rep, $request] = resetDownload('png'); $request->params['format'] = 'tiff';
checkDownload(runDownload($request)->response->status === 503, 'PNG must not be renamed to TIFF.');
[$object, $rep, $request] = resetDownload(); $rep->path = $directory.'/not-image';
checkDownload(runDownload($request)->response->status === 503, 'Non-image bytes were accepted as JPEG.');
[$object, $rep, $request] = resetDownload(); $object->values['acl'] = true; $object->values['access'] = 0;
checkDownload(runDownload($request)->rendered, 'ACL-readable object should preserve native ACL access behavior.');
[$object, $rep, $request] = resetDownload(); $rep->values['original_filename'] = "../bad\"\r\nX-Test: value.tif";
$controller = runDownload($request);
checkDownload(preg_match('/^[A-Za-z0-9_-]+\.jpg$/', $controller->view->getVar('image_download_name')) === 1, 'Unsafe attachment filename.');
[$object, $rep, $request] = resetDownload();
$toolbar = '<div class=\'detailMediaToolbar\'><a href="#" class="zoomButton" onclick="nativeZoom()" aria-label="Open Media View"><i class="fa fa-search-plus"></i></a><a class="setsButton" href="/Lightbox">Login to add to lightbox</a><a class="dlButton" href="/original">Download</a><a class="compare_link" href="/compare">Compare</a></div><!-- end detailMediaToolbar -->';
$html = tadlImageToolbar($request, $object, 101, $toolbar);
checkDownload(!str_contains($html, 'setsButton') && !str_contains($html, 'lightbox') && !str_contains($html, 'dlButton'), 'Old Lightbox/download buttons remain in toolbar.');
checkDownload(str_contains($html, 'nativeZoom()') && str_contains($html, 'compare_link') && str_contains($html, 'Open media view'), 'Native zoom/compare actions changed.');
checkDownload(substr_count($html, '<details') === 1 && str_contains($html, 'TIFF (to print)') && str_contains($html, 'JPG (to share)'), 'Toolbar must have one labeled dropdown.');
$slide = '<div data-representation_id="101"><img src="/synthetic/still.jpg">'.$toolbar.'</div><script>nativeInitialization()</script>';
$html = tadlImageViewerSlide($request, $object, 101, $slide);
checkDownload(str_starts_with($html, '<div data-representation_id="101">') && str_contains($html, '<script>nativeInitialization()</script>') && substr_count($html, '<details') === 1, 'Slide wrappers/scripts were altered or dropdown duplicated.');
$request->allowed = false;
$html = tadlImageToolbar($request, $object, 101, $toolbar);
checkDownload(!str_contains($html, '<details') && str_contains($html, 'nativeZoom()'), 'Download restrictions must preserve zoom.');
$object->rows[101]['mimetype'] = 'video/mp4';
checkDownload(tadlImageToolbar($request, $object, 101, $toolbar) === $toolbar, 'Image toolbar changes should not alter video actions.');
[$object, $rep, $request] = resetDownload();
require dirname(__DIR__).'/helpers/object_detail_media.php';
$view = new DownloadView($request);
$view->values = ['representation_count' => 1, 'representation_ids' => [101], 't_subject' => $object, 'slide_list' => [$slide]];
$renderBundle = function () { ob_start(); include dirname(__DIR__).'/views/bundles/representation_viewer_html.php'; return ob_get_clean(); };
$html = $renderBundle->call($view);
checkDownload(substr_count($html, '<details') === 1 && !str_contains($html, 'setsButton') && str_contains($html, 'JPG (to share)'), 'Actual single-image bundle did not replace its native toolbar.');
$view->values['representation_count'] = 2;
$view->values['slide_list'] = [$slide, $slide];
$html = $renderBundle->call($view);
checkDownload(str_contains($html, 'tadl-image-downloads') && !str_contains($html, 'setsButton') && str_contains($html, 'detailRepNavNext'), 'Multi-image bundle lost download controls or navigation.');
foreach (['id', 'object_id'] as $idParameter) {
	$request->params = [$idParameter => 42, 'context' => 'objects'];
	$view->values = ['viewer' => 'TileViewer', 'identifier' => 'representation:101', 'controls' => '<div class="repNav">Native navigation</div><div class="download"><form>Native download</form></div>'];
	$renderOverlay = function () { ob_start(); include dirname(__DIR__).'/views/mediaViewers/viewerWrapper.php'; return ob_get_clean(); };
	$html = $renderOverlay->call($view);
	checkDownload(substr_count($html, '<details') === 1 && str_contains($html, 'TIFF (to print)') && !str_contains($html, 'Native download') && str_contains($html, 'Native navigation'), $idParameter.': overlay dropdown or native navigation was lost.');
}
echo json_encode(['status' => 'passed', 'assertions' => $assertions, 'boundaries' => 'actual helper/controller; synthetic access/ACL/download/media-plugin APIs; real JPEG/TIFF/PNG bytes'], JSON_PRETTY_PRINT).PHP_EOL;
