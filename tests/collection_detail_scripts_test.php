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
define('__CA_ACL_READONLY_ACCESS__', 1);
$GLOBALS['detailScriptAssertions'] = 0;
$GLOBALS['detailScriptMode'] = 'only';
$GLOBALS['detailScriptShowHierarchy'] = true;
$GLOBALS['detailScriptHasChildren'] = true;
$GLOBALS['detailScriptIcon'] = "<span class='caIcon' data-state=\"loading\" data-note=\"collector's </script> <>& \\ \u{2028}\u{2029}\"></span>";
$GLOBALS['detailScriptLoading'] = "Loading collector's \"quoted\" media \\ & < > \u{2028}\u{2029}";

function checkDetailScripts($condition, $message) {
	$GLOBALS['detailScriptAssertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function _t($text) { return $text === 'Loading...' ? $GLOBALS['detailScriptLoading'] : $text; }
function caGetUserAccessValues($request) { $GLOBALS['bulkCollectionRequest'] = $request; return array(1); }
function caACLIsEnabled($record, $options = []) { return false; }
function tadlMediaPreference($request) { return $GLOBALS['detailScriptMode']; }
function tadlMediaEligibleIDs($table, $ids, $access) { return $ids; }
function caBusyIndicatorIcon($request) { return $GLOBALS['detailScriptIcon']; }
function caGetBrowseConfig() { return new DetailScriptConfig(); }
function caGetCollectionsConfig() { return new DetailScriptConfig(); }
function caNavLink($request, $label, $class, $module, $controller, $action) {
	return '<a href="'.htmlspecialchars(caNavUrl($request, $module, $controller, $action), ENT_QUOTES).'">'.$label.'</a>';
}
function caNavUrl($request, $module, $controller, $action, $params = array(), $options = array()) {
	$url = '/synthetic/'.$controller.'/'.$action;
	foreach ($params as $name => $value) { $url .= '/'.$name.'/'.(($options['dontURLEncodeParameters'] ?? false) ? $value : rawurlencode((string)$value)); }
	return $url;
}
class DetailScriptUser {
    public function __construct(private int $userclass) {}
    public function isStandardUser() { return $this->userclass === 0; }
}
class DetailScriptRequest {
    public DetailScriptUser $user;
    public array $cookies = [];
    public function __construct(public array $params = [], public bool $loggedIn = false, int $userclass = 1) { $this->user = new DetailScriptUser($userclass); }
    public function isLoggedIn() { return $this->loggedIn; }
    public function getParameter($name, $type, $options = []) { return $this->params[$name] ?? null; }
    public function getParameters($sources) { return $sources === ['COOKIE'] ? $this->cookies : $this->params; }
}
class DetailScriptConfig {
    public function getAssoc($name) { return ['objects' => ['sortBy' => ['Identifier' => 'idno', 'Title' => 'name']]]; }
	public function get($name) { return $name === 'landing_page_collection_type' ? 'collection' : ($name === 'do_not_display_collection_browser' && !$GLOBALS['detailScriptShowHierarchy']); }
}
class DetailScriptItem {
	private array $values;
	public function __construct($objectCount, private array $templates = [], private int $id = 42, private string $type = 'collection') {
		$this->values = array(
			'collection_id' => $id, 'parent_id' => $GLOBALS['detailScriptParents'][$id] ?? null, 'type_id' => $type, 'access' => $GLOBALS['detailScriptCollectionAccess'][$id] ?? 1, 'deleted' => 0,
			'entity_id' => 42,
			'place_id' => 42,
			'occurrence_id' => 42,
			'ca_objects.object_id' => array_slice(array(101, 102), 0, $objectCount),
			'ca_collections.hierarchy.collection_id' => array(42)
		);
	}
	public function getPrimaryKey() { return $this->id; }
	public function tableName() { return 'ca_collections'; }
	public function getTypeCode() { return $this->type; }
	public function hasElement($code) { return !in_array($code, $GLOBALS['detailScriptMissingElements'] ?? [], true); }
	public function load($id) {
		$this->id = (int)$id;
		$this->type = $GLOBALS['detailScriptCollectionTypes'][$this->id] ?? ($this->id === 42 ? 'collection' : 'container');
		$this->values['collection_id'] = $this->id;
		$this->values['parent_id'] = $GLOBALS['detailScriptParents'][$this->id] ?? null;
		$this->values['access'] = $GLOBALS['detailScriptCollectionAccess'][$this->id] ?? 1;
		return !in_array($this->id, $GLOBALS['detailScriptUnavailableCollections'] ?? [], true);
	}
	public function isReadable($request, $bundle = null) { return !in_array($bundle, $GLOBALS['detailScriptDeniedBundles'] ?? [], true); }
	public function getAttributeDisplayValues($code, $id, $options) {
		checkDetailScripts($code === 'external_link' && $id === $this->id && $options === ['checkAccess' => [1], 'convertCodesToDisplayText' => true], 'External links must read only the current unit with native access options.');
		$GLOBALS['detailScriptExternalReads'][] = $id;
		return $GLOBALS['detailScriptExternalLinks'] ?? [];
	}
	// The legacy template passes this result to array_shift() by reference. Keep
	// that unrelated PHP notice outside this emitted-JavaScript regression.
	public function &get($name, $options = array()) { return $this->values[$name]; }
	public function getWithTemplate($template, $options = array()) {
		$GLOBALS['detailScriptMetadataReads'][] = $template;
		if (preg_match('/^\^ca_collections\.(description|description_source|collection_scope_content|provenance|date|extent_text|extent|rights)\b/', $template)) {
			checkDetailScripts(($options['checkAccess'] ?? null) === [1] && ($options['dontReturnDefault'] ?? null) === true, 'Selected-unit metadata must retain native access options and never invent default values.');
		}
		checkDetailScripts(($options['convertCodesToDisplayText'] ?? null) === true, 'Detail fields lost native display conversion.');
		if ($template === '^ca_collections.preferred_labels.name') { return $GLOBALS['detailScriptCollectionLabels'][$this->id] ?? 'Synthetic collection '.$this->id; }
		foreach ($this->templates as $code => $value) {
			if (str_contains($template, $code)) { return $value; }
		}
		return '';
	}
}
require_once(__DIR__.'/support/collection_bulk_boundary.php');
class Datamodel {
 static function getInstanceByTableName($table, $cached) { return new BulkCollectionBoundary(); }
	static function getInstance($table, $initialize) { return new DetailScriptItem(0); }
}
class Db {
	function query($sql, $params) {
		checkDetailScripts(str_contains($sql, 'parent_id IN (?) AND access IN (?) AND deleted = 0') && $params === [[$GLOBALS['detailScriptExpectedCollection'] ?? 42], [1]], 'Hierarchy switch must inspect only accessible, undeleted direct children of the selected unit.');
		return new class {
			private bool $read = false;
			function nextRow() { if ($this->read || !$GLOBALS['detailScriptHasChildren']) { return false; } $this->read = true; return true; }
			function get($name) { return 43; }
		};
	}
}
class DetailScriptView {
	public $request;
	private array $values;
	public function __construct($objectCount, array $templates = [], array $params = [], $id = 42, $type = 'collection') {
		$GLOBALS['detailScriptExpectedCollection'] = $id;
		$this->request = new DetailScriptRequest($params);
		$this->values = array('item' => new DetailScriptItem($objectCount, $templates, $id, $type), 'comments' => array(), 'pdfEnabled' => true,
			'previousLink' => '<a href="/synthetic/Detail/collections/41">Previous</a>',
			'nextLink' => '<a href="/synthetic/Detail/collections/43">Next</a>',
			'resultsLink' => '<a href="/synthetic/results">Back</a>');
	}
	public function getVar($name) { return $this->values[$name] ?? null; }
	public function setVar($name, $value) { $this->values[$name] = $value; }
	public function render($table) {
		if ($table === 'Details/collection_items_html.php') {
			return '<div data-server-collection="'.htmlspecialchars(json_encode($this->getVar('collection_result_params')), ENT_QUOTES).'">Synthetic server-rendered items</div>';
		}
		if ($table === 'Details/collection_browser_html.php') { return '<div data-server-hierarchy="true">Synthetic server-rendered hierarchy</div>'; }
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
	checkDetailScripts(!preg_match('~<h3>(Description|Dates|Related collections|Related people|Related events|Related places)</h3>~', $html), 'Empty collection fields or relationships emitted headings.');
}
$html = (new DetailScriptView(0, ['ca_collections.description' => 'Synthetic description', 'ca_collections.date.dates_value' => '1930', 'relativeTo="ca_places"' => '<a href="/synthetic/place">Synthetic place</a>']))->render('ca_collections');
foreach (['flat', 'hierarchy'] as $mode) {
	$GLOBALS['detailScriptMetadataReads'] = [];
	$paged_html = (new DetailScriptView(2, ['ca_collections.description' => 'Repeated description', 'ca_collections.date.dates_value' => '1930'],
		['s' => 9, 'sort' => 'Title', 'direction' => 'desc', 'collection_view' => $mode]))->render('ca_collections');
	checkDetailScripts(!str_contains($paged_html, 'Repeated description') && !str_contains($paged_html, 'tadl-collection-metadata'), 'Subsequent collection pages must omit descriptive metadata.');
	checkDetailScripts(!array_filter($GLOBALS['detailScriptMetadataReads'], static fn($value) => str_contains($value, '^ca_collections.description') || str_contains($value, '^ca_collections.date') || str_contains($value, 'relativeTo="ca_entities"')), 'Subsequent pages must avoid fetching omitted metadata and related people.');
	checkDetailScripts(str_contains($paged_html, 'About this collection') && str_contains($paged_html, '/collection_view/'.$mode.'/view/images/sort/Title/direction/desc/s/0'), 'Later pages must link to the first page and retain collection view/sort state.');
	checkDetailScripts(str_contains($paged_html, 'View finding aid') && str_contains($paged_html, '<H1>') && str_contains($paged_html, 'Browse collections'), 'Paged collections must retain identity, finding aid and navigation.');
}
$first_page = (new DetailScriptView(0, ['ca_collections.description' => 'First page description'], ['s' => 0]))->render('ca_collections');
checkDetailScripts(str_contains($first_page, 'First page description') && !str_contains($first_page, 'About this collection'), 'The first page must retain full metadata without a redundant details link.');
$sticky_view = new DetailScriptView(2);
$sticky_view->request->cookies['tadlDisplayPreferences'] = json_encode(['view'=>'list', 'list_per_page'=>'50', 'collection_view'=>'hierarchy']);
$sticky_html = str_replace('\\/', '/', $sticky_view->render('ca_collections'));
$sticky_document = new DOMDocument(); $sticky_document->loadHTML($sticky_html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
$sticky_params = json_decode((new DOMXPath($sticky_document))->query('//*[@data-server-collection]')->item(0)->getAttribute('data-server-collection'), true);
checkDetailScripts($sticky_params['collection_view'] === 'hierarchy' && $sticky_params['view'] === 'list' && $sticky_params['n'] === 50 && $sticky_params['per_page'] === '50' && str_contains($sticky_html, 'data-tadl-collection-view="flat"'), 'Collection details must apply remembered mode, view and size to server results and the switch.');
$finding_document = new DOMDocument(); $finding_document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
$finding_xpath = new DOMXPath($finding_document);
$finding_view = $finding_xpath->query('//div[@class="exportCollection"]/a[contains(@href,"/CollectionFindingAid/View/")]')->item(0);
checkDetailScripts($finding_xpath->query('//div[@class="exportCollection"]/a')->length === 1 && $finding_view && trim($finding_view->textContent) === 'View finding aid (PDF)', 'The collection page must offer one descriptive View finding aid link, with downloading available inside the viewer.');
checkDetailScripts($finding_view && str_contains($finding_view->getAttribute('href'), '/collection_id/42') && !$finding_view->hasAttribute('target') && $finding_view->getAttribute('aria-haspopup') === 'dialog' && $finding_view->getAttribute('aria-controls') === 'caMediaPanel', 'View must open the existing document dialog and retain a direct PDF fallback.');
checkDetailScripts($finding_view->getAttribute('rel') === 'nofollow' && $finding_xpath->query('.//span[contains(@class,"glyphicon")]', $finding_view)->length === 0, 'View must remain a text link with descriptive link text and crawler directives.');
checkDetailScripts($finding_view->getAttribute('data-finding-aid-viewer') === '/synthetic/CollectionFindingAid/Viewer/collection_id/42' && str_contains($finding_view->getAttribute('onclick'), 'caMediaPanel.showPanel('), 'The viewer dialog must use the selected finding-aid endpoint.');
$finding_callback = $finding_view->getAttribute('onclick');
$finding_js = 'const assert = require("node:assert/strict"); const events = []; const link = {getAttribute: () => "/synthetic/CollectionFindingAid/Viewer/collection_id/42"}; const callback = new Function("event", "window", "caMediaPanel", '.json_encode($finding_callback).'); const panel = {showPanel: url => events.push(url)}; assert.equal(callback.call(link, {}, {caMediaPanel:panel}, panel), false); assert.deepEqual(events, [link.getAttribute()]); for (const modifier of ["ctrlKey","metaKey","shiftKey","altKey"]) {assert.equal(callback.call(link, {[modifier]:true}, {caMediaPanel:panel}, panel), undefined);} assert.equal(events.length,1); assert.equal(callback.call(link, {}, {}, undefined), undefined);';
$finding_process = proc_open(['node', '-e', $finding_js], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $finding_pipes);
fclose($finding_pipes[0]); $finding_output = stream_get_contents($finding_pipes[1]).stream_get_contents($finding_pipes[2]); fclose($finding_pipes[1]); fclose($finding_pipes[2]);
checkDetailScripts(proc_close($finding_process) === 0, 'Finding-aid viewer callback must retain modified-click and missing-viewer fallbacks: '.$finding_output);
$GLOBALS['detailScriptParents'] = [73 => 72, 72 => 71, 71 => 42];
$subunit_html = (new DetailScriptView(0, ['ca_collections.description' => 'Selected folder description'], [], 73, 'folder'))->render('ca_collections');
$subunit_document = new DOMDocument(); $subunit_document->loadHTML($subunit_html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
$subunit_xpath = new DOMXPath($subunit_document);
$subunit_view = $subunit_xpath->query('//div[@class="exportCollection"]/a')->item(0);
checkDetailScripts($subunit_view && $subunit_view->getAttribute('href') === '/synthetic/CollectionFindingAid/View/collection_id/42' && $subunit_view->getAttribute('data-finding-aid-viewer') === '/synthetic/CollectionFindingAid/Viewer/collection_id/42', 'Folder links and their direct-PDF fallbacks must open the whole hierarchy root finding aid.');
checkDetailScripts(str_contains($subunit_html, 'Selected folder description') && str_contains($subunit_html, 'data-server-collection=') && str_contains($subunit_html, '&quot;collection_id&quot;:73'), 'Resolving the aid root must not change the selected folder metadata or item scope.');
$GLOBALS['detailScriptUnavailableCollections'] = [71];
checkDetailScripts(!str_contains((new DetailScriptView(0, [], [], 73, 'folder'))->render('ca_collections'), 'exportCollection'), 'An unavailable ancestor must suppress the root finding-aid link instead of falling back to a partial aid.');
$GLOBALS['detailScriptUnavailableCollections'] = []; $GLOBALS['detailScriptParents'] = [];
checkDetailScripts(!str_contains($html, 'Part of:'), 'A root without parents must not display a self-referencing Part of line.');
$GLOBALS['detailScriptParents'] = [73 => 72, 72 => 42, 42 => 90, 90 => 91];
$GLOBALS['detailScriptCollectionAccess'] = [90 => 0];
$GLOBALS['detailScriptCollectionLabels'] = [42 => 'Synthetic root &amp; &lt;archive&gt;', 72 => 'Synthetic box', 90 => 'PRIVATE grouping', 91 => 'Ancestor above private grouping'];
$public_root_html = (new DetailScriptView(0))->render('ca_collections');
checkDetailScripts(!str_contains($public_root_html, 'Part of:') && str_contains($public_root_html, '/CollectionFindingAid/View/collection_id/42'), 'A public root beneath private administrative groups must omit Part of but retain its own finding aid.');
$public_folder_html = (new DetailScriptView(0, [], [], 73, 'folder'))->render('ca_collections');
$parent_document = new DOMDocument(); $parent_document->loadHTML($public_folder_html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); $parent_xpath = new DOMXPath($parent_document);
$parent_line = $parent_xpath->query('//div[@class="unit"][starts-with(normalize-space(.), "Part of:")]')->item(0);
checkDetailScripts($parent_line && $parent_line->textContent === 'Part of: Synthetic root & <archive> > Synthetic box', 'A folder must display genuine ancestors in root-to-parent order with safely escaped names.');
checkDetailScripts($parent_xpath->query('.//a', $parent_line)->length === 2 && $parent_xpath->query('.//a[@href="/synthetic/Detail/collections/73"]|.//archive', $parent_line)->length === 0 && !str_contains($public_folder_html, 'PRIVATE grouping') && !str_contains($public_folder_html, 'Ancestor above private grouping'), 'Part of must not link the current record, expose administrative groups or cross a hidden ancestor.');
checkDetailScripts(str_contains($public_folder_html, '/CollectionFindingAid/View/collection_id/42'), 'Subfolders beneath a public root must retain its full finding aid despite hidden administrative ancestors.');
$GLOBALS['detailScriptDeniedBundles'] = ['preferred_labels'];
checkDetailScripts(!str_contains((new DetailScriptView(0, [], [], 73, 'folder'))->render('ca_collections'), 'Part of:'), 'Unreadable parent labels must not produce a Part of heading.');
$GLOBALS['detailScriptDeniedBundles'] = []; $GLOBALS['detailScriptParents'] = []; $GLOBALS['detailScriptCollectionAccess'] = []; $GLOBALS['detailScriptCollectionLabels'] = [];
checkDetailScripts(!str_contains($html, 'Download as PDF') && !str_contains($html, '_pdf_ca_collections_summary'), 'Collection still links to the generic summary export.');
checkDetailScripts(str_contains($html, '<h3>Description</h3>Synthetic description') && str_contains($html, '<h3>Dates</h3>1930') && str_contains($html, '<h3>Related places</h3><a href="/synthetic/place">Synthetic place</a>'), 'Populated collection fields lost headings or native links.');
$long_description = '<p>'.str_repeat('Synthetic collection description. ', 30).'</p>';
$long_scope = '<p>'.str_repeat('Synthetic scope and content. ', 30).'</p>';
$html = (new DetailScriptView(0, ['ca_collections.description_source' => '', 'ca_collections.description' => $long_description, 'ca_collections.collection_scope_content' => $long_scope, 'ca_collections.date.dates_value' => '1930']))->render('ca_collections');
checkDetailScripts(substr_count($html, '<details class="tadl-long-field-details">') === 2, 'Long description and scope/content need independent native disclosures on the actual collection template.');
checkDetailScripts(str_contains($html, '<div class="tadl-long-field-full">'.$long_description.'</div>') && str_contains($html, '<div class="tadl-long-field-full">'.$long_scope.'</div>') && str_contains($html, '<h3>Dates</h3>1930'), 'Collection disclosures must keep complete rich values and leave short fields unchanged.');

// Reload/direct collection links forward only supported result state, never a supplied search/key.
foreach ([
    [['view' => 'list', 'sort' => 'Title', 'direction' => 'desc', 's' => 24, 'search' => 'unrelated', 'key' => 'unrelated'], '/view/list/sort/Title/direction/desc/s/24/n/25'],
    [['view' => 'unknown', 'sort' => 'unknown', 'direction' => 'unknown', 's' => -9], '/view/images/sort/Identifier/direction/asc/s/0/n/18']
] as [$params, $suffix]) {
    $html = (new DetailScriptView(2, [], $params))->render('ca_collections');
    $doc = new DOMDocument(); $doc->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    $forwarded = json_decode((new DOMXPath($doc))->query('//*[@data-server-collection]')->item(0)->getAttribute('data-server-collection'), true);
    $actual = ''; foreach (['view','sort','direction','s','n'] as $key) { $actual .= '/'.$key.'/'.$forwarded[$key]; }
    checkDetailScripts($actual === $suffix && $forwarded['collection_id'] === 42 && $forwarded['collection_view'] === 'flat', 'Server collection results failed to validate/forward their state.');
    checkDetailScripts(!str_contains($html, 'unrelated'), 'Caller search/key must not replace this collection.');
}
$html = (new DetailScriptView(2))->render('ca_collections');
checkDetailScripts(!str_contains($html, 'class="tadl-collection-metadata"'), 'Empty collection metadata must not reserve columns.');
$html = (new DetailScriptView(2, [
	'ca_collections.description_source' => 'Synthetic citation', 'ca_collections.description' => $long_description,
	'ca_collections.collection_scope_content' => $long_scope,
	'restrictToRelationshipTypes="creator"' => '<a href="/synthetic/creator">Synthetic creator</a>',
	'ca_collections.date.dates_value' => '1930', 'ca_collections.extent_text' => 'Synthetic extent',
	'ca_collections.language' => 'English', 'relativeTo="ca_list_items"' => 'Synthetic subject',
	'ca_collections.lcsh_terms' => 'Synthetic heading', 'ca_collections.rights.rightsText' => 'Synthetic rights',
	'ca_collections.rights.copyrightStatement' => 'Synthetic statement',
	'relativeTo="ca_places"' => '<a href="/synthetic/place">Synthetic place</a>'
]))->render('ca_collections');
checkDetailScripts(strpos($html, 'tadl-collection-metadata') < strpos($html, 'browseResultsContainer') && str_contains($html, 'tadl-collection-fields') && str_contains($html, 'tadl-collection-facts'), 'Populated metadata must sit beside the heading above contents.');
$document = new DOMDocument();
$document->loadHTML('<meta charset="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($document);
$column_labels = static fn($class) => array_map(static fn($node) => $node->textContent, iterator_to_array($xpath->query('//div[@class="'.$class.'"]//div[@class="unit"]/h3')));
checkDetailScripts($column_labels('tadl-collection-fields') === ['Description', 'Source of description', 'Scope and content'], 'Center column must group the narrative fields together.');
checkDetailScripts($column_labels('tadl-collection-facts') === ['Creators', 'Dates', 'Extent', 'Language', 'Vocabulary terms', 'Library of Congress subject headings', 'Rights', 'Copyright statement', 'Related places'], 'Right column must group collection facts and relationships without losing fields.');
checkDetailScripts($xpath->query('//div[@class="tadl-collection-facts"]//a[@href="/synthetic/creator"]')->length === 1, 'Moving creators must preserve their native links.');
// An archival unit's metadata does not depend on its level, object count or media.
foreach (['series', 'container', 'folder', 'sub_sub_series'] as $offset => $type) {
	$id = 70 + $offset;
	$GLOBALS['detailScriptExternalLinks'] = [29 => [901 => ['url_source' => 'Synthetic guide & index', 'url_entry' => 'https://example.org/guide?x=1&y=2'], 902 => ['url_source' => '<script>not markup</script>', 'url_entry' => 'javascript:alert(1)'], 903 => ['url_source' => 'No URL', 'url_entry' => '']]];
	$values = ['ca_collections.description' => 'Own '.$type.' description.', 'ca_collections.collection_scope_content' => 'Own '.$type.' scope.', 'ca_collections.extent_text' => '80 synthetic clippings; 20 x 30 cm', 'ca_collections.date.dates_value' => '1900-1910', 'ca_collections.language' => 'English', 'ca_collections.provenance' => $long_description, 'ca_collections.rights.rightsText' => 'Synthetic use conditions', 'ca_collections.internal_notes' => 'PRIVATE staff notes', 'ca_collections.mdpn_identifiers' => 'PRIVATE preservation identifiers'];
	$html = (new DetailScriptView(0, $values, [], $id, $type))->render('ca_collections');
	checkDetailScripts(str_contains($html, 'Own '.$type.' description.') && str_contains($html, 'Own '.$type.' scope.') && str_contains($html, '80 synthetic clippings; 20 x 30 cm') && str_contains($html, '<h3>Dates</h3>1900-1910') && str_contains($html, 'Synthetic use conditions'), 'Every archival level needs its own descriptive metadata even without catalogued objects.');
	checkDetailScripts(str_contains($html, '<h3>Provenance</h3>') && str_contains($html, '<div class="tadl-long-field-full">'.$long_description.'</div>'), 'Provenance must preserve rich text and long-field disclosures.');
	$doc = new DOMDocument(); $doc->loadHTML('<meta charset="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); $nodes = new DOMXPath($doc);
	checkDetailScripts($nodes->query('//div[h3="External links"]//a')->length === 1 && $nodes->query('//div[h3="External links"]//a')->item(0)->getAttribute('href') === 'https://example.org/guide?x=1&y=2', 'External links must preserve their source/URL pairing and use safely escaped HTTP(S) URLs.');
	checkDetailScripts($nodes->query('//div[h3="External links"]//script')->length === 0 && !str_contains($html, 'PRIVATE') && !str_contains($html, 'No URL'), 'Links must escape labels, avoid executable URLs and omit blank links; staff fields stay excluded.');
	checkDetailScripts(str_contains($html, '/collection_id/'.$id) && end($GLOBALS['detailScriptExternalReads']) === $id, 'Metadata and controls must use the selected archival unit rather than its parent or an object record.');
}
$GLOBALS['detailScriptDeniedBundles'] = ['description', 'description_source', 'collection_scope_content', 'provenance', 'date', 'extent_text', 'language', 'lcsh_terms', 'rights', 'external_link'];
$GLOBALS['detailScriptMetadataReads'] = []; $GLOBALS['detailScriptExternalReads'] = [];
$html = (new DetailScriptView(0, $values))->render('ca_collections');
checkDetailScripts(!str_contains($html, 'Own sub_sub_series description.') && !str_contains($html, '80 synthetic clippings') && !str_contains($html, '<h3>Provenance</h3>') && !str_contains($html, 'Synthetic use conditions') && !str_contains($html, '<h3>External links</h3>') && !$GLOBALS['detailScriptExternalReads'], 'Unreadable descriptive bundles must be omitted before fetching values.');
foreach ($GLOBALS['detailScriptMetadataReads'] as $template) {
	checkDetailScripts(!preg_match('/\^ca_collections\.(?:description|date|extent_text|language|lcsh_terms|rights|provenance|collection_scope_content)(?:\b|\.)/', $template), 'Denied native metadata must not be read.');
}
$GLOBALS['detailScriptDeniedBundles'] = []; $GLOBALS['detailScriptMissingElements'] = ['provenance', 'external_link'];
$html = (new DetailScriptView(0, $values))->render('ca_collections');
checkDetailScripts(!str_contains($html, '<h3>Provenance</h3>') && !str_contains($html, '<h3>External links</h3>'), 'A profile without optional descriptive fields must not be queried or display placeholders.');
$GLOBALS['detailScriptMissingElements'] = []; $GLOBALS['detailScriptExternalLinks'] = [];
foreach ([['ca_collections.description' => 'Synthetic description'], ['ca_collections.extent_text' => 'Synthetic extent']] as $values) {
	$html = (new DetailScriptView(0, $values))->render('ca_collections');
	checkDetailScripts(substr_count($html, 'class="tadl-collection-fields"') + substr_count($html, 'class="tadl-collection-facts"') === 1, 'A single populated metadata column must not reserve an empty sibling.');
}
$html = (new DetailScriptView(0))->render('ca_collections');
checkDetailScripts(strpos($html, 'exportCollection') < strpos($html, 'Collection hierarchy view') && str_contains($html, '/Detail/collections/42/collection_view/hierarchy/view/images/sort/Identifier/direction/asc/s/0'), 'Flat view needs an ordinary hierarchy link beneath the finding aid.');
$html = (new DetailScriptView(2, [], ['collection_view' => 'hierarchy', 'view' => 'list', 'sort' => 'Title', 'direction' => 'desc', 's' => 24]))->render('ca_collections');
checkDetailScripts(str_contains($html, 'Flat collection view') && str_contains($html, '/Detail/collections/42/collection_view/flat/view/list/sort/Title/direction/desc/s/0'), 'Return link must retain view/sort but reset paging for the larger scope.');
$GLOBALS['detailScriptHasChildren'] = false;
$html = (new DetailScriptView(0))->render('ca_collections');
checkDetailScripts(!str_contains($html, 'tadl-collection-view-switch') && !str_contains($html, 'collectionHierarchy'), 'Leaf collections must not offer an empty hierarchy.');
$GLOBALS['detailScriptHasChildren'] = true;

$staff_shortcut_fixtures = [];
foreach ([42 => 'collection', 73 => 'folder'] as $id => $type) {
	foreach ([[false, 0], [false, 1], [true, 1], [true, 255], [true, 99], [true, 0]] as [$logged_in, $userclass]) {
		$staff = $logged_in && $userclass === 0;
		$view = new DetailScriptView(0, [], [], $id, $type);
		$view->request = new DetailScriptRequest([], $logged_in, $userclass);
		$html = $view->render('ca_collections');
		$document = new DOMDocument(); $document->loadHTML('<meta charset="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); $xpath = new DOMXPath($document);
		$editor_links = $xpath->query('//div[@class="tadl-collection-header-actions"]/div[@class="tadl-collection-staff-actions"]/a');
		checkDetailScripts($editor_links->length === ($staff ? 1 : 0), 'The actual collection header must offer a Providence shortcut only to logged-in full-access staff.');
		checkDetailScripts($staff || (!str_contains($html, 'collections.tadl.org') && !str_contains($html, 'tadl-collection-staff-actions')), 'Anonymous/public account HTML must omit the staff action and its URL entirely.');
		if ($staff) {
			$link = $editor_links->item(0);
			checkDetailScripts($link->getAttribute('href') === 'https://collections.tadl.org/index.php/editor/collections/CollectionEditor/Edit/collection_id/'.$id, 'The collection shortcut must target the selected record, including a folder, rather than its finding-aid root.');
			checkDetailScripts($xpath->query('//nav[@class="tadl-record-nav"]/following-sibling::div[@class="tadl-collection-staff-actions"]')->length === 1, 'The staff button must sit beneath the collection navigation.');
			checkDetailScripts($link->getAttribute('target') === '_blank' && $link->getAttribute('rel') === 'noopener noreferrer' && str_contains($link->textContent, '(opens in a new tab)'), 'Actual collection markup must preserve safe new-tab behavior and its accessible hint.');
		}
		if ($id === 42 && ($staff || (!$logged_in && $userclass === 1))) {
			$header = $xpath->query('//header[contains(@class,"tadl-collection-record-header")]')->item(0);
			$header->getElementsByTagName('h1')->item(0)->nodeValue = 'Synthetic Community History Collection: Oral Histories, Photographs, Diaries, Family Papers and Records of Regional Organizations';
			$header->getElementsByTagName('h2')->item(0)->nodeValue = 'COLLECTION, SYNTHETIC.42';
			$staff_shortcut_fixtures[$staff ? 'collection-staff' : 'collection-public'] = $document->saveHTML($header);
		}
	}
}

foreach (array(
	array('name' => 'multiple objects and hierarchy', 'table' => 'ca_collections', 'objects' => 2, 'hierarchy' => true, 'mode' => 'only', 'collectionView' => 'hierarchy'),
	array('name' => 'all items and hierarchy', 'table' => 'ca_collections', 'objects' => 2, 'hierarchy' => true, 'mode' => 'all', 'collectionView' => 'hierarchy'),
	array('name' => 'single object without hierarchy', 'table' => 'ca_collections', 'objects' => 1, 'hierarchy' => false, 'mode' => 'only', 'collectionView' => 'hierarchy'),
	array('name' => 'single item without hierarchy and all items', 'table' => 'ca_collections', 'objects' => 1, 'hierarchy' => false, 'mode' => 'all', 'collectionView' => 'hierarchy'),
	array('name' => 'single media item with hierarchy', 'table' => 'ca_collections', 'objects' => 1, 'hierarchy' => true, 'mode' => 'only', 'collectionView' => 'hierarchy'),
	array('name' => 'single item with hierarchy and all items', 'table' => 'ca_collections', 'objects' => 1, 'hierarchy' => true, 'mode' => 'all', 'collectionView' => 'hierarchy'),
	array('name' => 'hierarchy without direct items', 'table' => 'ca_collections', 'objects' => 0, 'hierarchy' => true, 'mode' => 'all', 'collectionView' => 'hierarchy'),
	array('name' => 'flat root with only nested items', 'table' => 'ca_collections', 'objects' => 0, 'hierarchy' => false, 'mode' => 'only'),
	array('name' => 'flat single item', 'table' => 'ca_collections', 'objects' => 1, 'hierarchy' => false, 'mode' => 'all'),
	array('name' => 'invalid mode defaults to flat', 'table' => 'ca_collections', 'objects' => 0, 'hierarchy' => false, 'mode' => 'only', 'collectionView' => 'unknown')
) as $case) {
	$GLOBALS['detailScriptMode'] = $case['mode'];
	$GLOBALS['detailScriptShowHierarchy'] = $case['hierarchy'];
	$html = (new DetailScriptView($case['objects'], [], ['collection_view' => $case['collectionView'] ?? 'flat']))->render($case['table']);
	$contentsMode = ($case['collectionView'] ?? '') === 'hierarchy' ? 'hierarchy' : 'flat';
	$hasGrid = $contentsMode === 'flat' || $case['objects'] >= 1;
	checkDetailScripts(!str_contains($html, 'Related Object:') && !str_contains($html, '^ca_object_representations.media'), $case['name'].': object media must not be embedded in collection metadata.');
	checkDetailScripts(!preg_match('~navTop|navLeftRight|detailNavBg|\{\{\{(?:previousLink|nextLink)\}\}\}~', $html), $case['name'].': collection detail still reserves record-navigation columns.');
	$navigationDom = new DOMDocument(); $navigationDom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	$navigationXPath = new DOMXPath($navigationDom);
	checkDetailScripts($navigationXPath->query('//header[contains(@class,"tadl-record-header")]//nav[@class="tadl-record-nav"]/a')->length === 3
		&& !str_contains($html, '/synthetic/results'), $case['name'].': collection heading must have Prev, Next and Browse, without Back.');
	foreach (['previous', 'next'] as $direction) {
		$label = 'Go to '.$direction.' collection';
		checkDetailScripts($navigationXPath->query('//nav[@class="tadl-record-nav"]/a[@title="'.$label.'" and @aria-label="'.$label.'"]')->length === 1, $case['name'].': collection navigation must have contextual tooltips and accessible names.');
	}
	checkDetailScripts(str_contains($html, '<div class="row tadl-collection-detail">') && str_contains($html, "<div class='col-xs-12'>"), $case['name'].': collection content lost its full-width layout.');
	preg_match_all('~<script\b[^>]*>(.*?)</script\s*>~is', $html, $scripts);
	$expectedScriptCount = 0;
	checkDetailScripts(count($scripts[1]) === $expectedScriptCount, $case['name'].': unexpected inline script count.');
	checkDetailScripts((strpos($html, 'id="collectionHierarchy"') !== false) === $case['hierarchy'], $case['name'].': hierarchy rendering changed.');
	checkDetailScripts((strpos($html, 'id="browseResultsContainer"') !== false) === $hasGrid, $case['name'].': object contents rendering changed.');
	checkDetailScripts(str_contains($html, 'data-server-collection=') === $hasGrid && str_contains($html, 'data-server-hierarchy=') === $case['hierarchy'], $case['name'].': content must be rendered in the response.');
	checkDetailScripts(!str_contains($html, 'aria-busy') && !str_contains($html, 'Loading') && !str_contains($html, '.load(') && !str_contains($html, '.jscroll('), $case['name'].': result lists must not use placeholders or background requests.');
}

if (($argv[1] ?? '') === '--finding-aid-fixture') {
	print json_encode(['links' => $subunit_document->saveHTML($subunit_view->parentNode)], JSON_THROW_ON_ERROR);
} elseif (($argv[1] ?? '') === '--staff-shortcut-fixtures') {
	print json_encode($staff_shortcut_fixtures, JSON_THROW_ON_ERROR);
} else {
	echo json_encode(array('status' => 'passed', 'assertions' => $GLOBALS['detailScriptAssertions'], 'templates' => 'actual collection detail; authority pages have authority_detail_test.php', 'dependencies' => 'synthetic view/model/config boundaries; server fragments verified separately; finding-aid callback execution'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
}

$collection_source = file_get_contents(dirname(__DIR__).'/views/Details/ca_collections_default_html.php');
checkDetailScripts(!str_contains($collection_source, '.load(') && !str_contains($collection_source, 'Loading...'), 'Collection results and hierarchy must be server rendered.');
