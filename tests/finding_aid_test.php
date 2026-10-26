<?php
/** Actual finding-aid helper/controller/template with synthetic native boundaries. */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	if ($severity === E_DEPRECATED && getenv('TADL_TEST_COMPOSER_AUTOLOAD') && str_contains($file, '/vendor/')) { return true; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('pInteger', 1);
define('__CA_BUNDLE_ACCESS_READONLY__', 1);
define('__CA_ACL_READONLY_ACCESS__', 1);
$assertions = 0;
function aidCheck($condition, $message) {
	$GLOBALS['assertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function _t($text) { return $text; }
function caNavUrl($request, $module, $controller, $action, $params = []) {
	$url = '/synthetic/'.$controller.'/'.$action;
	foreach ($params as $key => $value) { $url .= '/'.$key.'/'.rawurlencode((string)$value); }
	return $url;
}
function caParseElementDimension($value, $options) { return $value ?: $options['default']; }
function caGetUserAccessValues($request) { return $request->access; }
function caGetCollectionsConfig() { return new AidConfig(); }
function caACLIsEnabled($record, $options) {
	aidCheck($options === ['forPawtucket' => true], 'ACL must use Pawtucket context.');
	return $record->row['acl'] ?? false;
}
function caMakeRelationshipTypeIDList($table, $types) {
	aidCheck($table === 'ca_entities_x_collections', 'Creator roles must use the native collection/entity relationship type list.');
	return $types === ['creator'] ? [21] : [];
}
class AidConfig {
	public array $values = [
		'enabled' => 1, 'hierarchy_sort' => 'rank', 'landing_page_collection_type' => 'collection',
		'collection_fields' => [
			'level' => ['label' => 'Level of description', 'bundles' => ['ca_collections.type_id']],
			'creators' => ['label' => 'Creators', 'related_table' => 'ca_entities', 'relationship_types' => ['creator'], 'bundles' => ['ca_entities.preferred_labels.displayname']],
			'dates' => ['label' => 'Dates', 'bundles' => ['ca_collections.date.dates_value']],
			'extent' => ['label' => 'Extent', 'bundles' => ['ca_collections.extent_text', 'ca_collections.extent']],
			'language' => ['label' => 'Language', 'display_language_labels' => 1, 'bundles' => ['ca_collections.language']],
			'description' => ['label' => 'Description', 'bundles' => ['ca_collections.description']],
			'scope' => ['label' => 'Scope and content', 'bundles' => ['ca_collections.collection_scope_content']],
			'rights' => ['label' => 'Rights', 'bundles' => ['ca_collections.rights.rightsText']],
			'copyright' => ['label' => 'Copyright statement', 'bundles' => ['ca_collections.rights.copyrightStatement']],
			'related_links' => ['label' => 'Related links', 'bundles' => ['ca_collections.external_link.url_line2.url_entry']],
			'optional' => ['label' => 'Missing field', 'bundles' => ['ca_collections.missing']]
		]
	];
	function get($key) { return $this->values[$key] ?? null; }
	function getAssoc($key) { return $this->get($key); }
}
class Configuration { static function load($path) { aidCheck(str_ends_with($path, '/conf/finding_aid.conf'), 'Unexpected config path.'); return $GLOBALS['aidConfig']; } }
class ca_metadata_elements { static function getElementDatatype($root) { return $GLOBALS['aidDatatypes'][$root] ?? 3; } }
class AidRequest {
	public $config;
	public $user;
	public array $access = [1];
	function __construct(public array $params = ['collection_id' => 42], public string $method = 'GET', public bool $loggedIn = false) {
		$this->config = new AidConfig(); $this->user = new stdClass();
	}
	function getUserID() { return 7; }
	function getRequestMethod() { return $this->method; }
	function getParameter($key, $type) { aidCheck($type === pInteger, 'Collection ID must use native integer parsing.'); return $this->params[$key] ?? null; }
	function isLoggedIn() { return $this->loggedIn; }
	function getBaseUrlPath() { return '/synthetic'; }
	function getViewsDirectoryPath() { return dirname(__DIR__).'/views'; }
}
class AidModel {
	public array $row = [];
	function __construct(public string $table) {}
	function load($id) { $GLOBALS['aidLoads'][] = [$this->table, $id]; $this->row = $GLOBALS['aidRows'][$this->table][$id] ?? []; return (bool)$this->row; }
	function tableName() { return $this->table; }
	function getPrimaryKey() { return $this->row['id'] ?? null; }
	function getTypeCode() { return $this->row['type_code'] ?? ($this->getPrimaryKey() === 42 ? 'collection' : 'series'); }
	function get($key) { return $this->row[$key] ?? null; }
	function hasField($key) { return in_array($key, ['idno', 'type_id', 'rank'], true); }
	function hasElement($key) { return in_array($key, $this->row['elements'] ?? ['description', 'date', 'extent_text', 'extent', 'language', 'collection_scope_content', 'provenance', 'rights', 'external_link'], true); }
	function isReadable($request, $bundle = null) { return ($this->row['readable'] ?? true) && !in_array($bundle, $this->row['denied'] ?? [], true); }
	function checkACLAccessForUser($user) { aidCheck($user instanceof stdClass, 'Native ACL must receive current user.'); return $this->row['acl_level'] ?? 1; }
	function getDb() { throw new RuntimeException('Finding aids must not query object counts or inventory.'); }
	function getWithTemplate($template, $options) {
		aidCheck(in_array($this->table, ['ca_collections', 'ca_entities'], true), 'Only descriptive units and checked creator names may be read.');
		$language = $template === '^ca_collections.language' && ($GLOBALS['aidDatatypes']['language'] ?? 3) === 3;
		aidCheck((($options['checkAccess'] ?? null) === [1] || ($language && $options['checkAccess'] === [])) && ($options['makeLink'] ?? null) === false && ($options['dontReturnDefault'] ?? null) === true, 'Only selected language List labels may omit term access filtering; all metadata needs plain text and no-default options.');
		$GLOBALS['aidReads'][] = [$this->table, $this->getPrimaryKey(), $template];
		if ($language && $options['checkAccess'] === [1]) { return ''; } // Synthetic default-private language term.
		return $this->row['values'][substr($template, 1)] ?? '';
	}
	function getHierarchyChildren($id, $options) {
		aidCheck($id === $this->getPrimaryKey() && $options === ['idsOnly' => true], 'Hierarchy traversal must use the selected node.');
		return $this->row['children'] ?? [];
	}
	function getRelatedItems($table, $options) {
		aidCheck($table === 'ca_entities', 'Finding aids must not query linked objects or storage locations, even for counts.');
		aidCheck($options === ['idsOnly' => true, 'checkAccess' => [1], 'user_id' => 7, 'restrictToRelationshipTypes' => [21], 'limit' => PHP_INT_MAX], 'Creator lookup must restrict roles, retain access/user context and remove native caps.');
		$GLOBALS['aidRelations'][] = $this->getPrimaryKey();
		return $this->row['related'][$table] ?? [];
	}
}
class Datamodel {
	static function getInstance($table, $cached) {
		aidCheck(in_array($table, ['ca_collections', 'ca_entities'], true), 'Finding aids must never load object, representation, or storage models.');
		return new AidModel($table);
	}
}
class AidView {
	public array $vars = [];
	function setVar($key, $value) { $this->vars[$key] = $value; }
	function getVar($key) { return $this->vars[$key] ?? null; }
	function render($path) { ob_start(); include dirname(__DIR__).'/views/'.$path; return ob_get_clean(); }
}
class View extends AidView {
	public $request;
	function __construct($request, $path) {
		aidCheck($path === dirname(__DIR__).'/views/mediaViewers', 'Finding aid must use the native media-viewer template path.');
		$this->request = $request;
	}
	function render($path) {
		if ($GLOBALS['aidViewerFail'] ?? false) { throw new RuntimeException('Synthetic viewer failure'); }
		return parent::render('mediaViewers/'.$path);
	}
}
class AidResponse {
	public int $status = 200;
	public array $headers = [];
	public string $body = '';
	function addHeader($key, $value) { $this->headers[$key] = $value; }
	function setHTTPResponseCode($code, $message) { $this->status = $code; }
	function addContent($text) { $this->body .= $text; }
}
class MetaTagManager {
	static string $title = '';
	static function setWindowTitle(string $title): bool { self::$title = $title; return true; }
}
class BasePawtucketController {
	public $view;
	public $rendered;
	function __construct(public $request, public $response) { $this->view = new AidView(); }
	function render($path, $raw) { aidCheck(($raw && $path === 'Details/finding_aid_binary.php') || (!$raw && $path === 'Details/finding_aid_html.php'), 'PDF must bypass wrappers; accessible HTML uses the shared shell.'); $this->rendered = $path; }
}
$directory = sys_get_temp_dir().'/tadl-aid-test-'.bin2hex(random_bytes(8));
mkdir($directory.'/pawtucket', 0700, true);
ini_set('error_log', $directory.'/diagnostics.log');
file_put_contents($directory.'/pawtucket/BasePawtucketController.php', '<?php');
define('__CA_LIB_DIR__', $directory);
register_shutdown_function(function () use ($directory) {
	if (isset($GLOBALS['aidRoutingCleanup'])) { ($GLOBALS['aidRoutingCleanup'])(); }
	foreach (['/pawtucket/BasePawtucketController.php', '/pdf_stub.php', '/diagnostics.log'] as $file) { if (is_file($directory.$file)) { unlink($directory.$file); } }
	rmdir($directory.'/pawtucket'); rmdir($directory);
});
if ($autoload = getenv('TADL_TEST_COMPOSER_AUTOLOAD')) {
	require $autoload;
} else {
	file_put_contents($directory.'/pdf_stub.php', <<<'STUB'
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
STUB);
	require $directory.'/pdf_stub.php';
}
require dirname(__DIR__).'/controllers/CollectionFindingAidController.php';
$GLOBALS['aidConfig'] = new AidConfig();
if ($dispatcher_path = getenv('TADL_TEST_REQUEST_DISPATCHER')) { require __DIR__.'/support/finding_aid_routing.php'; }
function aidRow($id, $table, $title, $identifier = '', $extra = []) {
	$label = $table === 'ca_entities' ? 'displayname' : 'name';
	return array_replace_recursive(['id' => $id, 'access' => 1, 'deleted' => 0, 'values' => [$table.'.preferred_labels.'.$label => $title, $table.'.idno' => $identifier]], $extra);
}
$GLOBALS['aidRows'] = [
	'ca_collections' => [
		42 => aidRow(42, 'ca_collections', 'Synthetic Archives Collection', 'SYN.42', [
			'children' => [7, 8, 10, 11, 12], 'related' => ['ca_objects' => range(1000, 5104), 'ca_entities' => [201, 201, 202, 203, 204, 205, 206]],
			'values' => ['ca_collections.type_id' => 'Collection', 'ca_collections.description' => '<p>A synthetic collection &amp; its history.</p>', 'ca_collections.date.dates_value' => '; ', 'ca_collections.extent' => 'Two boxes', 'ca_collections.provenance' => 'Transferred from a fictional community organization.', 'ca_collections.rights.rightsText' => 'Synthetic rights statement', 'ca_collections.rights.copyrightStatement' => 'Synthetic copyright statement', 'ca_collections.internal_notes' => 'PRIVATE staff note']
		]),
		7 => aidRow(7, 'ca_collections', 'Series A', 'SYN.7', ['children' => [14, 13], 'values' => ['ca_collections.type_id' => 'Series', 'ca_collections.description' => '<p>Series correspondence &amp; diaries.</p><p>Includes synthetic administrative records.</p>', 'ca_collections.date.dates_value' => '1920-1930', 'ca_collections.extent_text' => 'One box', 'ca_collections.language' => 'English', 'ca_collections.collection_scope_content' => 'Correspondence concerning community activities.']]),
		8 => aidRow(8, 'ca_collections', 'Private branch', '', ['access' => 0, 'children' => [9], 'values' => ['ca_collections.description' => 'PRIVATE branch description']]),
		9 => aidRow(9, 'ca_collections', 'Behind private branch', '', ['values' => ['ca_collections.extent_text' => 'PRIVATE descendant extent']]),
		10 => aidRow(10, 'ca_collections', 'Denied branch', '', ['acl' => true, 'acl_level' => 0]),
		11 => aidRow(11, 'ca_collections', 'Deleted branch', '', ['deleted' => 1]),
		12 => aidRow(12, 'ca_collections', 'Uncatalogued series', '', ['children' => [42], 'values' => ['ca_collections.description' => '<p>&nbsp;</p>']]),
		13 => aidRow(13, 'ca_collections', 'Drawer 2', 'SYN.D2', ['children' => [15], 'values' => ['ca_collections.type_id' => 'Container', 'ca_collections.description' => 'Drawer records.', 'ca_collections.extent_text' => 'Three folders']]),
		14 => aidRow(14, 'ca_collections', 'Drawer 10', 'SYN.D10', ['denied' => ['description', 'extent_text'], 'values' => ['ca_collections.description' => 'PRIVATE restricted description', 'ca_collections.extent_text' => 'PRIVATE restricted extent']]),
		15 => aidRow(15, 'ca_collections', 'Folder 1', 'SYN.F1', ['values' => ['ca_collections.type_id' => 'Folder', 'ca_collections.description' => '<p>Folder description with &lt;angle brackets&gt;.</p>', 'ca_collections.extent_text' => '80 newspaper clippings in one folder, 20 x 30 cm', 'ca_collections.collection_scope_content' => 'Clippings concerning a fictional community garden.', 'ca_collections.rights.rightsText' => 'Synthetic folder use conditions']])
	],
	'ca_entities' => [
		201 => aidRow(201, 'ca_entities', 'Example Community Organization'),
		202 => aidRow(202, 'ca_entities', 'PRIVATE creator', '', ['access' => 0]),
		203 => aidRow(203, 'ca_entities', 'PRIVATE deleted creator', '', ['deleted' => 1]),
		204 => aidRow(204, 'ca_entities', 'PRIVATE unreadable creator', '', ['readable' => false]),
		205 => aidRow(205, 'ca_entities', 'PRIVATE ACL creator', '', ['acl' => true, 'acl_level' => 0]),
		206 => aidRow(206, 'ca_entities', 'PRIVATE label creator', '', ['denied' => ['preferred_labels']])
	]
];
foreach ($GLOBALS['aidRows']['ca_collections'] as $id => &$row) {
	$row['values']['ca_collections.provenance'] ??= 'PROVIDENCE_ONLY_PROVENANCE_'.$id;
}
unset($row);
foreach ([7 => 42, 8 => 42, 9 => 8, 10 => 42, 11 => 42, 12 => 42, 13 => 7, 14 => 7, 15 => 13] as $id => $parent_id) {
	$GLOBALS['aidRows']['ca_collections'][$id]['parent_id'] = $parent_id;
}
$request = new AidRequest();
$collection = new AidModel('ca_collections'); $collection->load(42);
$GLOBALS['aidLoads'] = []; $GLOBALS['aidReads'] = []; $GLOBALS['aidRelations'] = [];
aidCheck(tadlFindingAidRoot($request, $collection) === $collection && !$GLOBALS['aidLoads'], 'A top-level finding-aid link must retain its root without ancestor queries.');
$folder = new AidModel('ca_collections'); $folder->load(15);
$GLOBALS['aidLoads'] = [];
aidCheck(tadlFindingAidRoot($request, $folder)->getPrimaryKey() === 42 && $folder->getPrimaryKey() === 15, 'A deeply nested folder must resolve the top-level finding aid without mutating the selected detail record.');
aidCheck($GLOBALS['aidLoads'] === [['ca_collections', 13], ['ca_collections', 7], ['ca_collections', 42]] && !$GLOBALS['aidReads'] && !$GLOBALS['aidRelations'], 'Root resolution must load only the parent chain, without reading metadata, children or objects.');
$saved_ancestor = $GLOBALS['aidRows']['ca_collections'][7];
foreach ([['access' => 0], ['deleted' => 1], ['readable' => false], ['acl' => true, 'acl_level' => 0]] as $restriction) {
	$GLOBALS['aidRows']['ca_collections'][7] = array_replace($saved_ancestor, $restriction);
	$GLOBALS['aidLoads'] = [];
	aidCheck(tadlFindingAidRoot($request, $folder)->getPrimaryKey() === 13 && !in_array(['ca_collections', 42], $GLOBALS['aidLoads'], true), 'A hidden ancestor must stop resolution at the highest contiguous public unit without loading anything above the boundary.');
}
$GLOBALS['aidRows']['ca_collections'][7] = $saved_ancestor;
$GLOBALS['aidRows']['ca_collections'][7]['denied'] = ['hierarchy'];
aidCheck(tadlFindingAidRoot($request, $folder) === null, 'A restricted hierarchy bundle must not be bypassed.');
$GLOBALS['aidRows']['ca_collections'][7] = $saved_ancestor;
$GLOBALS['aidRows']['ca_collections'][42]['parent_id'] = 90;
$collection->row['parent_id'] = 90;
$GLOBALS['aidRows']['ca_collections'][90] = aidRow(90, 'ca_collections', 'PRIVATE administrative group', '', ['access' => 0, 'parent_id' => 91]);
$GLOBALS['aidRows']['ca_collections'][91] = aidRow(91, 'ca_collections', 'Group above hidden ancestor');
$GLOBALS['aidLoads'] = [];
aidCheck(tadlFindingAidRoot($request, $collection) === $collection && !$GLOBALS['aidLoads'], 'A base collection must retain its finding aid without traversing administrative parent groups.');
aidCheck(array_keys(tadlCollectionContentsAncestry($request, $folder)) === [42, 7, 13, 15], 'The public chain must include only the root and its genuine descendants, in order.');
$privileged = new AidRequest(); $privileged->access = [0, 1];
aidCheck(tadlFindingAidRoot($privileged, $folder)->getPrimaryKey() === 42, 'Staff access to administrative groups must not move the finding aid above its configured base collection.');
unset($GLOBALS['aidRows']['ca_collections'][42]['parent_id'], $GLOBALS['aidRows']['ca_collections'][90], $GLOBALS['aidRows']['ca_collections'][91]);
unset($collection->row['parent_id']);
$restricted_folder = clone $folder; $restricted_folder->row['denied'] = ['hierarchy'];
$GLOBALS['aidLoads'] = [];
aidCheck(tadlFindingAidRoot($request, $restricted_folder) === null && !$GLOBALS['aidLoads'], 'Denied hierarchy access must not load any ancestors.');
$GLOBALS['aidRows']['ca_collections'][13]['parent_id'] = 999;
aidCheck(tadlFindingAidRoot($request, $folder) === null, 'A missing ancestor must not produce a partial finding-aid link.');
$GLOBALS['aidRows']['ca_collections'][13]['parent_id'] = 7;
$GLOBALS['aidRows']['ca_collections'][7]['parent_id'] = 15;
aidCheck(tadlFindingAidRoot($request, $folder) === null, 'Cyclic ancestry must terminate without producing a finding-aid link.');
$GLOBALS['aidRows']['ca_collections'][7]['parent_id'] = 42;
$data = tadlFindingAidData($request, $collection, $GLOBALS['aidConfig']);
aidCheck(!isset($data['object_count']) && !isset($data['objects']), 'Finding aids must describe archival units without object counts or inventory.');
aidCheck(array_keys($data['collections']) === [42, 7, 13, 15, 14, 12], 'Every readable descendant must appear in natural sibling order, with whole branches and no cycles.');
aidCheck(array_column($data['collections'], 'depth') === [0, 1, 2, 3, 2, 1], 'Hierarchy depth must retain every generation.');
aidCheck($data['collections'][15]['parent_id'] === 13 && $data['collections'][15]['identifier'] === 'SYN.F1', 'Nested parent and identifier must be retained.');
aidCheck($data['collections'][15]['fields']['extent']['value'] === '80 newspaper clippings in one folder, 20 x 30 cm', 'A folder without object records must retain its own descriptive extent and dimensions.');
foreach (['level', 'description', 'dates', 'extent', 'language', 'scope'] as $field) { aidCheck(isset($data['collections'][7]['fields'][$field]), 'Series metadata missing: '.$field); }
$strict_language = clone $GLOBALS['aidConfig']; $strict_language->values['collection_fields']['language']['display_language_labels'] = 0;
aidCheck(!isset(tadlFindingAidData($request, $collection, $strict_language)['collections'][7]['fields']['language']), 'Sites may retain language-term access filtering.');
$language_denied = new AidModel('ca_collections'); $language_denied->load(7); $language_denied->row['denied'] = ['language'];
$before = count($GLOBALS['aidReads']);
aidCheck(tadlFindingAidValue($request, $language_denied, ['ca_collections.language'], true) === '' && count($GLOBALS['aidReads']) === $before, 'A denied language bundle must never be fetched, even for label display.');
$GLOBALS['aidDatatypes']['language'] = 22;
$language_reference = new AidModel('ca_collections'); $language_reference->load(7);
aidCheck(tadlFindingAidValue($request, $language_reference, ['ca_collections.language'], true) === 'English', 'A language reference of another datatype must retain the normal access options.');
unset($GLOBALS['aidDatatypes']['language']);
aidCheck(tadlFindingAidValue($request, $collection, ['ca_collections.description'], true) === 'A synthetic collection & its history.', 'The language-label option must not bypass access filtering on other bundles.');
aidCheck($data['fields']['creators']['value'] === 'Example Community Organization', 'Creator names must be deduplicated by record ID and filtered by record/label permissions.');
aidCheck(!isset($data['fields']['dates'], $data['fields']['optional']) && $data['fields']['extent']['value'] === 'Two boxes', 'Missing fields and punctuation-only dates must disappear; extent must fall back.');
aidCheck($data['collections'][42]['fields'] === [] && $data['fields']['description']['value'] === 'A synthetic collection & its history.', 'Root metadata must appear only in the overview.');
aidCheck(!isset($data['collections'][12]['fields']['description'], $data['collections'][14]['fields']['description'], $data['collections'][14]['fields']['extent']), 'Blank and restricted metadata must be omitted without removing the unit.');
foreach ([['ca_collections', 14, '^ca_collections.description'], ['ca_collections', 14, '^ca_collections.extent_text'], ['ca_entities', 206, '^ca_entities.preferred_labels.displayname']] as $read) { aidCheck(!in_array($read, $GLOBALS['aidReads'], true), 'Restricted metadata must not be fetched.'); }
aidCheck(!array_intersect([8, 9, 10, 11], array_column($GLOBALS['aidReads'], 1)), 'Unreadable branches must not supply metadata.');
aidCheck(!array_intersect(['ca_objects', 'ca_object_representations', 'ca_storage_locations'], array_column($GLOBALS['aidLoads'], 0)), 'No object, representation or storage models may be loaded.');
aidCheck(tadlFindingAidText('<script>secret</script><h3>Heading</h3><p>Safe &amp; readable</p>') === "Heading\nSafe & readable", 'HTML must become readable plain text.');
$spaced_description = "<p>First paragraph &amp; context.</p>\r\n\r\n<p>&nbsp;</p>\r\n<p><br></p>\r\n<p>Second paragraph.</p>";
aidCheck(tadlFindingAidText($spaced_description) === "First paragraph & context.\n\nSecond paragraph.", 'Empty rich-text blocks and CRLF formatting must leave only one blank line between paragraphs.');
aidCheck(tadlFindingAidText("First paragraph.\r\n \t\r\n\rSecond paragraph.") === "First paragraph.\n\nSecond paragraph.", 'Whitespace-only lines and mixed newline styles must not expand paragraph spacing.');
aidCheck(tadlFindingAidText("First creator\r\nSecond creator\rStreet address<br>Town") === "First creator\nSecond creator\nStreet address\nTown", 'Ordinary line breaks in names, addresses and explicit breaks must remain intact.');
$spaced_collection = clone $collection;
$spaced_collection->row['values']['ca_collections.description'] = $spaced_description;
aidCheck(tadlFindingAidValue($request, $spaced_collection, ['ca_collections.description']) === "First paragraph & context.\n\nSecond paragraph.", 'Catalogue metadata must use compact paragraphs before either finding-aid view renders it.');
aidCheck(tadlFindingAidValue($request, $collection, ['ca_objects.idno', 'ca_collections.missing', 'ca_collections.description<script>']) === '', 'Wrong-table, missing and unsafe field mappings must be rejected.');
$view = new AidView(); $view->setVar('finding_aid', $data); $html = $view->render('Details/finding_aid_pdf_html.php');
foreach (['PRIVATE', 'directly linked', 'object_count', 'No accessible object records', '0 items', '4105', 'Missing field:', 'Level of description:'] as $excluded) { aidCheck(!str_contains($html, $excluded), 'Unwanted finding-aid output: '.$excluded); }
foreach (['Synthetic Archives Collection' => 'Collection', 'Series A' => 'Series', 'Drawer 2' => 'Container', 'Folder 1' => 'Folder'] as $title => $level) {
	aidCheck(str_contains($html, $title.' <span class="collection-level">('.$level.')</span>'), 'Recorded level must follow its own unit title in parentheses.');
}
aidCheck(str_contains($html, '.collection-level { font-weight: normal; }') && str_contains($html, 'Uncatalogued series</h3>'), 'Level suffixes must use normal weight and missing levels must not produce empty parentheses.');
$level_only = $data; $level_only['fields'] = ['level' => $data['fields']['level']];
$level_only['collections'][15]['fields'] = ['level' => $data['collections'][15]['fields']['level']];
$view->setVar('finding_aid', $level_only); $level_only_html = $view->render('Details/finding_aid_pdf_html.php');
aidCheck(!str_contains($level_only_html, 'About this collection') && str_contains($level_only_html, 'Folder 1 <span class="collection-level">(Folder)</span>'), 'A unit with only a level must show its title suffix without an empty metadata section.');
aidCheck(str_contains($html, '80 newspaper clippings in one folder, 20 x 30 cm') && str_contains($html, 'Clippings concerning a fictional community garden.') && str_contains($html, 'Synthetic folder use conditions'), 'All relevant folder fields must be rendered even with no linked objects.');
foreach (['ca_collections.rights.copyrightStatement' => 'Folder copyright.', 'ca_collections.external_link.url_line2.url_entry' => 'https://example.org/related-materials'] as $bundle => $value) {
	$folder = new AidModel('ca_collections'); $folder->load(15); $folder->row['values'][$bundle] = $value;
	aidCheck(in_array($value, array_column(tadlFindingAidFields($request, $folder, $GLOBALS['aidConfig']->values['collection_fields']), 'value'), true), 'Every configured administrative/supporting field must work at folder level.');
}
aidCheck(str_contains($html, 'Example Community Organization') && str_contains($html, 'Synthetic copyright statement'), 'Collection context and approved administrative fields must appear.');
aidCheck(!str_contains($html, 'Transferred from a fictional community organization.') && !str_contains($html, 'PROVIDENCE_ONLY_PROVENANCE') && !str_contains($html, 'Provenance'), 'PDF finding aids must omit provenance at the root and every descendant.');
aidCheck(!array_filter($GLOBALS['aidReads'], static fn($read) => str_contains($read[2], 'ca_collections.provenance')), 'Finding-aid data must not fetch provenance.');
$public_config = file_get_contents(dirname(__DIR__).'/conf/finding_aid.conf');
aidCheck(!preg_match('/ca_collections\.(?:provenance|custodhist|custohist)\b/', $public_config), 'The shipped finding-aid mappings must omit provenance and its custodial-history aliases.');
aidCheck(substr_count($html, 'class="collection"') === 5 && str_contains($html, 'margin-left: 24pt;') && str_contains($html, 'SYN.F1'), 'Every descendant must retain its indentation and identifier without repeating the root.');
aidCheck(substr_count($html, 'A synthetic collection &amp; its history.') === 1 && str_contains($html, 'Folder description with &lt;angle brackets&gt;.'), 'Metadata must be escaped and root prose must not be duplicated.');
$unsafe = $data; $unsafe['title'] = '<img src="file:///private/example" onerror="bad"> & title';
$unsafe['fields']['unsafe'] = ['label' => '<script>bad</script>', 'value' => '<img src="https://example.org">'];
$unsafe['fields']['level']['value'] = '<img src="file:///private/example">';
$unsafe['collections'][15]['title'] = '<script>bad</script>';
$unsafe['collections'][15]['identifier'] = '<img src="file:///private/example">';
$unsafe['collections'][15]['fields']['description']['value'] = '<img src="https://example.org/unsafe" onerror="bad">';
$unsafe['collections'][15]['fields']['level']['value'] = '<script>bad</script>';
$view->setVar('finding_aid', $unsafe); $unsafe_html = $view->render('Details/finding_aid_pdf_html.php');
aidCheck(!str_contains($unsafe_html, '<img') && !str_contains($unsafe_html, '<script>'), 'Every metadata label, value, identifier and title must be escaped.');
$single = clone $collection; $single->row['children'] = []; $single_data = tadlFindingAidData($request, $single, $GLOBALS['aidConfig']);
$view->setVar('finding_aid', $single_data); $single_html = $view->render('Details/finding_aid_pdf_html.php');
aidCheck(!str_contains($single_html, 'Arrangement and contents') && str_contains($single_html, 'Two boxes') && !str_contains($single_html, 'items.'), 'A leaf collection needs descriptive metadata without object totals or an empty hierarchy section.');
$bare = clone $single; $bare->row['values'] = []; $bare->row['related'] = [];
$view->setVar('finding_aid', tadlFindingAidData($request, $bare, $GLOBALS['aidConfig']));
$bare_html = $view->render('Details/finding_aid_pdf_html.php');
aidCheck(!str_contains($bare_html, 'About this collection') && !str_contains($bare_html, 'No accessible object'), 'Missing metadata must not generate empty sections or claim that the holdings are empty.');
$series = new AidModel('ca_collections'); $series->load(7); $series_data = tadlFindingAidData($request, $series, $GLOBALS['aidConfig']);
aidCheck(array_keys($series_data['collections']) === [7, 13, 15, 14] && $series_data['collections'][15]['depth'] === 2, 'A selected series must include its descendants, without its parent or siblings.');
$view->setVar('finding_aid', $series_data);
aidCheck(substr_count($view->render('Details/finding_aid_pdf_html.php'), 'Series correspondence &amp; diaries.') === 1, 'Selected-unit descriptions must appear once.');
$mapped = clone $GLOBALS['aidConfig']; $mapped->values['collection_fields']['description']['bundles'] = ['ca_collections.extent_text', 'ca_collections.extent'];
$mapped_data = tadlFindingAidData($request, $collection, $mapped);
aidCheck($mapped_data['fields']['description']['value'] === 'Two boxes' && $mapped_data['collections'][7]['fields']['description']['value'] === 'One box', 'The same configurable field mappings must apply to root and descendants.');
unset($mapped->values['collection_fields']['description']);
$mapped_data = tadlFindingAidData($request, $collection, $mapped);
foreach ($mapped_data['collections'] as $node) { aidCheck(!isset($node['fields']['description']), 'Removing a mapping must remove it at every level.'); }
$mapped->values['collection_fields']['creators']['relationship_types'] = ['unknown'];
$before = count($GLOBALS['aidRelations']); $mapped_data = tadlFindingAidData($request, $collection, $mapped);
aidCheck(!isset($mapped_data['fields']['creators']) && count($GLOBALS['aidRelations']) === $before, 'Invalid relationship types must not broaden the creator lookup.');
$mapped->values['collection_fields']['creators']['related_table'] = 'ca_objects';
$before = count($GLOBALS['aidRelations']); tadlFindingAidData($request, $collection, $mapped);
aidCheck(count($GLOBALS['aidRelations']) === $before, 'Unsupported related tables must fail closed without reading object data.');
$denied = clone $collection; $denied->row['denied'] = ['ca_entities'];
$before = count($GLOBALS['aidRelations']); $denied_data = tadlFindingAidData($request, $denied, $GLOBALS['aidConfig']);
aidCheck(!isset($denied_data['fields']['creators']) && !in_array(42, array_slice($GLOBALS['aidRelations'], $before), true), 'A denied creator relationship bundle must not be queried.');
$ranked = clone $collection; $GLOBALS['aidRows']['ca_collections'][7]['rank'] = 10; $GLOBALS['aidRows']['ca_collections'][12]['rank'] = 1;
aidCheck(array_keys(tadlFindingAidData($request, $ranked, $GLOBALS['aidConfig'])['collections']) === [42, 12, 7, 13, 15, 14], 'Recorded hierarchy rank must take precedence over alphabetical order.');
$by_name = clone $GLOBALS['aidConfig']; $by_name->values['hierarchy_sort'] = 'name';
aidCheck(array_keys(tadlFindingAidData($request, $ranked, $by_name)['collections']) === [42, 7, 13, 15, 14, 12], 'Sites may explicitly retain natural-name order.');
unset($GLOBALS['aidRows']['ca_collections'][7]['rank'], $GLOBALS['aidRows']['ca_collections'][12]['rank']);
$duplicates = clone $collection; $duplicates->row['children'] = [17, 16];
$GLOBALS['aidRows']['ca_collections'][16] = aidRow(16, 'ca_collections', 'Same series', 'SYN.A', ['children' => [18]]);
$GLOBALS['aidRows']['ca_collections'][17] = aidRow(17, 'ca_collections', 'Same series', 'SYN.B', ['children' => [19]]);
$GLOBALS['aidRows']['ca_collections'][18] = aidRow(18, 'ca_collections', 'Z child', 'SYN.Z');
$GLOBALS['aidRows']['ca_collections'][19] = aidRow(19, 'ca_collections', 'A child', 'SYN.ACHILD');
aidCheck(array_keys(tadlFindingAidData($request, $duplicates, $GLOBALS['aidConfig'])['collections']) === [42, 16, 18, 17, 19], 'Identically named branches must not interleave.');
foreach ([16, 17, 18, 19] as $id) { unset($GLOBALS['aidRows']['ca_collections'][$id]); }
$GLOBALS['aidRows']['ca_collections'][13]['denied'] = ['idno', 'hierarchy'];
$restricted = tadlFindingAidData($request, $collection, $GLOBALS['aidConfig']);
aidCheck(!isset($restricted['collections'][15]) && $restricted['collections'][13]['identifier'] === '', 'Identifier and hierarchy bundle restrictions must remain enforced.');
unset($GLOBALS['aidRows']['ca_collections'][13]['denied']);
$wide = clone $collection; $wide->row['children'] = range(2000, 2199);
foreach ($wide->row['children'] as $id) { $GLOBALS['aidRows']['ca_collections'][$id] = aidRow($id, 'ca_collections', 'Series '.($id-1999)); }
$wide_data = tadlFindingAidData($request, $wide, $GLOBALS['aidConfig']);
aidCheck(count($wide_data['collections']) === 201 && array_key_last($wide_data['collections']) === 2199, 'Every uncatalogued public component must be included, without a first-page cutoff.');
foreach ($wide->row['children'] as $id) { unset($GLOBALS['aidRows']['ca_collections'][$id]); }
foreach (['access' => 0, 'deleted' => 1, 'readable' => false, 'acl_level' => 0] as $key => $value) {
	$changed = clone $collection; $changed->row[$key] = $value; if ($key === 'acl_level') { $changed->row['acl'] = true; }
	aidCheck(tadlFindingAidData($request, $changed, $GLOBALS['aidConfig']) === null, 'Unreadable root collection must be rejected.');
}
$denied = clone $collection; $denied->row['denied'] = ['ca_objects'];
aidCheck(count(tadlFindingAidData($request, $denied, $GLOBALS['aidConfig'])['collections']) === 6, 'Object relationship permissions must not affect an archival metadata-only export.');
$denied->row['denied'] = ['hierarchy'];
aidCheck(count(tadlFindingAidData($request, $denied, $GLOBALS['aidConfig'])['collections']) === 1, 'A denied hierarchy bundle must not be traversed.');
$GLOBALS['aidRows']['ca_collections'][15]['values']['ca_collections.extent_text'] = 'Updated folder extent';
aidCheck(tadlFindingAidData($request, $collection, $GLOBALS['aidConfig'])['collections'][15]['fields']['extent']['value'] === 'Updated folder extent', 'Each export must reflect current metadata without a shared PDF cache.');
$GLOBALS['aidRows']['ca_collections'][15]['values']['ca_collections.extent_text'] = '80 newspaper clippings in one folder, 20 x 30 cm';
foreach (['Download', 'View', 'Viewer'] as $action) {
	foreach (['POST', 'HEAD', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method) {
		if ($action === 'Viewer' && $method === 'POST') { continue; }
		$before = count($GLOBALS['aidLoads']);
		$response = new AidResponse(); $controller = new CollectionFindingAidController(new AidRequest(['collection_id' => 42], $method), $response); $controller->$action();
		aidCheck($response->status === 405 && $response->headers['Allow'] === ($action === 'Viewer' ? 'GET, POST' : 'GET')
			&& !$controller->rendered && $response->body === '' && count($GLOBALS['aidLoads']) === $before, $action.': unsupported methods must fail before loading a collection.');
	}
	foreach ($action === 'Viewer' ? ['GET', 'POST'] : ['GET'] as $method) {
		foreach ([[[], 400], [['collection_id' => -1], 400], [['collection_id' => 999], 404], [['collection_id' => 8], 404], [['collection_id' => 10], 404]] as [$params, $expected]) {
			$response = new AidResponse(); $controller = new CollectionFindingAidController(new AidRequest($params, $method), $response); $controller->$action();
			aidCheck($response->status === $expected && !$controller->rendered && $response->body === '', $action.' '.$method.': invalid/missing/unreadable requests must not export or view.');
		}
		$case = new AidRequest(['collection_id' => 42], $method); $case->config->values['pawtucket_requires_login'] = 1;
		$response = new AidResponse(); $controller = new CollectionFindingAidController($case, $response); $controller->$action();
		aidCheck($response->status === 403 && !$controller->rendered && $response->body === '', $action.' '.$method.': required login must be enforced.');
		$GLOBALS['aidConfig']->values['enabled'] = 0;
		$response = new AidResponse(); $controller = new CollectionFindingAidController(new AidRequest(['collection_id' => 42], $method), $response); $controller->$action();
		aidCheck($response->status === 404 && !$controller->rendered && $response->body === '', $action.' '.$method.': disabled finding aids must be rejected.');
		$GLOBALS['aidConfig']->values['enabled'] = 1;
	}
}
$response = new AidResponse(); $controller = new CollectionFindingAidController(new AidRequest(), $response); $controller->Download();
aidCheck($response->status === 200 && $controller->rendered && $response->headers['Cache-Control'] === 'private, no-store', 'Valid downloads must produce an uncached PDF attachment.');
aidCheck($controller->view->getVar('finding_aid_name') === 'SYN-42-finding-aid.pdf', 'Filename must safely identify the collection.');
aidCheck(str_starts_with($controller->view->getVar('finding_aid_bytes'), '%PDF-'), 'Download bytes must be PDF.');
aidCheck($controller->view->getVar('finding_aid_inline') === false, 'Download must retain attachment behavior.');
$response = new AidResponse(); $inline_controller = new CollectionFindingAidController(new AidRequest(), $response); $inline_controller->View();
aidCheck($response->status === 200 && $inline_controller->rendered && $inline_controller->view->getVar('finding_aid_inline') === true && $response->headers['Cache-Control'] === 'private, no-store', 'View must use the checked PDF export with inline display and private caching.');
aidCheck($inline_controller->view->getVar('finding_aid_name') === 'SYN-42-finding-aid.pdf' && str_starts_with($inline_controller->view->getVar('finding_aid_bytes'), '%PDF-'), 'Viewed PDF must retain its download filename and real PDF bytes.');
foreach (['GET', 'POST'] as $method) {
	$before = count($GLOBALS['aidLoads']);
	$response = new AidResponse(); $viewer_controller = new CollectionFindingAidController(new AidRequest(['collection_id' => 42], $method), $response); $viewer_controller->Viewer();
	aidCheck($response->status === 200 && str_contains($response->body, 'id="caMediaOverlayContent"') && str_contains($response->body, 'id="viewerContainer"'), 'Finding aid must reuse the actual PDF.js document viewer and native media overlay.');
	aidCheck(str_contains($response->body, "window.pdfjsContentURL = '/synthetic/CollectionFindingAid/View/collection_id/42'") && str_contains($response->body, '/assets/pdfjs/viewer/viewer.js'), 'Native PDF.js must load the access-checked generated PDF endpoint.');
	aidCheck(str_contains($response->body, '/CollectionFindingAid/Download/collection_id/42') && str_contains($response->body, 'Close media viewer') && $response->headers['Cache-Control'] === 'private, no-store' && $response->headers['X-Robots-Tag'] === 'noindex, nofollow', 'Viewer must retain download/close controls and privacy/crawler headers.');
	aidCheck(str_contains($response->body, 'class="tadl-document-viewer tadl-finding-aid-viewer"') && str_contains($response->body, 'title="Finding aid: Synthetic Archives Collection (SYN.42)"') && str_contains($response->body, '<span class="tadl-viewer-object-identifier"> (SYN.42)</span>'), 'The bounded finding-aid viewer must display the readable collection identifier after its title.');
	aidCheck(array_slice($GLOBALS['aidLoads'], $before) === [['ca_collections', 42]], 'Opening the viewer must validate only the root; PDF generation happens once when PDF.js requests it.');
}
$saved_root = $GLOBALS['aidRows']['ca_collections'][42];
foreach ([['', [], ''], ['PRIVATE identifier', ['idno'], ''], ['SYN &quot;42&quot; &amp; &lt;unsafe&gt;', [], ' (SYN "42" & <unsafe>)']] as [$identifier, $denied, $suffix]) {
	$GLOBALS['aidRows']['ca_collections'][42]['values']['ca_collections.idno'] = $identifier;
	$GLOBALS['aidRows']['ca_collections'][42]['denied'] = $denied;
	$response = new AidResponse(); (new CollectionFindingAidController(new AidRequest(), $response))->Viewer();
	$document = new DOMDocument(); $document->loadHTML('<meta charset="UTF-8">'.$response->body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	$nodes = new DOMXPath($document); $heading = $nodes->query('//div[@class="objectInfo"]')->item(0);
	aidCheck($heading && $heading->textContent === 'Finding aid: Synthetic Archives Collection'.$suffix && $heading->getAttribute('title') === $heading->textContent && $nodes->query('.//unsafe', $heading)->length === 0, 'Viewer identifiers must be escaped and missing/restricted values omitted without empty parentheses.');
}
$GLOBALS['aidRows']['ca_collections'][42] = $saved_root;
$GLOBALS['aidViewerFail'] = true;
$response = new AidResponse(); (new CollectionFindingAidController(new AidRequest(), $response))->Viewer();
aidCheck($response->status === 503 && str_contains($response->body, 'Please use Download instead.') && !str_contains($response->body, 'Synthetic viewer failure'), 'Viewer errors must provide a safe download fallback.');
$GLOBALS['aidViewerFail'] = false;
if (!$autoload) {
	aidCheck($GLOBALS['aidPDFOptions'] === ['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false], 'Unsafe PDF features must remain disabled.');
	aidCheck($GLOBALS['aidPDFFooter'] === '{PAGE_NUM} / {PAGE_COUNT}', 'Page numbers must be present.');
	$GLOBALS['aidPDFFail'] = true; $response = new AidResponse(); $failed = new CollectionFindingAidController(new AidRequest(), $response); $failed->Download();
	aidCheck($response->status === 503 && !$failed->rendered && !str_contains($response->body, 'Synthetic renderer failure'), 'Renderer failures must stay safe and actionable.');
	$response = new AidResponse(); $failed = new CollectionFindingAidController(new AidRequest(), $response); $failed->View();
	aidCheck($response->status === 503 && !$failed->rendered, 'Inline viewing must fail safely when PDF rendering fails.');
}
if ($output = getenv('TADL_TEST_FINDING_AID_PDF')) {
	aidCheck((bool)$autoload, 'A PDF sample requires real Composer dependencies.');
	for ($i = 1;$i <= 12;$i++) {
		$id = 60000 + $i;
		$GLOBALS['aidRows']['ca_collections'][$id] = aidRow($id, 'ca_collections', 'Folder '.$i.': Český correspondence and administrative records', 'SYN.F'.str_pad((string)$i, 2, '0', STR_PAD_LEFT), [
			'values' => [
				'ca_collections.type_id' => 'Folder', 'ca_collections.date.dates_value' => (string)(1920 + $i),
				'ca_collections.extent_text' => 'One folder; approximately '.($i*10).' clippings and letters, 20 x 30 cm',
				'ca_collections.description' => $i === 1 ? str_repeat('<p>Extended folder description: synthetic correspondence documents research, community events, preservation decisions and administrative work. This paragraph must wrap normally and continue across pages without clipping or losing text.</p>', 30).'<p>End of extended folder description.</p>' : '<p>Materials concerning a fictional community organization and its public activities.</p>',
				'ca_collections.collection_scope_content' => 'Records describe the contents of this folder without requiring separate object records.'
			]
		]);
		$GLOBALS['aidRows']['ca_collections'][13]['children'][] = $id;
	}
	$sample_root = new AidModel('ca_collections'); $sample_root->load(42);
	$sample = tadlFindingAidData($request, $sample_root, $GLOBALS['aidConfig']);
	aidCheck(count($sample['collections']) === 18 && $sample['collections'][60012]['depth'] === 3, 'PDF sample must include every nested descriptive unit.');
	$view->setVar('finding_aid', $sample);
	file_put_contents($output, tadlFindingAidPDF($view->render('Details/finding_aid_pdf_html.php')));
}
if (!$autoload) {
	$diagnostics = file_get_contents($directory.'/diagnostics.log');
	aidCheck(str_contains($diagnostics, '"operation":"finding_aid"') && str_contains($diagnostics, '"collection_id":42'), 'Failed exports must record safe operator diagnostics.');
	aidCheck(!str_contains($diagnostics, 'Synthetic renderer failure') && !str_contains($diagnostics, $directory), 'Diagnostics must omit exception messages and paths.');
}
if (($argv[1] ?? '') === '--viewer-fixture') {
	aidCheck((bool)$autoload, 'Browser fixture requires real Composer dependencies.');
	// Enough pages to exercise the actual sidebar's vertical scrollbar.
	$browser_data = $data;
	$browser_data['fields']['description']['value'] = str_repeat("Synthetic narrative for the sidebar regression. Each paragraph is readable text used to exercise multi-page finding aids.\n\n", 160);
	$view->setVar('finding_aid', $browser_data);
	$browser_pdf = tadlFindingAidPDF($view->render('Details/finding_aid_pdf_html.php'));
	$viewers = [];
	foreach (['GET', 'POST'] as $method) {
		$response = new AidResponse(); (new CollectionFindingAidController(new AidRequest(['collection_id' => 42], $method), $response))->Viewer();
		$viewers[$method] = ['status' => $response->status, 'headers' => $response->headers, 'body' => $response->body];
	}
	// Native object-PDF control shapes, including long imported representation
	// labels and record/representation navigation. No catalogue or media is loaded.
	$object_viewers = [];
	foreach (['first', 'second', 'minimal'] as $document) {
		$object_view = new View(new AidRequest(), dirname(__DIR__).'/views/mediaViewers');
		$label = 'https_example_org_files_original_'.str_repeat('abcdef0123456789', 12).' (synthetic-newspaper-'.$document.'.pdf)';
		$controls = '<div class="objectInfo">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</div>';
		if ($document !== 'minimal') {
			$controls .= '<div class="repNav"><a href="#" onclick="jQuery(\'#caMediaPanelContentArea\').load(\'/object-pdf?document=second\'); return false;" aria-label="Next representation">→</a> 1 of 12</div>';
			$controls .= '<div class="nextPreviousLinks"><a href="#" onclick="jQuery(\'#caMediaPanelContentArea\').load(\'/object-pdf?document=second\'); return false;">← Previous synthetic newspaper ('.str_repeat('previous_identifier_', 8).')</a>';
			$controls .= ' | <a href="#" onclick="jQuery(\'#caMediaPanelContentArea\').load(\'/object-pdf?document=first\'); return false;">Next synthetic newspaper ('.str_repeat('next_identifier_', 8).') →</a></div>';
			if ($document === 'second') {
				$controls .= '<div class="download"><form><label>Download as <select name="version"><option>original</option></select></label><button type="button" aria-label="Download media representations">↓</button></form></div>';
			}
		}
		foreach (['viewer'=>'pdfjs', 'context'=>'objects', 'original_media_url'=>'/synthetic-object.pdf', 'search'=>'', 'scroll_mode'=>-1, 'controls'=>$controls, 'hideOverlayControls'=>$document==='minimal'] as $key=>$value) {
			$object_view->setVar($key, $value);
		}
		$object_viewers[$document] = $object_view->render('viewerWrapper.php');
	}
	print json_encode(['viewers' => $viewers, 'object_viewers'=>$object_viewers, 'pdf' => base64_encode($browser_pdf)], JSON_THROW_ON_ERROR);
} else {
	echo "Finding aid passed: {$GLOBALS['assertions']} assertions (".($autoload?'real Dompdf':'synthetic renderer').").\n";
}

$response = new AidResponse(); $text_controller = new CollectionFindingAidController(new AidRequest(), $response); $text_controller->Text();
aidCheck($text_controller->rendered === 'Details/finding_aid_html.php' && $response->headers['Cache-Control'] === 'private, no-store', 'Accessible finding aid must recheck access and use private HTML.');
aidCheck(MetaTagManager::$title === 'Finding aid: '.$text_controller->view->getVar('finding_aid')['title'], 'Text finding aids must set a descriptive native page title from the already checked collection name.');
$text_html = $text_controller->view->render('Details/finding_aid_html.php');
$text_document = new DOMDocument(); $text_document->loadHTML($text_html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); $text_xpath = new DOMXPath($text_document);
aidCheck(!str_contains($text_html, 'Transferred from a fictional community organization.') && !str_contains($text_html, 'PROVIDENCE_ONLY_PROVENANCE') && !str_contains($text_html, 'Provenance'), 'Text finding aids must omit provenance at all hierarchy levels too.');
aidCheck($text_xpath->query('//h1')->length === 1 && $text_xpath->query('//dl/dt')->length > 0 && $text_xpath->query('//ol/li/ol/li')->length > 0, 'HTML finding aids must expose headings, terms/values and the full nested archival hierarchy.');
aidCheck($text_xpath->query('//div[@class="tadl-finding-aid-actions"]/a[@href="/synthetic/Detail/collections/42" and contains(@class,"btn")]')->item(0)->textContent === 'Return to collection', 'Text finding aids must return to their own collection through a native numeric route.');
$response = new AidResponse(); $series_text = new CollectionFindingAidController(new AidRequest(['collection_id' => 7]), $response); $series_text->Text();
aidCheck($series_text->view->getVar('collection_url') === '/synthetic/Detail/collections/7' && MetaTagManager::$title === 'Finding aid: '.$series_text->view->getVar('finding_aid')['title'], 'A directly requested series finding aid must return to that series and use its own title.');
MetaTagManager::$title = 'Unchanged title';
$response = new AidResponse(); (new CollectionFindingAidController(new AidRequest(['collection_id' => 999]), $response))->Text();
aidCheck($response->status === 404, 'Unavailable HTML finding aids must remain unavailable.');
aidCheck(MetaTagManager::$title === 'Unchanged title', 'Unreadable collections must not supply title metadata.');

$root_level = $text_controller->view->getVar('finding_aid')['fields']['level']['value'] ?? '';
aidCheck($root_level === '' || str_contains($text_html, '('.htmlspecialchars($root_level, ENT_QUOTES, 'UTF-8').')'), 'HTML must retain the root archival level provided in the equivalent PDF.');
