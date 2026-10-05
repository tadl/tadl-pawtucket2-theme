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
function tadlMediaFacetItems($request, $items, $info) { return ($info['synthetic_media_filtered'] ?? false) ? [] : $items; }
function caGetAddToSetInfo($request) { return []; }
function caDisplayLightbox($request) { return false; }
function caBusyIndicatorIcon($request) { return '<span class="synthetic-spinner"></span>'; }
function caNavUrl($request, $module, $controller, $action, $params = []) {
	return '/synthetic/'.($controller === 'Detail' ? 'Detail/' : '').($action === '*' ? 'objects' : $action).'?'.http_build_query($params);
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
	function __construct(private array $definitions, private string $table = 'ca_objects', private array $contents = []) {}
	function getInfoForFacets() { return $this->definitions; }
	function getFacet($facet, $options = []) {
		checkContext(($options['checkAccess'] ?? null) === [1] && isset($options['request']), 'Hierarchy availability must use request access filtering.');
		return array_key_exists($facet, $this->contents) ? $this->contents[$facet] : [['id' => 42, 'label' => 'Synthetic value']];
	}
	function filterHitsByACL($ids, $tableNum, $userID) {
		checkContext($tableNum === 72 && $userID === 7, 'ACL filter must use current table and user.');
		return array_values(array_filter($ids, fn($id) => $GLOBALS['contextRecords'][$this->table][$id]['aclAllowed'] ?? true));
	}
}
class ContextRequest {
	public array $access = [1];
	public string $mediaMode = 'only';
	function __construct(public string $controller = 'Browse', public bool $ajax = false, public ?int $filteredCount = null, public array $params = []) {}
	function getParameter($name, $type, $options = []) { return $this->params[$name] ?? ($type === pInteger ? 0 : null); }
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
			'config' => new ContextConfig(), 'export_formats' => [], 'sortBy' => ['Identifier' => 'ca_objects.idno', 'Title' => 'ca_object_labels.name'],
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
foreach (['images' => 9, 'list' => 24] as $view => $pageSize) {
	$html = (new ContextView(new ContextRequest('Search', true, 45, ['tadl_collection_controls' => 1, 'tadl_collection_id' => 42]), [], 90, ['view' => $view]))->render('Browse/browse_results_html.php');
	$document = new DOMDocument(); $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	$xpath = new DOMXPath($document);
	checkContext($xpath->query('//div[contains(@class,"tadl-collection-results-toolbar")]')->length === 1 && $xpath->query('//div[@aria-label="Result display options"]')->length === 1, 'Embedded collection needs exactly one Tiles/List toolbar.');
	checkContext(str_contains($html, 'Page 1 of '.(int)ceil(45 / $pageSize)) && str_contains($html, 's='.$pageSize), 'Embedded top pager must use filtered count and current view page size.');
	checkContext(strpos($html, 'tadl-collection-results-toolbar') < strpos($html, 'data-synthetic-cards'), 'Embedded collection controls must precede its first-page cards.');
	checkContext(str_contains($html, '/Detail/collections/42?') && str_contains($html, 'sort=Identifier') && !str_contains($html, 'tadl_collection_controls=') && !str_contains($html, '/Search/'), 'Collection view/page links must retain the collection detail route and sort.');
}
// Both pagers and sort/view actions stay on the collection for later pages.
foreach (['images' => 9, 'list' => 24] as $view => $pageSize) {
    $request = new ContextRequest('Search', true, 45, ['tadl_collection_controls' => 1, 'tadl_collection_id' => 42]);
    $html = (new ContextView($request, [], 90, ['view' => $view, 'start' => $pageSize, 'sort' => 'Title', 'sort_direction' => 'desc']))->render('Browse/browse_results_html.php');
    $document = new DOMDocument(); $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    checkContext($xpath->query('//button[contains(@class,"tadl-results-options")]')->length === 1, 'Every collection page must have Options.');
    checkContext(str_contains($html, 'Page 2 of ') && str_contains($html, 'data-tadl-result-count="45"'), 'Later pages must keep filtered counts and current page.');
    foreach ($xpath->query('//div[contains(@class,"tadl-collection-results-toolbar")]//a[@href!="#"]') as $anchor) {
        $url = $anchor->getAttribute('href');
        checkContext(str_starts_with($url, '/synthetic/Detail/collections/42?') && !str_contains($url, 'key=') && !str_contains($url, 'tadl_collection_'), 'Collection controls leaked Search state or lost collection route.');
    }
    $pager = tadlBrowseResultPager($request, 45, $pageSize, $pageSize, 'synthetic-key', $view, 'Title', 'desc', false);
    checkContext(str_contains($pager, 'sort=Title') && str_contains($pager, 'direction=desc') && str_contains($pager, 's=0'), 'Bottom pager must retain sort/direction and allow Previous.');
}
$html = (new ContextView(new ContextRequest('Search', true), [], 45))->render('Browse/browse_results_html.php');
checkContext(!str_contains($html, 'tadl-collection-results-toolbar'), 'Ordinary AJAX result blocks gained collection-only controls.');
$html = (new ContextView(new ContextRequest('Search', false, null, ['tadl_collection_controls' => 1]), [], 45))->render('Browse/browse_results_html.php');
checkContext(!str_contains($html, 'tadl-collection-results-toolbar') && substr_count($html, "aria-label='Result display options'") === 1, 'Full search pages gained duplicate controls.');
$facets = contextFacetDefinitions();
$facets['entity_facet']['content'] = [['id' => 42, 'label' => 'Synthetic Studio']];
$facets['place_facet'] = array_merge($facets['place_facet'], ['group_mode' => 'hierarchical', 'description' => 'Generic hierarchy copy should not appear', 'content' => []]);
$html = (new ContextView(new ContextRequest(), [], 45, ['facets' => $facets]))->render('Browse/browse_refine_subview_html.php');
checkContext(str_contains($html, '<h3>People and organizations</h3>') && str_contains($html, '<h3>Places</h3>'), 'Sidebar authority terminology is incorrect.');
checkContext(!str_contains($html, 'Generic hierarchy copy'), 'Generic hierarchy description remains.');
checkContext(str_contains($html, 'getFacetHierarchyLevel?facet=place_facet') && str_contains($html, 'linkTo=morePanel') && str_contains($html, 'id="bHierarchyList_place_facet"'), 'Hierarchy AJAX behavior changed.');
$document = new DOMDocument(); $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($document);
checkContext($xpath->query('//div[@id="bHierarchyList_place_facet"]//a[contains(@href,"facet=place_facet") and contains(@href,"id=42")]')->length === 1, 'Deferred Places must contain a usable native filter before AJAX.');
checkContext($xpath->query('//div[@id="bHierarchyList_place_facet_remote" and @hidden]')->length === 1, 'Hierarchy response must not overwrite usable links while loading.');
checkContext(str_contains($html, 'facet=entity_facet&amp;id=42&amp;view=images'), 'Flat facet navigation changed.');

// Run the actual loader across empty, script-only, failed and populated responses.
preg_match_all('~<script\b[^>]*>(.*?)</script\s*>~is', $html, $facetScripts);
$hierarchyScripts = array_values(array_filter($facetScripts[1], static fn($script) => str_contains($script, 'var choices =')));
checkContext(count($hierarchyScripts) === 1, 'Exactly one deferred Places loader is expected.');
$facetLoaderCode = <<<'JS'
const vm = require('node:vm');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const script = JSON.parse(fs.readFileSync(0, 'utf8'));
let assertions = 0;
for (const fixture of [
    { status: 'success', links: [] },
    { status: 'success', links: [], response: '<script>synthetic();</script>' },
    { status: 'error', links: ['Error page link'] },
    { status: 'success', links: ['Synthetic hierarchy choice'] },
    { status: 'notmodified', links: ['Synthetic cached hierarchy choice'] }
]) {
    const choices = { links: ['Synthetic native filter'], empty() { this.links = []; return this; }, append(links) { this.links.push(...links); return this; } };
    const hierarchy = {
        removed: false,
        load(url, callback) {
            assert.ok(url.includes('facet=place_facet') && url.includes('key=synthetic-key') && url.includes('linkTo=morePanel'));
            assertions++;
            callback.call(this, fixture.response || '', fixture.status);
        },
        find(selector) { assert.equal(selector, 'a'); return fixture.links; },
        contents() { return fixture.links; },
        remove() { this.removed = true; }
    };
    const document = {};
    const jQuery = selector => {
        if (selector === document) return { ready(callback) { callback(); } };
        if (selector === '#bHierarchyList_place_facet') return choices;
        if (selector === '#bHierarchyList_place_facet_remote') return hierarchy;
        throw Error('Unexpected selector: ' + selector);
    };
    new vm.Script(script).runInNewContext({ jQuery, document }, { timeout: 1000 });
    assert.deepEqual(choices.links, fixture.status !== 'error' && fixture.links.length ? fixture.links : ['Synthetic native filter']);
    assert.equal(hierarchy.removed, true);
    assertions += 2;
}
process.stdout.write(String(assertions));
JS;
$process = proc_open([getenv('TADL_TEST_NODE') ?: 'node', '-e', $facetLoaderCode], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (!is_resource($process)) { throw new RuntimeException('Node.js is required for the hierarchy loader regression.'); }
fwrite($pipes[0], json_encode($hierarchyScripts[0], JSON_THROW_ON_ERROR)); fclose($pipes[0]);
$loaderAssertions = stream_get_contents($pipes[1]); $loaderErrors = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
checkContext(proc_close($process) === 0, 'Hierarchy fallback failed: '.$loaderErrors);
$GLOBALS['contextAssertions'] += (int)$loaderAssertions;

foreach ([
	['content' => []],
	['content' => false, 'deferred_load' => true],
	['content' => true, 'group_mode' => 'hierarchical'],
	['content' => [['id' => 42, 'label' => 'Synthetic excluded collection']], 'synthetic_media_filtered' => true]
] as $emptyFacet) {
	$facets = ['collection_facet' => array_merge(contextFacetDefinitions()['collection_facet'], $emptyFacet)];
	$extra = ['facets' => $facets, 'browse' => new ContextBrowse(contextFacetDefinitions(), 'ca_objects', ['collection_facet' => []])];
	$view = new ContextView(new ContextRequest(), [], 45, $extra);
	checkContext(trim($view->render('Browse/browse_refine_subview_html.php')) === '', 'Empty facets emitted a panel, heading or loader.');
	$html = $view->render('Browse/browse_results_html.php');
	checkContext(!str_contains($html, "id='bRefineButton'") && !str_contains($html, "id='bRefine'"), 'Empty filters left a sidebar or toggle.');
	checkContext(str_contains($html, "<div class='col-sm-12'>"), 'Results did not reclaim empty sidebar space.');
	checkContext(str_contains($html, 'data-synthetic-cards') && str_contains($html, 'Page 1 of 5'), 'Empty filters changed results or pagination.');
}
$facets = contextFacetDefinitions();
$facets['entity_facet']['content'] = [['id' => 42, 'label' => 'Synthetic Studio']];
$facets['place_facet']['content'] = [];
$html = (new ContextView(new ContextRequest(), [], 45, ['facets' => $facets]))->render('Browse/browse_results_html.php');
checkContext(str_contains($html, '<h3>People and organizations</h3>') && !str_contains($html, '<h3>Places</h3>'), 'Mixed facets retained an empty heading or lost populated filters.');
checkContext(str_contains($html, "id='bRefineButton'") && str_contains($html, "id='bRefine'"), 'Populated facets lost their panel or toggle.');
checkContext(str_contains($html, "col-sm-8 col-md-8 col-lg-8"), 'Populated sidebar lost its allocated column.');

$subjectFacets = ['term_facet' => ['type' => 'authority', 'table' => 'ca_list_items', 'label_singular' => 'Subject', 'group_mode' => 'alphabetical', 'content' => [
	['id' => 42, 'label' => 'Synthetic bridges'], ['id' => 43, 'label' => 'Archives <script>x</script> & maps'],
	['id' => 0, 'label' => 'Invalid'], ['id' => 44, 'label' => ' '], ['id' => 42, 'label' => 'Synthetic bridges']
]]];
$html = (new ContextView(new ContextRequest(), [], 45, ['browse_type' => 'subjects', 'facets' => $subjectFacets]))->render('Browse/browse_results_html.php');
checkContext(str_contains($html, '>Browse Subjects</h1>') && str_contains($html, 'tadl-subject-index-list'), 'Subject landing page must list subjects.');
checkContext(!str_contains($html, 'data-synthetic-cards') && !str_contains($html, 'Tiles') && !str_contains($html, 'tadl-results-pager'), 'Subject landing page leaked unfiltered object results or their controls.');
checkContext(str_contains($html, 'facet=term_facet&amp;id=42&amp;clear=1'), 'Subject selection must start a clean native browse.');
checkContext(strpos($html, 'Archives') < strpos($html, 'Synthetic bridges') && substr_count($html, '>Synthetic bridges</a>') === 1, 'Subject list order or deduplication failed.');
checkContext(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;') && !str_contains($html, '>Invalid</a>'), 'Subject index failed escaping or ID validation.');
$html = (new ContextView(new ContextRequest(), [], 45, ['browse_type' => 'subjects']))->render('Browse/browse_results_html.php');
checkContext(str_contains($html, 'No subjects are available.') && !str_contains($html, 'data-synthetic-cards'), 'Empty subject index must not show all objects.');
$subjectCriteria = [['facet_name' => 'term_facet', 'facet' => 'Subject', 'id' => 42, 'value' => 'Synthetic bridges']];
$html = (new ContextView(new ContextRequest(), $subjectCriteria, 45, ['browse_type' => 'subjects', 'facets' => $subjectFacets]))->render('Browse/browse_results_html.php');
checkContext(str_contains($html, 'data-synthetic-cards') && str_contains($html, 'Browse all subjects') && str_contains($html, 'subjects?clear=1'), 'Selected subject must show related objects and a link back to the index.');
checkContext(!str_contains($html, 'tadl-subject-index-list'), 'Selected subject still shows landing directory.');
$html = (new ContextView(new ContextRequest(), [], 45))->render('Browse/browse_results_html.php');
checkContext(!str_contains($html, 'Browse Subjects') && !str_contains($html, 'tadl-subject-browse'), 'Subject layout leaked onto normal object browse.');

echo json_encode(['status' => 'passed', 'assertions' => $GLOBALS['contextAssertions'], 'boundaries' => 'actual results/refine templates with synthetic record, access, ACL, result, URL and cache APIs'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
