<?php
/** Actual guards/controller entry points and HTTP errors; no database or application bootstrap. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('pString', 1); define('pInteger', 2);
$checks = 0;
function exportCheck($value, $message) { $GLOBALS['checks']++; if (!$value) { throw new RuntimeException($message); } }
class ExportRequest {
	function __construct(public string $controller, public array $sources = []) { $this->config = new ExportConfig(); }
	public $config;
	function isAjax() { return false; }
	function isLoggedIn() { return false; }
	function getController() { return $this->controller; }
	function getParameter($key, $type) {
		foreach (['GET', 'POST', 'PATH'] as $source) {
			if (!isset($this->sources[$source][$key])) { continue; }
			$value = $this->sources[$source][$key];
			return $type === pInteger ? (is_numeric($value) ? (int)$value : null) : (is_string($value) ? rawurldecode($value) : null);
		}
		return '';
	}
}
class ExportConfig { function get($name) { return false; } }
function caGetBrowseConfig() { return new ExportConfig(); }
function caSetPageCSSClasses($classes) {}
class AppController {
	static $request;
	static function getInstance() { return new self(); }
	static function instanceExists() { return false; }
	function getRequest() { return self::$request; }
}
require_once(dirname(__DIR__).'/helpers/result_exports.php');
function exportDispatch($controller, $sources) {
	AppController::$request = $request = new ExportRequest($controller, $sources);
	$response = new stdClass();
	if (!defined('__CA_LIB_DIR__')) { define('__CA_LIB_DIR__', __CA_APP_DIR__.'/lib'); define('__CA_MODELS_DIR__', __CA_APP_DIR__.'/models'); }
	if (class_exists($controller.'Controller', false)) { tadlGuardResultExportRequest($request); return new ($controller.'Controller')($request, $response); }
	if (in_array($controller, ['Search', 'Browse'], true)) {
		// Re-enter the actual theme shim as the native dispatcher does on each request.
		require dirname(__DIR__).'/controllers/'.$controller.'Controller.php';
	} else {
		require_once dirname(__DIR__).'/controllers/'.$controller.'Controller.php';
	}
	$class = $controller.'Controller';
	return new $class($request, $response);
}
if (defined('TADL_EXPORT_HTTP')) {
	$parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
	if (($parts[0] ?? '') === 'index.php') { array_shift($parts); }
	$controller = array_shift($parts); array_shift($parts); // action
	$params = [];
	for ($i = 0; $i + 1 < count($parts); $i += 2) { $params[rawurldecode($parts[$i])] = rawurldecode($parts[$i + 1]); }
	try {
		exportDispatch($controller, ['GET' => $_GET, 'POST' => $_POST, 'PATH' => $params]);
		header('X-Synthetic-Native-Work: yes'); echo 'Synthetic normal catalogue response';
	} catch (TadlResultExportDisabledException $e) {
		require dirname(__DIR__).'/views/system/fatal_error_html.php';
	}
	return;
}
$directory = sys_get_temp_dir().'/tadl-result-exports-'.bin2hex(random_bytes(8));
foreach (['controllers','helpers','models','lib/Browse'] as $folder) { mkdir($directory.'/'.$folder, 0700, true); }
define('__CA_APP_DIR__', $directory);
$native = <<<'PHP'
<?php
$GLOBALS['nativeLoaded'] = true;
class SearchController {
 function __construct(&$request, &$response, $view_paths = null) { $GLOBALS['nativeConstructed'] = true; }
}

PHP;
file_put_contents($directory.'/controllers/SearchController.php', $native);
file_put_contents($directory.'/controllers/FindController.php', '<?php require_once __DIR__."/SearchController.php"; class FindController extends SearchController { public $request; public $response; public $view; public $opo_config; function __construct(&$request,&$response,$paths=null) { parent::__construct($request,$response,$paths); $this->request=$request; $this->response=$response; $this->view=new class { function setVar($name,$value) {} }; } }');
foreach (['models/ca_collections.php','helpers/browseHelpers.php'] as $file) { file_put_contents($directory.'/'.$file, '<?php'); }
file_put_contents($directory.'/lib/Browse/ObjectBrowse.php', '<?php class ObjectBrowse {}');
$process = null;
try {
	// The first legacy request must be refused before even loading native classes.
	$blocked = false;
	try { exportDispatch('Search', ['PATH' => ['view' => 'pdf', 'download' => '1', 'export_format' => '_pdf_checklist']]); }
	catch (TadlResultExportDisabledException $e) { $blocked = true; }
	exportCheck($blocked && empty($GLOBALS['nativeLoaded']) && !class_exists('SearchController', false), 'Search exports must stop before native controller loading.');
	foreach (['Search', 'Browse', 'CollectionContents', 'PlaceContents', 'FamilyContents'] as $controller) {
		foreach (['GET', 'POST', 'PATH'] as $source) {
			foreach ([['view' => 'pdf'], ['view' => 'xlsx'], ['view' => 'pptx'], ['export_format' => '_pdf_thumbnails'], ['export_format' => 'basic_excel'], ['download' => '1']] as $params) {
				$GLOBALS['nativeConstructed'] = false; $blocked = false;
				try { exportDispatch($controller, [$source => $params]); }
				catch (TadlResultExportDisabledException $e) { $blocked = true; exportCheck(tadlPublicErrorStatus($e) === 410, 'Retired exports must have HTTP 410.'); }
				exportCheck($blocked && !$GLOBALS['nativeConstructed'], $controller.'/'.$source.': export reached native construction/search.');
			}
		}
		foreach ([[], ['view' => 'images'], ['view' => 'list'], ['view' => 'map'], ['view' => 'timelineData'], ['download' => '0', 'export_format' => '']] as $params) {
			$GLOBALS['nativeConstructed'] = false;
			exportDispatch($controller, ['GET' => $params]);
			exportCheck($GLOBALS['nativeConstructed'], $controller.': normal requests must retain native dispatch.');
		}
	}
	foreach (['Detail', 'ImageDownload', 'CollectionFindingAid', 'FindingAid', 'DocumentText', 'Lightbox', 'MultiSearch'] as $controller) {
		tadlGuardResultExportRequest(new ExportRequest($controller, ['GET' => ['view' => 'pdf', 'download' => '1', 'export_format' => 'synthetic']]));
		exportCheck(true, 'Other download and account controllers must retain their own policy.');
	}
	// Preserve native precedence: a GET request can select normal results over a PATH view.
	exportDispatch('Search', ['GET' => ['view' => 'images'], 'PATH' => ['view' => 'pdf']]);
	$router = '<?php define("TADL_EXPORT_HTTP", true); define("__CA_APP_DIR__", '.var_export($directory, true).'); require '.var_export(__FILE__, true).';';
	file_put_contents($directory.'/router.php', $router);
	$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
	$address = stream_socket_get_name($socket, false); fclose($socket);
	$process = proc_open([PHP_BINARY, '-S', $address, $directory.'/router.php'], [0 => ['pipe', 'r'], 1 => ['file', $directory.'/server.log', 'a'], 2 => ['file', $directory.'/server.log', 'a']], $pipes);
	exportCheck(is_resource($process), 'Local HTTP server did not start.'); fclose($pipes[0]);
	for ($i = 0; $i < 100; $i++) { if ($ready = @fsockopen(explode(':', $address)[0], (int)explode(':', $address)[1])) { fclose($ready); break; } usleep(20000); }
	$cases = [
		['GET', '/Browse/subjects/view/pdf/download/1/export_format/_pdf_checklist', '', 410],
		['GET', '/index.php/Search/objects?view=xlsx&export_format=basic_excel', '', 410],
		['POST', '/Browse/objects', 'view=pptx&export_format=basic_ppt', 410],
		['GET', '/CollectionContents/Objects/collection_id/42/view/pdf', '', 410],
		['GET', '/PlaceContents/Objects/place_id/42?export_format=SYNTHETIC_SECRET', '', 410],
		['GET', '/FamilyContents/Objects/entity_id/42/download/1', '', 410],
		['HEAD', '/Browse/objects/view/pdf', '', 410],
		['GET', '/Search/objects/view/images', '', 200],
		['GET', '/Browse/subjects/view/list', '', 200],
		['GET', '/CollectionContents/Objects/collection_id/42/view/images', '', 200],
	];
	foreach ($cases as [$method, $path, $payload, $status]) {
		$options = ['method' => $method, 'ignore_errors' => true, 'timeout' => 5];
		if ($payload !== '') { $options += ['header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => $payload]; }
		$body = file_get_contents('http://'.$address.$path, false, stream_context_create(['http' => $options]));
		$headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : (get_defined_vars()['http_response_header'] ?? []);
		$joined = join("\n", $headers);
		exportCheck(str_contains($headers[0], (string)$status), $method.' '.$path.': incorrect HTTP status.');
		if ($status === 410) {
			exportCheck(!str_contains($joined, 'X-Synthetic-Native-Work') && !str_contains($joined, 'Content-Disposition'), 'Disabled exports must not reach native construction or send an attachment.');
			exportCheck(str_contains($joined, 'X-Robots-Tag: noindex, nofollow') && str_contains($joined, 'Cache-Control: no-store'), 'Disabled exports need error/crawler response headers.');
			exportCheck(!str_contains($body, 'SYNTHETIC_SECRET'), 'Export refusal must not reflect request data.');
			if ($method !== 'HEAD') { exportCheck(str_contains($body, 'Result exports are no longer available'), 'Legacy links need a clear retirement message.'); }
		} else { exportCheck(str_contains($joined, 'X-Synthetic-Native-Work: yes'), 'Normal results must still reach native construction.'); }
	}
	print "Result exports passed: {$checks} assertions (early guards, native constructor boundaries, parameter sources, unaffected routes and real HTTP 410 responses).\n";
} finally {
	if (is_resource($process)) { proc_terminate($process); proc_close($process); }
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
	rmdir($directory);
}
