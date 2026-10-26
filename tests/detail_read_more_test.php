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
	['ca_objects.description', 'Description'], ['ca_objects.coverageNotes', 'Coverage notes'],
	['ca_entities.biography', 'Description'], ['ca_places.description', 'Description'],
	['ca_occurrences.description_source', 'Source of description']
] as [$bundle, $label]) {
	$item = new ReadMoreItem(['^'.$bundle => $rich]);
	$html = tadlDetailField($request, $item, $label, '^'.$bundle);
	$xpath = readMoreDocument($html);
	checkReadMore($xpath->query('//details[@class="tadl-long-field-details" and not(@open)]/summary')->length === 1, $bundle.': long text must start collapsed with a native disclosure.');
	checkReadMore($xpath->query('//div[starts-with(@class,"tadl-long-field-preview")]')->length === 1, $bundle.': collapsed long text needs a preview.');
	checkReadMore($xpath->query('//summary/span[@class="sr-only"]')->item(0)->textContent === ': '.$label, $bundle.': disclosure must identify its field for assistive technology.');
	checkReadMore($xpath->query('//summary/span[@class="tadl-read-more"]')->item(0)->textContent === 'Read more'
		&& $xpath->query('//summary/span[@class="tadl-read-less"]')->item(0)->textContent === 'Read less', $bundle.': disclosure needs both state labels.');
	checkReadMore(str_contains($html, '<div class="tadl-long-field-full">'.$rich.'</div>'), $bundle.': the complete rich value must remain intact, not truncated or rebuilt.');
	checkReadMore($xpath->query('//details/div[@class="tadl-long-field-full"]/following-sibling::button[@type="button" and @class="tadl-long-field-collapse" and @hidden]')->length === 1, $bundle.': the enhanced collapse control must follow the full value and stay hidden without JavaScript.');
	checkReadMore($xpath->query('//button[@class="tadl-long-field-collapse"]/span[@class="sr-only"]')->item(0)->textContent === ': '.$label, $bundle.': the ending collapse action must retain its field context.');
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
$preview = $xpath->query('//div[starts-with(@class,"tadl-long-field-preview")]')->item(0);
checkReadMore(str_starts_with($preview->textContent, '<script>synthetic()</script> café & Synthetic words'), 'Preview must decode entities and separate rich-text paragraphs.');
checkReadMore($preview->getElementsByTagName('script')->length === 0 && $preview->getElementsByTagName('p')->length === 2, 'Preview must preserve paragraph formatting and keep escaped tag text inert.');
checkReadMore(mb_strlen($preview->textContent) <= 321 && str_ends_with($preview->textContent, '…'), 'Preview must be bounded without splitting a Unicode character.');
checkReadMore($xpath->query('//summary/span[@class="sr-only"]')->item(0)->textContent === ': Source <synthetic> & "citation"', 'Field names must be escaped in the disclosure label.');
$item = new ReadMoreItem(['blank' => '<p>&nbsp;</p>', 'fallback' => $rich]);
checkReadMore(str_contains(tadlDetailFirstAvailableField($request, $item, 'Description', ['blank', 'fallback']), '<div class="tadl-long-field-full">'.$rich.'</div>'), 'First-available fields must get the same complete expandable value.');

// The real relationship/external-link callers share this renderer across records and galleries.
$rows = [];
for ($id = 1; $id <= 14; $id++) {
	$rows[] = '<a id="synthetic-link-'.$id.'" href="/synthetic/entities/'.$id.'?a=1&amp;b=2" target="_blank" rel="noopener">Synthetic linked record '.$id.'</a> <em>(synthetic associated role '.$id.')</em>';
}
$linked = join('<br/>', $rows);
foreach (['Related people/organizations', 'Creators', 'Publisher', 'Related collections', 'Related objects', 'Related events', 'Related places', 'Vocabulary terms', 'External links', 'Gallery related people'] as $label) {
	$template = '<unit relativeTo="ca_entities" delimiter="<br/>"><l>^ca_entities.preferred_labels.displayname</l> (^relationship_typename)</unit>';
	$item = new ReadMoreItem([$template => $linked]);
	$html = tadlDetailField($request, $item, $label, $template);
	$xpath = readMoreDocument($html);
	$preview = $xpath->query('//div[contains(@class,"tadl-long-field-preview--rich")]')->item(0);
	checkReadMore($preview && $preview->getElementsByTagName('a')->length === 5 && $preview->getElementsByTagName('br')->length === 4, $label.': collapsed entries must retain five complete links and their row breaks.');
	checkReadMore($preview->getElementsByTagName('em')->length === 5 && str_contains($preview->textContent, '(synthetic associated role 5)'), $label.': relationship roles and emphasis must remain paired with their links.');
	checkReadMore($preview->getElementsByTagName('a')->item(4)->getAttribute('href') === '/synthetic/entities/5?a=1&b=2'
		&& $preview->getElementsByTagName('a')->item(0)->getAttribute('target') === '_blank' && $preview->getElementsByTagName('a')->item(0)->getAttribute('rel') === 'noopener', $label.': native link destinations and attributes must survive.');
	checkReadMore(!str_contains($preview->textContent, 'record 6') && $xpath->query('//*[@id="synthetic-link-1"]')->length === 1, $label.': omitted entries must remain inside the disclosure without duplicate DOM IDs.');
	checkReadMore(str_contains($html, '<div class="tadl-long-field-full">'.$linked.'</div>') && count($item->reads) === 1 && $item->reads[0][1]['checkAccess'] === [1, 2], $label.': full values and permission-preserving single reads must remain intact.');
}
foreach (['ul', 'ol'] as $tag) {
	$value = '<'.$tag.' id="synthetic-list">'.join('', array_map(static fn($row) => '<li>'.$row.'</li>', $rows)).'</'.$tag.'>';
	$xpath = readMoreDocument(tadlDetailExpandableValue($value, 'Linked list'));
	$preview = $xpath->query('//div[contains(@class,"tadl-long-field-preview--rich")]')->item(0);
	checkReadMore($preview->getElementsByTagName($tag)->length === 1 && $preview->getElementsByTagName('li')->length === 5, $tag.': previews must retain list formatting and whole entries.');
	checkReadMore($xpath->query('//*[@id="synthetic-list"]')->length === 1, $tag.': previews must not duplicate list IDs.');
}
$fewRows = '<a href="/synthetic/1">'.str_repeat('Synthetic complete name ', 20).'</a> (synthetic role)<br/><a href="/synthetic/2">'.str_repeat('Synthetic second name ', 20).'</a> (synthetic role)';
checkReadMore(tadlDetailExpandableValue($fewRows, 'Related people', true) === $fewRows, 'A few long linked entries must stay complete without an empty/useless disclosure.');
$oneRow = '<a href="/synthetic/1">'.str_repeat('Synthetic complete name ', 30).'</a> (synthetic associated role)';
checkReadMore(tadlDetailExpandableValue($oneRow, 'Related people', true) === $oneRow, 'A single long linked entry must retain its complete relationship role.');
$formatted = '<p id="synthetic-paragraph"><a href="https://example.org/guide">Synthetic guide</a> <strong>Important context</strong> and <em>emphasis</em>. '.str_repeat('Synthetic archive words ', 60).'<a href="/synthetic/later">Later hidden link</a></p>';
$xpath = readMoreDocument(tadlDetailExpandableValue($formatted, 'Description'));
$preview = $xpath->query('//div[contains(@class,"tadl-long-field-preview--rich")]')->item(0);
checkReadMore($preview->getElementsByTagName('a')->length === 1 && $preview->getElementsByTagName('strong')->length === 1 && $preview->getElementsByTagName('em')->length === 1, 'Prose previews must preserve their visible link and semantic emphasis.');
checkReadMore(!str_contains($preview->textContent, 'Later hidden link') && mb_strlen($preview->textContent) <= 321, 'Prose preview DOM must omit later focusable links and stay bounded.');
checkReadMore($xpath->query('//*[@id="synthetic-paragraph"]')->length === 1, 'Rich prose excerpts must not duplicate source IDs.');
$widget = '<script>syntheticPreviewCode()</script><form><button>Hidden widget</button></form><p>Visible context '.str_repeat('synthetic words ', 60).'</p>';
$xpath = readMoreDocument(tadlDetailExpandableValue($widget, 'Description'));
$preview = $xpath->query('//div[contains(@class,"tadl-long-field-preview--rich")]')->item(0);
checkReadMore($preview->getElementsByTagName('script')->length === 0 && $preview->getElementsByTagName('form')->length === 0 && $preview->getElementsByTagName('button')->length === 0, 'Excerpts must not copy executable nodes or active widgets.');
$literalRows = $rows; $literalRows[0] = '<a href="/synthetic/literal">&lt;Synthetic&gt; &amp; literal</a>';
$xpath = readMoreDocument(tadlDetailExpandableValue(join('<br/>', $literalRows), 'Creators', true));
$preview = $xpath->query('//div[contains(@class,"tadl-long-field-preview--rich")]')->item(0);
checkReadMore($preview->getElementsByTagName('a')->item(0)->textContent === '<Synthetic> & literal', 'Literal angle brackets and entities in link labels must survive DOM excerpting.');
$linkedProse = '<a href="/synthetic/guide">Synthetic guide</a> '.str_repeat('Long narrative context ', 40).'<br/>'.str_repeat('Another narrative paragraph ', 30).'<br/>Final paragraph.';
checkReadMore(str_contains(tadlDetailExpandableValue($linkedProse, 'Description'), 'tadl-long-field-details'), 'Linked prose with line breaks must still get a bounded excerpt rather than become an uncollapsed association list.');

function caDetailLink($request, $text, $class, $table, $id) { return '<a href="/synthetic/record">'.$text.'</a>'; }
$galleryView = new class($request, $linked) {
	public $request; private $item;
	function __construct($request, $linked) {
		$this->request = $request;
		$this->item = new ReadMoreItem(['<unit relativeTo="ca_entities" delimiter="<br/>"><l>^ca_entities.preferred_labels.displayname</l> (^relationship_typename)</unit>' => $linked]);
	}
	function getVar($name) { return $name === 'object' ? $this->item : ($name === 'label' ? 'Synthetic gallery record' : 42); }
	function render() { ob_start(); include dirname(__DIR__).'/views/Gallery/set_item_info_html.php'; return ob_get_clean(); }
};
$galleryXPath = readMoreDocument($galleryView->render());
checkReadMore($galleryXPath->query('//div[contains(@class,"tadl-long-field-preview--rich")]/a')->length === 5, 'Actual gallery metadata must get the same linked-entry repair.');

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
