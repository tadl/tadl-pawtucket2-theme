<?php
/** Synthetic sets, errors and logs only; never boots or writes to an application. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$assertions = 0;
function hardeningCheck($value, $message) {
	$GLOBALS['assertions']++;
	if (!$value) { throw new RuntimeException($message); }
}
define('pInteger', 1);
class HardeningConfig { function get($name) { return $GLOBALS['galleryType']; } }
class HardeningRequest {
	public bool $ajax = false, $download = false, $cleanURLs = false;
	public string $base = '/catalog';
	function __construct(public string $action, public array $params = [], public array $access = [1], public string $controller = 'Gallery') {}
	function getController() { return $this->controller; }
	function getAction() { return $this->action; }
	function getParameter($name, $type) { hardeningCheck($type === pInteger, 'Set ID must use native parsing.'); return $this->params[$name] ?? 0; }
	function isAjax() { return $this->ajax; }
	function isDownload() { return $this->download; }
}
function caGetGalleryConfig() { return new HardeningConfig(); }
function caGetUserAccessValues($request) { return $request->access; }
class ca_lists { function getItemIDFromList($list, $code) { return $code === 'public_presentation' ? 8 : 0; } }
class ca_sets {
	private array $row;
	function load($id) { $GLOBALS['galleryLoadCount']++; $this->row = $GLOBALS['sets'][$id] ?? []; return (bool)$this->row; }
	function get($field) { return $this->row[$field] ?? null; }
	function isReadable($request) { return $this->row['readable']; }
}
class AppController {
	static $request;
	static $response = null;
	static function getInstance() { return new self(); }
	static function instanceExists() { return self::$response !== null; }
	function getRequest() { return self::$request; }
	function getResponse() { return self::$response; }
}
class HardeningResponse {
	public int $status = 404;
	public bool $redirect = false;
	public string $type = 'text/html';
	public array $content = ['notifications' => 'Synthetic notice', 'default' => 'Page is not available'], $headers = [];
	function getHTTPResponseCode() { return $this->status; }
	function isRedirect() { return $this->redirect; }
	function getContentType() { return $this->type; }
	function setContent($html, $segment = 'default') { $this->content[$segment] = $html; }
	function addHeader($name, $value) { $this->headers[$name] = $value; }
}
class AssetLoadManager {
	static array $registered = [];
	static function register($package, $library) { self::$registered[] = [$package, $library]; }
}
$root = dirname(__DIR__);
$directory = sys_get_temp_dir().'/tadl-hardening-'.bin2hex(random_bytes(8));
mkdir($directory.'/models', 0700, true); mkdir($directory.'/controllers', 0700);
define('__CA_MODELS_DIR__', $directory.'/models'); define('__CA_APP_DIR__', $directory);
foreach (['ca_sets', 'ca_lists'] as $model) { file_put_contents($directory.'/models/'.$model.'.php', '<?php'); }
file_put_contents($directory.'/controllers/GalleryController.php', '<?php $GLOBALS["nativeGalleryLoaded"] = true;');
$GLOBALS['galleryType'] = 'public_presentation';
$GLOBALS['galleryLoadCount'] = 0;
$GLOBALS['sets'] = [42 => ['type_id' => 8, 'access' => 1, 'deleted' => 0, 'readable' => true]];
require $root.'/helpers/gallery_access.php';
require $root.'/helpers/export_diagnostics.php';
$process = null;
try {
	// Native ResultContext includes this shim from Detail to call the static
	// return-to-gallery helper. No Gallery action is being dispatched there.
	foreach (['Detail'=>'objects', 'Search'=>'objects', 'Lightbox'=>'Index'] as $controller=>$action) {
		AppController::$request = new HardeningRequest($action, [], [1], $controller);
		require $root.'/controllers/GalleryController.php';
		hardeningCheck(!empty($GLOBALS['nativeGalleryLoaded']) && $GLOBALS['galleryLoadCount'] === 0, 'Including Gallery for '.$controller.' navigation must load native helpers without treating the current object as a gallery set.');
	}
	foreach (['42', 'getSetInfo', 'getSetInfoAsJSON', 'getSetItemRep', 'getSetItemInfo', 'ajaxGetMapItem'] as $action) {
		AppController::$request = new HardeningRequest($action, ['set_id' => 42]);
		require $root.'/controllers/GalleryController.php';
		hardeningCheck(!empty($GLOBALS['nativeGalleryLoaded']), $action.': public presentations must retain the native controller.');
		foreach (['type_id' => 9, 'access' => 0, 'deleted' => 1, 'readable' => false] as $field => $value) {
			$before = $GLOBALS['sets'][42]; $GLOBALS['sets'][42][$field] = $value;
			$denied = false;
			try { require $root.'/controllers/GalleryController.php'; }
			catch (TadlPublicNotFoundException $error) { $denied = true; }
			hardeningCheck($denied, $action.': '.$field.' must deny before native action dispatch.');
			$GLOBALS['sets'][42] = $before;
		}
	}
	foreach ([new HardeningRequest('getSetInfo'), new HardeningRequest('999'), new HardeningRequest('42', [], [0])] as $request) {
		$denied = false;
		try { tadlGuardGalleryRequest($request); } catch (TadlPublicNotFoundException $error) { $denied = true; }
		hardeningCheck($denied, 'Absent/unknown/denied sets must fail closed.');
	}
	$GLOBALS['galleryType'] = '';
	$denied = false;
	try { tadlGuardGalleryRequest(new HardeningRequest('42')); } catch (TadlPublicNotFoundException $error) { $denied = true; }
	hardeningCheck($denied, 'Missing gallery type must not expose arbitrary sets.');
	tadlGuardGalleryRequest(new HardeningRequest('Index', ['set_id' => 42]));
	$GLOBALS['galleryType'] = 'public_presentation';
	hardeningCheck(tadlPublicErrorStatus(new RuntimeException('synthetic internal detail')) === 500, 'Unexpected failures need HTTP 500.');
	hardeningCheck(tadlPublicErrorStatus(null, true) === 404, 'Native routing failures need HTTP 404.');
	hardeningCheck(tadlPublicErrorStatus(new TadlPublicNotFoundException()) === 404, 'Denied sets need indistinguishable HTTP 404.');
	hardeningCheck(tadlPublicErrorStatus(null, false, 503) === 503, 'Existing error status must survive.');
	ini_set('error_log', $directory.'/diagnostics.log');
	tadlLogExportFailure('image', ['object_id' => 42, 'representation_id' => 8, 'format' => 'jpg', 'password' => 'SYNTHETIC_SECRET'], new RuntimeException('SYNTHETIC_SECRET /private/synthetic-path'));
	tadlLogExportFailure('finding_aid', ['collection_id' => 42, 'format' => 'pdf']);
	$logs = file($directory.'/diagnostics.log', FILE_IGNORE_NEW_LINES);
	hardeningCheck(count($logs) === 2 && !str_contains(join('', $logs), 'SYNTHETIC_SECRET') && !str_contains(join('', $logs), '/private/'), 'Diagnostic logs must omit messages, paths and unknown context.');
	$entry = json_decode(substr($logs[0], strpos($logs[0], '{')), true, 512, JSON_THROW_ON_ERROR);
	hardeningCheck($entry['object_id'] === 42 && $entry['representation_id'] === 8 && $entry['exception'] === 'RuntimeException' && isset($entry['line']), 'Operator logs must identify the failure and safe code location.');
	$view = new class {
		public $request;
		function __construct() { $this->request = new HardeningRequest('Show'); }
		function getVar($key) { throw new RuntimeException('An ordinary error view must not read referrer or error details.'); }
		function render() { ob_start(); include dirname(__DIR__).'/views/system/error_html.php'; return ob_get_clean(); }
	};
	function _t($text) { return $text; }
	function caNavUrl($request, $module, $controller, $action) { return $request->base.($request->cleanURLs ? '' : '/index.php').'/'.$controller.'/'.$action; }
	hardeningCheck(str_contains($view->render(), 'Browse collections'), 'Ordinary native errors must provide recovery without rendering diagnostic values.');
	foreach ([false, true] as $cleanURLs) {
		$request = new HardeningRequest('unknown-synthetic-page'); $request->cleanURLs = $cleanURLs;
		$response = AppController::$response = new HardeningResponse();
		hardeningCheck(tadlPrepareNotFoundPage($request), 'Native fallback 404s must be decorated after dispatch.');
		hardeningCheck($response->status === 404 && $response->content['notifications'] === 'Synthetic notice', 'Decorating errors must preserve status and other native response segments.');
		hardeningCheck($response->headers === ['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex, nofollow'], 'Fallback 404s need no-store/noindex headers.');
		$dom = new DOMDocument(); $dom->loadHTML($response->content['default'], LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
		$xpath = new DOMXPath($dom);
		$prefix = '/catalog'.($cleanURLs ? '' : '/index.php');
		$form = $xpath->query('//form[@role="search"]')->item(0);
		hardeningCheck($form && $form->getAttribute('action') === $prefix.'/MultiSearch/Index' && $form->getAttribute('method') === 'get', 'Recovery search must use native GET multisearch URLs in both URL modes.');
		hardeningCheck($xpath->query('//label[@for="tadl-error-search-input"]')->length === 1 && $xpath->query('//input[@name="search" and @id="tadl-error-search-input"]')->length === 1, 'Recovery search must have a unique associated label and native field name.');
		hardeningCheck($xpath->query('//a[@href="'.$prefix.'/Collections/Index"]')->length === 1 && $xpath->query('//a[@href="'.$prefix.'/Search/advanced/objects"]')->length === 1 && $xpath->query('//a[@href="'.$prefix.'/Front/Index"]')->length === 1, 'All recovery destinations must use native subdirectory-safe links.');
		hardeningCheck($xpath->query('//h1')->length === 1 && !str_contains($response->content['default'], 'Page is not available'), 'The plain fallback needs one useful page heading.');
	}
	foreach (['success', 'server-error', 'ajax', 'download', 'json', 'redirect'] as $case) {
		$request = new HardeningRequest('Index'); $response = AppController::$response = new HardeningResponse();
		if ($case === 'success') { $response->status = 200; }
		if ($case === 'server-error') { $response->status = 503; }
		if ($case === 'ajax') { $request->ajax = true; }
		if ($case === 'download') { $request->download = true; }
		if ($case === 'json') { $response->type = 'application/json'; }
		if ($case === 'redirect') { $response->redirect = true; }
		$assetsBefore = AssetLoadManager::$registered;
		hardeningCheck(!tadlPrepareNotFoundPage($request) && $response->content['default'] === 'Page is not available' && !$response->headers && AssetLoadManager::$registered === $assetsBefore, $case.': preserve successful, non-404 and non-page responses without loading error CSS.');
	}
	hardeningCheck(AssetLoadManager::$registered === [['pawtucket', 'errorcss'], ['pawtucket', 'errorcss']], 'Only rendered 404s should register the scoped, versioned error stylesheet.');
	AppController::$response = null;
	hardeningCheck(str_contains(file_get_contents($root.'/views/pageFormat/pageHeader.php'), 'tadlPrepareNotFoundPage($this->request)'), 'The shared page header must decorate native fallback responses.');

	// Exercise actual HTTP headers and the error view, independent of the normal header.
	$router = '<?php require '.var_export($root.'/helpers/public_errors.php', true).';'
		.' $case = $_GET["case"] ?? "failure"; $g_errored = $case === "route";'
		.' $e = $case === "missing" ? new TadlPublicNotFoundException("SYNTHETIC_SECRET") : new RuntimeException("SYNTHETIC_SECRET");'
		.' $errstr = $errfile = "SYNTHETIC_SECRET"; $request_params = ["password2" => "SYNTHETIC_SECRET"];'
		.' if ($case === "unavailable") { http_response_code(503); }'
		.' require '.var_export($root.'/views/system/fatal_error_html.php', true).';';
	file_put_contents($directory.'/router.php', $router);
	$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
	$address = stream_socket_get_name($socket, false); fclose($socket);
	$process = proc_open([PHP_BINARY, '-S', $address, $directory.'/router.php'], [0 => ['file', '/dev/null', 'r'], 1 => ['file', $directory.'/server.log', 'a'], 2 => ['file', $directory.'/server.log', 'a']], $pipes);
	$deadline = microtime(true) + 5;
	do { $ready = @stream_socket_client('tcp://'.$address, $errno, $error, .1); if (!$ready) { usleep(20000); } } while (!$ready && microtime(true) < $deadline);
	hardeningCheck((bool)$ready, 'Synthetic HTTP server did not start.'); fclose($ready);
	foreach (['failure' => 500, 'route' => 404, 'missing' => 404, 'unavailable' => 503] as $case => $status) {
		$body = file_get_contents('http://'.$address.'/?case='.$case, false, stream_context_create(['http' => ['ignore_errors' => true]]));
		$responseHeaders = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : (get_defined_vars()['http_response_header'] ?? []);
		$headers = join('\n', $responseHeaders);
		hardeningCheck(str_contains($responseHeaders[0], (string)$status), $case.': wrong HTTP status.');
		hardeningCheck(str_contains($headers, 'Cache-Control: no-store') && str_contains($headers, 'X-Robots-Tag: noindex, nofollow') && str_contains($headers, "default-src 'none'") && str_contains($headers, "form-action 'self'"), $case.': standalone error headers must permit only same-origin recovery forms.');
		hardeningCheck(!str_contains($body, 'SYNTHETIC_SECRET') && !str_contains($body, 'Request parameters') && str_contains($body, 'tadl-error-content'), $case.': public errors must omit all diagnostics and provide recovery.');
		hardeningCheck($status === 404 ? str_contains($body, '/index.php/MultiSearch/Index') && str_contains($body, 'Browse collections') : !str_contains($body, '<form'), $case.': standalone 404s need working fallback links/search, while server errors keep simple recovery.');
	}
	echo 'Production hardening passed: '.$assertions.' assertions (synthetic Gallery routing, HTTP errors and private diagnostics).'.PHP_EOL;
} finally {
	if (is_resource($process)) { proc_terminate($process); proc_close($process); }
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
	rmdir($directory);
}

$contribute_config = file_get_contents(dirname(__DIR__).'/conf/contribute.conf');
if (!preg_match('/^enabled\s*=\s*0\s*$/m', $contribute_config)) { throw new RuntimeException('Contribution forms must be disabled at the native controller gate.'); }
