<?php
/** Actual finding-aid helper/controller/template with synthetic native boundaries. */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	if ($severity === E_DEPRECATED && getenv('TADL_TEST_COMPOSER_AUTOLOAD') && str_contains($file, '/vendor/')) { return true; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('pInteger', 1);
define('__CA_ACL_READONLY_ACCESS__', 1);
$assertions = 0;
function aidCheck($condition, $message) {
	$GLOBALS['assertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function _t($text) { return $text; }
function caGetUserAccessValues($request) { return $request->access; }
function caACLIsEnabled($record, $options) {
	aidCheck($options === ['forPawtucket' => true], 'ACL must use Pawtucket context.');
	return $record->row['acl'] ?? false;
}
class AidConfig {
	public array $values = [
		'enabled' => 1,
		'collection_fields' => [
			'description' => ['label' => 'Description', 'bundles' => ['ca_collections.description']],
			'dates' => ['label' => 'Dates', 'bundles' => ['ca_collections.date.dates_value']],
			'extent' => ['label' => 'Extent', 'bundles' => ['ca_collections.extent_text', 'ca_collections.extent']],
			'rights' => ['label' => 'Rights', 'bundles' => ['ca_collections.rights.rightsText']]
		]
	];
	function get($key) { return $this->values[$key] ?? null; }
	function getAssoc($key) { return $this->get($key); }
}
class Configuration { static function load($path) { aidCheck(str_ends_with($path, '/conf/finding_aid.conf'), 'Unexpected config path.'); return $GLOBALS['aidConfig']; } }
class AidRequest {
	public $config;
	public $user;
	public array $access = [1];
	function __construct(public array $params = ['collection_id' => 42], public string $method = 'GET', public bool $loggedIn = false) {
		$this->config = new AidConfig(); $this->user = new stdClass();
	}
	function getRequestMethod() { return $this->method; }
	function getParameter($key, $type) { aidCheck($type === pInteger, 'Collection ID must use native integer parsing.'); return $this->params[$key] ?? null; }
	function isLoggedIn() { return $this->loggedIn; }
}
class AidModel {
	public array $row = [];
	function __construct(public string $table) {}
	function load($id) { $GLOBALS['aidLoads'][] = [$this->table, $id]; $this->row = $GLOBALS['aidRows'][$this->table][$id] ?? []; return (bool)$this->row; }
	function tableName() { return $this->table; }
	function getPrimaryKey() { return $this->row['id'] ?? null; }
	function get($key) { return $this->row[$key] ?? null; }
	function hasField($key) { return in_array($key, ['idno', 'home_location_id'], true); }
	function hasElement($key) { return in_array($key, $this->row['elements'] ?? ['description', 'date', 'extent', 'rights', 'legacy_accession_number'], true); }
	function isReadable($request, $bundle = null) { return ($this->row['readable'] ?? true) && !in_array($bundle, $this->row['denied'] ?? [], true); }
	function checkACLAccessForUser($user) { aidCheck($user instanceof stdClass, 'Native ACL must receive current user.'); return $this->row['acl_level'] ?? 1; }
	function getWithTemplate($template, $options) {
		aidCheck($this->table === 'ca_collections', 'Count-only finding aids must not fetch object or storage metadata.');
		aidCheck(($options['checkAccess'] ?? null) === [1] && ($options['makeLink'] ?? null) === false, 'Metadata must preserve access and plain text options.');
		$GLOBALS['aidReads'][] = [$this->table, $this->getPrimaryKey(), $template];
		return $this->row['values'][substr($template, 1)] ?? '';
	}
	function getHierarchyChildren($id, $options) {
		aidCheck($id === $this->getPrimaryKey() && $options === ['idsOnly' => true], 'Hierarchy traversal must use the selected node.');
		return $this->row['children'] ?? [];
	}
	function getRelatedItems($table, $options) {
		aidCheck($options === ['idsOnly' => true, 'checkAccess' => [1], 'limit' => PHP_INT_MAX], 'Counts must remove native relationship limits and preserve access.');
		return $this->row['related'][$table] ?? [];
	}
}
class Datamodel {
	static function getInstance($table, $unused) {
		aidCheck(in_array($table, ['ca_collections', 'ca_objects'], true), 'Count-only finding aids must not load storage records.');
		return new AidModel($table);
	}
}
class AidView {
	public array $vars = [];
	function setVar($key, $value) { $this->vars[$key] = $value; }
	function getVar($key) { return $this->vars[$key] ?? null; }
	function render($path) { ob_start(); include dirname(__DIR__).'/views/'.$path; return ob_get_clean(); }
}
class AidResponse {
	public int $status = 200;
	public array $headers = [];
	public string $body = '';
	function addHeader($key, $value) { $this->headers[$key] = $value; }
	function setHTTPResponseCode($code, $message) { $this->status = $code; }
	function addContent($text) { $this->body .= $text; }
}
class BasePawtucketController {
	public $view;
	public $rendered;
	function __construct(public $request, public $response) { $this->view = new AidView(); }
	function render($path, $raw) { aidCheck($raw && $path === 'Details/finding_aid_binary.php', 'PDF must bypass page wrappers.'); $this->rendered = $path; }
}
$directory = sys_get_temp_dir().'/tadl-aid-test-'.bin2hex(random_bytes(8));
mkdir($directory.'/pawtucket', 0700, true);
file_put_contents($directory.'/pawtucket/BasePawtucketController.php', '<?php');
define('__CA_LIB_DIR__', $directory);
register_shutdown_function(function () use ($directory) {
	if (isset($GLOBALS['aidRoutingCleanup'])) { ($GLOBALS['aidRoutingCleanup'])(); }
	foreach (['/pawtucket/BasePawtucketController.php', '/pdf_stub.php'] as $file) { if (is_file($directory.$file)) { unlink($directory.$file); } }
	rmdir($directory.'/pawtucket'); rmdir($directory);
});
if ($autoload = getenv('TADL_TEST_COMPOSER_AUTOLOAD')) {
	require $autoload;
} else {
	file_put_contents($directory.'/pdf_stub.php', <<<'PHP'
<?php
namespace Dompdf;
class Options { function __construct($options) { $GLOBALS['aidPDFOptions'] = $options; } }
class Dompdf {
 function __construct($options) {}
 function setPaper($size, $orientation) { if ($size !== 'letter' || $orientation !== 'portrait') { throw new \RuntimeException('Unexpected paper.'); } }
 function loadHtml($html, $encoding) { $GLOBALS['aidPDFHtml'] = $html; }
 function render() { if ($GLOBALS['aidPDFFail'] ?? false) { throw new \RuntimeException('Synthetic renderer failure'); } }
 function getCanvas() { return $this; }
 function getFontMetrics() { return $this; }
 function getFont($name) { return $name; }
 function page_text($x, $y, $text, $font, $size, $color) { $GLOBALS['aidPDFFooter'] = $text; }
 function output() { return '%PDF-1.4'.str_repeat('synthetic renderer boundary ', 50).'%%EOF'; }
}
PHP);
	require $directory.'/pdf_stub.php';
}
require dirname(__DIR__).'/controllers/CollectionFindingAidController.php';
$GLOBALS['aidConfig'] = new AidConfig();
if ($dispatcher_path = getenv('TADL_TEST_REQUEST_DISPATCHER')) {
	require __DIR__.'/support/finding_aid_routing.php';
}
function aidRow($id, $table, $title, $identifier = '', $extra = []) {
	return array_replace_recursive(['id' => $id, 'access' => 1, 'deleted' => 0, 'values' => [$table.'.preferred_labels.name' => $title, $table.'.idno' => $identifier]], $extra);
}
$GLOBALS['aidRows'] = [
	'ca_collections' => [
		42 => aidRow(42, 'ca_collections', 'Synthetic Archives Collection', 'SYN.42', [
			'children' => [7, 8, 10, 11, 12], 'related' => ['ca_objects' => [101, 101, 102, 103, 104, 105, 106, 107, 108, 109]],
			'values' => ['ca_collections.description' => '<p>A synthetic collection &amp; its history.</p>', 'ca_collections.date.dates_value' => '; ', 'ca_collections.extent' => 'Two boxes', 'ca_collections.rights.rightsText' => 'Synthetic rights statement']
		]),
		7 => aidRow(7, 'ca_collections', 'Series A', 'SYN.7', ['children' => [14, 13], 'related' => ['ca_objects' => [101, 103, 111]]]),
		8 => aidRow(8, 'ca_collections', 'Private branch', '', ['access' => 0, 'children' => [9]]),
		9 => aidRow(9, 'ca_collections', 'Behind private branch', '', ['related' => ['ca_objects' => [110]]]),
		10 => aidRow(10, 'ca_collections', 'Denied branch', '', ['acl' => true, 'acl_level' => 0]),
		11 => aidRow(11, 'ca_collections', 'Deleted branch', '', ['deleted' => 1]),
		12 => aidRow(12, 'ca_collections', 'Empty series', '', ['children' => [42]]),
		13 => aidRow(13, 'ca_collections', 'Drawer 2', 'SYN.D2', ['children' => [15]]),
		14 => aidRow(14, 'ca_collections', 'Drawer 10', 'SYN.D10'),
		15 => aidRow(15, 'ca_collections', 'Folder 1', 'SYN.F1')
	],
	'ca_objects' => [
		101 => aidRow(101, 'ca_objects', 'Český časopis, 1930', 'SYN.10', ['home_location_id' => 50, 'related' => ['ca_storage_locations' => [51]], 'values' => ['ca_objects.date.dates_value' => '1930', 'ca_objects.legacy_accession_number' => 'OLD.10']]),
		102 => aidRow(102, 'ca_objects', 'Object without media', 'SYN.2', ['related' => ['ca_storage_locations' => [50, 51, 52, 53, 54]]]),
		103 => aidRow(103, 'ca_objects', 'Different object with the same identifier', 'SYN.10', ['denied' => ['ca_storage_locations']]),
		104 => aidRow(104, 'ca_objects', 'Private object', 'SECRET.4', ['access' => 0]),
		105 => aidRow(105, 'ca_objects', 'Deleted object', 'SECRET.5', ['deleted' => 1]),
		106 => aidRow(106, 'ca_objects', 'Unreadable object', 'SECRET.6', ['readable' => false]),
		107 => aidRow(107, 'ca_objects', 'ACL denied object', 'SECRET.7', ['acl' => true, 'acl_level' => 0]),
		109 => aidRow(109, 'ca_objects', 'Hidden name', 'HIDDEN', ['denied' => ['preferred_labels', 'idno', 'legacy_accession_number', 'date', 'ca_storage_locations']]),
		110 => aidRow(110, 'ca_objects', 'Hidden branch object', 'SECRET.10'),
		111 => aidRow(111, 'ca_objects', 'Child-only object', 'SYN.11')
	]
];
$request = new AidRequest();
$collection = new AidModel('ca_collections'); $collection->load(42);
$GLOBALS['aidLoads'] = []; $GLOBALS['aidReads'] = [];
$data = tadlFindingAidData($request, $collection, $GLOBALS['aidConfig']);
aidCheck($data['object_count'] === 5, 'Readable objects must be counted once by ID, including child-only records and those without media.');
aidCheck(!array_key_exists('objects', $data), 'Finding-aid data must contain counts rather than an object inventory.');
aidCheck(array_keys($data['collections']) === [42, 12, 7, 13, 15, 14], 'All readable descendants must appear in branch order with natural sibling sorting; unreadable/deleted branches and cycles must be excluded.');
aidCheck($data['collections'][42]['count'] === 4 && $data['collections'][7]['count'] === 3 && $data['collections'][12]['count'] === 0, 'Collection counts must count directly linked readable unique objects.');
aidCheck(array_column($data['collections'], 'depth') === [0, 1, 1, 2, 3, 2], 'Hierarchy depth must preserve each generation, including empty subcollections.');
aidCheck($data['collections'][15]['parent_id'] === 13 && $data['collections'][15]['identifier'] === 'SYN.F1', 'Nested collection parent and readable identifier must be retained.');
$object_loads = array_column(array_filter($GLOBALS['aidLoads'], static fn($load) => $load[0] === 'ca_objects'), 1);
aidCheck(count($object_loads) === count(array_unique($object_loads)), 'Shared objects must be loaded once for access checks.');
aidCheck(array_unique(array_column($GLOBALS['aidReads'], 0)) === ['ca_collections'], 'Counts must not fetch per-object metadata.');
aidCheck(!isset($data['fields']['dates']) && $data['fields']['extent']['value'] === 'Two boxes', 'Empty punctuation-only dates must disappear; extent must fall back.');
aidCheck(tadlFindingAidText('<script>secret</script><p>Safe &amp; readable</p>') === 'Safe & readable', 'Text must remove scripts and decode entities.');
aidCheck(tadlFindingAidValue($request, $collection, ['ca_objects.idno', 'ca_collections.missing', 'ca_collections.description<script>']) === '', 'Field mapping must reject wrong table/missing/unsafe fields.');
$view = new AidView(); $view->setVar('finding_aid', $data); $html = $view->render('Details/finding_aid_pdf_html.php');
aidCheck(!str_contains($html, 'SECRET') && !str_contains($html, 'Hidden name') && !str_contains($html, 'Private branch'), 'PDF contains unreadable information.');
aidCheck(!str_contains($html, '>Dates:</span><br>') && str_contains($html, 'SYN.42') && str_contains($html, 'records with and without media'), 'PDF lost collection identifier/media scope or restored empty dates.');
aidCheck(str_contains($html, '<h2>Collection contents</h2>') && str_contains($html, '<p>5 items.') && str_contains($html, '4 directly linked items') && str_contains($html, '3 directly linked items'), 'PDF must show unique total and directly linked collection counts.');
aidCheck(str_contains($html, 'Directly linked counts above can overlap.'), 'PDF must distinguish the unique total from overlapping collection counts.');
aidCheck(substr_count($html, 'class="collection"') === 6 && str_contains($html, 'margin-left: 42pt;') && str_contains($html, '[SYN.F1]'), 'PDF must list every descendant with nested indentation and collection identifiers.');
aidCheck(!str_contains($html, 'Synthetic Archives Collection &gt;'), 'PDF must show nested collection names rather than repeated flattened paths.');
foreach (['Object inventory', 'class="entry"', 'Český časopis, 1930', 'Object without media', 'Child-only object', 'SYN.10', 'OLD.10', 'Recorded storage location'] as $entry) {
	aidCheck(!str_contains($html, $entry), 'PDF must not contain individual object entries: '.$entry);
}
$unsafe = $data; $unsafe['title'] = '<img src="file:///private/example" onerror="bad"> & title';
$unsafe['fields']['unsafe'] = ['label' => '<script>bad</script>', 'value' => '<img src="https://example.com">'];
$unsafe['collections'][15]['title'] = '<script>bad</script>';
$unsafe['collections'][15]['identifier'] = '<img src="file:///private/example">';
$view->setVar('finding_aid', $unsafe); $unsafe_html = $view->render('Details/finding_aid_pdf_html.php');
aidCheck(!str_contains($unsafe_html, '<img') && !str_contains($unsafe_html, '<script>'), 'PDF must escape all catalog fields.');
$empty = $data; $empty['object_count'] = 0; $empty['collections'] = [42 => array_replace($data['collections'][42], ['count' => 0])]; $empty['fields'] = [];
$view->setVar('finding_aid', $empty); $empty_html = $view->render('Details/finding_aid_pdf_html.php');
aidCheck(str_contains($empty_html, '<p>0 items.') && str_contains($empty_html, 'No accessible object records') && !str_contains($empty_html, 'About this collection') && !str_contains($empty_html, 'Collection organization'), 'Empty contents must have a useful state without empty headings.');
$single = clone $collection; $single->row['children'] = []; $single->row['related']['ca_objects'] = [102];
$view->setVar('finding_aid', tadlFindingAidData($request, $single, $GLOBALS['aidConfig']));
aidCheck(str_contains($view->render('Details/finding_aid_pdf_html.php'), '<p>1 item.'), 'Single-object total must use singular wording.');
$series = new AidModel('ca_collections'); $series->load(7);
$series_data = tadlFindingAidData($request, $series, $GLOBALS['aidConfig']);
aidCheck($series_data['object_count'] === 3 && array_keys($series_data['collections']) === [7, 13, 15, 14] && $series_data['collections'][15]['depth'] === 2, 'Selected subcollection must export its descendants relative to itself, without its parent or siblings.');
// Equal sibling names must not cause their different descendants to interleave.
$duplicates = clone $collection; $duplicates->row['children'] = [17, 16];
$GLOBALS['aidRows']['ca_collections'][16] = aidRow(16, 'ca_collections', 'Same series', 'SYN.A', ['children' => [18]]);
$GLOBALS['aidRows']['ca_collections'][17] = aidRow(17, 'ca_collections', 'Same series', 'SYN.B', ['children' => [19]]);
$GLOBALS['aidRows']['ca_collections'][18] = aidRow(18, 'ca_collections', 'Z child', 'SYN.Z');
$GLOBALS['aidRows']['ca_collections'][19] = aidRow(19, 'ca_collections', 'A child', 'SYN.ACHILD');
aidCheck(array_keys(tadlFindingAidData($request, $duplicates, $GLOBALS['aidConfig'])['collections']) === [42, 16, 18, 17, 19], 'Each identically named branch must remain together.');
foreach ([16, 17, 18, 19] as $id) { unset($GLOBALS['aidRows']['ca_collections'][$id]); }
// Empty nested branches still belong in the hierarchy; object presence and
// media preference must never determine which readable collections are listed.
$empty_tree = clone $collection; $empty_tree->row['related'] = [];
$GLOBALS['aidRows']['ca_collections'][7]['related'] = [];
$empty_tree_data = tadlFindingAidData($request, $empty_tree, $GLOBALS['aidConfig']);
$view->setVar('finding_aid', $empty_tree_data);
aidCheck($empty_tree_data['object_count'] === 0 && count($empty_tree_data['collections']) === 6 && substr_count($view->render('Details/finding_aid_pdf_html.php'), '0 directly linked items') === 6, 'Entire empty hierarchy must remain listed with zero counts.');
$GLOBALS['aidRows']['ca_collections'][7]['related'] = ['ca_objects' => [101, 103, 111]];
$GLOBALS['aidRows']['ca_collections'][13]['denied'] = ['idno', 'hierarchy'];
$restricted_tree = tadlFindingAidData($request, $collection, $GLOBALS['aidConfig']);
aidCheck(!isset($restricted_tree['collections'][15]) && $restricted_tree['collections'][13]['identifier'] === '', 'Collection identifier and hierarchy bundle restrictions must remain enforced.');
unset($GLOBALS['aidRows']['ca_collections'][13]['denied']);
// Hierarchy traversal has no first-page or object-presence cutoff either.
$wide = clone $collection; $wide->row['children'] = range(2000, 2199); $wide->row['related'] = [];
foreach ($wide->row['children'] as $id) { $GLOBALS['aidRows']['ca_collections'][$id] = aidRow($id, 'ca_collections', 'Series '.($id - 1999)); }
$wide_data = tadlFindingAidData($request, $wide, $GLOBALS['aidConfig']);
aidCheck(count($wide_data['collections']) === 201 && array_key_last($wide_data['collections']) === 2199 && $wide_data['object_count'] === 0, 'All readable subcollections must be listed even in a wide, empty hierarchy.');
foreach ($wide->row['children'] as $id) { unset($GLOBALS['aidRows']['ca_collections'][$id]); }
foreach (['access' => 0, 'deleted' => 1, 'readable' => false, 'acl_level' => 0] as $key => $value) {
	$changed = clone $collection; $changed->row[$key] = $value; if ($key === 'acl_level') { $changed->row['acl'] = true; }
	aidCheck(tadlFindingAidData($request, $changed, $GLOBALS['aidConfig']) === null, 'Unreadable root collection must be rejected.');
}
$denied = clone $collection; $denied->row['denied'] = ['ca_objects', 'hierarchy'];
aidCheck(tadlFindingAidData($request, $denied, $GLOBALS['aidConfig'])['object_count'] === 0, 'Denied relationship/hierarchy bundles must be honored.');
$denied->row['denied'] = ['ca_objects'];
$denied_data = tadlFindingAidData($request, $denied, $GLOBALS['aidConfig']);
aidCheck($denied_data['object_count'] === 3 && $denied_data['collections'][42]['count'] === 0, 'Unreadable root relationships must not prevent readable descendant counts or disclose root objects.');
// A new export reflects updates immediately; no shared or on-disk export cache.
$GLOBALS['aidRows']['ca_objects'][101]['access'] = 0;
$updated = tadlFindingAidData($request, $collection, $GLOBALS['aidConfig']);
aidCheck($updated['object_count'] === 4 && $updated['collections'][42]['count'] === 3 && $updated['collections'][7]['count'] === 2, 'Counts must reflect current record access on each export.');
$GLOBALS['aidRows']['ca_objects'][101]['access'] = 1;
// More than both known native default relationship caps (1000 / 4000).
$large = clone $collection; $large->row['children'] = []; $large->row['related']['ca_objects'] = range(1000, 5104);
foreach ($large->row['related']['ca_objects'] as $id) { $GLOBALS['aidRows']['ca_objects'][$id] = aidRow($id, 'ca_objects', 'Synthetic inventory entry '.$id, 'SYN.'.$id); }
$large_data = tadlFindingAidData($request, $large, $GLOBALS['aidConfig']);
aidCheck($large_data['object_count'] === 4105 && $large_data['collections'][42]['count'] === 4105, 'Large collection counts were silently capped.');
foreach ($large->row['related']['ca_objects'] as $id) { unset($GLOBALS['aidRows']['ca_objects'][$id]); }
foreach ([
	[new AidRequest([], 'POST'), 405], [new AidRequest([]), 400], [new AidRequest(['collection_id' => -1]), 400],
	[new AidRequest(['collection_id' => 999]), 404], [new AidRequest(['collection_id' => 8]), 404],
	[new AidRequest(['collection_id' => 10]), 404]
] as [$case, $expected]) {
	$response = new AidResponse(); $controller = new CollectionFindingAidController($case, $response); $controller->Download();
	aidCheck($response->status === $expected && !$controller->rendered, 'Invalid/missing/unreadable request must not export.');
}
$case = new AidRequest(); $case->config->values['pawtucket_requires_login'] = 1;
$response = new AidResponse(); (new CollectionFindingAidController($case, $response))->Download(); aidCheck($response->status === 403, 'Required login must be enforced.');
$GLOBALS['aidConfig']->values['enabled'] = 0;
$response = new AidResponse(); (new CollectionFindingAidController(new AidRequest(), $response))->Download(); aidCheck($response->status === 404, 'Disabled finding aids must be rejected.');
$GLOBALS['aidConfig']->values['enabled'] = 1;
$response = new AidResponse(); $controller = new CollectionFindingAidController(new AidRequest(), $response); $controller->Download();
aidCheck($response->status === 200 && $controller->rendered && $response->headers['Cache-Control'] === 'private, no-store', 'Valid download must produce an uncached PDF attachment.');
aidCheck($controller->view->getVar('finding_aid_name') === 'SYN-42-finding-aid.pdf', 'Filename must be safe and identify the collection.');
aidCheck(str_starts_with($controller->view->getVar('finding_aid_bytes'), '%PDF-'), 'Download bytes must be PDF.');
if (!$autoload) {
	aidCheck($GLOBALS['aidPDFOptions'] === ['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false], 'Unsafe PDF features must remain disabled.');
	aidCheck($GLOBALS['aidPDFFooter'] === '{PAGE_NUM} / {PAGE_COUNT}', 'Page numbering must be present.');
	$GLOBALS['aidPDFFail'] = true; $response = new AidResponse(); $failed = new CollectionFindingAidController(new AidRequest(), $response); $failed->Download();
	aidCheck($response->status === 503 && !$failed->rendered && !str_contains($response->body, 'Synthetic renderer failure'), 'Renderer failure must be safe and actionable.');
}
if ($output = getenv('TADL_TEST_FINDING_AID_PDF')) {
	aidCheck((bool)$autoload, 'Real PDF sample requires Composer autoload.');
	// Build the sample through the actual exporter so shared/unique counts match
	// the production document rather than patched data. Long object titles must
	// not make the count-only PDF grow into an inventory.
	$GLOBALS['aidRows']['ca_collections'][42]['related']['ca_objects'] = array_values(array_diff($GLOBALS['aidRows']['ca_collections'][42]['related']['ca_objects'], [109]));
	$GLOBALS['aidRows']['ca_objects'][102]['values']['ca_objects.idno'] = 'SYN.002';
	foreach ([101, 103] as $id) { $GLOBALS['aidRows']['ca_objects'][$id]['values']['ca_objects.idno'] = 'SYN.010'; }
	for ($i = 5; $i <= 56; $i++) {
		$id = 50000 + $i;
		$row = $GLOBALS['aidRows']['ca_objects'][101]; $row['id'] = $id;
		$row['values']['ca_objects.preferred_labels.name'] = 'Synthetic item '.$i.': '.($i === 8 ? str_repeat('A long archival title with useful historical context and descriptive details. ', 25) : 'Correspondence and photographs from the synthetic collection.');
		$row['values']['ca_objects.idno'] = 'SYN.'.str_pad((string)$i, 3, '0', STR_PAD_LEFT);
		$GLOBALS['aidRows']['ca_objects'][$id] = $row;
		$GLOBALS['aidRows']['ca_collections'][42]['related']['ca_objects'][] = $id;
		$GLOBALS['aidRows']['ca_collections'][7]['related']['ca_objects'][] = $id;
	}
	// Exercise nested organization across page boundaries with long labels,
	// empty folders and non-ASCII collection names, without listing any objects.
	for ($i = 1; $i <= 30; $i++) {
		$id = 60000 + $i;
		$GLOBALS['aidRows']['ca_collections'][$id] = aidRow($id, 'ca_collections', 'Folder '.$i.': Český correspondence, photographs and administrative records from the synthetic collection', 'SYN.F'.str_pad((string)$i, 2, '0', STR_PAD_LEFT));
		$GLOBALS['aidRows']['ca_collections'][13]['children'][] = $id;
	}
	$sample_root = new AidModel('ca_collections'); $sample_root->load(42);
	$sample = tadlFindingAidData($request, $sample_root, $GLOBALS['aidConfig']);
	aidCheck($sample['object_count'] === 56 && $sample['collections'][42]['count'] === 55 && $sample['collections'][7]['count'] === 55, 'Sample counts must reflect shared and child-only records.');
	aidCheck(count($sample['collections']) === 36 && $sample['collections'][60030]['depth'] === 3, 'PDF sample must include every nested subcollection.');
	$view->setVar('finding_aid', $sample);
	file_put_contents($output, tadlFindingAidPDF($view->render('Details/finding_aid_pdf_html.php')));
}
echo "Finding aid passed: {$GLOBALS['assertions']} assertions (".($autoload ? 'real Dompdf' : 'synthetic renderer').").\n";
