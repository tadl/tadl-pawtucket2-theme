<?php
/** Actual subject helper, native relationship/access boundaries simulated; no DB. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('__CA_BUNDLE_ACCESS_READONLY__', 1);
$assertions = 0;
function checkSubjects($condition, $message) {
	$GLOBALS['assertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function _t($text) { return $text; }
function caGetBundleAccessLevel($table, $bundle) { return $GLOBALS['subjectDeniedBundle'] === "$table.$bundle" ? 0 : 1; }
function caGetUserAccessValues($request) { return [1]; }
function caACLIsEnabled($table, $options) { return $GLOBALS['subjectACL']; }
function caNavUrl($request, $module, $controller, $action, $params) { return '/'.$controller.'/'.$action.'?'.http_build_query($params); }
function caGetBrowseInstance($table) { return new SubjectBrowse(); }
class Datamodel { static function getInstance($table, $new) { return $GLOBALS['subjectTarget'] ? new SubjectTarget() : null; } }
class SubjectTarget { function tableNum() { return 33; } }
class SubjectBrowse {
	function filterHitsByACL($ids, $table, $user) {
		checkSubjects($ids === [12, 9, 12, 0, -1, 13] && $table === 33 && $user === 7, 'Incorrect front-end ACL inputs.');
		return $GLOBALS['subjectReadable'];
	}
}
class SubjectRequest { function getUserID() { return 7; } }
class SubjectObject {
	public array $calls = [];
	public array $rows = [];
	function __construct(public int $id = 42) {}
	function getPrimaryKey() { return $this->id; }
	function getRelatedItems($table, $options) {
		$this->calls[] = [$table, $options];
		return $this->rows;
	}
}
require dirname(__DIR__).'/views/Details/detail_field_helpers.php';
require dirname(__DIR__).'/views/Browse/tadl_result_context_helpers.php';
$subjectDeniedBundle = '';
$subjectACL = false;
$subjectTarget = true;
$subjectReadable = [12];
$object = new SubjectObject();
$object->rows = [
	['item_id' => 12, 'label' => 'Synthetic bridges'],
	['item_id' => 9, 'label' => 'Archives <script>alert("x")</script> & photographs'],
	['item_id' => 12, 'label' => 'Synthetic bridges'],
	['item_id' => 0, 'label' => 'Invalid zero ID'],
	['item_id' => -1, 'label' => 'Invalid negative ID'],
	['item_id' => 13, 'label' => ' ']
];
$request = new SubjectRequest();
$html = tadlObjectSubjects($request, $object);
checkSubjects($object->calls === [['ca_list_items', ['checkAccess' => [1]]]], 'Relationship read did not preserve native access filtering.');
$document = new DOMDocument();
$document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($document);
$links = $xpath->query('//li/a');
checkSubjects($links->length === 2, 'Duplicate/invalid/empty subjects were displayed.');
checkSubjects($links->item(0)->textContent === $object->rows[1]['label'], 'Subjects lost text or alphabetical order.');
checkSubjects($xpath->query('//script')->length === 0, 'Subject label became executable markup.');
checkSubjects($xpath->query('//label')->item(0)->textContent === 'Subjects', 'Missing Subjects heading.');
foreach ($links as $i => $link) {
	$url = parse_url($link->getAttribute('href'));
	parse_str($url['query'], $params);
	checkSubjects($url['path'] === '/Browse/subjects' && $params === ['facet' => 'term_facet', 'id' => (string)($i === 0 ? 9 : 12), 'clear' => '1'], 'Subject link did not seed a clean native vocabulary browse.');
}
foreach (['ca_objects.ca_list_items', 'ca_list_items.preferred_labels'] as $denied) {
	$subjectDeniedBundle = $denied; $object->calls = [];
	checkSubjects(tadlObjectSubjects($request, $object) === '' && !$object->calls, 'Restricted subject bundle leaked or was read.');
}
$subjectDeniedBundle = ''; $subjectACL = true;
$html = tadlObjectSubjects($request, $object);
checkSubjects(str_contains($html, 'Synthetic bridges') && !str_contains($html, 'Archives'), 'Front-end-only ACL restrictions leaked a subject.');
$subjectTarget = false;
checkSubjects(tadlObjectSubjects($request, $object) === '', 'Missing ACL target failed open.');
$subjectACL = false;
checkSubjects(tadlObjectSubjects($request, new SubjectObject()) === '', 'Empty subject field was displayed.');
checkSubjects(tadlObjectSubjects($request, new SubjectObject(0)) === '', 'Unloaded object displayed subjects.');
checkSubjects(tadlObjectSubjects($request, null) === '', 'Missing object displayed subjects.');
checkSubjects(tadlResultFacetHeading(['type' => 'authority', 'table' => 'ca_list_items']) === 'Subjects', 'Browse/refine heading still calls subjects terms.');
echo json_encode(['status' => 'passed', 'assertions' => $assertions, 'boundaries' => 'actual subject rendering/facet heading; synthetic native relationship, bundle access and ACL APIs'], JSON_PRETTY_PRINT).PHP_EOL;
