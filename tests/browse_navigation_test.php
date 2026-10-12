<?php
/** Actual menu/detail links and configured defaults; no application or database. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$checks = 0;
function browseCheck($condition, $message) {
	$GLOBALS['checks']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function _t($text) { return $text; }
function caUcFirstUTF8Safe($text) { return ucfirst($text); }
function caGetInfoForBrowseType($type) { return $GLOBALS['browseTypes'][$type] ?? null; }
function caGetBrowseTypes($options = []) { return $GLOBALS['menuTypes']; }
function caGetBrowseConfig() { return new BrowseNavigationConfig(); }
function caNavLink($request, $label, $class, $module, $controller, $action, $params = [], $attributes = []) {
	$url = '/synthetic/'.$controller.'/'.$action.($params ? '?'.http_build_query($params) : '');
	$attrs = '';
	foreach ($attributes as $key => $value) { $attrs .= ' '.$key.'="'.htmlspecialchars($value, ENT_QUOTES, 'UTF-8').'"'; }
	return '<a href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'"'.$attrs.'>'.$label.'</a>';
}
class BrowseNavigationConfig {
	function get($name) { return $name === 'browseMenuFormat' ? 'list' : null; }
}
class BrowseNavigationRequest {
	function getController() { return 'Detail'; }
}
class BrowseNavigationView {
	public $request;
	function __construct() { $this->request = new BrowseNavigationRequest(); }
	function getVar($name) {
		return ['previousLink' => '<a href="/synthetic/previous">Prev</a>', 'nextLink' => '<a href="/synthetic/next">Next</a>'][$name] ?? null;
	}
	function renderMenu() {
		ob_start();
		try { include dirname(__DIR__).'/views/pageFormat/browseMenu.php'; return ob_get_clean(); }
		catch (Throwable $error) { ob_end_clean(); throw $error; }
	}
}
function browseLinks($html) {
	$document = new DOMDocument();
	$document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	return (new DOMXPath($document))->query('//a[starts-with(@href,"/synthetic/Browse/")]');
}

// Read only these simple sort maps; optional native parsing below checks full syntax.
$source = file_get_contents(dirname(__DIR__).'/conf/browse.conf');
$expected = [
	'objects' => ['Identifier', 'ca_objects.idno'],
	'subjects' => ['Title', 'ca_object_labels.name'],
	'collections' => ['Name', 'ca_collection_labels.name'],
	'people' => ['Identifier', 'ca_entities.idno'],
	'organizations' => ['Identifier', 'ca_entities.idno'],
	'families' => ['Identifier', 'ca_entities.idno'],
	'occurrences' => ['Name', 'ca_occurrence_labels.name'],
	'places' => ['Name', 'ca_place_labels.name']
];
$GLOBALS['browseTypes'] = [];
foreach ($expected as $type => [$sort, $field]) {
	browseCheck(preg_match('/^\t'.preg_quote($type, '/').' = \{(.*?)(?=^\t\},?$)/ms', $source, $section) === 1, $type.': missing browse configuration.');
	$info = ['displayName' => 'Synthetic '.$type, 'dontShowInBrowseMenu' => false];
	foreach (['sortBy', 'sortDirection'] as $map) {
		browseCheck(preg_match('/\b'.$map.'\s*=\s*\{([^}]*)\}/s', $section[1], $match) === 1, $type.': missing sort map.');
		foreach (explode(',', $match[1]) as $pair) {
			[$key, $value] = array_map('trim', explode('=', trim($pair), 2));
			$info[$map][$key] = $value;
		}
	}
	browseCheck(array_key_first($info['sortBy']) === $sort && $info['sortBy'][$sort] === $field && $info['sortDirection'][$sort] === 'asc', $type.': default must be the intended ascending logical field.');
	if ($type === 'families') {
		browseCheck(str_contains($section[1], 'restrictToTypes = [fam]') && str_contains($section[1], 'defaultView = images'), 'Families must target the catalogue family type and default to Tiles.');
		$info['defaultView'] = 'images'; $info['views'] = ['images' => [], 'list' => []];
	}
	$GLOBALS['browseTypes'][$type] = $info;
}
require dirname(__DIR__).'/views/Details/detail_field_helpers.php';

$GLOBALS['menuTypes'] = $GLOBALS['browseTypes'];
$view = new BrowseNavigationView();
$links = browseLinks($view->renderMenu());
browseCheck($links->length === count($expected), 'Menu must retain every browse destination.');
foreach (array_keys($expected) as $index => $type) {
	$sort = $expected[$type][0];
	$params = $type === 'subjects' ? ['clear' => 1] : ['sort' => $sort, 'direction' => 'asc'];
	if ($type === 'families') { $params['view'] = 'images'; }
	$url = '/synthetic/Browse/'.$type.'?'.http_build_query($params);
	browseCheck($links->item($index)->getAttribute('href') === $url && $links->item($index)->getAttribute('rel') === 'nofollow', $type.': menu must start with its configured default and retain crawler policy.');
	$GLOBALS['menuTypes'] = [$type => $GLOBALS['browseTypes'][$type]];
	browseCheck(browseLinks($view->renderMenu())->item(0)->getAttribute('href') === $url, $type.': a single-target menu must use the same default.');
	$detailLinks = browseLinks(tadlDetailNavigation($view, $type, 'Browse <synthetic>'));
	browseCheck($detailLinks->item(0)->getAttribute('href') === $url && $detailLinks->item(0)->textContent === 'Browse <synthetic>', $type.': detail Browse link must use the same default and escape its label.');
}
$nav = tadlDetailNavigation($view, 'index', 'Browse collections', 'Collections');
browseCheck(str_contains($nav, 'href="/synthetic/Collections/index"') && !str_contains($nav, '?sort='), 'Dedicated collection index keeps its own native configuration.');
browseCheck(str_contains($nav, '/synthetic/previous') && str_contains($nav, '/synthetic/next'), 'Default entry sorting must not replace native Prev/Next links.');
$objectNav = tadlDetailNavigation($view);
browseCheck(str_contains($objectNav, '/synthetic/previous') && str_contains($objectNav, '/synthetic/next') && browseLinks($objectNav)->length === 0, 'Object navigation must retain Prev/Next without a Browse destination.');
$directObjectView = new class extends BrowseNavigationView { function getVar($name) { return null; } };
browseCheck(tadlDetailNavigation($directObjectView) === '', 'Objects without result context must omit the empty navigation wrapper.');
browseCheck(tadlBrowseEntryParameters('unknown') === [], 'An unconfigured destination must retain its ordinary native link.');
browseCheck(tadlBrowseEntryParameters('places', ['sortBy' => ['Custom' => 'synthetic_field'], 'sortDirection' => ['Custom' => 'DESC']]) === ['sort' => 'Custom', 'direction' => 'desc'], 'Entry links must follow site configuration instead of hardcoded names.');
browseCheck(tadlBrowseEntryParameters('places', ['sortBy' => ['Custom' => 'synthetic_field'], 'sortDirection' => ['Custom' => 'invalid']])['direction'] === 'asc', 'Invalid directions fall back to native ascending order.');

if ($parser = getenv('TADL_TEST_CONFIGURATION')) {
	// Bypass app bootstrap/cache loading: parse only the tracked theme file.
	require $parser;
	$class = new ReflectionClass(Configuration::class);
	$config = $class->newInstanceWithoutConstructor();
	$class->getProperty('ops_config_settings')->setValue($config, ['scalars' => [], 'lists' => [], 'assoc' => []]);
	browseCheck($config->loadFile(dirname(__DIR__).'/conf/browse.conf'), 'Native parser rejected browse.conf.');
	$types = $config->getAssoc('browseTypes');
	browseCheck($types['families']['defaultView'] === 'images' && array_key_first($types['families']['views']) === 'images', 'Native family configuration must default to Tiles.');
	foreach ($expected as $type => [$sort, $field]) {
		browseCheck($types[$type]['sortBy'] === $GLOBALS['browseTypes'][$type]['sortBy'] && $types[$type]['sortDirection'] === $GLOBALS['browseTypes'][$type]['sortDirection'], $type.': native parser differs from the portable sort-map checks.');
	}
}
echo "Browse navigation passed: {$checks} assertions.\n";
