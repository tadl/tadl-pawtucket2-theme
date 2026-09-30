<?php
/** Run with php tests/media_preference_controller_test.php; synthetic boundary stubs only. */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});

// The real controller includes its native parent. Supply only that dependency
// locally so this check never boots the app or connects to its database.
$testLibDirectory = sys_get_temp_dir().'/tadl-preference-controller-'.bin2hex(random_bytes(6));
mkdir($testLibDirectory.'/pawtucket', 0700, true);
file_put_contents($testLibDirectory.'/pawtucket/BasePawtucketController.php', '<?php');
define('__CA_LIB_DIR__', $testLibDirectory);
register_shutdown_function(function () use ($testLibDirectory) {
	unlink($testLibDirectory.'/pawtucket/BasePawtucketController.php');
	rmdir($testLibDirectory.'/pawtucket');
	rmdir($testLibDirectory);
});

class BasePawtucketController {
	public $request;
	public $response;
	public function __construct($request, $response) { $this->request = $request; $this->response = $response; }
}
class PreferenceTestRequest {
	public function __construct(public array $post, private string $method = 'POST', private string $base = '/archive') {}
	public function getRequestMethod() { return $this->method; }
	public function getBaseUrlPath() { return $this->base; }
	public function getParameters($sources) {
		if ($sources !== array('POST')) { throw new RuntimeException('Controller must read POST only.'); }
		return $this->post;
	}
}
class PreferenceTestResponse {
	public int $status = 200;
	public array $headers = array();
	public ?string $location = null;
	public function addHeader($name, $value) { $this->headers[$name] = $value; }
	public function setHTTPResponseCode($code, $message) { $this->status = $code; }
	public function setRedirect($location, $code = 302) { $this->location = $location; $this->status = $code; }
}
$GLOBALS['preferenceWrites'] = array();
function tadlSetMediaPreference($request, $mode) {
	if ($GLOBALS['preferenceWriteFailure'] ?? false) { return false; }
	$GLOBALS['preferenceWrites'][] = $mode;
	return true;
}
function caValidateCSRFToken($request, $token) { return $token === 'synthetic-valid-csrf'; }
function caNavUrl($request, $module, $controller, $action) { return rtrim($request->getBaseUrlPath(), '/').'/'.$controller.'/'.$action; }
require dirname(__DIR__).'/controllers/MediaPreferenceController.php';

$assertions = 0;
function checkPreferenceController($condition, $message) {
	$GLOBALS['assertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function runPreferenceRequest($overrides = array(), $method = 'POST', $base = '/archive', $writeFailure = false) {
	$GLOBALS['preferenceWrites'] = array();
	$GLOBALS['preferenceWriteFailure'] = $writeFailure;
	$post = array_replace(array('tadlMediaPreference' => 'only', 'csrfToken' => 'synthetic-valid-csrf', 'returnTo' => $base.'/Collections/index?view=list'), $overrides);
	$request = new PreferenceTestRequest($post, $method, $base);
	$response = new PreferenceTestResponse();
	(new MediaPreferenceController($request, $response))->Set();
	return $response;
}

foreach (array('GET', 'HEAD', 'PUT') as $method) {
	$response = runPreferenceRequest(array(), $method);
	checkPreferenceController($response->status === 405 && $response->headers['Allow'] === 'POST', $method.': missing method restriction.');
	checkPreferenceController(!$GLOBALS['preferenceWrites'] && $response->location === null, $method.': mutated or redirected.');
}
foreach (array(null, '', 'unknown', 'ONLY', array('only'), true, 1) as $mode) {
	$response = runPreferenceRequest(array('tadlMediaPreference' => $mode));
	checkPreferenceController($response->status === 400 && !$GLOBALS['preferenceWrites'] && $response->location === null, 'Invalid mode was accepted.');
}
foreach (array(null, '', '0', 'invalid', array('synthetic-valid-csrf'), true) as $token) {
	$response = runPreferenceRequest(array('csrfToken' => $token));
	checkPreferenceController($response->status === 403 && !$GLOBALS['preferenceWrites'] && $response->location === null, 'Invalid token mutated preference.');
}
foreach (array('only', 'all') as $mode) {
	$response = runPreferenceRequest(array('tadlMediaPreference' => $mode));
	checkPreferenceController($response->status === 303 && $GLOBALS['preferenceWrites'] === array($mode), $mode.': valid POST did not update preference once.');
	checkPreferenceController($response->location === '/archive/Collections/index?view=list', $mode.': valid return path changed.');
}
$response = runPreferenceRequest(array(), 'POST', '/archive', true);
checkPreferenceController($response->status === 500 && $response->location === null && !$GLOBALS['preferenceWrites'], 'Failed persistence was reported as a successful redirect.');

$validTargets = array('/archive', '/archive/', '/archive/Collections/Index/page/2/view/list', '/archive/Detail/objects/42', '/archive/MultiSearch/Index?search=synthetic+%3Csample%3E+%26+test', '/archive/Search/objects?search=https%3A%2F%2Fexample.org%2Fsynthetic#results');
foreach ($validTargets as $target) {
	$response = runPreferenceRequest(array('returnTo' => $target));
	checkPreferenceController($response->location === $target && $response->status === 303, 'Valid local URL rejected: '.$target);
}
$invalidTargets = array(null, array('/archive/Collections/Index'), '', 'Collections/Index', 'https://example.org/', '//example.org/archive', '\\example.org', '/archive\\example.org', "/archive/Index\r\nLocation: https://example.org", '/archive/%0d%0aIndex', '/archive/%5cIndex', '/%2fexample.org/archive', '/archive/../other', '/archive/%2e%2e/other', '/archive/%252e%252e/other', '/archive/%255cIndex', '/%252fexample.org/archive', '/archive/%250dIndex', '/archive/%2525252e%2525252e/other', '/other/Collections', '/archive-other/Index');
foreach ($invalidTargets as $target) {
	$response = runPreferenceRequest(array('returnTo' => $target));
	checkPreferenceController($response->location === '/archive/Front/Index' && $response->status === 303, 'Unsafe return URL was not replaced.');
}
$response = runPreferenceRequest(array('returnTo' => '/Collections/Index'), 'POST', '');
checkPreferenceController($response->location === '/Collections/Index', 'Root installation rejected valid local URL.');
$response = runPreferenceRequest(array('returnTo' => '//example.org/'), 'POST', '');
checkPreferenceController($response->location === '/Front/Index', 'Root installation accepted protocol-relative URL.');

echo json_encode(array('status' => 'passed', 'assertions' => $assertions, 'controller' => 'actual theme MediaPreferenceController', 'dependencies' => 'synthetic request/response/CSRF/persistence boundaries'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
