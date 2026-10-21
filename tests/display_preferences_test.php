<?php
/** Validated display policy and real picker/link markup; no application or catalogue. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
define('pString', 'string'); define('pInteger', 'integer');
require_once(__DIR__.'/../helpers/collection_contents.php');
require_once(__DIR__.'/../views/Browse/tadl_result_helpers.php');
function _t($text, ...$values) { foreach ($values as $i => $value) { $text = str_replace('%'.($i+1), (string)$value, $text); } return $text; }
class DisplayRequest {
	function __construct(public array $params = [], public array $cookies = [], public string $controller = 'Search', public string $action = 'objects') {}
	function getParameter($key, $type) { return $this->params[$key] ?? null; }
	function getParameters($sources) { return $sources === ['COOKIE'] ? $this->cookies : $this->params; }
	function getController() { return $this->controller; }
	function getAction() { return $this->action; }
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
	echo displayFixture(new DisplayRequest($input['params'], $input['cookies'], $input['controller'] ?? 'Search', $input['action'] ?? 'objects'), $input['total']);
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
foreach (['open','closed'] as $state) {
	displayCheck(tadlDisplayPreferences(new DisplayRequest([], ['tadlDisplayPreferences'=>json_encode(['filters'=>$state])])) === ['filters'=>$state], 'Explicit filter visibility must be a validated display preference.');
}
foreach (['invalid', true, ['open']] as $state) {
	displayCheck(tadlDisplayPreferences(new DisplayRequest([], ['tadlDisplayPreferences'=>json_encode(['filters'=>$state])])) === [], 'Invalid filter preferences must fall back to the responsive default.');
}
$cookies = ['tadlDisplayPreferences'=>json_encode(['images_per_page'=>'27', 'collections_images_per_page'=>'90', 'list_per_page'=>'50'])];
foreach ([['Collections','Index','tiles'], ['Search','collections','images'], ['Browse','collections','images']] as [$controller,$action,$view]) {
	$request = new DisplayRequest(['view'=>$view], $cookies, $controller, $action);
	displayCheck(tadlResultPageSize($request, 144) === 90 && tadlPreferredPageChoice($request,$view) === '90', 'Every collection-record grid must use its independent remembered size.');
	$html = displayFixture($request,144);
	$document = new DOMDocument(); $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING); $xpath = new DOMXPath($document);
	displayCheck(array_map(fn($node)=>(int)$node->getAttribute('value'), iterator_to_array($xpath->query('//select/option'))) === range(9,90,9), 'Collection grids must offer nine-item steps through 90.');
	displayCheck($xpath->query('//form[@data-tadl-page-size-key="collections_images_per_page"]')->length === 1, 'The browser must save the collection-specific preference key.');
	foreach ([81,84,90,91,144] as $total) {
		$request->params['per_page'] = 'all';
		displayCheck(tadlResultPageSize($request,$total) === ($total <= 90 ? $total : 18), 'Collection All must remain bounded at 90 and fall back safely for larger sets.');
		$picker = tadlPerPagePicker($request,$total,tadlResultPageSize($request,$total),['',$controller,$action],['view'=>$view]);
		displayCheck(str_contains($picker,'value="all"') === ($total <= 90), 'Collection All must only be offered for small result sets.');
	}
	$request->params = ['view'=>'list'];
	displayCheck(tadlResultPageSize($request,144) === 50, 'The collection grid policy must preserve the existing list policy.');
}
foreach ([['Search','objects'],['Browse','people'],['Browse','organizations'],['Browse','places'],['Browse','occurrences'],['Detail','collections'],['CollectionContents','Objects']] as [$controller,$action]) {
	$request = new DisplayRequest(['view'=>'images'],$cookies,$controller,$action);
	displayCheck(tadlResultPageSize($request,144) === 27, 'Collection grid preferences must not change object/entity/place grids or objects within a collection.');
	$request->params['per_page'] = '90';
	displayCheck(tadlResultPageSize($request,144) === 18, 'The 90-item option belongs only to collection-record grids.');
}
$request = new DisplayRequest(['view'=>'tiles'],['tadlDisplayPreferences'=>'{"images_per_page":"72"}'],'Collections','Index');
displayCheck(tadlResultPageSize($request,144) === 18, 'Collection grids must start at 18 rather than inheriting the former shared grid setting.');
foreach (['all','99',['90']] as $bad) {
	$request->cookies = ['tadlDisplayPreferences'=>json_encode(['collections_images_per_page'=>$bad])];
	displayCheck(tadlResultPageSize($request,144) === 18, 'Malformed or All collection preferences must not become a sticky size.');
}
echo "Display preferences passed: {$checks} assertions.\n";
