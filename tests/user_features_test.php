<?php
/** Native auth/ORM/URL boundaries; actual theme helpers, forms and result views. */
define('pString', 'string');
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
function _t($text, ...$values) { foreach ($values as $i => $value) { $text = str_replace('%'.($i + 1), (string)$value, $text); } return $text; }
function caDisplayLightbox($request) { return $request->isLoggedIn() && !$request->config->get('disable_lightbox'); }
function caNavUrl($request, $module, $controller, $action, $params = []) { return $request->urlPrefix.'/'.$controller.'/'.$action.($params ? '?'.http_build_query($params) : ''); }
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
function caGetInfoForBrowseType($type) { return ['sortBy' => ['Identifier' => 'ca_objects.idno']]; }
function caDetailLink($request, $label, $class, $table, $id, $params = null, $attributes = []) {
	$extra = '';
	foreach ((array)$attributes as $key => $value) { $extra .= ' '.$key.'="'.htmlspecialchars($value, ENT_QUOTES, 'UTF-8').'"'; }
	return '<a href="/Detail/objects/'.(int)$id.'"'.$extra.'>'.$label.'</a>';
}
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
	function getParameter($key, $type) { return null; }
	public string $urlPrefix = '';
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
	public static string $identifier = 'SYNTHETIC.42', $label = 'Synthetic object', $extra = '';
	private bool $done = false;
	function numHits() { return 1; }
	function getPrimaryKeyValues() { return [42]; }
	function seek($start) { $this->done = false; }
	function nextHit() { if ($this->done) { return false; } $this->done = true; return true; }
	function get($key, $options = []) {
		return match ($key) {
			'ca_objects.object_id' => 42,
			'ca_objects.idno' => self::$identifier,
			'ca_objects.preferred_labels' => self::$label,
			'ca_object_representations.media.medium' => '<img src="/synthetic.jpg" alt="">',
			default => null
		};
	}
	function getMediaTag($field, $version, $options) { return '<img src="/synthetic.jpg" alt="">'; }
	function getWithTemplate($template) { return self::$extra; }
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
if (($argv[1] ?? '') === '--citation-fixtures') {
	$fixtures = [];
	foreach (['object-citation' => ['', 42], 'object-citation-subdirectory' => ['/catalogue/index.php', 73]] as $name => [$prefix, $id]) {
		$request = new FeatureRequest(); $request->urlPrefix = $prefix;
		// Use the actual detail view; replace only native template expansion, not its controls.
		$html = (new FeatureView($request, ['item' => new FeatureDetailObject($id)]))->render('Details/ca_objects_default_html.php');
		$html = preg_replace('/\{\{\{representationViewer\}\}\}/', '<img src="/synthetic.svg" alt="Synthetic catalogue photograph" width="600" height="400" style="max-width:100%;height:auto">', $html);
		$html = preg_replace('/<H1>.*?<\/H1>/s', '<h1><span class="tadl-object-title">Synthetic object '.$id.'</span></h1>', $html);
		$html = preg_replace('/<H2>.*?<\/H2>/s', '<h2>Still image</h2>', $html);
		$fixtures[$name] = preg_replace('/\{\{\{.*?\}\}\}/s', '', $html);
	}
	print json_encode($fixtures, JSON_THROW_ON_ERROR); exit;
}
$request = new FeatureRequest();
checkUserFeature(tadlUserMenu($request) === '', 'Anonymous visitors must have no account/login controls.');
$request->loggedIn = true;
$menu = tadlUserMenu($request);
foreach (['My account', '/LoginReg/profileForm', '/LoginReg/Logout'] as $value) {
	checkUserFeature(str_contains($menu, $value), 'Missing logged-in account entry: '.$value);
}
checkUserFeature(!str_contains($menu, 'LoginForm') && !str_contains($menu, 'register') && !str_contains(strtolower($menu), 'lightbox'), 'Account menu must not expose login, registration or lightboxes, even when native lightboxes are enabled.');
$document = new DOMDocument(); $document->loadHTML($menu);
$xpath = new DOMXPath($document);
checkUserFeature($xpath->query('//a[@class="dropdown-toggle" and @href="/LoginReg/profileForm"]')->length === 1, 'Account menu must have a usable fallback and avoid the Bootstrap 3/jQuery bare-hash selector error.');
checkUserFeature($xpath->query('//ul[@role="menu"]//a[@role="menuitem"]')->length === 2, 'Profile/logout actions must support native keyboard menu navigation.');
$request->config->values['disable_lightbox'] = 1;
checkUserFeature(!str_contains(tadlUserMenu($request), 'Lightbox') && str_contains(tadlUserMenu($request), 'profileForm'), 'Disabled-Lightbox policy must keep profile/logout.');

// Use the actual object view, including an object without media or readable bundles.
foreach ([[false, 0], [false, 1], [true, 1], [true, 255], [true, 99], [true, 0]] as [$loggedIn, $userclass]) {
	$request = new FeatureRequest($loggedIn, false, $userclass);
	$staff = $loggedIn && $userclass === 0;
	$link = tadlProvidenceObjectLink($request, 42);
	checkUserFeature(($link !== '') === $staff, 'Editor link must require both a login and native full-access user class.');
	$collection_link = tadlProvidenceCollectionLink($request, 73);
	checkUserFeature(($collection_link !== '') === $staff, 'Collection editor link must use the same login/full-access staff gate.');
	$html = (new FeatureView($request, ['item' => new FeatureDetailObject()]))->render('Details/ca_objects_default_html.php');
	checkUserFeature(!str_contains(strtolower($html), 'lightbox'), 'Object details must omit lightbox buttons, callbacks and wrappers for every user class, even with native lightboxes enabled.');
	$document = new DOMDocument(); $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	$xpath = new DOMXPath($document);
	$links = $xpath->query('//div[contains(@class,"tadl-object-info")]//a[contains(@class,"tadl-providence-object")]');
	checkUserFeature($links->length === ($staff ? 1 : 0), 'Object metadata column must render the editor action only for staff.');
	checkUserFeature($staff || !str_contains($html, 'collections.tadl.org'), 'Nonstaff HTML must omit the editor URL entirely.');
	checkUserFeature($staff || !str_contains($html, 'tadl-object-staff-actions'), 'Nonstaff HTML must omit the staff action wrapper.');
	$citation = $xpath->query('//div[contains(@class,"tadl-object-info")]/*[last()][@data-tadl-citation]');
	checkUserFeature($citation->length === 1, 'Citation must sit at the bottom of the object metadata column for every account class and objects without media or readable bundles.');
	checkUserFeature($citation->item(0)->hasAttribute('hidden') && $citation->item(0)->getAttribute('data-citation-path') === '/Detail/objects/42', 'Citation must start hidden without JavaScript and target the displayed object without navigation state.');
	checkUserFeature($citation->item(0)->getAttribute('data-citation-credit') === 'Image courtesy of Traverse Area District Library Local History Collection', 'Citation must use the FAQ attribution exactly.');
	checkUserFeature($xpath->query('//*[@data-tadl-citation]//button[@type="button" and @data-tadl-copy-citation]//*[contains(@class,"fa-copy") and @aria-hidden="true"]')->length === 1, 'Copy citation must use a native button and decorative standard copy icon.');
	checkUserFeature($xpath->query('//*[@data-tadl-citation]//*[@data-tadl-citation-status and @role="status"]')->length === 1, 'Copy feedback must be announced without moving focus.');
	checkUserFeature($xpath->query('//*[@data-tadl-citation]//textarea[@data-tadl-citation-text and @readonly and @hidden and @aria-label="Citation"]')->length === 1, 'Unavailable clipboard fallback must have a labelled, initially hidden readonly text field.');
	if ($staff) {
		$collection_document = new DOMDocument(); $collection_document->loadHTML($collection_link);
		$collection_anchor = $collection_document->getElementsByTagName('a')->item(0);
		checkUserFeature($collection_anchor->getAttribute('href') === 'https://collections.tadl.org/index.php/editor/collections/CollectionEditor/Edit/collection_id/73', 'Collection editor must use its own route and the selected archival unit ID.');
		checkUserFeature($collection_anchor->getAttribute('target') === '_blank' && $collection_anchor->getAttribute('rel') === 'noopener noreferrer' && str_contains($collection_anchor->textContent, '(opens in a new tab)'), 'Collection shortcut must retain new-tab safety and the accessible hint.');
		$anchor = $links->item(0);
		checkUserFeature($anchor->getAttribute('href') === 'https://collections.tadl.org/index.php/editor/objects/ObjectEditor/Edit/Screen49/object_id/42', 'Editor URL must use the currently displayed object ID.');
		checkUserFeature($anchor->getAttribute('target') === '_blank' && $anchor->getAttribute('rel') === 'noopener noreferrer', 'Editor must open a new tab without access to the opener.');
		checkUserFeature(str_contains($anchor->textContent, 'View in Providence') && str_contains($anchor->textContent, '(opens in a new tab)'), 'Editor action must have a useful label and accessible new-tab hint.');
	}
}
$prefixedRequest = new FeatureRequest(); $prefixedRequest->urlPrefix = '/catalogue/index.php';
$prefixedRequest->config->values['site_host'] = 'https://untrusted.example.invalid';
$prefixedCitation = (new FeatureView($prefixedRequest, ['item' => new FeatureDetailObject(73)]))->render('Details/ca_objects_default_html.php');
checkUserFeature(str_contains($prefixedCitation, 'data-citation-path="/catalogue/index.php/Detail/objects/73"') && !str_contains($prefixedCitation, 'untrusted.example.invalid'), 'Citation must retain native subdirectory/script routing without importing configured or request hosts.');
$objectNavigation = (new FeatureView(new FeatureRequest(), [
	'item' => new FeatureDetailObject(),
	'previousLink' => '<a href="/Detail/objects/41">Previous</a>',
	'nextLink' => '<a href="/Detail/objects/43">Next</a>'
]))->render('Details/ca_objects_default_html.php');
$document = new DOMDocument(); $document->loadHTML($objectNavigation, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($document);
checkUserFeature($xpath->query('//div[contains(@class,"tadl-object-info")]/*[1][self::nav and @class="tadl-record-nav"]')->length === 1, 'Object navigation must precede the heading inside the metadata column, without displacing the photo.');
foreach (['previous' => 41, 'next' => 43] as $direction => $id) {
	$label = 'Go to '.$direction.' object';
	checkUserFeature($xpath->query('//nav/a[@href="/Detail/objects/'.$id.'" and @title="'.$label.'" and @aria-label="'.$label.'"]')->length === 1, 'Object navigation must retain native destinations and contextual tooltips/accessible names.');
}
$request = new FeatureRequest(true, false, 0);
checkUserFeature(str_contains(tadlProvidenceObjectLink($request, '123'), '/object_id/123"'), 'Native numeric-string object IDs must be supported.');
checkUserFeature(str_contains(tadlProvidenceCollectionLink($request, '123'), '/collection_id/123"'), 'Native numeric-string collection IDs must be supported.');
checkUserFeature(tadlProvidenceRecordLink($request, 'ca_entities', 42) === '' && tadlProvidenceRecordLink($request, '../objects', 42) === '', 'Unsupported editor routes must not be rendered.');
foreach ([null, 0, -1, '', '42/other', '42" onclick="synthetic()', '42.5', [], true, 42.5] as $invalidID) {
	checkUserFeature(tadlProvidenceObjectLink($request, $invalidID) === '', 'Invalid object IDs must not get Providence actions.');
	checkUserFeature(tadlProvidenceCollectionLink($request, $invalidID) === '', 'Invalid collection IDs must not get Providence actions.');
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
FeatureResult::$identifier = '';
FeatureResult::$label = '';
FeatureResult::$extra = '<p>Synthetic extra information with <a href="/synthetic">a related link</a>.</p>';
foreach (['images', 'list'] as $viewName) {
	$request = new FeatureRequest(false);
	$html = (new FeatureView($request, ['result' => new FeatureResult(), 'facets' => [], 'criteria' => [], 'key' => 'synthetic-key', 'start' => 0, 'row_id' => 0, 'options' => [], 'table' => 'ca_objects', 'primaryKey' => 'object_id', 'config' => $request->config, 'view' => $viewName]))->render('Browse/browse_results_'.$viewName.'_html.php');
	$document = new DOMDocument(); $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	$xpath = new DOMXPath($document);
	checkUserFeature($xpath->query('//small/a')->length === 0, 'A missing identifier must not produce an empty link.');
	checkUserFeature($xpath->query('//a[@aria-label="View Untitled"]')->length === 1, 'A thumbnail needs a usable name even without a record label.');
	checkUserFeature($xpath->query('//details[@class="tadl-result-extra"]/summary')->length === 1 && !str_contains($html, 'onmouseover'), 'Additional information must have a native keyboard disclosure instead of hover-only content.');
}
$header = file_get_contents(dirname(__DIR__).'/views/pageFormat/pageHeader.php');
checkUserFeature(str_contains($header, 'tadlUserMenu($this->request)') && !str_contains($header, 'LoginForm') && !str_contains($header, 'RegisterForm'), 'Shared header must render the gated menu without anonymous auth links.');
echo 'User features passed: '.$assertions.' assertions (synthetic native session/URL/result boundaries).'.PHP_EOL;
