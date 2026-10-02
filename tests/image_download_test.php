<?php
/** Synthetic models and media-plugin boundary; no application bootstrap or database. */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	// Older bundled dependencies emit PHP 8.5 deprecations; keep theme warnings strict.
	if ($severity === E_DEPRECATED && getenv('TADL_TEST_COMPOSER_AUTOLOAD') && str_contains($file, '/vendor/')) { return true; }
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
function caDetailLink($request, $content, $class, $table, $id) {
	checkDownload($table === 'ca_objects', 'Gallery image linked to the wrong record table.');
	return '<a href="/Detail/objects?object_id='.(int)$id.'">'.$content.'</a>';
}
class DownloadConfig { function get($key) { return $GLOBALS['requiresLogin'] ?? false; } }
class DownloadRequest {
	public bool $allowed = true;
	public array $versions = ['original'];
	public DownloadConfig $config;
	public string $controller = 'Detail';
	function __construct(public array $params = [], public string $method = 'GET', public bool $loggedIn = false) { $this->config = new DownloadConfig(); }
	function getParameter($key, $type) { return $this->params[$key] ?? null; }
	function parameterExists($key) { return array_key_exists($key, $this->params) ? true : null; }
	function getRequestMethod() { return $this->method; }
	function getUserID() { return $this->loggedIn ? 7 : null; }
	function isLoggedIn() { return $this->loggedIn; }
	function getController() { return $this->controller; }
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
class DownloadPDFOptions { function __construct(public array $values) {} }
class DownloadPDFRenderer {
	static public string $failure = '';
	static public array $calls = [];
	private string $html;
	function __construct($options) { self::$calls[] = ['options', $options->values]; }
	function setPaper($size, $orientation) { self::$calls[] = ['paper', $size, $orientation]; }
	function loadHtml($html) { $this->html = $html; self::$calls[] = ['html', $html]; }
	function render() { if (self::$failure === 'render') { throw new RuntimeException('Synthetic renderer failure'); } }
	function output() {
		if (self::$failure === 'bytes') { return '<html>Not a PDF</html>'; }
		preg_match('~data:image/jpeg;base64,([^\"]+)~', $this->html, $match);
		return "%PDF-1.4\n".base64_decode($match[1])."\n%%EOF\n";
	}
}
if ($autoload = getenv('TADL_TEST_COMPOSER_AUTOLOAD')) { require $autoload; }
else {
	class_alias(DownloadPDFOptions::class, 'Dompdf\\Options');
	class_alias(DownloadPDFRenderer::class, 'Dompdf\\Dompdf');
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
	checkDownload(str_contains($html, '>PDF</a>') && str_contains($html, 'format=pdf'), 'Image menu is missing PDF.');
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
foreach (['png', 'tiff', 'jpg'] as $format) {
	[$object, $rep, $request] = resetDownload($format); $request->params['format'] = 'pdf';
	$originalHash = hash_file('sha256', $rep->path);
	$controller = runDownload($request); $download = $controller->view->getVar('image_download');
	if (!$controller->rendered && $autoload) { tadlPrepareImageDownload(['path' => $rep->path], 'pdf'); }
	checkDownload($controller->rendered && $download['mime'] === 'application/pdf' && $download['temporary'], $format.': PDF download failed.');
	$bytes = file_get_contents($download['path']);
	checkDownload(str_starts_with($bytes, '%PDF-') && str_ends_with(rtrim($bytes), '%%EOF') && (new finfo(FILEINFO_MIME_TYPE))->file($download['path']) === 'application/pdf', 'PDF response has wrong bytes.');
	checkDownload(str_ends_with($controller->view->getVar('image_download_name'), '.pdf') && count(Downloadlog::$entries) === 1, 'PDF filename/logging missing.');
	checkDownload(hash_file('sha256', $rep->path) === $originalHash, 'PDF conversion modified the original.');
	if (!$autoload) {
		$options = DownloadPDFRenderer::$calls[count(DownloadPDFRenderer::$calls) - 3][1];
		checkDownload(!$options['isRemoteEnabled'] && !$options['isPhpEnabled'] && !$options['isJavascriptEnabled'] && fileperms($options['tempDir']) % 512 === 0700, 'PDF renderer must use private scratch files and disable remote content/scripts.');
		$paper = DownloadPDFRenderer::$calls[count(DownloadPDFRenderer::$calls) - 2];
		checkDownload($paper === ['paper', 'letter', 'landscape'], 'Landscape image did not use landscape letter paper.');
		$html = end(DownloadPDFRenderer::$calls)[1];
		preg_match('~data:image/jpeg;base64,([^\"]+)~', $html, $match);
		$dimensions = getimagesizefromstring(base64_decode($match[1]));
		checkDownload($dimensions[0] === 13 && $dimensions[1] === 7, 'PDF lost full-resolution image pixels.');
	} else {
		checkDownload(preg_match_all('~/Type /Page\b~', $bytes) === 1, 'Image PDF must contain exactly one page.');
		checkDownload(preg_match('~/Subtype /Image\s*/Width 13\s*/Height 7~', $bytes) === 1, 'Actual PDF did not retain full-resolution image pixels.');
		checkDownload(preg_match('~/MediaBox \[0(?:\.\d+)? 0(?:\.\d+)? 792(?:\.\d+)? 612(?:\.\d+)?\]~', $bytes) === 1, 'Actual PDF used wrong paper size/orientation.');
		if ($format === 'jpg') { checkDownload(str_contains($bytes, file_get_contents($rep->path)), 'PDF re-encoded the JPEG original.'); }
	}
}
if ($autoload && ($magick = getenv('TADL_TEST_MAGICK'))) {
	$process = proc_open([$magick, $directory.'/image.jpg', '-rotate', '90', $directory.'/portrait.jpg'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	fclose($pipes[0]); stream_get_contents($pipes[1]); fclose($pipes[1]);
	$error = stream_get_contents($pipes[2]); fclose($pipes[2]);
	checkDownload(proc_close($process) === 0, 'Portrait fixture could not be generated: '.$error);
	$portrait = tadlPrepareImageDownload(['path' => $directory.'/portrait.jpg'], 'pdf');
	$bytes = file_get_contents($portrait['path']);
	checkDownload(preg_match('~/Subtype /Image\s*/Width 7\s*/Height 13~', $bytes) === 1, 'Portrait PDF resized the image.');
	checkDownload(preg_match('~/MediaBox \[0(?:\.\d+)? 0(?:\.\d+)? 612(?:\.\d+)? 792(?:\.\d+)?\]~', $bytes) === 1 && preg_match_all('~/Type /Page\b~', $bytes) === 1, 'Portrait image did not fit on one portrait letter page.');
}
if (!$autoload) {
	foreach (['render', 'bytes'] as $failure) {
		[$object, $rep, $request] = resetDownload(); $request->params['format'] = 'pdf'; DownloadPDFRenderer::$failure = $failure;
		$controller = runDownload($request);
		checkDownload($controller->response->status === 503 && !$controller->rendered && !Downloadlog::$entries, 'PDF renderer failure was served/logged as a download.');
	}
	DownloadPDFRenderer::$failure = '';
}
[$object, $rep, $request] = resetDownload(); $request->params['format'] = 'tiff';
$controller = runDownload($request); $download = $controller->view->getVar('image_download');
checkDownload($controller->rendered && $download['path'] === $rep->path && $download['mime'] === 'image/tiff' && !Media::$calls, 'TIFF must stream the original unchanged.');
foreach (['jpg', 'pdf'] as $requestedFormat) {
foreach (['method', 'format', 'objectID', 'repID', 'load', 'objectAccess', 'objectACL', 'objectDeleted', 'repACL', 'repDeleted', 'unattached', 'bundle', 'policy', 'version', 'missing', 'queued', 'icon', 'nonimage', 'login'] as $case) {
	[$object, $rep, $request] = resetDownload();
	$request->params['format'] = $requestedFormat;
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
}
foreach (['jpg', 'pdf'] as $requestedFormat) {
foreach (['read', 'write', 'dimensions', 'format'] as $failure) {
	[$object, $rep, $request] = resetDownload(); Media::$failure = $failure;
	$request->params['format'] = $requestedFormat;
	$controller = runDownload($request);
	checkDownload($controller->response->status === 503 && !$controller->rendered && !Downloadlog::$entries, $failure.': conversion failed open.');
}
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
$toolbar = '<div class=\'detailMediaToolbar\'><a href="#" class="zoomButton" onclick="nativeZoom()" aria-label="Open Media View" title="Open Media View"><i class="fa fa-search-plus"></i></a><a class="setsButton" href="/Lightbox">Login to add to lightbox</a><a class="dlButton" href="/original">Download</a><a class="compare_link" href="/compare">Compare</a></div><!-- end detailMediaToolbar -->';
$html = tadlImageToolbar($request, $object, 101, $toolbar);
checkDownload(!str_contains($html, 'setsButton') && !str_contains($html, 'lightbox') && !str_contains($html, 'dlButton'), 'Old Lightbox/download buttons remain in toolbar.');
checkDownload(str_contains($html, 'nativeZoom()') && str_contains($html, 'compare_link') && str_contains($html, 'Media viewer'), 'Native zoom/compare actions changed.');
checkDownload(str_contains($html, 'aria-label="Media viewer"') && str_contains($html, 'title="Media viewer"'), 'Media viewer accessible name and tooltip must match its visible label.');
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
foreach (['objects', 'gallery'] as $overlayContext) {
	foreach (['id', 'object_id'] as $idParameter) {
		$request->params = [$idParameter => 42, 'context' => $overlayContext];
		$view->values = ['viewer' => 'TileViewer', 'identifier' => 'representation:101', 'controls' => '<div class="repNav">Native navigation</div><div class="download"><form>Native download</form></div>'];
		$renderOverlay = function () { ob_start(); include dirname(__DIR__).'/views/mediaViewers/viewerWrapper.php'; return ob_get_clean(); };
		$html = $renderOverlay->call($view);
		checkDownload(substr_count($html, '<details') === 1 && str_contains($html, 'TIFF (to print)') && !str_contains($html, 'Native download') && str_contains($html, 'Native navigation'), $overlayContext.'/'.$idParameter.': overlay dropdown or native navigation was lost.');
	}
}

// Gallery AJAX replaces the entire actual media partial. Its toolbar and global
// navigation callback must follow the new item rather than retain the first IDs.
$galleryItems = [
	['item' => 21, 'object' => 42, 'representation' => 101, 'format' => 'tiff'],
	['item' => 22, 'object' => 43, 'representation' => 202, 'format' => 'jpg'],
	['item' => 23, 'object' => 44, 'representation' => 303, 'format' => 'png']
];
$galleryFixtures = [];
foreach ($galleryItems as $position => $item) {
	[$object, $rep, $request] = resetDownload($item['format']);
	$object->id = $item['object']; $rep->id = $item['representation'];
	$object->rows = [$rep->id => ['mimetype' => $rep->info['MIMETYPE']]];
	$request->controller = 'Gallery';
	$zoomUrl = caNavUrl($request, '', 'Detail', 'GetMediaOverlay', ['context' => 'gallery', 'id' => $object->id, 'representation_id' => $rep->id, 'set_id' => 301, 'overlay' => 1]);
	$zoomCallback = 'caMediaPanel.showPanel('.json_encode($zoomUrl).', function() { var url = jQuery("#" + caMediaPanel.getPanelID()).data("reloadUrl"); if(url) { window.location = url; } }); return false;';
	$galleryToolbar = '<div class="detailMediaToolbar"><a href="#" class="zoomButton" onclick="'.htmlspecialchars($zoomCallback, ENT_QUOTES, 'UTF-8').'"><i class="fa fa-search-plus"></i></a><a href="#" class="compare_link" data-id="representation:'.$rep->id.'">Compare</a><a href="#" class="setsButton">Lightbox</a><a href="#" class="dlButton">Download original</a></div><!-- end detailMediaToolbar -->';
	$previous = $galleryItems[$position - 1] ?? []; $next = $galleryItems[$position + 1] ?? [];
	$view = new DownloadView($request);
	$view->values = [
		'set_id' => 301, 'row_id' => $object->id, 'table' => 'ca_objects', 'representation_id' => $rep->id,
		'previous_item_id' => $previous['item'] ?? 0, 'previous_row_id' => $previous['object'] ?? 0, 'previous_representation_id' => $previous['representation'] ?? 0,
		'next_item_id' => $next['item'] ?? 0, 'next_row_id' => $next['object'] ?? 0, 'next_representation_id' => $next['representation'] ?? 0,
		'rep' => '<img src="/synthetic/gallery-'.$rep->id.'.jpg" alt="Synthetic gallery image" width="1300" height="700">', 'repToolBar' => $galleryToolbar
	];
	$renderGallery = function () { ob_start(); include dirname(__DIR__).'/views/Gallery/set_item_rep_html.php'; return ob_get_clean(); };
	$html = $renderGallery->call($view);
	$document = new DOMDocument();
	$document->loadHTML('<!doctype html><html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	$xpath = new DOMXPath($document);
	$class = static function ($name) { return 'contains(concat(" ", normalize-space(@class), " "), " '.$name.' ")'; };
	$toolbars = $xpath->query('//div['.$class('tadl-image-toolbar').']');
	checkDownload($toolbars->length === 1, 'Gallery item '.$item['item'].' must render exactly one current image toolbar.');
	$zoom = $xpath->query('//a['.$class('zoomButton').']');
	checkDownload($zoom->length === 1 && $zoom->item(0)->getAttribute('onclick') === $zoomCallback && str_contains($zoom->item(0)->textContent, 'Media viewer'), 'Gallery lost or rebuilt the native media-view callback.');
	$compare = $xpath->query('//a['.$class('compare_link').']');
	checkDownload($compare->length === 1 && $compare->item(0)->getAttribute('data-id') === 'representation:'.$rep->id, 'Gallery compare action retained the previous representation.');
	$menus = $xpath->query('//details['.$class('tadl-image-downloads').']');
	checkDownload($menus->length === 1, 'Gallery download menu was missing or duplicated.');
	$formats = [];
	foreach ($xpath->query('.//a', $menus->item(0)) as $link) {
		checkDownload(parse_url($link->getAttribute('href'), PHP_URL_PATH) === '/ImageDownload/Download', 'Gallery download stopped using the checked endpoint.');
		parse_str((string)parse_url($link->getAttribute('href'), PHP_URL_QUERY), $params);
		checkDownload(($params['object_id'] ?? '') === (string)$object->id && ($params['representation_id'] ?? '') === (string)$rep->id, 'Gallery download retained a different selected image.');
		$formats[] = $params['format'] ?? '';
	}
	sort($formats);
	checkDownload($formats === ($item['format'] === 'tiff' ? ['jpg', 'pdf', 'tiff'] : ['jpg', 'pdf']), 'Gallery download formats did not follow the selected original.');
	checkDownload($xpath->query('//a['.$class('setsButton').' or '.$class('dlButton').']')->length === 0, 'Gallery retained replaced Lightbox/download actions.');
	$previousArrow = $xpath->query('//a['.$class('galleryDetailPrevious').']');
	$nextArrow = $xpath->query('//a['.$class('galleryDetailNext').']');
	checkDownload($previousArrow->length === 1 && $nextArrow->length === 1, 'Gallery navigation controls disappeared.');
	$galleryFixtures[] = [
		'item' => $item, 'previous' => $previous, 'next' => $next, 'zoomUrl' => $zoomUrl,
		'zoomCallback' => $zoom->item(0)->getAttribute('onclick'),
		'previousCallback' => $previousArrow->item(0)->getAttribute('onclick'), 'nextCallback' => $nextArrow->item(0)->getAttribute('onclick'),
		'scripts' => array_map(static function ($script) { return $script->textContent; }, iterator_to_array($xpath->query('//script')))
	];
}
$actionsScript = file_get_contents(dirname(__DIR__).'/views/Details/image_actions_script.php');
preg_match('~<script>(.*?)</script>~s', $actionsScript, $scriptMatch);
$js = <<<'JS'
const vm = require('node:vm');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const input = JSON.parse(fs.readFileSync(0, 'utf8'));
let current = null, visible = true, ready, clears = 0;
const target = {
 children: [],
 contains(toolbar) { return this.children.includes(toolbar); },
 replaceChildren() { this.children = []; clears++; },
 appendChild(toolbar) { this.children.push(toolbar); current = null; }
};
const document = {
 querySelector() { return visible ? { querySelectorAll() { return [...(current ? [current] : []), ...target.children]; } } : null; },
 getElementById() { return target; }
};
const context = vm.createContext({document, jQuery: () => ({ready(callback) {ready = callback;}})});
new vm.Script(input.actions).runInContext(context);
const first = {representation:101}, second = {representation:102};
current = first; ready();
assert.equal(target.children[0], first); // Preserve the actual node and its callbacks.
ready(); assert.equal(target.children[0], first); // Repeated ready callbacks keep controls.
current = second; context.tadlPlaceImageToolbar(true);
assert.deepEqual(target.children, [second]); // A new slide replaces stale download links.
context.tadlPlaceImageToolbar(true);
assert.equal(target.children.length, 0); // Switching to video removes image-only actions.
current = first; context.tadlPlaceImageToolbar(true);
assert.deepEqual(target.children, [first]);
visible = false; context.tadlPlaceImageToolbar(true);
assert.equal(target.children[0], first); // No object-detail page means no relocation.
assert.equal(clears, 4);

const loads = [], highlights = [], opened = [];
const galleryContext = vm.createContext({
 window: {},
 jQuery(selector) { return {
  load(url) { loads.push({selector, url}); return this; },
  data(key) { assert.equal(selector, '#syntheticMediaPanel'); assert.equal(key, 'reloadUrl'); return '/Gallery/301?set_item_id=22'; }
 }; },
 galleryHighlightThumbnail(id) { highlights.push(id); },
 caMediaPanel: {showPanel(url, callback) { opened.push({url, callback}); }, getPanelID() { return 'syntheticMediaPanel'; }}
});
const invoke = callback => new vm.Script('(function(){' + callback + '})()').runInContext(galleryContext);
for (const fixture of input.gallery) {
 // jQuery.load executes the new partial's scripts, replacing caGalleryNav.
 for (const script of fixture.scripts) new vm.Script(script).runInContext(galleryContext);
 opened.length = 0;
 invoke(fixture.zoomCallback);
 assert.equal(opened.length, 1);
 assert.equal(opened[0].url, fixture.zoomUrl);
 opened[0].callback();
 assert.equal(galleryContext.window.location, '/Gallery/301?set_item_id=22');
 for (const direction of ['previous', 'next']) {
  loads.length = 0; highlights.length = 0;
  invoke(fixture[direction + 'Callback']);
  const selected = fixture[direction];
  if (!selected.item) {
   assert.equal(loads.length, 0); assert.equal(highlights.length, 0); continue;
  }
  assert.equal(loads.length, 3);
  assert.deepEqual(highlights, ['galleryIcon' + selected.item]);
  const expected = [
   {selector:'#galleryDetailImageArea', path:'/Gallery/getSetItemRep', params:{item_id:selected.item, set_id:301}},
   {selector:'#galleryDetailObjectInfo', path:'/Gallery/getSetItemInfo', params:{item_id:selected.item, set_id:301}},
   {selector:'#caMediaPanelContentArea:visible', path:'/Detail/GetMediaOverlay', params:{context:'gallery', id:selected.object, representation_id:selected.representation, set_id:301, overlay:1}}
  ];
  for (let i = 0; i < expected.length; i++) {
   const url = new URL(loads[i].url, 'https://example.org');
   assert.equal(loads[i].selector, expected[i].selector);
   assert.equal(url.pathname, expected[i].path);
   for (const [key, value] of Object.entries(expected[i].params)) assert.equal(url.searchParams.get(key), String(value));
  }
 }
}
JS;
$process = proc_open([getenv('TADL_TEST_NODE') ?: 'node', '-e', $js], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
fwrite($pipes[0], json_encode(['actions' => $scriptMatch[1], 'gallery' => $galleryFixtures], JSON_THROW_ON_ERROR)); fclose($pipes[0]);
$output = stream_get_contents($pipes[1]); fclose($pipes[1]);
$errors = stream_get_contents($pipes[2]); fclose($pipes[2]);
checkDownload(proc_close($process) === 0, 'Image action or gallery callback regression failed: '.$errors.$output);
echo json_encode(['status' => 'passed', 'assertions' => $assertions, 'boundaries' => 'actual helper/controller/action script/gallery media partial; synthetic access/ACL/media/DOM APIs; Node.js gallery callbacks; real image bytes; '.($autoload ? 'real bundled Dompdf' : 'synthetic PDF renderer')], JSON_PRETTY_PRINT).PHP_EOL;
