<?php
/** Actual theme helper/view; synthetic native records, permissions and PDF text. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('__CA_BUNDLE_ACCESS_READONLY__', 1);
define('__CA_ACL_READONLY_ACCESS__', 1);
$assertions = 0;
function checkDocumentText($condition, $message) {
	$GLOBALS['assertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function _t($text) { return $text; }
function caGetUserAccessValues($request) { return $request->access; }
function caACLIsEnabled($record, $options = []) {
	checkDocumentText(($options['forPawtucket'] ?? false) === true, 'Item ACL check must include Pawtucket-only ACLs.');
	return $record->values['acl_enabled'] ?? false;
}
function caGetBundleAccessLevel($table, $bundle) { return 0; } // Unrelated detail fields are unavailable.
function caDisplayLightbox($request) { return false; }
function caObjectRepresentationThumbnails($request, $representationID, $object, $options) { return []; }
class TextRequest {
	public object $user;
	function __construct(public array $access = [1], public array $denied = []) { $this->user = (object)['id' => 0]; }
	function isLoggedIn() { return false; }
}
class TextRecord {
	public array $reads = [];
	function __construct(public string $table, public int $id = 42, public array $values = []) {
		$this->values += ['access' => 1, 'deleted' => 0, 'mimetype' => 'application/pdf'];
	}
	function getPrimaryKey() { return $this->id; }
	function get($key, $options = []) { $this->reads[] = [$key, $options]; return $this->values[$key] ?? null; }
	function isReadable($request, $bundle = null) {
		return !($this->values['unreadable'] ?? false)
			&& !in_array($this->table.':'.($bundle ?? ''), $request->denied, true);
	}
	function checkACLAccessForUser($user) { return $this->values['acl_access'] ?? 1; }
	function getWithTemplate($template, $options = []) { return ''; }
}
class TextObject extends TextRecord {
	public array $representationCalls = [];
	function __construct(public array $rows = [], array $values = []) { parent::__construct('ca_objects', 42, $values); }
	function hasElement($element) { return $element === 'pdf_text' && ($this->values['has_pdf_text'] ?? true); }
	function getRepresentations($versions, $sizes, $options) {
		$this->representationCalls[] = [$versions, $sizes, $options];
		return $this->rows;
	}
}
class ca_object_representations extends TextRecord {
	static public array $records = [];
	static public array $loaded = [];
	function __construct($id = 0) { parent::__construct('ca_object_representations', 0); if ($id) { $this->load($id); } }
	function load($id) {
		if (!isset(self::$records[$id])) { return false; }
		$this->id = (int)$id;
		$this->values = self::$records[$id] + ['access' => 1, 'deleted' => 0, 'mimetype' => 'application/pdf'];
		self::$loaded[$id] = $this;
		return true;
	}
}
class Datamodel {
	static function getInstance($table, $new) {
		checkDocumentText($table === 'ca_object_representations' && $new === true, 'PDF text must load its native representation model.');
		return new ca_object_representations();
	}
}
function textPDFRow($id, $rank = 0, $primary = false, $label = '') {
	return ['representation_id' => $id, 'mimetype' => 'application/pdf', 'rank' => $rank, 'is_primary' => $primary, 'label' => $label];
}
function textImport($text) { return [42 => [700 => ['pdf_text' => $text]], 999 => [800 => ['pdf_text' => 'Unrelated synthetic text']]]; }
function textDocument($html) {
	$document = new DOMDocument();
	$document->loadHTML('<meta charset="utf-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	return $document;
}
require dirname(__DIR__).'/helpers/document_text.php';
$request = new TextRequest();
ca_object_representations::$records = [10 => ['media_content' => "First page\r\nSecond line\fNext page < 3 & 4 > 2"]];
$object = new TextObject([textPDFRow(10)]);
$entries = tadlObjectDocumentText($request, $object);
checkDocumentText(count($entries) === 1 && $entries[0]['source'] === 'pdf', 'Accessible newspaper PDF must supply extracted text.');
checkDocumentText($entries[0]['text'] === "First page\nSecond line\n\nNext page < 3 & 4 > 2", 'Newlines/page breaks must remain readable without removing literal angle brackets.');
$call = $object->representationCalls[0];
checkDocumentText($call === [[], null, ['simple' => true, 'checkAccess' => [1]]], 'PDF discovery must use native access filtering without derivatives or a relationship cap.');

$import = "Imported transcription: café <script>synthetic()</script> & text\nLine two";
$object->values['ca_objects.pdf_text'] = textImport($import);
$html = tadlObjectDocumentTextHTML($request, $object);
$xpath = new DOMXPath(textDocument($html));
checkDocumentText($xpath->query('//details[@class="tadl-document-text" and not(@open)]/summary')->item(0)->textContent === 'Document text', 'Document text must start collapsed with a native keyboard-accessible summary.');
checkDocumentText($xpath->query('//div[@class="tadl-document-text-content"]')->item(0)->textContent === $import, 'Imported text must retain Unicode and literal text after escaping.');
checkDocumentText(!str_contains($html, '<script>') && !str_contains($html, 'First page') && !str_contains($html, 'Unrelated synthetic text'), 'Imported text must override extraction, omit other records and never execute HTML.');
checkDocumentText(!in_array('media_content', array_column(ca_object_representations::$loaded[10]->reads, 0), true), 'Imported text must not fetch the longer extracted text unnecessarily.');
$options = array_values(array_filter($object->reads, static fn($read) => $read[0] === 'ca_objects.pdf_text'))[0][1];
foreach (['returnWithStructure' => true, 'checkAccess' => [1], 'dontReturnDefault' => true, 'convertLineBreaks' => false, 'highlighting' => false, 'doRefSubstitution' => false] as $key => $value) {
	checkDocumentText(($options[$key] ?? null) === $value, 'Imported text must preserve native getter option: '.$key);
}
$object->values['ca_objects.pdf_text'] = textImport(" \n\t\u{00a0}\u{200b}");
checkDocumentText(tadlObjectDocumentText($request, $object)[0]['source'] === 'pdf', 'Whitespace-only migrated text must fall back to extraction.');
$object->values['has_pdf_text'] = false;
$object->values['ca_objects.pdf_text'] = textImport('Unavailable imported field');
checkDocumentText(tadlObjectDocumentText($request, $object)[0]['source'] === 'pdf', 'Installations without pdf_text must still show extracted PDF text.');
$object->values['has_pdf_text'] = true;
checkDocumentText(tadlObjectDocumentText(new TextRequest([1], ['ca_objects:pdf_text']), $object)[0]['source'] === 'pdf', 'Denied migrated-text bundle must fall back to readable extracted text.');
checkDocumentText(tadlObjectDocumentText(new TextRequest([1], ['ca_object_representations:media_content']), $object)[0]['source'] === 'imported', 'Readable imported text must not require access to extracted-text bundle.');
unset($object->values['ca_objects.pdf_text']);
checkDocumentText(tadlObjectDocumentTextHTML(new TextRequest([1], ['ca_object_representations:media_content']), $object) === '', 'Denied extracted-text bundle must emit no empty section or heading.');

// Deliberately return unfiltered rows to exercise the loaded-record checks too.
foreach (['access' => 0, 'deleted' => 1, 'unreadable' => true, 'acl_access' => 0, 'mimetype' => 'image/tiff'] as $key => $value) {
	ca_object_representations::$records[10] = ['media_content' => 'Hidden text', 'acl_enabled' => true, $key => $value];
	$object->values['ca_objects.pdf_text'] = textImport('Must remain hidden without a readable PDF');
	checkDocumentText(tadlObjectDocumentTextHTML($request, $object) === '', 'Ineligible PDF must hide both text sources: '.$key);
}
ca_object_representations::$records = [10 => ['media_content' => 'Public PDF text']];
foreach (['access' => 0, 'deleted' => 1, 'unreadable' => true, 'acl_access' => 0] as $key => $value) {
	$ineligible = new TextObject([textPDFRow(10)], ['acl_enabled' => true, $key => $value]);
	checkDocumentText(tadlObjectDocumentTextHTML($request, $ineligible) === '' && !$ineligible->representationCalls, 'Ineligible object must stop before PDF discovery: '.$key);
}
foreach (['ca_objects:ca_object_representations', 'ca_object_representations:media'] as $bundle) {
	checkDocumentText(tadlObjectDocumentTextHTML(new TextRequest([1], [$bundle]), $object) === '', 'Unreadable media bundle must hide document text: '.$bundle);
}
checkDocumentText(tadlObjectDocumentTextHTML(new TextRequest([]), $object) === '', 'An empty access mask must fail closed.');
checkDocumentText(tadlObjectDocumentTextHTML($request, null) === '', 'Missing object must have no document section.');
$missing = new TextObject([textPDFRow(404)]);
checkDocumentText(tadlObjectDocumentTextHTML($request, $missing) === '', 'Missing representation must have no document section.');
$missing->id = 0;
checkDocumentText(tadlObjectDocumentTextHTML($request, $missing) === '', 'Unloaded object must have no document section.');
$tiff = new TextObject([['representation_id' => 20, 'mimetype' => 'image/tiff']], ['ca_objects.pdf_text' => textImport('Private pair transcript')]);
checkDocumentText(tadlObjectDocumentTextHTML($request, $tiff) === '', 'TIFF with no accessible PDF must not display migrated PDF text.');
$tiff->rows[] = textPDFRow(10);
ca_object_representations::$records[10]['access'] = 0;
checkDocumentText(tadlObjectDocumentTextHTML($request, $tiff) === '', 'TIFF with a private paired PDF must not display either source.');
ca_object_representations::$records[10] = ['media_content' => " \n\f\u{00a0}"];
checkDocumentText(tadlObjectDocumentTextHTML($request, new TextObject([textPDFRow(10)])) === '', 'Image-only/empty PDF must not display a heading or wrapper.');

ca_object_representations::$records = [
	10 => ['media_content' => 'Rank 2'], 11 => ['media_content' => 'Rank 1 primary'],
	12 => ['media_content' => 'Rank 1 second'], 13 => ['media_content' => 'Rank 1 third'],
	14 => ['media_content' => 'Blank label', 'unreadable' => false]
];
$object = new TextObject([textPDFRow(13, 1), textPDFRow(10, 2), textPDFRow(12, 1), textPDFRow(11, 1, true, 'Synthetic <b>PDF</b>'), textPDFRow(14, 3), textPDFRow(11, 1, true)]);
$entries = tadlObjectDocumentText($request, $object);
checkDocumentText(array_column($entries, 'text') === ['Rank 1 primary', 'Rank 1 second', 'Rank 1 third', 'Rank 2', 'Blank label'], 'Multiple PDFs must retain stable rank/primary/ID order without duplicates.');
$xpath = new DOMXPath(textDocument(tadlObjectDocumentTextHTML($request, $object)));
checkDocumentText($xpath->query('//h3')->length === 5 && $xpath->query('//h3')->item(0)->textContent === 'Synthetic <b>PDF</b>' && $xpath->query('//h3//b')->length === 0, 'Multiple-PDF headings must safely identify each text source.');
checkDocumentText($xpath->query('//h3')->item(4)->textContent === 'PDF 5', 'Empty representation label must get a useful fallback.');
$entries = tadlObjectDocumentText(new TextRequest([1], ['ca_object_representations:preferred_labels']), $object);
checkDocumentText(array_filter(array_column($entries, 'label')) === [], 'Denied preferred-label bundle must not leak a label.');

// Render the actual object template and verify full-width placement after both columns.
class TextView {
	function __construct(public TextRequest $request, public TextObject $object) {}
	function getVar($name) { return $name === 'item' ? $this->object : null; }
	function render() { ob_start(); include dirname(__DIR__).'/views/Details/ca_objects_default_html.php'; return ob_get_clean(); }
}
$html = (new TextView($request, $object))->render();
$xpath = new DOMXPath(textDocument($html));
$section = $xpath->query('//details[@class="tadl-document-text"]')->item(0);
checkDocumentText($section && str_contains($section->parentNode->getAttribute('class'), 'tadl-object-content'), 'Document text must span the container outside the two-column grid.');
checkDocumentText($xpath->query('preceding-sibling::div[contains(@class,"row")]/div[contains(concat(" ",normalize-space(@class)," ")," tadl-object-media ")]', $section)->length === 1
	&& $xpath->query('preceding-sibling::div[contains(@class,"row")]/div[contains(concat(" ",normalize-space(@class)," ")," tadl-object-info ")]', $section)->length === 1, 'Document text must follow both the image and metadata columns.');
ca_object_representations::$records[10]['access'] = 0;
$html = (new TextView($request, $tiff))->render();
checkDocumentText(!str_contains($html, 'tadl-document-text'), 'Actual TIFF detail must omit an empty document-text section.');
echo 'Document text passed: '.$assertions.' assertions (synthetic PDF/import sources, native access and actual detail view).'.PHP_EOL;
