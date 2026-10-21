<?php
/** Retired Lightbox routes stop before native controller loading; no app or database. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$checks = 0;
function disabledLightboxCheck($condition, $message) {
	$GLOBALS['checks']++;
	if (!$condition) { throw new RuntimeException($message); }
}
class AppController {
	static public $request;
	static function getInstance() { return new self(); }
	function getRequest() { return self::$request; }
}
class DisabledLightboxRequest {
	function __construct(public string $controller, public string $action, public string $method, public bool $loggedIn) {}
	function getController() { return $this->controller; }
	function getAction() { return $this->action; }
	function getRequestMethod() { return $this->method; }
	function isLoggedIn() { return $this->loggedIn; }
}
$theme = dirname(__DIR__);
require $theme.'/helpers/public_errors.php';
$directory = sys_get_temp_dir().'/tadl-disabled-lightbox-'.bin2hex(random_bytes(8));
mkdir($directory.'/controllers', 0700, true);
define('__CA_APP_DIR__', $directory);
file_put_contents($directory.'/controllers/LightboxController.php', '<?php $GLOBALS["nativeLightboxLoaded"] = true; class LightboxController { static function getReturnToResultsUrl($request) { return "/synthetic/native-return"; } }');
try {
	foreach (['Lightbox','lightbox','LIGHTBOX'] as $controller) {
		foreach (['Index','addItemForm','AjaxAddItem','ajaxSaveSetInfo','AjaxDeleteSet','Present','Share','AjaxReorderItems'] as $action) {
			foreach (['GET','POST','HEAD'] as $method) {
				foreach ([false,true] as $loggedIn) {
					AppController::$request = new DisabledLightboxRequest($controller,$action,$method,$loggedIn);
					$blocked = false;
					try { require $theme.'/controllers/LightboxController.php'; }
					catch (TadlPublicNotFoundException $error) { $blocked = tadlPublicErrorStatus($error) === 404; }
					disabledLightboxCheck($blocked && empty($GLOBALS['nativeLightboxLoaded']), 'Every Lightbox request must stop with 404 before native controller loading, regardless of action, method or authentication.');
				}
			}
		}
	}
	AppController::$request = new DisabledLightboxRequest('Detail','objects','GET',false);
	require $theme.'/controllers/LightboxController.php';
	disabledLightboxCheck(!empty($GLOBALS['nativeLightboxLoaded']) && LightboxController::getReturnToResultsUrl(AppController::$request) === '/synthetic/native-return', 'Native ResultContext may include the controller for static helpers without dispatching Lightbox.');
	disabledLightboxCheck(preg_match('/^disable_lightbox\s*=\s*1\s*$/m', file_get_contents($theme.'/conf/app.conf')) === 1, 'The native Lightbox feature must also be disabled in theme configuration.');
	$assets = file_get_contents($theme.'/conf/assets.conf');
	preg_match('/_default\s*=\s*\[([^]]*)\]/s', $assets, $default);
	disabledLightboxCheck(!str_contains($default[1], 'lightboxOrder'), 'Ordinary catalogue pages must not load unused lightbox ordering code.');
	echo "Disabled Lightbox passed: {$checks} assertions (all routes retired, native helper compatibility, configuration and assets).\n";
} finally {
	unlink($directory.'/controllers/LightboxController.php');
	rmdir($directory.'/controllers'); rmdir($directory);
}
