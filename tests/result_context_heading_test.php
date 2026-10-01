<?php
/** Standalone regression: php tests/result_context_heading_test.php. No app or DB boots. */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('pString', 1);
define('pInteger', 2);
$GLOBALS['contextAssertions'] = 0;
$GLOBALS['contextLabelReads'] = [];
$GLOBALS['contextRecords'] = [
	'ca_places' => [
		42 => ['label' => 'Synthetic Harbor'],
		43 => ['label' => 'Synthetic private place', 'access' => 0],
		44 => ['label' => 'Synthetic deleted place', 'deleted' => 1],
		45 => ['label' => 'Synthetic ACL-hidden place', 'acl' => true, 'aclAllowed' => false],
		46 => ['label' => 'Synthetic restricted label', 'readable' => false],
		47 => ['label' => 'Synthetic wrong record', 'primaryKey' => 999],
		48 => ['label' => '   '],
		49 => ['label' => 'Synthetic <script>alert("x")</script> & "Harbor"'],
		50 => ['label' => 'Synthetic ACL-visible place', 'acl' => true, 'access' => 0],
	],
	'ca_entities' => [42 => ['label' => 'Synthetic Person'], 43 => ['label' => 'Synthetic Studio']],
	'ca_occurrences' => [42 => ['label' => 'Synthetic Exhibition']],
	'ca_collections' => [42 => ['label' => 'Synthetic Collection']]
];
function checkContext($condition, $message) {
	$GLOBALS['contextAssertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function _t($text, ...$values) {
	foreach ($values as $i => $value) { $text = str_replace('%'.($i + 1), (string)$value, $text); }
	return $text;
}
function caGetOption($key, $options, $default = null) { return $options[$key] ?? $default; }
function caGetUserAccessValues($request) { return $request->access; }
function caACLIsEnabled($subject, $options = []) {
	checkContext(($options['forPawtucket'] ?? false) === true, 'Lookup must check Pawtucket ACL configuration.');
	return $subject->get('acl');
}
function caGetBrowseInstance($table) { return new ContextBrowse([], $table); }
function tadlFilterMediaResult($request, $result) { $result->count = $request->filteredCount ?? $result->count; }
function tadlMediaPreference($request) { return $request->mediaMode; }
function tadlMediaResultContext($view, $result, $type, $block = null) {}
function tadlMediaFacetItems($request, $items, $info) { return $items; }
function caGetAddToSetInfo($request) { return []; }
function caBusyIndicatorIcon($request) { return '<span class="synthetic-spinner"></span>'; }
function caNavUrl($request, $module, $controller, $action, $params = []) {
	return '/synthetic/'.($action === '*' ? 'objects' : $action).'?'.http_build_query($params);
}
function caNavLink($request, $text, $class, $module, $controller, $action, $params = []) {
	return '<a class="'.htmlspecialchars($class, ENT_QUOTES, 'UTF-8').'" href="'.htmlspecialchars(caNavUrl($request, $module, $controller, $action, $params), ENT_QUOTES, 'UTF-8').'">'.$text.'</a>';
}
class Datamodel {
	static function getInstance($table, $initialize = true) { return new ContextSubject($table); }
}
class ContextSubject {
	private array $values = [];
	private int $id = 0;
	function __construct(private string $table) {}
	function load($id) {
		$this->id = (int)$id;
		$this->values = $GLOBALS['contextRecords'][$this->table][$id] ?? [];
		return (bool)$this->values;
	}
	function getPrimaryKey() { return $this->values['primaryKey'] ?? $this->id; }
	function get($name) { return $this->values[$name] ?? ($name === 'access' ? 1 : null); }
	function hasField($name) { return in_array($name, ['access', 'deleted'], true); }
	function tableNum() { return 72; }
	function isReadable($request, $bundle = null) {
		checkContext($bundle === 'preferred_labels', 'Lookup must enforce label bundle access.');
		return $this->values['readable'] ?? true;
	}
	function getLabelForDisplay() {
		$GLOBALS['contextLabelReads'][] = $this->table.':'.$this->id;
		return $this->values['label'];
	}
	function getProperty($name) { return $name === 'NAME_SINGULAR' ? 'record' : 'records'; }
}
class ContextBrowse {
	function __construct(private array $definitions, private string $table = 'ca_objects') {}
	function getInfoForFacets() { return $this->definitions; }
	function getFacet($facet) { return [['id' => 42, 'label' => 'Synthetic value']]; }
	function filterHitsByACL($ids, $tableNum, $userID) {
		checkContext($tableNum === 72 && $userID === 7, 'ACL filter must use current table and user.');
		return array_values(array_filter($ids, fn($id) => $GLOBALS['contextRecords'][$this->table][$id]['aclAllowed'] ?? true));
	}
}
class ContextRequest {
	public array $access = [1];
	public string $mediaMode = 'only';
	function __construct(public string $controller = 'Browse', public bool $ajax = false, public ?int $filteredCount = null) {}
	function getParameter($name, $type, $options = []) { return $type === pInteger ? 0 : null; }
	function getController() { return $this->controller; }
	function isAjax() { return $this->ajax; }
	function getUserID() { return 7; }
}
class ContextResult {
	function __construct(public int $count) {}
	function numHits() { return $this->count; }
	function getPrimaryKeyValues() { return $this->count ? range(1, $this->count) : []; }
}
class ContextConfig {
	function get($key) { return $key === 'cache_timeout' ? 0 : null; }
}
class ExternalCache {
	static function contains($key, $group) { return false; }
	static function save($key, $html, $group, $timeout) {}
}
class ContextView {
	private array $values;
	function __construct(public ContextRequest $request, array $criteria, int $count = 45, array $extra = []) {
		$this->values = array_merge([
			'result' => new ContextResult($count), 'criteria' => $criteria, 'facets' => [],
			'key' => 'synthetic-key', 'access_values' => [1], 'hits_per_block' => 9, 'start' => 0,
			'views' => ['images' => [], 'list' => []], 'view' => 'images', 'sort' => 'Identifier',
			'sort_direction' => 'asc', 'table' => 'ca_objects', 't_instance' => new ContextSubject('ca_objects'),
			'options' => [], 'browseInfo' => ['table' => 'ca_objects', 'labelSingular' => 'object', 'labelPlural' => 'objects'],
			'config' => new ContextConfig(), 'export_formats' => [], 'sortBy' => ['Identifier' => 'ca_objects.idno'],
			'browse' => new ContextBrowse(contextFacetDefinitions()), 'browse_type' => 'objects'
		], $extra);
	}
	function getVar($name) { return $this->values[$name] ?? null; }
	function setVar($name, $value) { $this->values[$name] = $value; }
	function render($file) {
		if (preg_match('~Browse/browse_results_(images|list)_html.php~', $file)) {
			return '<div data-synthetic-cards="true"></div><a class="jscroll-next" href="/synthetic/next?key=synthetic-key">Next</a>';
		}
		ob_start();
		try { include dirname(__DIR__).'/views/'.$file; return ob_get_clean(); }
		catch (Throwable $error) { ob_end_clean(); throw $error; }
	}
}
function contextFacetDefinitions() {
	$definitions = [];
	foreach (['entity' => 'ca_entities', 'place' => 'ca_places', 'occurrence' => 'ca_occurrences', 'collection' => 'ca_collections'] as $name => $table) {
		$definitions[$name.'_facet'] = ['type' => 'authority', 'table' => $table, 'label_singular' => $name === 'entity' ? 'person' : $name, 'group_mode' => 'list'];
	}
	$definitions['type_facet'] = ['type' => 'fieldList', 'label_singular' => 'Object type', 'group_mode' => 'list'];
	return $definitions;
}
function contextCriterion($facet, $id, $value = 'Native label must not be trusted') {
	return ['facet_name' => $facet, 'id' => $id, 'value' => $value, 'facet' => $facet === '_search' ? 'Search' : $facet];
}
function contextHeading($html) {
	preg_match('~<H1>\s*<span class=\'tadl-results-title-text\'>(.*?)</span>\s*</H1>~s', $html, $matches);
	return isset($matches[1]) ? html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8') : null;
}

foreach ([['place_facet', 42, 'Synthetic Harbor'], ['entity_facet', 42, 'Synthetic Person'], ['entity_facet', 43, 'Synthetic Studio'], ['occurrence_facet', 42, 'Synthetic Exhibition'], ['collection_facet', 42, 'Synthetic Collection']] as [$facet, $id, $label]) {
	$html = (new ContextView(new ContextRequest(), [contextCriterion($facet, $id)]))->render('Browse/browse_results_html.php');
	checkContext(contextHeading($html) === 'Items related to '.$label, $facet.': missing contextual heading.');
	checkContext(str_contains($html, '<p class="tadl-results-context-count">45 items</p>'), $facet.': missing accurate count subtitle.');
	checkContext(str_contains($html, 'removeCriterion='.$facet.'&amp;removeID='.$id) && str_contains($html, 'key=synthetic-key'), $facet.': criterion removal/key changed.');
	checkContext(!str_contains($html, 'Native label must not be trusted'), $facet.': unsafe native label used.');
	checkContext(str_contains($html, 'aria-label=\'Results pagination\'') && str_contains($html, 'Page 1 of 5'), $facet.': pagination changed.');
}
foreach (['entity_id:42' => 'Synthetic Person', 'place_id:42' => 'Synthetic Harbor', 'occurrence_id:42' => 'Synthetic Exhibition', 'collection_id:42' => 'Synthetic Collection', ' ca_places.place_id : 42 ' => 'Synthetic Harbor'] as $query => $label) {
	$criteria = [contextCriterion('_search', $query, 'Raw display query: '.$query), contextCriterion('type_facet', 3, 'Synthetic type')];
	$html = (new ContextView(new ContextRequest('Search'), $criteria))->render('Browse/browse_results_html.php');
	checkContext(contextHeading($html) === 'Items related to '.$label, $query.': related context lost when refining.');
	checkContext(str_contains(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'), 'Related to:'.$label), $query.': related search is not human-readable.');
	checkContext(!str_contains($html, 'Raw display query:'), $query.': raw related query displayed.');
}
foreach (['place_id:42 OR place_id:43', 'place_id:42 AND harbor', 'place_id:*', 'place_id:0', 'place_id:-42', 'place_id:42<script>', 'ca_entities.place_id:42', 'place_id:9999999999999999999999999'] as $query) {
	$html = (new ContextView(new ContextRequest('Search'), [contextCriterion('_search', $query, 'Synthetic search')]))->render('Browse/browse_results_html.php');
	checkContext(contextHeading($html) === '45 objects', 'Malformed/compound query must not become a title: '.$query);
}
foreach ([43, 44, 45, 46, 47, 48, 999] as $id) {
	$readsBefore = count($GLOBALS['contextLabelReads']);
	foreach (['place_facet', '_search'] as $facet) {
		$criteria = [contextCriterion($facet, $facet === '_search' ? 'place_id:'.$id : $id, 'Synthetic secret from native criteria')];
		$html = (new ContextView(new ContextRequest($facet === '_search' ? 'Search' : 'Browse'), $criteria))->render('Browse/browse_results_html.php');
		checkContext(contextHeading($html) === '45 objects', 'Unavailable authority must use generic heading: '.$id);
		checkContext(str_contains($html, 'Unavailable item') && !str_contains($html, 'Synthetic secret'), 'Unavailable label leaked into selected criteria: '.$id);
	}
	if ($id !== 48) { checkContext(count($GLOBALS['contextLabelReads']) === $readsBefore, 'Denied authority label was read: '.$id); }
}
$html = (new ContextView(new ContextRequest(), [contextCriterion('place_facet', 50)]))->render('Browse/browse_results_html.php');
checkContext(contextHeading($html) === 'Items related to Synthetic ACL-visible place', 'Pawtucket ACL access should replace public status restriction.');
$html = (new ContextView(new ContextRequest(), [contextCriterion('place_facet', 49)]))->render('Browse/browse_results_html.php');
checkContext(!str_contains($html, '<script>alert(') && str_contains($html, '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;'), 'Authority label must be escaped in heading and chip.');
foreach ([0 => '0 objects', 1 => '1 object', 2 => '2 objects'] as $count => $heading) {
	$html = (new ContextView(new ContextRequest(), [], $count))->render('Browse/browse_results_html.php');
	checkContext(contextHeading($html) === $heading, 'Generic singular/plural count is wrong.');
}
$html = (new ContextView(new ContextRequest(), [contextCriterion('place_facet', 42), contextCriterion('entity_facet', 42)]))->render('Browse/browse_results_html.php');
checkContext(contextHeading($html) === '45 objects', 'Multiple authority criteria must not choose arbitrary context.');
$html = (new ContextView(new ContextRequest(), [contextCriterion('place_facet', 42)], 45, ['table' => 'ca_entities', 'browseInfo' => ['table' => 'ca_entities', 'labelSingular' => 'person', 'labelPlural' => 'people']]))->render('Browse/browse_results_html.php');
checkContext(contextHeading($html) === '45 people', 'Authority result pages should retain their generic headings.');

foreach ([0, 1, 8] as $filteredCount) {
	$html = (new ContextView(new ContextRequest('Search', true, $filteredCount), [], 45))->render('Browse/browse_results_html.php');
	checkContext(str_contains($html, 'data-tadl-result-count="'.$filteredCount.'"'), 'AJAX summary must use count after media filtering.');
	checkContext(str_contains($html, '>'.($filteredCount === 1 ? '1 item' : $filteredCount.' items').'</p>'), 'AJAX summary singular/plural text is wrong.');
	checkContext(strpos($html, 'tadl-related-results-summary') < strpos($html, 'data-synthetic-cards'), 'Summary must precede cached result cards.');
	checkContext(!str_contains($html, '<H1>') && str_contains($html, 'class="jscroll-next"'), 'AJAX response changed heading or next-link behavior.');
}
$html = (new ContextView(new ContextRequest('Browse', true), [], 45))->render('Browse/browse_results_html.php');
checkContext(!str_contains($html, 'tadl-related-results-summary'), 'Browse AJAX must not gain Search-only related summary.');
$facets = contextFacetDefinitions();
$facets['entity_facet']['content'] = [['id' => 42, 'label' => 'Synthetic Studio']];
$facets['place_facet'] = array_merge($facets['place_facet'], ['group_mode' => 'hierarchical', 'description' => 'Generic hierarchy copy should not appear', 'content' => []]);
$html = (new ContextView(new ContextRequest(), [], 45, ['facets' => $facets]))->render('Browse/browse_refine_subview_html.php');
checkContext(str_contains($html, '<h3>People and organizations</h3>') && str_contains($html, '<H3>Places</H3>'), 'Sidebar authority terminology is incorrect.');
checkContext(!str_contains($html, 'Generic hierarchy copy'), 'Generic hierarchy description remains.');
checkContext(str_contains($html, 'getFacetHierarchyLevel?facet=place_facet') && str_contains($html, 'linkTo=morePanel') && str_contains($html, "id='bHierarchyList_place_facet'"), 'Hierarchy AJAX behavior changed.');
checkContext(str_contains($html, 'facet=entity_facet&amp;id=42&amp;view=images'), 'Flat facet navigation changed.');

echo json_encode(['status' => 'passed', 'assertions' => $GLOBALS['contextAssertions'], 'boundaries' => 'actual results/refine templates with synthetic record, access, ACL, result, URL and cache APIs'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
