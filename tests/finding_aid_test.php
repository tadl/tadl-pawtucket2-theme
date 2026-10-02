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
		'enabled' => 1, 'include_storage_locations' => 1,
		'collection_fields' => [
			'description' => ['label' => 'Description', 'bundles' => ['ca_collections.description']],
			'dates' => ['label' => 'Dates', 'bundles' => ['ca_collections.date.dates_value']],
			'extent' => ['label' => 'Extent', 'bundles' => ['ca_collections.extent_text', 'ca_collections.extent']],
			'rights' => ['label' => 'Rights', 'bundles' => ['ca_collections.rights.rightsText']]
		],
		'inventory_fields' => [
			'identifier' => ['label' => 'Identifier / accession number', 'bundles' => ['ca_objects.idno']],
			'legacy_identifier' => ['label' => 'Legacy accession number', 'bundles' => ['ca_objects.legacy_accession_number']],
			'dates' => ['label' => 'Dates', 'bundles' => ['ca_objects.date.dates_value']]
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
	function load($id) { $this->row = $GLOBALS['aidRows'][$this->table][$id] ?? []; return (bool)$this->row; }
	function tableName() { return $this->table; }
	function getPrimaryKey() { return $this->row['id'] ?? null; }
	function get($key) { return $this->row[$key] ?? null; }
	function hasField($key) { return in_array($key, ['idno', 'home_location_id'], true); }
	function hasElement($key) { return in_array($key, $this->row['elements'] ?? ['description', 'date', 'extent', 'rights', 'legacy_accession_number'], true); }
	function isReadable($request, $bundle = null) { return ($this->row['readable'] ?? true) && !in_array($bundle, $this->row['denied'] ?? [], true); }
	function checkACLAccessForUser($user) { aidCheck($user instanceof stdClass, 'Native ACL must receive current user.'); return $this->row['acl_level'] ?? 1; }
	function getWithTemplate($template, $options) {
		aidCheck(($options['checkAccess'] ?? null) === [1] && ($options['makeLink'] ?? null) === false, 'Metadata must preserve access and plain text options.');
		$GLOBALS['aidReads'][] = [$this->table, $this->getPrimaryKey(), $template];
		return $this->row['values'][substr($template, 1)] ?? '';
	}
	function getHierarchyChildren($id, $options) {
		aidCheck($id === $this->getPrimaryKey() && $options === ['idsOnly' => true], 'Hierarchy traversal must use the selected node.');
		return $this->row['children'] ?? [];
	}
	function getRelatedItems($table, $options) {
		aidCheck($options === ['idsOnly' => true, 'checkAccess' => [1], 'limit' => PHP_INT_MAX], 'Inventory must remove native relationship limits and preserve access.');
		return $this->row['related'][$table] ?? [];
	}
}
class Datamodel { static function getInstance($table, $unused) { return new AidModel($table); } }
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
require dirname(__DIR__).'/controllers/FindingAidController.php';
$GLOBALS['aidConfig'] = new AidConfig();
function aidRow($id, $table, $title, $identifier = '', $extra = []) {
	return array_replace_recursive(['id' => $id, 'access' => 1, 'deleted' => 0, 'values' => [$table.'.preferred_labels.name' => $title, $table.'.idno' => $identifier]], $extra);
}
$GLOBALS['aidRows'] = [
	'ca_collections' => [
		42 => aidRow(42, 'ca_collections', 'Synthetic Archives Collection', 'SYN.42', [
			'children' => [7, 8, 10, 11, 12], 'related' => ['ca_objects' => [101, 101, 102, 103, 104, 105, 106, 107, 108, 109]],
			'values' => ['ca_collections.description' => '<p>A synthetic collection &amp; its history.</p>', 'ca_collections.date.dates_value' => '; ', 'ca_collections.extent' => 'Two boxes', 'ca_collections.rights.rightsText' => 'Synthetic rights statement']
		]),
		7 => aidRow(7, 'ca_collections', 'Series A', 'SYN.7', ['related' => ['ca_objects' => [101, 103]]]),
		8 => aidRow(8, 'ca_collections', 'Private branch', '', ['access' => 0, 'children' => [9]]),
		9 => aidRow(9, 'ca_collections', 'Behind private branch', '', ['related' => ['ca_objects' => [110]]]),
		10 => aidRow(10, 'ca_collections', 'Denied branch', '', ['acl' => true, 'acl_level' => 0]),
		11 => aidRow(11, 'ca_collections', 'Deleted branch', '', ['deleted' => 1]),
		12 => aidRow(12, 'ca_collections', 'Empty series', '', ['children' => [42]])
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
		110 => aidRow(110, 'ca_objects', 'Hidden branch object', 'SECRET.10')
	],
	'ca_storage_locations' => [
		50 => aidRow(50, 'ca_storage_locations', 'Box 1 / Folder 2', 'SYN.LOC.1'),
		51 => aidRow(51, 'ca_storage_locations', 'Old box', 'SYN.LOC.2'),
		52 => aidRow(52, 'ca_storage_locations', 'Private location', 'SECRET.LOC', ['access' => 0]),
		53 => aidRow(53, 'ca_storage_locations', 'Deleted location', '', ['deleted' => 1]),
		54 => aidRow(54, 'ca_storage_locations', 'Denied location', '', ['acl' => true, 'acl_level' => 0])
	]
];
$request = new AidRequest();
$collection = new AidModel('ca_collections'); $collection->load(42);
$data = tadlFindingAidData($request, $collection, $GLOBALS['aidConfig']);
aidCheck(count($data['objects']) === 4, 'Readable objects must be unique by ID, including those without media.');
aidCheck(array_keys($data['collections']) === [42, 12, 7], 'Unreadable/deleted branches must not appear or be traversed.');
aidCheck($data['collections'][42]['count'] === 4 && $data['collections'][7]['count'] === 2, 'Collection counts must count readable unique objects.');
$objects = array_column($data['objects'], null, 'id');
aidCheck(count($objects[101]['collections']) === 2, 'Shared object must retain both memberships in a single inventory entry.');
aidCheck($objects[101]['fields']['location']['value'] === 'Box 1 / Folder 2 (SYN.LOC.1)', 'Readable home location must take precedence.');
aidCheck(!str_contains($objects[102]['fields']['location']['value'], 'Private') && str_contains($objects[102]['fields']['location']['value'], 'Old box'), 'Related locations must be checked individually.');
aidCheck(!isset($objects[103]['fields']['location']) && $objects[109]['title'] === 'Object' && !$objects[109]['fields'], 'Unreadable bundles must not leak metadata.');
aidCheck(!isset($data['fields']['dates']) && $data['fields']['extent']['value'] === 'Two boxes', 'Empty punctuation-only dates must disappear; extent must fall back.');
aidCheck(array_search(102, array_column($data['objects'], 'id'), true) < array_search(101, array_column($data['objects'], 'id'), true), 'Identifiers must sort naturally.');
aidCheck($data['objects'][count($data['objects']) - 1]['id'] === 109, 'Unidentified records must sort last.');
aidCheck(tadlFindingAidText('<script>secret</script><p>Safe &amp; readable</p>') === 'Safe & readable', 'Text must remove scripts and decode entities.');
aidCheck(tadlFindingAidValue($request, $collection, ['ca_objects.idno', 'ca_collections.missing', 'ca_collections.description<script>']) === '', 'Field mapping must reject wrong table/missing/unsafe fields.');
$view = new AidView(); $view->setVar('finding_aid', $data); $html = $view->render('Details/finding_aid_pdf_html.php');
aidCheck(!str_contains($html, 'SECRET') && !str_contains($html, 'Hidden name') && !str_contains($html, 'Private branch'), 'PDF contains unreadable information.');
aidCheck(!str_contains($html, '>Dates:</span><br>') && str_contains($html, 'OLD.10') && str_contains($html, 'records with and without media'), 'PDF lost identifier/media scope or restored empty dates.');
aidCheck(substr_count($html, 'Český časopis, 1930') === 1, 'Inventory must not repeat an object linked to multiple collections.');
$unsafe = $data; $unsafe['title'] = '<img src="file:///private/example" onerror="bad"> & title';
$unsafe['fields']['unsafe'] = ['label' => '<script>bad</script>', 'value' => '<img src="https://example.com">'];
$view->setVar('finding_aid', $unsafe); $unsafe_html = $view->render('Details/finding_aid_pdf_html.php');
aidCheck(!str_contains($unsafe_html, '<img') && !str_contains($unsafe_html, '<script>'), 'PDF must escape all catalog fields.');
$empty = $data; $empty['objects'] = []; $empty['collections'] = [42 => $data['collections'][42]]; $empty['fields'] = [];
$view->setVar('finding_aid', $empty); $empty_html = $view->render('Details/finding_aid_pdf_html.php');
aidCheck(str_contains($empty_html, 'No accessible object records') && !str_contains($empty_html, 'About this collection') && !str_contains($empty_html, 'Collection organization'), 'Empty inventory must have a useful state without empty headings.');
foreach (['access' => 0, 'deleted' => 1, 'readable' => false, 'acl_level' => 0] as $key => $value) {
	$changed = clone $collection; $changed->row[$key] = $value; if ($key === 'acl_level') { $changed->row['acl'] = true; }
	aidCheck(tadlFindingAidData($request, $changed, $GLOBALS['aidConfig']) === null, 'Unreadable root collection must be rejected.');
}
$denied = clone $collection; $denied->row['denied'] = ['ca_objects', 'hierarchy'];
aidCheck(!tadlFindingAidData($request, $denied, $GLOBALS['aidConfig'])['objects'], 'Denied relationship/hierarchy bundles must be honored.');
$config = clone $GLOBALS['aidConfig']; $config->values['include_storage_locations'] = 0;
foreach (tadlFindingAidData($request, $collection, $config)['objects'] as $object) { aidCheck(!isset($object['fields']['location']), 'Locations must be configurable.'); }
// A new export reflects updates immediately; no shared or on-disk export cache.
$GLOBALS['aidRows']['ca_objects'][101]['values']['ca_objects.preferred_labels.name'] = 'Updated catalog title';
aidCheck(str_contains(json_encode(tadlFindingAidData($request, $collection, $config)), 'Updated catalog title'), 'Export must reflect current data.');
$GLOBALS['aidRows']['ca_objects'][101]['values']['ca_objects.preferred_labels.name'] = 'Český časopis, 1930';
// More than both known native default relationship caps (1000 / 4000).
$large = clone $collection; $large->row['children'] = []; $large->row['related']['ca_objects'] = range(1000, 5104);
foreach ($large->row['related']['ca_objects'] as $id) { $GLOBALS['aidRows']['ca_objects'][$id] = aidRow($id, 'ca_objects', 'Synthetic inventory entry '.$id, 'SYN.'.$id); }
aidCheck(count(tadlFindingAidData($request, $large, $config)['objects']) === 4105, 'Large inventory was silently capped.');
foreach ($large->row['related']['ca_objects'] as $id) { unset($GLOBALS['aidRows']['ca_objects'][$id]); }
foreach ([
	[new AidRequest([], 'POST'), 405], [new AidRequest([]), 400], [new AidRequest(['collection_id' => -1]), 400],
	[new AidRequest(['collection_id' => 999]), 404], [new AidRequest(['collection_id' => 8]), 404],
	[new AidRequest(['collection_id' => 10]), 404]
] as [$case, $expected]) {
	$response = new AidResponse(); $controller = new FindingAidController($case, $response); $controller->Download();
	aidCheck($response->status === $expected && !$controller->rendered, 'Invalid/missing/unreadable request must not export.');
}
$case = new AidRequest(); $case->config->values['pawtucket_requires_login'] = 1;
$response = new AidResponse(); (new FindingAidController($case, $response))->Download(); aidCheck($response->status === 403, 'Required login must be enforced.');
$GLOBALS['aidConfig']->values['enabled'] = 0;
$response = new AidResponse(); (new FindingAidController(new AidRequest(), $response))->Download(); aidCheck($response->status === 404, 'Disabled finding aids must be rejected.');
$GLOBALS['aidConfig']->values['enabled'] = 1;
$response = new AidResponse(); $controller = new FindingAidController(new AidRequest(), $response); $controller->Download();
aidCheck($response->status === 200 && $controller->rendered && $response->headers['Cache-Control'] === 'private, no-store', 'Valid download must produce an uncached PDF attachment.');
aidCheck($controller->view->getVar('finding_aid_name') === 'SYN-42-finding-aid.pdf', 'Filename must be safe and identify the collection.');
aidCheck(str_starts_with($controller->view->getVar('finding_aid_bytes'), '%PDF-'), 'Download bytes must be PDF.');
if (!$autoload) {
	aidCheck($GLOBALS['aidPDFOptions'] === ['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false], 'Unsafe PDF features must remain disabled.');
	aidCheck($GLOBALS['aidPDFFooter'] === '{PAGE_NUM} / {PAGE_COUNT}', 'Page numbering must be present.');
	$GLOBALS['aidPDFFail'] = true; $response = new AidResponse(); $failed = new FindingAidController(new AidRequest(), $response); $failed->Download();
	aidCheck($response->status === 503 && !$failed->rendered && !str_contains($response->body, 'Synthetic renderer failure'), 'Renderer failure must be safe and actionable.');
}
if ($output = getenv('TADL_TEST_FINDING_AID_PDF')) {
	aidCheck((bool)$autoload, 'Real PDF sample requires Composer autoload.');
	// Build the sample through the actual exporter so counts, memberships and
	// natural sorting match the production document rather than patched data.
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
	$sample_root = new AidModel('ca_collections'); $sample_root->load(42);
	$sample = tadlFindingAidData($request, $sample_root, $GLOBALS['aidConfig']);
	aidCheck(count($sample['objects']) === 55 && $sample['collections'][42]['count'] === 55, 'Sample counts must reflect its actual inventory.');
	$view->setVar('finding_aid', $sample);
	file_put_contents($output, tadlFindingAidPDF($view->render('Details/finding_aid_pdf_html.php')));
}
echo "Finding aid passed: {$GLOBALS['assertions']} assertions (".($autoload ? 'real Dompdf' : 'synthetic renderer').").\n";
