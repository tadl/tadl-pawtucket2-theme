<?php
/** Actual templates with synthetic native configuration/request/model boundaries. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$checks = 0;
function a11yCheck($value, $message) { $GLOBALS['checks']++; if (!$value) { throw new RuntimeException($message); } }
function _t($text, ...$values) { foreach ($values as $i => $value) { $text = str_replace('%'.($i + 1), (string)$value, $text); } return $text; }
function caNavUrl($request, $module, $controller, $action, $params = []) { return '/'.$controller.'/'.$action.($params ? '?'.http_build_query($params) : ''); }
function caNavLink($request, $label, $class, $module, $controller, $action) { return '<a href="'.caNavUrl($request, $module, $controller, $action).'">'.$label.'</a>'; }
function caDetailLink($request, $label, $class, $table, $id) { return '<a href="/Detail/objects/'.(int)$id.'">'.$label.'</a>'; }
function caGenerateCSRFToken($request) { return 'synthetic-token'; }
function caGetContactConfig() { return new A11yConfig(['check_security' => 1]); }
function caGetOption($key, $options, $default = null) { return $options[$key] ?? $default; }
function caGetLightboxDisplayName() { return ['singular' => 'lightbox', 'plural' => 'lightboxes']; }
function caHTMLHiddenInput($name, $attributes) { return '<input type="hidden" name="'.$name.'" value="synthetic-token">'; }
function caGetPageCSSClasses() { return !empty($GLOBALS['a11yFront']) ? 'class="front"' : (!empty($GLOBALS['a11yGallery']) ? 'class="gallery"' : ''); }
function caUcFirstUTF8Safe($text) { return ucfirst($text); }
function caGetBrowseTypes($options) { return ['objects' => ['displayName' => 'Objects', 'dontShowInBrowseMenu' => false], 'places' => ['displayName' => 'Places', 'dontShowInBrowseMenu' => false]]; }
function caGetBrowseConfig() { return new A11yConfig(['browseMenuFormat' => 'list']); }
class Session { static function getVar($key) { return null; } static function setVar($key, $value) {} }
class MetaTagManager {
	static function getWindowTitle() { return 'Synthetic accessibility fixture'; }
	static function addMeta(...$args) {} static function addMetaProperty(...$args) {}
	static function getHTML() { return ''; }
}
class TooltipManager { static function getLoadHTML() { return ''; } }
class AssetLoadManager {
	static function getLoadHTML(...$args) {
		return '<link rel="stylesheet" href="/native/bootstrap/css/bootstrap.css">'
			.'<link rel="stylesheet" href="/native/fontawesome/css/font-awesome.css">'
			.'<link rel="stylesheet" href="/themes/tadl/assets/pawtucket/css/main.css">'
			.'<link rel="stylesheet" href="/themes/tadl/assets/pawtucket/css/fonts.css">'
			.'<link rel="stylesheet" href="/themes/tadl/assets/pawtucket/css/theme.css">'
			.'<link rel="stylesheet" href="/themes/tadl/assets/pawtucket/css/accessibility.css">'
			.'<script src="/native/jquery/js/jquery.js"></script>'
			.'<script src="/native/jquery/jquery-ui/jquery-ui.js"></script>'
			.'<script src="/native/bootstrap/js/bootstrap.js"></script>'
			.'<script src="/native/ca/js/ca.genericpanel.js"></script>'
			.'<script src="/themes/tadl/assets/pawtucket/js/user-features.js"></script>'
			.'<script src="/themes/tadl/assets/pawtucket/js/lightbox-order.js"></script>'
			.'<script src="/themes/tadl/assets/pawtucket/js/gallery-navigation.js"></script>';
	}
}
define('pInteger', 1); define('pString', 2);
define('__NOTIFICATION_TYPE_ERROR__', 1); define('__NOTIFICATION_TYPE_WARNING__', 2);
class A11yConfig {
	function __construct(public array $values = []) {}
	function get($name) { foreach ((array)$name as $key) { if (isset($this->values[$key])) { return $this->values[$key]; } } return null; }
}
class A11yRequest {
	public A11yConfig $config;
	function __construct(public bool $ajax = false, public string $controller = 'Lightbox') { $this->config = new A11yConfig(['dontAllowRegistration' => true]); }
	function getParameter($key, ...$args) { return null; }
	function getParameters(...$args) { return []; }
	function getAction() { return 'Index'; }
	function getRequestUrl() { return '/Contact/Form'; }
	function getThemeUrlPath() { return '/themes/tadl'; }
	function getThemeDirectoryPath() { return dirname(__DIR__); }
	function isAjax() { return $this->ajax; }
	function isLoggedIn() { return false; }
	function getController() { return $this->controller; }
}
class A11yView {
	function __construct(public array $vars = [], public A11yRequest $request = new A11yRequest()) {}
	function getVar($key) { return $this->vars[$key] ?? null; }
	function render($path) {
		$file = dirname(__DIR__).'/views/'.$path;
		if (!is_file($file) && basename($path) === $path) { $file = dirname(__DIR__).'/views/mediaViewers/'.$path; }
		ob_start(); include $file; return ob_get_clean();
	}
}
class CookieOptionsManager {
	static function allow($category) { return $category === 'analytics'; }
	static function cookieManagerEnabled() { return false; }
	static function showBanner() { return false; }
}
class ca_set_items { function __construct($id) {} function get(...$args) { return '<img src="/synthetic.svg" alt="Synthetic gallery item">'; } }
class ca_objects {
	function getPrimaryMediaForIDs($ids, ...$args) { return array_fill_keys($ids, ['tags' => ['mediumlarge' => '<img src="/synthetic.svg" alt="Synthetic featured item">']]); }
	function getPreferredDisplayLabelsForIDs($ids) { return array_fill_keys($ids, 'Synthetic featured item'); }
}
require dirname(__DIR__).'/helpers/form_accessibility.php';
$fixtures = [];
function a11yDocument($html) {
	$document = new DOMDocument();
	$document->loadHTML('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Synthetic accessibility test</title></head><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	return new DOMXPath($document);
}
function a11yLabels($html) {
	$xpath = a11yDocument($html);
	$ids = [];
	foreach ($xpath->query('//*[@id]') as $node) { a11yCheck(!isset($ids[$node->getAttribute('id')]), 'Duplicate control/content ID.'); $ids[$node->getAttribute('id')] = true; }
	foreach ($xpath->query('//input[not(@type="hidden")]|//select|//textarea') as $control) {
		$id = $control->getAttribute('id');
		$labelled = $control->hasAttribute('aria-label') || $control->hasAttribute('aria-labelledby')
			|| $xpath->query('ancestor::label', $control)->length
			|| ($id && $xpath->query('//label[@for="'.$id.'"]')->length);
		a11yCheck($labelled, 'Control lacks an associated label: '.$control->getAttribute('name'));
		foreach (preg_split('/\s+/', trim($control->getAttribute('aria-describedby'))) as $description) {
			if ($description !== '') { a11yCheck(isset($ids[$description]), 'Error/help description target does not exist.'); }
		}
	}
	return $xpath;
}
foreach ([[], ['name' => true, 'email' => true, 'message' => true, 'security' => true, 'display_errors' => ['Enter the required information.']]] as $errors) {
	$html = (new A11yView(['errors' => $errors]))->render('Contact/form_html.php');
	$xpath = a11yLabels($html);
	foreach (['name', 'email', 'message', 'security'] as $field) {
		$input = $xpath->query('//*[@id="'.$field.'"]')->item(0);
		a11yCheck($input->hasAttribute('required'), 'Required fields need native required semantics.');
		a11yCheck($input->getAttribute('aria-invalid') === ($errors ? 'true' : ''), 'Invalid state must match actual validation.');
	}
	a11yCheck($xpath->query('//input[@id="email" and @type="email" and @autocomplete="email"]')->length === 1, 'Email purpose must be explicit.');
	$fixtures[$errors ? 'contact-errors' : 'contact'] = $html;
}
foreach (['', 'reset'] as $action) {
	$html = (new A11yView(['action' => $action, 'key' => 'synthetic-reset-key']))->render('LoginReg/form_reset_html.php');
	$xpath = a11yLabels($html);
	if ($action) { a11yCheck($xpath->query('//input[@type="password" and @autocomplete="new-password"]')->length === 2, 'New passwords must support password managers.'); }
	$fixtures[$action ? 'new-password' : 'password-recovery'] = $html;
}
$fixtures['login-dialog'] = (new A11yView([], new A11yRequest(true)))->render('LoginReg/form_login_html.php');
$fixtures['media-dialog'] = (new A11yView(['viewer' => 'TileViewer', 'hideOverlayControls' => true, 'viewerHTML' => '<img src="/synthetic.svg" alt="Synthetic enlarged image">']))->render('mediaViewers/viewerWrapper.php');
$xpath = a11yLabels($fixtures['login-dialog']);
a11yCheck($xpath->query('//button[@type="button" and @aria-label="Close dialog"]')->length === 1, 'Dialog close must be a named native button.');
$errors = array_fill_keys(['general', 'name', 'description', 'group_id', 'user', 'access'], null);
foreach (['set_list_html.php', 'set_detail_html.php'] as $file) {
	$source = file_get_contents(dirname(__DIR__).'/views/Lightbox/'.$file);
	a11yCheck(str_contains($source, 'class="btn btn-default bGear dropdown-toggle"') && !str_contains($source, 'class="glyphicon glyphicon-cog bGear"'), 'Lightbox options must be a named native button.');
}
$html = (new A11yView(['user_groups' => [['group_id' => 1, 'name' => 'Synthetic group', 'access' => 1]], 'users' => [['user_id' => 2, 'name' => 'Synthetic user', 'email' => 'example@example.org', 'access' => 1, 'owner' => false]]]))->render('Lightbox/set_access_html.php');
$xpath = a11yDocument($html);
a11yCheck($xpath->query('//a/button|//button/a')->length === 0 && $xpath->query('//button[contains(@class,"btn-xs")]')->length === 4, 'Access changes must use buttons without nested interactive controls.');
$fixtures['share-lightbox'] = (new A11yView(['display_name' => 'lightbox', 'errors' => $errors, 'user_groups' => [['group_id' => 7, 'name' => 'Synthetic group']]]))->render('Lightbox/form_share_set_html.php');
a11yLabels($fixtures['share-lightbox']);
$clip_html = (new A11yView(['player_name' => 'synthetic-player', 'annotation_list' => ['Synthetic clip', '<b>Second clip</b>'], 'annotation_times' => [[0, 5], [5, 10]]]))->render('Details/annotations_html.php');
$clip_xpath = a11yDocument($clip_html);
a11yCheck($clip_xpath->query('//button[@type="button" and contains(@class,"tadl-featured-clip")]')->length === 2 && $clip_xpath->query('//h2')->length === 1, 'Featured clips need named keyboard buttons and a section heading.');
$comments_html = (new A11yView(['item_id' => 9, 'tablename' => 'ca_set_items']))->render('Lightbox/ajax_comments.php');
$comments_html = str_replace('{{{item_id}}}', '9', $comments_html);
$comments_xpath = a11yLabels($comments_html);
a11yCheck($comments_xpath->query('//*[@role="alert"]')->length === 1 && $comments_xpath->query('//*[@role="status"]')->length === 1, 'Comments require error and save-status announcements.');
a11yCheck(substr_count($comments_html, '.fail(function') === 2 && !str_contains($comments_html, '.html(data.errors'), 'Comment writes require safe error messages and transport failure handling.');
$fixtures['cookies'] = (new A11yView([
	'config' => new A11yConfig(),
	'cookiesByCategory' => [
		'necessary' => ['title' => 'Necessary cookies', 'required' => true, 'cookies' => [['name' => 'Synthetic session', 'description' => 'Synthetic session description.']]],
		'analytics' => ['title' => 'Analytics cookies', 'cookies' => [['name' => 'Synthetic analytics', 'description' => 'Synthetic analytics description.']]],
	]
]))->render('Cookies/form_manage_html.php');
$xpath = a11yLabels($fixtures['cookies']);
a11yCheck($xpath->query('//fieldset/legend')->length === 1 && $xpath->query('//input[@type="radio" and @name="cookie_options_analytics"]')->length === 2, 'Cookie choices need native grouped radios and the existing submitted name.');
a11yCheck($xpath->query('//input[@name="cookie_options_analytics" and @checked and @value="1"]')->length === 1, 'Saved cookie choices must be preserved.');
foreach (['fname' => 'given-name', 'lname' => 'family-name', 'email' => 'email', 'password' => 'new-password'] as $field => $purpose) {
	$html = tadlAccessibleAccountControl('<label for="'.$field.'">Synthetic field</label><input type="hidden" name="keep" value="synthetic"><input name="'.$field.'" autocomplete="off" value="synthetic" />', $field, 'Synthetic validation error');
	$xpath = a11yLabels($html);
	$control = $xpath->query('//input[@id="'.$field.'"]')->item(0);
	a11yCheck($control->getAttribute('name') === $field && $control->getAttribute('autocomplete') === $purpose, 'Native submitted name must survive purpose annotations.');
	a11yCheck($control->getAttribute('aria-invalid') === 'true', 'Native fields must expose validation errors.');
}
$fixtures['gallery'] = (new A11yView(['set_id' => 7, 'set_items' => array_map(static fn($id) => ['item_id' => $id], range(1, 14)), 'section_name' => 'Gallery', 'label' => 'Synthetic gallery']))->render('Gallery/detail_html.php');
$fixtures['featured'] = '<h1>Synthetic featured collection</h1>'.(new A11yView(['access_values' => [1], 'featured_set_item_ids' => [1, 2, 3, 4]]))->render('Front/featured_set_slideshow_html.php');
$fixtures['notifications'] = '<h1>Synthetic notifications</h1>'.(new A11yView(['notifications' => [['type' => 1, 'message' => 'Synthetic error notice.'], ['type' => 3, 'message' => 'Synthetic informational notice.']]]))->render('pageFormat/notifications.php');
foreach ([1, 2, 3] as $id) {
	$fixtures['gallery-item-'.$id] = (new A11yView([
		'previous_item_id' => $id > 1 ? $id - 1 : 0, 'next_item_id' => $id < 3 ? $id + 1 : 0,
		'set_id' => 7, 'row_id' => $id, 'table' => 'ca_objects', 'representation_id' => 0,
		'previous_row_id' => $id - 1, 'next_row_id' => $id + 1,
		'previous_representation_id' => 0, 'next_representation_id' => 0,
		'rep' => '<img src="/synthetic.svg" alt="Synthetic gallery item '.$id.'">'
	]))->render('Gallery/set_item_rep_html.php');
}
$header = file_get_contents(dirname(__DIR__).'/views/pageFormat/pageHeader.php');
$footer = file_get_contents(dirname(__DIR__).'/views/pageFormat/pageFooter.php');
a11yCheck(str_contains($header, '<main id="main" tabindex="-1">') && str_contains($footer, '</main><!-- end main -->'), 'The skip target must be a focusable native main landmark.');
require dirname(__DIR__).'/helpers/media_preferences.php';
$fixtures['header'] = (new A11yView())->render('pageFormat/pageHeader.php');
$fixtures['footer'] = (new A11yView())->render('pageFormat/pageFooter.php');
$GLOBALS['a11yFront'] = true;
$fixtures['front-header'] = (new A11yView([], new A11yRequest(false, 'Front')))->render('pageFormat/pageHeader.php');
$GLOBALS['a11yFront'] = false;
$GLOBALS['a11yGallery'] = true;
$fixtures['gallery-header'] = (new A11yView([], new A11yRequest(false, 'Gallery')))->render('pageFormat/pageHeader.php');
$GLOBALS['a11yGallery'] = false;
$order_buttons = (new A11yView())->render('Lightbox/order_buttons_html.php');
$fixtures['lightbox-order'] = '<h1>Synthetic lightbox</h1><p id="tadl-lightbox-order-status" role="status"></p><div id="lbSetResultLoadContainer" data-order-url="/Lightbox/AjaxReorderItems/csrfToken/synthetic-token" data-order-success="Item order saved." data-order-error="Could not save item order. The previous order has been restored." data-order-boundary="The item is already at this end of the list.">';
foreach ([1, 2, 3] as $id) { $fixtures['lightbox-order'] .= '<div id="row-'.$id.'"><h2>Synthetic item '.$id.'</h2>'.$order_buttons.'</div>'; }
$fixtures['lightbox-order'] .= '</div><script>jQuery(function($){$("#lbSetResultLoadContainer").sortable({start:function(){tadlRememberLightboxOrder(this);},update:function(){tadlSaveLightboxOrder(this);}});});</script>';
if (($argv[1] ?? '') === '--fixtures') { print json_encode($fixtures, JSON_THROW_ON_ERROR); }
else { print "Accessibility passed: {$checks} assertions (actual forms, errors, cookie controls, gallery and native account fields).\n"; }
