<?php
/**
 * Run with php tests/collection_detail_scripts_test.php (PHP and Node.js required).
 * Renders the real template with synthetic application boundaries; no app or DB boots.
 */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});

define('pString', 1);
define('pInteger', 2);
$GLOBALS['detailScriptAssertions'] = 0;
$GLOBALS['detailScriptMode'] = 'only';
$GLOBALS['detailScriptShowHierarchy'] = true;
$GLOBALS['detailScriptIcon'] = "<span class='caIcon' data-state=\"loading\" data-note=\"collector's </script> <>& \\ \u{2028}\u{2029}\"></span>";
$GLOBALS['detailScriptLoading'] = "Loading collector's \"quoted\" media \\ & < > \u{2028}\u{2029}";

function checkDetailScripts($condition, $message) {
	$GLOBALS['detailScriptAssertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function _t($text) { return $text === 'Loading...' ? $GLOBALS['detailScriptLoading'] : $text; }
function caGetUserAccessValues($request) { return array(1); }
function tadlMediaPreference($request) { return $GLOBALS['detailScriptMode']; }
function tadlMediaEligibleIDs($table, $ids, $access) { return $ids; }
function caBusyIndicatorIcon($request) { return $GLOBALS['detailScriptIcon']; }
function caGetBrowseConfig() { return new DetailScriptConfig(); }
function caGetCollectionsConfig() { return new DetailScriptConfig(); }
function caNavUrl($request, $module, $controller, $action, $params = array(), $options = array()) {
	$url = '/synthetic/'.$controller.'/'.$action;
	foreach ($params as $name => $value) { $url .= '/'.$name.'/'.(($options['dontURLEncodeParameters'] ?? false) ? $value : rawurlencode((string)$value)); }
	return $url;
}
class DetailScriptRequest {
    public function __construct(public array $params = []) {}
    public function getParameter($name, $type, $options = []) { return $this->params[$name] ?? null; }
}
class DetailScriptConfig {
    public function getAssoc($name) { return ['objects' => ['sortBy' => ['Identifier' => 'idno', 'Title' => 'name']]]; }
	public function get($name) { return $name === 'do_not_display_collection_browser' && !$GLOBALS['detailScriptShowHierarchy']; }
}
class DetailScriptItem {
	private array $values;
	public function __construct($objectCount, private array $templates = []) {
		$this->values = array(
			'collection_id' => 42,
			'entity_id' => 42,
			'place_id' => 42,
			'occurrence_id' => 42,
			'ca_objects.object_id' => array_slice(array(101, 102), 0, $objectCount),
			'ca_collections.hierarchy.collection_id' => array(42)
		);
	}
	// The legacy template passes this result to array_shift() by reference. Keep
	// that unrelated PHP notice outside this emitted-JavaScript regression.
	public function &get($name, $options = array()) { return $this->values[$name]; }
	public function getWithTemplate($template, $options = array()) {
		checkDetailScripts(($options['convertCodesToDisplayText'] ?? null) === true, 'Detail fields lost native display conversion.');
		foreach ($this->templates as $code => $value) {
			if (str_contains($template, $code)) { return $value; }
		}
		return '';
	}
}
class DetailScriptView {
	public $request;
	private array $values;
	public function __construct($objectCount, array $templates = [], array $params = []) {
		$this->request = new DetailScriptRequest($params);
		$this->values = array('item' => new DetailScriptItem($objectCount, $templates), 'comments' => array(), 'pdfEnabled' => true);
	}
	public function getVar($name) { return $this->values[$name] ?? null; }
	public function render($table) {
		ob_start();
		try {
			include dirname(__DIR__).'/views/Details/'.$table.'_default_html.php';
			$html = ob_get_clean();
			// Native detail template processing fills these record ID placeholders.
			return str_replace(array('^ca_entities.entity_id', '^ca_places.place_id', '^ca_occurrences.occurrence_id'), '42', $html);
		} catch (Throwable $error) {
			ob_end_clean();
			throw $error;
		}
	}
}

foreach ([[], ['ca_collections.description' => '<p>&nbsp;</p>', 'ca_collections.date.dates_value' => '<br/>', 'relativeTo="ca_places"' => '&nbsp;']] as $values) {
	$html = (new DetailScriptView(0, $values))->render('ca_collections');
	checkDetailScripts(!preg_match('~<label>(Description|Dates|Related collections|Related people|Related events|Related places)</label>~', $html), 'Empty collection fields or relationships emitted headings.');
}
$html = (new DetailScriptView(0, ['ca_collections.description' => 'Synthetic description', 'ca_collections.date.dates_value' => '1930', 'relativeTo="ca_places"' => '<a href="/synthetic/place">Synthetic place</a>']))->render('ca_collections');
checkDetailScripts(str_contains($html, 'Download Finding Aid') && str_contains($html, '/synthetic/CollectionFindingAid/Download/collection_id/42'), 'Finding aid must download the selected collection.');
checkDetailScripts(!str_contains($html, 'Download as PDF') && !str_contains($html, '_pdf_ca_collections_summary'), 'Collection still links to the generic summary export.');
checkDetailScripts(str_contains($html, '<label>Description</label>Synthetic description') && str_contains($html, '<label>Dates</label>1930') && str_contains($html, '<label>Related places</label><a href="/synthetic/place">Synthetic place</a>'), 'Populated collection fields lost headings or native links.');

// Reload/direct collection links forward only supported result state, never a supplied search/key.
foreach ([
    [['view' => 'list', 'sort' => 'Title', 'direction' => 'desc', 's' => 24, 'search' => 'unrelated', 'key' => 'unrelated'], '/view/list/sort/Title/direction/desc/s/24/n/24'],
    [['view' => 'unknown', 'sort' => 'unknown', 'direction' => 'unknown', 's' => -9], '/view/images/sort/Identifier/direction/asc/s/0/n/9']
] as [$params, $suffix]) {
    $html = (new DetailScriptView(2, [], $params))->render('ca_collections');
    checkDetailScripts(str_contains(str_replace('\\/', '/', $html), 'collection_id%3A42/tadl_collection_controls/1/tadl_collection_id/42'.$suffix), 'Collection loader failed to validate/forward its own result state.');
    checkDetailScripts(!str_contains($html, 'unrelated'), 'Caller search/key must not replace this collection.');
}
$html = (new DetailScriptView(2))->render('ca_collections');
checkDetailScripts(!str_contains($html, 'class="tadl-collection-metadata"'), 'Empty collection metadata must not reserve columns.');
$html = (new DetailScriptView(2, ['ca_collections.extent_text' => 'Synthetic extent', 'relativeTo="ca_places"' => '<a href="/synthetic/place">Synthetic place</a>']))->render('ca_collections');
checkDetailScripts(strpos($html, 'tadl-collection-metadata') < strpos($html, 'collectionHierarchy') && str_contains($html, 'tadl-collection-fields') && str_contains($html, 'tadl-collection-relationships'), 'Populated metadata must sit beside the heading above contents.');

$cases = array();
foreach (array(
	array('name' => 'multiple objects and hierarchy', 'table' => 'ca_collections', 'objects' => 2, 'hierarchy' => true, 'mode' => 'only', 'objectSearch' => 'collection_id%3A42'),
	array('name' => 'all items and hierarchy', 'table' => 'ca_collections', 'objects' => 2, 'hierarchy' => true, 'mode' => 'all', 'objectSearch' => 'collection_id%3A42'),
	array('name' => 'single object without hierarchy', 'table' => 'ca_collections', 'objects' => 1, 'hierarchy' => false, 'mode' => 'only')
) as $case) {
	$GLOBALS['detailScriptMode'] = $case['mode'];
	$GLOBALS['detailScriptShowHierarchy'] = $case['hierarchy'];
	$html = (new DetailScriptView($case['objects']))->render($case['table']);
	checkDetailScripts(!preg_match('~navTop|navLeftRight|detailNavBg|\{\{\{(?:previousLink|nextLink)\}\}\}~', $html), $case['name'].': collection detail still reserves record-navigation columns.');
	checkDetailScripts(str_contains($html, '<div class="row tadl-collection-detail">') && str_contains($html, "<div class='col-xs-12'>"), $case['name'].': collection content lost its full-width layout.');
	preg_match_all('~<script\b[^>]*>(.*?)</script\s*>~is', $html, $scripts);
	$expectedScriptCount = ($case['hierarchy'] ? 1 : 0) + ($case['objects'] >= 2 ? 1 : 0) + ($case['extraScripts'] ?? 0);
	checkDetailScripts(count($scripts[1]) === $expectedScriptCount, $case['name'].': unexpected inline script count.');
	checkDetailScripts((strpos($html, 'id="collectionHierarchy"') !== false) === $case['hierarchy'], $case['name'].': hierarchy rendering changed.');
	checkDetailScripts((strpos($html, 'id="browseResultsContainer"') !== false) === ($case['objects'] >= 2), $case['name'].': object contents rendering changed.');
	$cases[] = array(
		'name' => $case['name'],
		'scripts' => $scripts[1],
		'hierarchyUrl' => $case['hierarchy'] ? '/synthetic/Collections/collectionHierarchy/collection_id/42' : null,
		'objectsUrl' => $case['objects'] >= 2 ? '/synthetic/Search/objects/search/'.$case['objectSearch'].'/tadl_collection_controls/1/tadl_collection_id/42/view/images/sort/Identifier/direction/asc/s/0/n/9' : null,
		'loadingHtml' => $GLOBALS['detailScriptIcon'].' '.$GLOBALS['detailScriptLoading']
	);
}

// Parsing alone catches the original caIcon syntax error. Running the scripts
// against a small jQuery boundary also proves both loads and jscroll initialize.
$nodeCode = <<<'JS'
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const cases = JSON.parse(fs.readFileSync(0, 'utf8'));
let assertions = 0;
for (const fixture of cases) {
    const loads = [];
    const scrollers = [];
    const jquery = selector => ({
        ready(callback) { callback(); },
        load(url, callback) { loads.push({ selector, url }); if (callback) callback(); },
        jscroll(options) { scrollers.push({ selector, options }); },
        readmore() {}
    });
    const context = vm.createContext({ document: {}, jQuery: jquery, $: jquery });
    for (const [index, script] of fixture.scripts.entries()) {
        new vm.Script(script, { filename: `${fixture.name} inline script ${index + 1}` }).runInContext(context, { timeout: 1000 });
    }
    const expectedLoads = [];
    if (fixture.hierarchyUrl) expectedLoads.push({ selector: '#collectionHierarchy', url: fixture.hierarchyUrl });
    if (fixture.objectsUrl) expectedLoads.push({ selector: '#browseResultsContainer', url: fixture.objectsUrl });
    assert.deepEqual(loads, expectedLoads, `${fixture.name}: hierarchy or contents did not load`);
    assertions++;
    assert.equal(scrollers.length, fixture.objectsUrl ? 1 : 0, `${fixture.name}: wrong jscroll initialization count`);
    assertions++;
    if (fixture.objectsUrl) {
        assert.equal(scrollers[0].selector, '#browseResultsContainer');
        assert.equal(scrollers[0].options.loadingHtml, fixture.loadingHtml, `${fixture.name}: loading markup or translation changed`);
        assert.equal(scrollers[0].options.autoTrigger, true);
        assert.equal(scrollers[0].options.padding, 20);
        assert.equal(scrollers[0].options.nextSelector, 'a.jscroll-next');
        assertions += 5;
    }
}
process.stdout.write(JSON.stringify({ assertions }));
JS;
$process = proc_open(array(getenv('TADL_TEST_NODE') ?: 'node', '-e', $nodeCode), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
if (!is_resource($process)) { throw new RuntimeException('Node.js is required to parse emitted inline JavaScript.'); }
fwrite($pipes[0], json_encode($cases, JSON_THROW_ON_ERROR));
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$status = proc_close($process);
checkDetailScripts($status === 0, "Rendered detail JavaScript failed in Node.js:\n".$stderr);
$nodeResult = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
echo json_encode(array('status' => 'passed', 'assertions' => $GLOBALS['detailScriptAssertions'] + $nodeResult['assertions'], 'templates' => 'actual collection detail; authority pages have authority_detail_test.php', 'dependencies' => 'synthetic view/model/config/jQuery boundaries; Node.js syntax and execution'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
