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
define('__CA_BUNDLE_ACCESS_READONLY__', 1);
function caGetBundleAccessLevel($table, $bundle) { return 0; }
function caObjectRepresentationThumbnails($request, $representationID, $object, $options) { return []; }
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
class FeatureUser {
	function __construct(public int $userclass = 1) {}
	function isStandardUser() { return $this->userclass === 0; }
}
class FeatureRequest {
	public FeatureConfig $config;
	public FeatureUser $user;
	function __construct(public bool $loggedIn = false, public bool $ajax = false, int $userclass = 1) {
		$this->config = new FeatureConfig(['dontAllowRegistration' => 1, 'cache_timeout' => 0]);
		$this->user = new FeatureUser($userclass);
	}
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
function caGetPlaceholder($type, $key) { return ''; }
class ca_list_items {
	function load($id) { return false; }
	function get($field) { return ''; }
}
class FeatureDetailObject {
	function __construct(private int $id = 42) {}
	function getPrimaryKey() { return $this->id; }
	function getWithTemplate($template, $options = []) { return $template === '^ca_objects.idno' ? 'SYNTHETIC.'.$this->id : ''; }
}
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

// Use the actual object view, including an object without media or readable bundles.
foreach ([[false, 0], [false, 1], [true, 1], [true, 255], [true, 99], [true, 0]] as [$loggedIn, $userclass]) {
	$request = new FeatureRequest($loggedIn, false, $userclass);
	$staff = $loggedIn && $userclass === 0;
	$link = tadlProvidenceObjectLink($request, 42);
	checkUserFeature(($link !== '') === $staff, 'Editor link must require both a login and native full-access user class.');
	$html = (new FeatureView($request, ['item' => new FeatureDetailObject()]))->render('Details/ca_objects_default_html.php');
	$document = new DOMDocument(); $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	$xpath = new DOMXPath($document);
	$links = $xpath->query('//div[contains(@class,"tadl-object-info")]//a[contains(@class,"tadl-providence-object")]');
	checkUserFeature($links->length === ($staff ? 1 : 0), 'Object metadata column must render the editor action only for staff.');
	checkUserFeature($staff || !str_contains($html, 'collections.tadl.org'), 'Nonstaff HTML must omit the editor URL entirely.');
	checkUserFeature($staff || !str_contains($html, 'tadl-object-staff-actions'), 'Nonstaff HTML must omit the staff action wrapper.');
	if ($staff) {
		$anchor = $links->item(0);
		checkUserFeature($anchor->getAttribute('href') === 'https://collections.tadl.org/index.php/editor/objects/ObjectEditor/Edit/Screen49/object_id/42', 'Editor URL must use the currently displayed object ID.');
		checkUserFeature($anchor->getAttribute('target') === '_blank' && $anchor->getAttribute('rel') === 'noopener noreferrer', 'Editor must open a new tab without access to the opener.');
		checkUserFeature(str_contains($anchor->textContent, 'View in Providence') && str_contains($anchor->textContent, '(opens in a new tab)'), 'Editor action must have a useful label and accessible new-tab hint.');
	}
}
$request = new FeatureRequest(true, false, 0);
checkUserFeature(str_contains(tadlProvidenceObjectLink($request, '123'), '/object_id/123"'), 'Native numeric-string object IDs must be supported.');
foreach ([null, 0, -1, '', '42/other', '42" onclick="synthetic()', '42.5', [], true, 42.5] as $invalidID) {
	checkUserFeature(tadlProvidenceObjectLink($request, $invalidID) === '', 'Invalid object IDs must not get Providence actions.');
}

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
	checkUserFeature(!$keys[0] && !$keys[1], $viewName.': result HTML must not be persisted across users.');
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
