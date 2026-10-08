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
	function hasElement($element) {
		return in_array($element, ['transcription', 'pdf_text'], true) && ($this->values['has_'.$element] ?? true);
	}
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
function textImport($text, $bundle = 'transcription') {
	return [42 => [700 => [$bundle => $text]], 999 => [800 => [$bundle => 'Unrelated synthetic text']]];
}
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

$import = "Imported transcription: café & text\nLine two";
$legacy = "Legacy PDF text: café synthetic & text\nLine two";
$object->values['ca_objects.transcription'] = textImport('<div class="mw-parser-output"><p>Imported transcription: <em>café</em> &amp; text<br>Line two</p></div>');
$object->values['ca_objects.pdf_text'] = textImport("Legacy PDF text: café <b>synthetic</b> &amp; text\nLine two", 'pdf_text');
$object->reads = [];
checkDocumentText(tadlObjectDocumentText($request, $object) === $entries, 'Extracted PDF text must win when all three sources are populated.');
checkDocumentText(!array_intersect(['ca_objects.transcription', 'ca_objects.pdf_text'], array_column($object->reads, 0)), 'Populated extraction must not fetch lower-priority metadata.');

ca_object_representations::$records[10]['media_content'] = " \n\f\u{00a0}\u{200b}";
$object->reads = [];
$html = tadlObjectDocumentTextHTML($request, $object);
$xpath = new DOMXPath(textDocument($html));
checkDocumentText($xpath->query('//details[@class="tadl-document-text" and not(@open)]/summary')->item(0)->textContent === 'Document text', 'Document text must start collapsed with a native keyboard-accessible summary.');
checkDocumentText($xpath->query('//div[@class="tadl-document-text-content"]')->item(0)->textContent === $import, 'Imported HTML must become readable text with Unicode, entities and line breaks preserved.');
checkDocumentText(!str_contains($html, 'mw-parser-output') && !str_contains($html, '&lt;div') && !str_contains($html, '<em>'), 'Imported HTML wrappers and formatting must not appear as markup or literal tags.');
checkDocumentText(!str_contains($html, '<script>') && !str_contains($html, 'Legacy PDF text') && !str_contains($html, 'Unrelated synthetic text'), 'Transcription must precede pdf_text, omit other records and never execute HTML.');
checkDocumentText(in_array('media_content', array_column(ca_object_representations::$loaded[10]->reads, 0), true), 'Extraction must be checked before falling back to transcription.');
checkDocumentText(!in_array('ca_objects.pdf_text', array_column($object->reads, 0), true), 'Populated transcription must not fetch lower-priority pdf_text.');
$options = array_values(array_filter($object->reads, static fn($read) => $read[0] === 'ca_objects.transcription'))[0][1];
foreach (['returnWithStructure' => true, 'checkAccess' => [1], 'dontReturnDefault' => true, 'convertLineBreaks' => false, 'highlighting' => false, 'doRefSubstitution' => false] as $key => $value) {
	checkDocumentText(($options[$key] ?? null) === $value, 'Imported text must preserve native getter option: '.$key);
}
foreach ([
	'<div><p>Dear Synthetic Reader:<br />First line<br/>Second line</p><p>Another paragraph.</p></div>' => "Dear Synthetic Reader:\nFirst line\nSecond line\n\nAnother paragraph.",
	"<p>First</p>\n\n<div>Second</div>" => "First\n\nSecond",
	'<ul><li>One</li><li>Two</li></ul>' => "One\nTwo",
	'<table><tr><td>One</td><td>Two</td></tr><tr><td>Three</td><td>Four</td></tr></table>' => "One Two\nThree Four",
	'<p>A &amp; B &lt; 3. <img src="invalid" onerror="synthetic()">Safe.</p>' => 'A & B < 3. Safe.',
	'<p>&lt;img src=x onerror=synthetic()&gt;</p>' => '<img src=x onerror=synthetic()>',
	"Plain café < 3 & 4 > 2\r\nSecond line\fNext page" => "Plain café < 3 & 4 > 2\nSecond line\n\nNext page",
	'0' => '0'
] as $stored => $expected) {
	$imported = new TextObject([textPDFRow(10)], ['ca_objects.transcription' => textImport($stored)]);
	$rendered = tadlObjectDocumentTextHTML($request, $imported);
	$content = (new DOMXPath(textDocument($rendered)))->query('//div[@class="tadl-document-text-content"]')->item(0);
	checkDocumentText($content && $content->textContent === $expected, 'Metadata HTML cleanup must preserve readable plain text and structural breaks.');
	checkDocumentText($content->getElementsByTagName('*')->length === 0 && !str_contains($rendered, '<script>'), 'Cleaned metadata must remain escaped text, including decoded angle brackets.');
}
$object->values['ca_objects.transcription'] = textImport('<div><p>&nbsp; <br></p></div>');
checkDocumentText(tadlObjectDocumentText($request, $object)[0]['text'] === $legacy, 'HTML-only transcription must fall through to populated legacy text.');
$object->values['ca_objects.transcription'] = textImport(" \n\t\u{00a0}\u{200b}");
checkDocumentText(tadlObjectDocumentText($request, $object)[0]['text'] === $legacy, 'Blank extraction and whitespace-only transcription must fall back to pdf_text.');
$html = tadlObjectDocumentTextHTML($request, $object);
$xpath = new DOMXPath(textDocument($html));
checkDocumentText($xpath->query('//div[@class="tadl-document-text-content"]')->item(0)->textContent === $legacy
	&& !str_contains($html, '<b>') && !str_contains($html, 'Unrelated synthetic text'), 'pdf_text must preserve escaped Unicode/plain text and omit other records.');
$options = array_values(array_filter($object->reads, static fn($read) => $read[0] === 'ca_objects.pdf_text'))[0][1];
foreach (['returnWithStructure' => true, 'checkAccess' => [1], 'dontReturnDefault' => true, 'convertLineBreaks' => false, 'highlighting' => false, 'doRefSubstitution' => false] as $key => $value) {
	checkDocumentText(($options[$key] ?? null) === $value, 'pdf_text must preserve native getter option: '.$key);
}
$object->values['ca_objects.pdf_text'] = [42 => [700 => ['pdf_text' => " \n"], 701 => ['pdf_text' => "First\r\nSecond\fThird"]]];
checkDocumentText(tadlObjectDocumentText($request, $object)[0]['text'] === "First\nSecond\n\nThird", 'Fallback metadata must ignore empty repeat values and preserve normalized line/page breaks.');
$object->values['ca_objects.pdf_text'] = textImport($legacy, 'pdf_text');
$object->values['has_transcription'] = false;
$object->values['ca_objects.transcription'] = textImport('Unavailable imported field');
checkDocumentText(tadlObjectDocumentText($request, $object)[0]['text'] === $legacy, 'Installations without transcription must still support pdf_text.');
$object->values['has_pdf_text'] = false;
checkDocumentText(tadlObjectDocumentTextHTML($request, $object) === '', 'Missing fallback elements with blank extraction must produce no section.');
$object->values['has_pdf_text'] = true;
$object->values['has_transcription'] = true;
$object->values['ca_objects.transcription'] = textImport($import);
$object->reads = [];
checkDocumentText(tadlObjectDocumentText(new TextRequest([1], ['ca_objects:transcription']), $object)[0]['text'] === $legacy, 'Denied transcription bundle must fall back to readable pdf_text.');
checkDocumentText(!in_array('ca_objects.transcription', array_column($object->reads, 0), true), 'Denied transcription must not be fetched.');
checkDocumentText(tadlObjectDocumentText(new TextRequest([1], ['ca_object_representations:media_content']), $object)[0]['text'] === $import, 'Readable transcription must not require access to extracted-text bundle.');
$object->values['ca_objects.transcription'] = textImport(" \n\t\u{00a0}");
checkDocumentText(tadlObjectDocumentText(new TextRequest([1], ['ca_object_representations:media_content']), $object)[0]['text'] === $legacy, 'Denied extraction and empty transcription must allow readable pdf_text.');
$object->reads = [];
checkDocumentText(tadlObjectDocumentTextHTML(new TextRequest([1], ['ca_objects:pdf_text']), $object) === '', 'Denied pdf_text with other sources blank must produce no section.');
checkDocumentText(!in_array('ca_objects.pdf_text', array_column($object->reads, 0), true), 'Denied pdf_text must not be fetched.');
$object->values['ca_objects.transcription'] = textImport($import);
checkDocumentText(tadlObjectDocumentTextHTML(new TextRequest([1], ['ca_objects:transcription', 'ca_objects:pdf_text']), $object) === '', 'Denied metadata bundles must not leak either fallback.');
ca_object_representations::$records[10]['media_content'] = 'Public extraction';
checkDocumentText(tadlObjectDocumentText(new TextRequest([1], ['ca_objects:transcription', 'ca_objects:pdf_text']), $object)[0]['text'] === 'Public extraction', 'Readable extraction must not require access to either metadata bundle.');
ca_object_representations::$records[10]['media_content'] = " \n\f\u{00a0}";
$object->values['ca_objects.transcription'] = textImport("\u{200b}");
$object->values['ca_objects.pdf_text'] = textImport(" \n\t\u{00a0}\u{FEFF}", 'pdf_text');
checkDocumentText(tadlObjectDocumentTextHTML($request, $object) === '', 'All three whitespace-only sources must produce no section or heading.');
$object->values['ca_objects.transcription'] = textImport('<div><p>&nbsp;</p></div>');
$object->values['ca_objects.pdf_text'] = textImport('<p><br></p>', 'pdf_text');
checkDocumentText(tadlObjectDocumentTextHTML($request, $object) === '', 'Empty imported HTML in both metadata fields must produce no section or heading.');
unset($object->values['ca_objects.transcription']);
unset($object->values['ca_objects.pdf_text']);
checkDocumentText(tadlObjectDocumentTextHTML($request, $object) === '', 'Blank extraction with no metadata values must produce no section.');

// Deliberately return unfiltered rows to exercise the loaded-record checks too.
foreach (['access' => 0, 'deleted' => 1, 'unreadable' => true, 'acl_access' => 0, 'mimetype' => 'image/tiff'] as $key => $value) {
	ca_object_representations::$records[10] = ['media_content' => 'Hidden text', 'acl_enabled' => true, $key => $value];
	$object->values['ca_objects.transcription'] = textImport('Must remain hidden without a readable PDF');
	$object->values['ca_objects.pdf_text'] = textImport('Must also remain hidden without a readable PDF', 'pdf_text');
	checkDocumentText(tadlObjectDocumentTextHTML($request, $object) === '', 'Ineligible PDF must hide all text sources: '.$key);
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
$tiff = new TextObject([['representation_id' => 20, 'mimetype' => 'image/tiff']], [
	'ca_objects.transcription' => textImport('Private pair transcript'),
	'ca_objects.pdf_text' => textImport('Private pair PDF text', 'pdf_text')
]);
checkDocumentText(tadlObjectDocumentTextHTML($request, $tiff) === '', 'TIFF with no accessible PDF must not display migrated PDF text.');
$tiff->rows[] = textPDFRow(10);
ca_object_representations::$records[10]['access'] = 0;
checkDocumentText(tadlObjectDocumentTextHTML($request, $tiff) === '', 'TIFF with a private paired PDF must not display any text source.');
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
ca_object_representations::$records[11]['media_content'] = '';
$partial = new TextObject([textPDFRow(10), textPDFRow(11)], [
	'ca_objects.transcription' => textImport($import), 'ca_objects.pdf_text' => textImport($legacy, 'pdf_text')
]);
checkDocumentText(array_column(tadlObjectDocumentText($request, $partial), 'text') === ['Rank 2'], 'Any populated readable PDF extraction must suppress both metadata fallbacks, even when another PDF is blank.');
checkDocumentText(!array_intersect(['ca_objects.transcription', 'ca_objects.pdf_text'], array_column($partial->reads, 0)), 'Partially populated PDF extraction must not fetch lower-priority metadata.');
ca_object_representations::$records[11]['media_content'] = 'Rank 1 primary';

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
