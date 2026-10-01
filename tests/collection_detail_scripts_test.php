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
function caGetCollectionsConfig() { return new DetailScriptConfig(); }
function caNavUrl($request, $module, $controller, $action, $params = array(), $options = array()) {
	$url = '/synthetic/'.$controller.'/'.$action;
	foreach ($params as $name => $value) { $url .= '/'.$name.'/'.(($options['dontURLEncodeParameters'] ?? false) ? $value : rawurlencode((string)$value)); }
	return $url;
}
class DetailScriptConfig {
	public function get($name) { return $name === 'do_not_display_collection_browser' && !$GLOBALS['detailScriptShowHierarchy']; }
}
class DetailScriptItem {
	private array $values;
	public function __construct($objectCount) {
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
	public function getWithTemplate($template, $options = array()) { return ''; }
}
class DetailScriptView {
	public $request;
	private array $values;
	public function __construct($objectCount) {
		$this->request = new stdClass();
		$this->values = array('item' => new DetailScriptItem($objectCount), 'comments' => array());
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

$cases = array();
foreach (array(
	array('name' => 'multiple objects and hierarchy', 'table' => 'ca_collections', 'objects' => 2, 'hierarchy' => true, 'mode' => 'only', 'objectSearch' => 'collection_id%3A42'),
	array('name' => 'all items and hierarchy', 'table' => 'ca_collections', 'objects' => 2, 'hierarchy' => true, 'mode' => 'all', 'objectSearch' => 'collection_id%3A42'),
	array('name' => 'single object without hierarchy', 'table' => 'ca_collections', 'objects' => 1, 'hierarchy' => false, 'mode' => 'only')
) as $case) {
	$GLOBALS['detailScriptMode'] = $case['mode'];
	$GLOBALS['detailScriptShowHierarchy'] = $case['hierarchy'];
	$html = (new DetailScriptView($case['objects']))->render($case['table']);
	preg_match_all('~<script\b[^>]*>(.*?)</script\s*>~is', $html, $scripts);
	$expectedScriptCount = ($case['hierarchy'] ? 1 : 0) + ($case['objects'] >= 2 ? 1 : 0) + ($case['extraScripts'] ?? 0);
	checkDetailScripts(count($scripts[1]) === $expectedScriptCount, $case['name'].': unexpected inline script count.');
	checkDetailScripts((strpos($html, 'id="collectionHierarchy"') !== false) === $case['hierarchy'], $case['name'].': hierarchy rendering changed.');
	checkDetailScripts((strpos($html, 'id="browseResultsContainer"') !== false) === ($case['objects'] >= 2), $case['name'].': object contents rendering changed.');
	$cases[] = array(
		'name' => $case['name'],
		'scripts' => $scripts[1],
		'hierarchyUrl' => $case['hierarchy'] ? '/synthetic/Collections/collectionHierarchy/collection_id/42' : null,
		'objectsUrl' => $case['objects'] >= 2 ? '/synthetic/Search/objects/search/'.$case['objectSearch'] : null,
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
