<?php
/** Native auth/ORM/URL boundaries; actual theme helpers, forms and result views. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	if ($severity === E_DEPRECATED && getenv('TADL_TEST_COMPOSER_AUTOLOAD') && str_contains($file, '/vendor/')) { return true; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$assertions = 0;
function checkUserFeature($condition, $message) {
	$GLOBALS['assertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function _t($text) { return $text; }
function caDisplayLightbox($request) { return $request->isLoggedIn() && !$request->config->get('disable_lightbox'); }
function caNavUrl($request, $module, $controller, $action, $params = []) { return '/'.$controller.'/'.$action.($params ? '?'.http_build_query($params) : ''); }
function caNavLink($request, $label, $class, $module, $controller, $action, $params = [], $attributes = []) {
	$attributeText = '';
	foreach ($attributes as $key => $value) { $attributeText .= ' '.htmlspecialchars($key).'="'.htmlspecialchars($value, ENT_QUOTES, 'UTF-8').'"'; }
	return '<a href="'.htmlspecialchars(caNavUrl($request, $module, $controller, $action, $params), ENT_QUOTES, 'UTF-8').'"'.$attributeText.'>'.$label.'</a>';
}
function caGenerateCSRFToken($request) { return 'synthetic-token'; }
function caGetOption($name, $options, $default) { return $options[$name] ?? $default; }
function caGetUserAccessValues($request) { return [1]; }
function caGetIconsConfig() { return new FeatureConfig(); }
function caDetailLink($request, $label, $class, $table, $id) { return '<a href="/Detail/objects/'.(int)$id.'">'.$label.'</a>'; }
class FeatureConfig {
	function __construct(public array $values = []) {}
	function get($key) {
		foreach ((array)$key as $name) { if (array_key_exists($name, $this->values)) { return $this->values[$name]; } }
		return null;
	}
	function getAssoc($key) { return []; }
}
class FeatureRequest {
	public FeatureConfig $config;
	function __construct(public bool $loggedIn = false, public bool $ajax = false) { $this->config = new FeatureConfig(['dontAllowRegistration' => 1, 'cache_timeout' => 0]); }
	function isLoggedIn() { return $this->loggedIn; }
	function isAjax() { return $this->ajax; }
}
class FeatureResult {
	private bool $done = false;
	function numHits() { return 1; }
	function seek($start) { $this->done = false; }
	function nextHit() { if ($this->done) { return false; } $this->done = true; return true; }
	function get($key, $options = []) {
		return match ($key) {
			'ca_objects.object_id' => 42,
			'ca_objects.idno' => 'SYNTHETIC.42',
			'ca_objects.preferred_labels' => 'Synthetic object',
			'ca_object_representations.media.medium' => '<img src="/synthetic.jpg" alt="">',
			default => null
		};
	}
	function getMediaTag($field, $version, $options) { return '<img src="/synthetic.jpg" alt="">'; }
	function getWithTemplate($template) { return ''; }
}
class ca_list_items {}
class ExternalCache {
	static public array $keys = [];
	static function contains($key, $group) { return false; }
	static function save($key, $value, $group, $timeout) { self::$keys[] = $key; }
}
class FeatureView {
	function __construct(public FeatureRequest $request, public array $values = []) {}
	function getVar($name) { return $this->values[$name] ?? null; }
	function render($file) { ob_start(); try { include dirname(__DIR__).'/views/'.$file; return ob_get_clean(); } catch (Throwable $error) { ob_end_clean(); throw $error; } }
}
require dirname(__DIR__).'/helpers/user_features.php';
$request = new FeatureRequest();
checkUserFeature(tadlUserMenu($request) === '' && tadlAddToLightboxLink($request, 42) === '', 'Anonymous visitors must have no account/login/lightbox controls.');
$request->loggedIn = true;
$menu = tadlUserMenu($request);
foreach (['My account', '/Lightbox/Index', '/LoginReg/profileForm', '/LoginReg/Logout'] as $value) {
	checkUserFeature(str_contains($menu, $value), 'Missing logged-in account entry: '.$value);
}
checkUserFeature(!str_contains($menu, 'LoginForm') && !str_contains($menu, 'register'), 'Account menu must not expose login or registration.');
$document = new DOMDocument(); $document->loadHTML($menu);
$xpath = new DOMXPath($document);
checkUserFeature($xpath->query('//a[@class="dropdown-toggle" and @href="/LoginReg/profileForm"]')->length === 1, 'Account menu must have a usable fallback and avoid the Bootstrap 3/jQuery bare-hash selector error.');
checkUserFeature($xpath->query('//ul[@role="menu"]//a[@role="menuitem"]')->length === 3, 'Account actions must support native keyboard menu navigation.');
$link = tadlAddToLightboxLink($request, 42);
$document = new DOMDocument(); $document->loadHTML($link);
$anchor = $document->getElementsByTagName('a')->item(0);
checkUserFeature($anchor->getAttribute('href') === '/Lightbox/addItemForm?object_id=42' && str_contains($anchor->getAttribute('onclick'), 'caMediaPanel.showPanel('), 'Lightbox link must target the native form with the current object.');
checkUserFeature(tadlAddToLightboxLink($request, 0) === '', 'Invalid object IDs must not get actions.');
$request->config->values['disable_lightbox'] = 1;
checkUserFeature(!str_contains(tadlUserMenu($request), 'Lightbox') && str_contains(tadlUserMenu($request), 'profileForm') && tadlAddToLightboxLink($request, 42) === '', 'Native disabled-Lightbox policy must keep profile/logout.');

foreach (['images', 'list'] as $viewName) {
	$keys = [];
	foreach ([false, true] as $loggedIn) {
		$request = new FeatureRequest($loggedIn);
		$view = new FeatureView($request, ['result' => new FeatureResult(), 'facets' => [], 'criteria' => [], 'key' => 'synthetic-key', 'start' => 0, 'row_id' => 0, 'options' => [], 'table' => 'ca_objects', 'primaryKey' => 'object_id', 'config' => $request->config, 'view' => $viewName]);
		$html = $view->render('Browse/browse_results_'.$viewName.'_html.php');
		checkUserFeature(!str_contains($html, 'tadl-add-lightbox') && !str_contains($html, '/Lightbox/addItemForm'), $viewName.': results must omit Lightbox actions for every session.');
		checkUserFeature(!str_contains($html, 'Login to') && !str_contains($html, 'LoginForm'), $viewName.': result leaked a login prompt.');
		if ($loggedIn) {
			$document = new DOMDocument(); $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
			$xpath = new DOMXPath($document);
			checkUserFeature($xpath->query('//div[contains(@class,"ItemText")]//a[@href="/Detail/objects/42" and text()="Synthetic object"]')->length === 1, $viewName.': removing the action must preserve the object title link.');
		}
		$keys[] = end(ExternalCache::$keys);
	}
	checkUserFeature($keys[0] !== $keys[1], $viewName.': native session separation must remain in result caches.');
}
foreach ([false, true] as $ajax) {
	$request = new FeatureRequest(false, $ajax);
	$html = (new FeatureView($request, ['message' => '<script>synthetic</script>']))->render('LoginReg/form_login_html.php');
	$document = new DOMDocument(); $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	$xpath = new DOMXPath($document);
	checkUserFeature($xpath->query('//form[@method="POST" and @action="/LoginReg/login"]')->length === 1 && $xpath->query('//input[@name="csrfToken" and @value="synthetic-token"]')->length === 1, 'Login must keep native POST action and CSRF.');
	checkUserFeature($xpath->query('//input[@autocomplete="username"]')->length === 1 && $xpath->query('//input[@type="password" and @autocomplete="current-password"]')->length === 1, 'Login should work with password managers.');
	checkUserFeature(!str_contains($html, 'registerForm') && str_contains($html, 'Forgot your password?') && !str_contains($html, '<script>synthetic</script>'), 'Login pilot must hide registration, retain reset and escape errors.');
}
$header = file_get_contents(dirname(__DIR__).'/views/pageFormat/pageHeader.php');
checkUserFeature(str_contains($header, 'tadlUserMenu($this->request)') && !str_contains($header, 'LoginForm') && !str_contains($header, 'RegisterForm'), 'Shared header must render the gated menu without anonymous auth links.');
echo 'User features passed: '.$assertions.' assertions (synthetic native session/URL/result boundaries).'.PHP_EOL;
