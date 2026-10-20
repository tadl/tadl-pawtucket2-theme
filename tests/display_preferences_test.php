<?php
/** Validated display policy and real picker/link markup; no application or catalogue. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
define('pString', 'string'); define('pInteger', 'integer');
require_once(__DIR__.'/../helpers/collection_contents.php');
require_once(__DIR__.'/../views/Browse/tadl_result_helpers.php');
function _t($text, ...$values) { foreach ($values as $i => $value) { $text = str_replace('%'.($i+1), (string)$value, $text); } return $text; }
class DisplayRequest {
	function __construct(public array $params = [], public array $cookies = []) {}
	function getParameter($key, $type) { return $this->params[$key] ?? null; }
	function getParameters($sources) { return $sources === ['COOKIE'] ? $this->cookies : $this->params; }
	function getController() { return 'Search'; }
	function getAction() { return 'objects'; }
	function isAjax() { return false; }
}
function caNavUrl($request, $module, $controller, $action, $params = []) {
	return '/'.($controller === '*' ? $request->getController() : $controller).'/'.($action === '*' ? $request->getAction() : $action).($params ? '?'.http_build_query($params) : '');
}
function caNavLink($request, $label, $class, $module, $controller, $action, $params, $attributes = []) {
	$html = '<a class="'.htmlspecialchars($class, ENT_QUOTES).'" href="'.htmlspecialchars(caNavUrl($request, $module, $controller, $action, $params), ENT_QUOTES).'"';
	foreach ($attributes as $key => $value) { $html .= ' '.$key.'="'.htmlspecialchars($value, ENT_QUOTES).'"'; }
	return $html.'>'.$label.'</a>';
}
function displayFixture($request, $total) {
	$key = 'synthetic-'.$total;
	$size = tadlResultPageSize($request, $total);
	$start = min(max(0, (int)($request->params['s'] ?? 0)), max(0, ((int)ceil($total / $size)-1)*$size));
	$view = tadlDisplayView($request);
	$mode = tadlCollectionContentsMode($request);
	$html = '<section data-view="'.$view.'" data-collection-view="'.$mode.'"><div class="tadl-collection-results-toolbar"><div class="tadl-collection-results-intro"><div class="tadl-collection-results-heading"><h2>Collection items</h2><p>'.$total.' items</p></div>';
	$html .= tadlBrowsePerPagePicker($request, $total, $size, $key, $view, 'Title', 'asc', false);
	$html .= '</div><div class="tadl-collection-results-options"><button type="button" class="tadl-results-action">Options</button>'.tadlBrowseResultViewControls($request, ['images'=>[], 'list'=>[]], $view, $key, 'Title', 'asc', $size, false).'</div>';
	$html .= tadlBrowseResultPager($request, $total, $start, $size, $key, $view, 'Title', 'asc', false, 'top').'</div>';
	$html .= '<a data-tadl-collection-view="'.($mode === 'flat' ? 'hierarchy' : 'flat').'" href="?collection_view='.($mode === 'flat' ? 'hierarchy' : 'flat').'">Switch collection view</a> <a href="/Detail/collections/43">Another collection</a>';
	$html .= '<div class="tadl-results-grid">';
	for ($i=$start; $i<min($total, $start+$size); $i++) { $html .= '<article data-result><a href="/Detail/objects/'.($i+1).'">Synthetic item '.($i+1).'</a></article>'; }
	return $html.'</div></section>';
}
if (($argv[1] ?? '') === '--render') {
	$input = json_decode(stream_get_contents(STDIN), true, flags:JSON_THROW_ON_ERROR);
	echo displayFixture(new DisplayRequest($input['params'], $input['cookies']), $input['total']);
	exit;
}
$checks = 0;
function displayCheck($value, $message) { $GLOBALS['checks']++; if (!$value) { throw new RuntimeException($message); } }
$request = new DisplayRequest();
displayCheck(tadlResultPageSize($request) === 18 && tadlDisplayView($request) === 'images' && tadlCollectionContentsMode($request) === 'flat', 'Fresh visitors need eighteen tiles and flat collections.');
foreach (['images'=>[9,18,27,36,45,54,63,72], 'list'=>[25,50,100]] as $displayView => $choices) {
	foreach ([1, 8, 9, 10, 18, 25, 27, 50, 71, 72, 73, 100, 101, 144] as $total) {
		foreach (array_merge($choices, ['all']) as $choice) {
			$request = new DisplayRequest(['view'=>$displayView, 'per_page' => $choice]);
			$size = tadlResultPageSize($request, $total);
			displayCheck($size <= max($choices) && $size <= $total, 'Page sizes must obey the total and view limit.');
			$html = displayFixture($request, $total);
			$document = new DOMDocument(); $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING); $xpath = new DOMXPath($document);
			foreach ($xpath->query('//select/option') as $option) {
				$value = $option->getAttribute('value');
				displayCheck($value === 'all' ? $total <= max($choices) : (in_array((int)$value, $choices, true) && (int)$value < $total), 'Picker offered an excessive count or incomplete step.');
			}
			displayCheck($xpath->query('//select/option[@selected]')->length === 1, 'Picker must reflect the effective page size, including All for small results.');
			displayCheck($xpath->query('//select/option[@value="all"]')->length === ($total <= max($choices) ? 1 : 0), 'All must appear only within the view limit.');
			displayCheck($xpath->query('//form[@method="get"]//input[@name="s" and @value="0"]')->length === 1, 'Changing page size must restart at the beginning using GET.');
		}
	}
}
foreach (['999999', '-9', '0', 'all<script>', ['18'], '24'] as $bad) {
	displayCheck(tadlResultPageChoice(new DisplayRequest(['per_page'=>$bad])) === '18', 'Unsupported page sizes must be rejected.');
}
$cookie = json_encode(['view'=>'list', 'collection_view'=>'hierarchy', 'list_per_page'=>'50', 'images_per_page'=>'18', 'search'=>'must not be restored']);
$request = new DisplayRequest([], ['tadlDisplayPreferences'=>$cookie]);
displayCheck(tadlDisplayView($request) === 'list' && tadlCollectionContentsMode($request) === 'hierarchy' && tadlResultPageSize($request) === 50, 'Validated preferences must survive a new request without URL parameters.');
displayCheck(!isset(tadlDisplayPreferences($request)['search']), 'Cookies must not restore query/scope state.');
$request->params = ['view'=>'images', 'collection_view'=>'flat', 'per_page'=>'27'];
displayCheck(tadlDisplayView($request) === 'images' && tadlCollectionContentsMode($request) === 'flat' && tadlResultPageSize($request) === 27, 'Explicit URL choices must override remembered defaults.');
displayCheck(tadlResultPageSize(new DisplayRequest([], ['tadlDisplayPreferences'=>'{"images_per_page":"all"}']), 1000) === 18, 'All must never become a remembered default, including old or crafted cookies.');
foreach (['invalid', 'null', '[]', str_repeat('x',513), '{"view":["list"],"images_per_page":999999}'] as $bad) {
	displayCheck(tadlDisplayPreferences(new DisplayRequest([], ['tadlDisplayPreferences'=>$bad])) === [], 'Malformed cookies must safely fall back.');
}
echo "Display preferences passed: {$checks} assertions.\n";
