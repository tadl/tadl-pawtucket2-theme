<?php
/** Exercise the actual native URL parser without bootstrapping a database. */
aidCheck(is_file($dispatcher_path), 'Native dispatcher path must exist.');
$routing_files = [
	'BaseObject.php', 'ApplicationError.php', 'Controller/Request/RequestHTTP.php',
	'Controller/Router.php', 'Controller/Response/ResponseHTTP.php',
	'AccessRestrictions.php', 'ApplicationPluginManager.php'
];
$routing_directories = [
	'Controller', 'Controller/Request', 'Controller/Response',
	'app-controllers', 'app-plugins', 'app-plugins/FindingAid', 'app-plugins/FindingAid/controllers'
];
foreach ($routing_directories as $path) { mkdir($directory.'/'.$path, 0700); }
foreach ($routing_files as $path) { file_put_contents($directory.'/'.$path, '<?php'); }
$GLOBALS['aidRoutingCleanup'] = function () use ($directory, $routing_files, $routing_directories) {
	foreach ($routing_files as $path) { unlink($directory.'/'.$path); }
	foreach (array_reverse($routing_directories) as $path) { rmdir($directory.'/'.$path); }
};
class BaseObject {}
require $dispatcher_path;
define('__CA_THEME_DIR__', dirname(__DIR__, 2));
$routing_request = new class extends AidRequest {
	public string $path;
	public array $route = [];
	function getPathInfo() { return $this->path; }
	function getScriptName() { return 'index.php'; }
	function getBaseUrlPath() { return ''; }
	function setIsApplicationPlugin($value) { $this->route['plugin'] = $value; }
	function setModulePath($value) { $this->route['module'] = $value; }
	function getModulePath() { return $this->route['module']; }
	function setController($value) { $this->route['controller'] = $value; }
	function setAction($value) { $this->route['action'] = $value; }
	function setActionExtra($value) { $this->route['extra'] = $value; }
	function setControllerUrl($value) { $this->route['url'] = $value; }
	function setParameter($key, $value, $source) { $this->params[$key] = $value; }
};
$routing_request->config->values['controllers_directory'] = $directory.'/app-controllers';
$routing_request->config->values['application_plugins'] = $directory.'/app-plugins';
$dispatcher = (new ReflectionClass(RequestDispatcher::class))->newInstanceWithoutConstructor();
$routing_request->path = '/FindingAid/Download/collection_id/42';
$dispatcher->setRequest($routing_request);
aidCheck($routing_request->route['module'] === 'FindingAid/controllers' && $routing_request->route['controller'] === 'Download', 'Old route must reproduce the native plugin collision.');
$routing_request->route = []; $routing_request->params = [];
$routing_request->path = '/CollectionFindingAid/Download/collection_id/42';
$dispatcher->setRequest($routing_request);
aidCheck($routing_request->route['module'] === '' && $routing_request->route['controller'] === 'CollectionFindingAid' && $routing_request->route['action'] === 'Download', 'Finding aid must resolve to the theme controller, outside the bundled plugin.');
aidCheck($routing_request->params === ['collection_id' => '42'], 'Native path parsing must preserve the selected collection.');
aidCheck(is_file(__CA_THEME_DIR__.'/controllers/'.$routing_request->route['controller'].'Controller.php') && method_exists($routing_request->route['controller'].'Controller', $routing_request->route['action']), 'Native resolution must identify an existing theme controller and action.');
