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
	function __construct(public array $values = []) { $this->request = new BrowseNavigationRequest(); }
	function getVar($name) {
		if (array_key_exists($name, $this->values)) { return $this->values[$name]; }
		$direction = ['previousLink' => 'previous', 'nextLink' => 'next'][$name] ?? null;
		return $direction ? '<a href="/synthetic/'.$direction.'">'.$GLOBALS['detailNavigation'][$name].'</a>' : null;
	}
	function renderMenu() {
		ob_start();
		try { include dirname(__DIR__).'/views/pageFormat/browseMenu.php'; return ob_get_clean(); }
		catch (Throwable $error) { ob_end_clean(); throw $error; }
	}
}
class ResultContext {
	static public array $ids = [42];
	static public $return = null;
	static public string $type = 'search';
	static function getResultContextForLastFind($request, $table) { return new self(); }
	function getResultList() { return self::$ids; }
	function getParameter($name, $storedOnly = false) {
		browseCheck($name === 'tadl_result_return' && $storedOnly, 'Return navigation must read stored context without accepting request overrides.');
		return self::$return;
	}
	function findType() { return self::$type; }
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

$detailSource = file_get_contents(dirname(__DIR__).'/conf/detail.conf');
preg_match_all('/^\s*(previousLink|nextLink)\s*=\s*(.+),$/m', $detailSource, $configuredLinks, PREG_SET_ORDER);
browseCheck(count($configuredLinks) === 12, 'Every configured detail type must retain previous/next navigation.');
foreach ($configuredLinks as [, $name, $markup]) {
	$document = new DOMDocument();
	$document->loadHTML('<a>'.$markup.'</a>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	$link = $document->getElementsByTagName('a')->item(0);
	$icon = $name === 'previousLink' ? $link->firstChild : $link->lastChild;
	browseCheck($icon->nodeName === 'i' && $icon->getAttribute('aria-hidden') === 'true' && str_contains($icon->getAttribute('class'), $name === 'previousLink' ? 'fa-angle-left' : 'fa-angle-right'), 'Configured detail arrows must precede Previous and follow Next, and remain decorative.');
	$GLOBALS['detailNavigation'][$name] = $markup;
}

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
$nav = tadlDetailNavigation($view, 'index', 'Browse collections', 'Collections', 'collections');
browseCheck(str_contains($nav, 'href="/synthetic/Collections/index"') && !str_contains($nav, '?sort='), 'Dedicated collection index keeps its own native configuration.');
browseCheck(str_contains($nav, '/synthetic/previous') && str_contains($nav, '/synthetic/next'), 'Default entry sorting must not replace native Prev/Next links.');
$document = new DOMDocument(); $document->loadHTML($nav, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
$collectionLinks = (new DOMXPath($document))->query('//nav/a');
browseCheck(array_map(static fn($link) => $link->textContent, iterator_to_array($collectionLinks)) === ['Browse collections', 'Previous', 'Next'], 'Collection navigation must place Browse collections before the native Previous/Next links.');
$objectNav = tadlDetailNavigation($view);
browseCheck(str_contains($objectNav, '/synthetic/previous') && str_contains($objectNav, '/synthetic/next') && browseLinks($objectNav)->length === 0, 'Object navigation must retain Prev/Next without a Browse destination.');
foreach (['objects'=>'object', 'collections'=>'collection', 'people'=>'person', 'organizations'=>'organization', 'families'=>'family', 'places'=>'place', 'occurrences'=>'event', 'unknown'=>'record'] as $type => $singular) {
	$html = tadlDetailNavigation($view, null, '', 'Browse', $type);
	$document = new DOMDocument(); $document->loadHTML('<meta charset="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	$links = (new DOMXPath($document))->query('//nav/a');
	foreach (['previous', 'next'] as $index => $direction) {
		$link = $links->item($index);
		$label = 'Go to '.$direction.' '.$singular;
		browseCheck($link->getAttribute('title') === $label && $link->getAttribute('aria-label') === $label, $type.': tooltip and accessible name must identify the neighboring record type.');
		browseCheck($link->getAttribute('href') === '/synthetic/'.$direction, $type.': labeling must preserve the native destination.');
	}
	browseCheck($links->item(0)->textContent === 'Previous' && $links->item(1)->textContent === 'Next' && $links->item(0)->firstChild->nodeName === 'i' && $links->item(1)->lastChild->nodeName === 'i', $type.': labels must preserve the configured visible text and arrow order.');
}
$directObjectView = new class extends BrowseNavigationView { function getVar($name) { return null; } };
browseCheck(tadlDetailNavigation($directObjectView) === '', 'Objects without result context must omit the empty navigation wrapper.');
$returnView = new BrowseNavigationView([
	'item' => new class { function getPrimaryKey() { return 42; } },
	'resultsURL' => '/Search/objects?key=synthetic-key&row_id=42'
]);
$navigationFixtures = [];
foreach ([
	[null, 'search', '/Search/objects?key=synthetic-key&row_id=42', 'Back to results'],
	[null, 'multisearch', '/Search/objects?key=synthetic-key&row_id=42', 'Back to results'],
	[null, 'gallery', '/Search/objects?key=synthetic-key&row_id=42', 'Back to gallery'],
	[null, 'lightbox', '/Search/objects?key=synthetic-key&row_id=42', 'Back to lightbox'],
	[['url'=>'/Detail/collections/77?view=list&s=50&per_page=25&collection_view=hierarchy', 'kind'=>'collection'], 'search', '/Detail/collections/77?view=list&s=50&per_page=25&collection_view=hierarchy', 'Back to collection'],
	[['url'=>'/Browse/objects?key=synthetic-key&view=images&s=18&per_page=18', 'kind'=>'results'], 'browse', '/Browse/objects?key=synthetic-key&view=images&s=18&per_page=18', 'Back to results']
] as [$target, $type, $url, $label]) {
	ResultContext::$return = $target; ResultContext::$type = $type;
	$html = tadlDetailNavigation($returnView);
	if ($target && $target['kind'] === 'collection') { $navigationFixtures['object-return'] = $html; }
	$document = new DOMDocument(); $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	$links = (new DOMXPath($document))->query('//nav/a'); $back = $links->item(0);
	browseCheck($links->length === 3 && $back->getAttribute('class') === 'tadl-record-return' && $back->textContent === $label && $back->getAttribute('href') === $url, 'The contextual return must precede Previous/Next and preserve the full source URL.');
	browseCheck($back->getAttribute('title') === $label && $back->getAttribute('aria-label') === $label && $back->firstChild->getAttribute('class') === 'small', 'The return button must have contextual tooltip/accessibility text and the shared label styling.');
}
$returnView->values['previousLink'] = $returnView->values['nextLink'] = '';
$navigationFixtures['object-return-only'] = tadlDetailNavigation($returnView);
browseCheck(str_contains(tadlDetailNavigation($returnView), 'Back to results'), 'Single-item result sets must still offer a return button.');
ResultContext::$ids = [41,43];
browseCheck(tadlDetailNavigation($returnView) === '', 'An unrelated last result set must not supply a return button on a direct visit.');
ResultContext::$ids = [42];
foreach (['https://example.org/', '//example.org/', '/\\example.org/', '/Search/objects'."\n".'bad', 'javascript:alert(1)'] as $unsafeURL) {
	ResultContext::$return = ['url'=>$unsafeURL, 'kind'=>'collection'];
	browseCheck(tadlDetailNavigation($returnView) === '', 'Return URLs must stay within the catalogue and reject ambiguous URL syntax.');
}
ResultContext::$return = ['url'=>'/Search/objects?search=%22%3E%3Cscript%3E&view=list', 'kind'=>'<script>'];
$html = tadlDetailNavigation($returnView);
browseCheck(str_contains($html, '&amp;view=list') && !str_contains($html, '<script>') && str_contains($html, 'Back to results'), 'Return URL attributes must be escaped and labels must come from the fixed contextual vocabulary.');
browseCheck(!str_contains(tadlDetailNavigation($returnView, 'collections', 'Browse collections', 'Browse', 'collections'), 'tadl-record-return'), 'Other record types must retain their existing navigation.');
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
	browseCheck($config->loadFile(dirname(__DIR__).'/conf/detail.conf'), 'Native parser rejected detail.conf.');
	foreach ($config->getAssoc('detailTypes') as $type => $detail) {
		foreach ($GLOBALS['detailNavigation'] as $name => $markup) {
			browseCheck($detail['options'][$name] === $markup, $type.': native parser must preserve arrow markup.');
		}
	}
}
if (in_array('--fixtures', $argv, true)) { echo json_encode($navigationFixtures, JSON_THROW_ON_ERROR); }
else { echo "Browse navigation passed: {$checks} assertions.\n"; }
