<?php
/** Actual shared renderer; synthetic rich text, permissions and media captions. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$checks = 0;
function checkReadMore($condition, $message) {
	$GLOBALS['checks']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function _t($text) { return $text; }
function caGetUserAccessValues($request) { return $request->access; }
class ReadMoreItem {
	public array $reads = [];
	function __construct(public array $values) {}
	function getWithTemplate($template, $options = []) {
		$this->reads[] = [$template, $options];
		return $this->values[$template] ?? '';
	}
}
function readMoreDocument($html) {
	$document = new DOMDocument();
	$document->loadHTML('<meta charset="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	return new DOMXPath($document);
}
require dirname(__DIR__).'/views/Details/detail_field_helpers.php';
$request = (object)['access' => [1, 2]];
$rich = '<p>'.str_repeat('Synthetic café &amp; archive context. ', 24).'</p><ul><li>Full inventory</li></ul>'
	.'<p><a href="https://example.org/guide">Research guide</a> with <strong>original formatting</strong>.</p>';
foreach ([
	['ca_collections.description', 'Description'], ['ca_collections.collection_scope_content', 'Scope and content'],
	['ca_objects.description', 'Description'], ['ca_objects.provenance', 'Provenance'],
	['ca_entities.biography', 'Description'], ['ca_places.description', 'Description'],
	['ca_occurrences.description_source', 'Source of description']
] as [$bundle, $label]) {
	$item = new ReadMoreItem(['^'.$bundle => $rich]);
	$html = tadlDetailField($request, $item, $label, '^'.$bundle);
	$xpath = readMoreDocument($html);
	checkReadMore($xpath->query('//details[@class="tadl-long-field-details" and not(@open)]/summary')->length === 1, $bundle.': long text must start collapsed with a native disclosure.');
	checkReadMore($xpath->query('//div[@class="tadl-long-field-preview"]')->length === 1, $bundle.': collapsed long text needs a preview.');
	checkReadMore($xpath->query('//summary/span[@class="sr-only"]')->item(0)->textContent === ': '.$label, $bundle.': disclosure must identify its field for assistive technology.');
	checkReadMore($xpath->query('//summary/span[@class="tadl-read-more"]')->item(0)->textContent === 'Read more'
		&& $xpath->query('//summary/span[@class="tadl-read-less"]')->item(0)->textContent === 'Read less', $bundle.': disclosure needs both state labels.');
	checkReadMore(str_contains($html, '<div class="tadl-long-field-full">'.$rich.'</div>'), $bundle.': the complete rich value must remain intact, not truncated or rebuilt.');
	checkReadMore($item->reads === [['^'.$bundle, ['convertCodesToDisplayText' => true, 'checkAccess' => [1, 2]]]], $bundle.': disclosure must preserve native permissions without extra reads.');
}
foreach (['0', 'A short description.', '<p>Short &amp; formatted <strong>description</strong>.</p>', str_repeat('é', 600), str_repeat('<span></span>', 100).'Short', str_repeat('&nbsp;', 700).'Short'] as $short) {
	checkReadMore(tadlDetailExpandableValue($short, 'Description') === $short, 'Short or markup-heavy values must remain unchanged without a control.');
}
checkReadMore(str_contains(tadlDetailExpandableValue(str_repeat('é', 601), 'Description'), 'tadl-long-field-details'), 'The long-text boundary must count Unicode characters rather than bytes.');
foreach (['', " \n", '<p>&nbsp;</p>'] as $blank) {
	checkReadMore(tadlDetailField($request, new ReadMoreItem(['^synthetic' => $blank]), 'Description', '^synthetic') === '', 'Empty values must omit the field, preview and disclosure.');
}
$escaped = '&lt;script&gt;synthetic()&lt;/script&gt; café &amp; ';
$html = tadlDetailExpandableValue('<p>'.$escaped.'</p><p>'.str_repeat('Synthetic words ', 60).'</p>', 'Source <synthetic> & "citation"');
$xpath = readMoreDocument($html);
$preview = $xpath->query('//div[@class="tadl-long-field-preview"]')->item(0);
checkReadMore(str_starts_with($preview->textContent, '<script>synthetic()</script> café & Synthetic words'), 'Preview must decode entities and separate rich-text paragraphs.');
checkReadMore($preview->getElementsByTagName('script')->length === 0 && $preview->getElementsByTagName('a')->length === 0, 'Preview must contain plain text without partial markup or hidden focusable links.');
checkReadMore(mb_strlen($preview->textContent) <= 321 && str_ends_with($preview->textContent, '…'), 'Preview must be bounded without splitting a Unicode character.');
checkReadMore($xpath->query('//summary/span[@class="sr-only"]')->item(0)->textContent === ': Source <synthetic> & "citation"', 'Field names must be escaped in the disclosure label.');
$item = new ReadMoreItem(['blank' => '<p>&nbsp;</p>', 'fallback' => $rich]);
checkReadMore(str_contains(tadlDetailFirstAvailableField($request, $item, 'Description', ['blank', 'fallback']), '<div class="tadl-long-field-full">'.$rich.'</div>'), 'First-available fields must get the same complete expandable value.');

class ca_object_representations {
	function __construct(private int $id) {}
	function getPrimaryKey() { return $this->id; }
	function getWithTemplate($template, $options) { return $GLOBALS['caption']; }
}
class ReadMoreMedia {
	function getRepresentations($versions, $sizes, $options) {
		checkReadMore($options === ['checkAccess' => [1, 2]], 'Caption discovery must retain native representation access filtering.');
		return [10 => []];
	}
}
$GLOBALS['caption'] = $rich;
checkReadMore(str_contains(tadlObjectRepresentationCaptions($request, new ReadMoreMedia()), '<div class="tadl-long-field-full">'.$rich.'</div>'), 'Long media captions must receive the same disclosure.');
$GLOBALS['caption'] = 'A short caption.';
checkReadMore(!str_contains(tadlObjectRepresentationCaptions($request, new ReadMoreMedia()), 'tadl-long-field'), 'Short media captions must stay fully visible.');
echo 'Detail read more passed: '.$checks.' assertions (synthetic metadata, complete rich text and native disclosures).'.PHP_EOL;
