<?php
/** Safe archival emphasis through both actual finding-aid templates; synthetic data only. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
require dirname(__DIR__).'/helpers/finding_aid.php';
function _t($text) { return $text; }
$checks = 0;
function formatCheck($condition, $message) {
	$GLOBALS['checks']++;
	if (!$condition) { throw new RuntimeException($message); }
}
$description = tadlFindingAidFieldText('<p><strong>Abstract</strong></p><p>Synthetic collection description &amp; context.</p><p><b>History</b></p><p>Recorded <i>historical context</i> for a synthetic collection.</p>');
$scope = tadlFindingAidFieldText('<p><span style="font-weight: bold; color: red">Advertisements</span></p><p>Synthetic printed advertisements.</p><p><span style="font-weight:700">Early History</span></p><p>Synthetic records of early community life.</p>');
formatCheck(str_contains($description['html'] ?? '', '<strong>Abstract</strong>') && str_contains($description['html'], '<strong>History</strong>') && str_contains($description['html'], '<em>historical context</em>'), 'Semantic bold/italic must survive without altering the plain-text value.');
formatCheck(str_contains($scope['html'] ?? '', '<strong>Advertisements</strong>') && str_contains($scope['html'], '<strong>Early History</strong>') && !str_contains($scope['html'], 'style='), 'Editor span styles must become semantic emphasis without retaining CSS.');
foreach ([$description, $scope] as $field) {
	formatCheck(tadlFindingAidText($field['html']) === $field['value'], 'Formatting must preserve every character and paragraph boundary.');
}
foreach ([
	'<p>First &amp; second.</p>', 'Literal < 3 and > 2', '&lt;strong&gt;Literal tag&lt;/strong&gt;', '<p><strong>&nbsp;</strong></p>'
] as $raw) {
	formatCheck(!isset(tadlFindingAidFieldText($raw)['html']), 'Plain or empty text must not acquire rich markup or a duplicate payload.');
}
$spaced = tadlFindingAidFieldText("<p><b>Abstract</b></p>\r\n\r\n<p><strong>&nbsp;</strong></p>\r\n<p><br></p>\r\n<p>Next paragraph.</p>");
formatCheck($spaced['html'] === "<strong>Abstract</strong>\n\nNext paragraph.", 'Empty editor blocks and CRLF must not reintroduce large paragraph gaps.');
foreach ([
	'<p><b onclick="alert(1)">Safe</b><script>secret()</script><style>body{display:none}</style></p>',
	'<strong><a href="javascript:alert(1)">Safe link text</a><img src="https://example.org/pixel" onerror="alert(1)"></strong>',
	'<p style="font-weight:900;font-style:italic;background:url(https://example.org/pixel)">Styled text</p>',
	'<b>Unicode café &amp; literal &lt;img src=x&gt;.</b>',
	'<strong>Safe</strong><svg><script>alert(1)</script></svg>',
	'<strong><em>Nested emphasis</strong> after malformed nesting.'
] as $raw) {
	$field = tadlFindingAidFieldText($raw);
	$html = tadlFindingAidFieldHTML($field);
	formatCheck(strpbrk(preg_replace('~<(?:/?strong|/?em|br)>~', '', $html), '<>') === false, 'Only canonical emphasis and line breaks may reach the document.');
	$document = new DOMDocument(); $document->loadHTML('<html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	$xpath = new DOMXPath($document);
	formatCheck($xpath->query('//*[@*]|//script|//style|//img|//svg|//iframe|//object|//a')->length === 0, 'Imported handlers, attributes, links and resources must never become active markup.');
	formatCheck(tadlFindingAidText($html) === $field['value'], 'Sanitizing hostile or malformed markup must not lose readable text.');
}
$untrusted = ['value' => '<img src=x onerror=alert(1)>', 'html' => '<strong onclick="alert(1)">Untrusted</strong>'];
formatCheck(tadlFindingAidFieldHTML($untrusted) === '&lt;img src=x onerror=alert(1)&gt;', 'The rendering boundary must reject noncanonical markup even when supplied as an HTML field.');
$plain = tadlFindingAidFieldText('Same text'); $bold = tadlFindingAidFieldText('<b>Same text</b>');
formatCheck($plain['value'] === $bold['value'] && $plain !== $bold, 'Formatting-only edits must change the finding-aid cache input.');
class FormattingAidView {
	function __construct(private array $vars) {}
	function getVar($key) { return $this->vars[$key] ?? null; }
	function render($path) { ob_start(); include dirname(__DIR__).'/views/Details/'.$path; return ob_get_clean(); }
}
$view = new FormattingAidView([
	'finding_aid' => ['title' => 'Synthetic Archives Collection', 'identifier' => 'SYN.42', 'generated' => 'Synthetic date',
		'fields' => ['description' => ['label' => 'Description'] + $description, 'scope' => ['label' => 'Scope and content'] + $scope],
		'collections' => [42 => ['parent_id' => null], 43 => ['parent_id' => 42, 'depth' => 1, 'title' => 'Synthetic folder', 'identifier' => 'SYN.43', 'fields' => ['description' => ['label' => 'Description'] + $description]]]],
	'collection_url' => '/Detail/collections/42', 'download_url' => '/CollectionFindingAid/Download/collection_id/42'
]);
$fixtures = ['pdf_html' => $view->render('finding_aid_pdf_html.php'), 'text_html' => $view->render('finding_aid_html.php')];
foreach ($fixtures as $html) {
	formatCheck(substr_count($html, '<strong>Abstract</strong>') === 2 && substr_count($html, '<strong>History</strong>') === 2, 'Root and descendant descriptions must retain bold labels in each output.');
	formatCheck(str_contains($html, '<strong>Advertisements</strong>') && str_contains($html, '<strong>Early History</strong>'), 'Scope-and-content headings must remain bold in each output.');
}
if (($argv[1] ?? '') === '--fixtures') { echo json_encode($fixtures, JSON_THROW_ON_ERROR); }
else { echo "Finding-aid emphasis passed: {$checks} assertions (safe markup, editor styles, paragraph spacing, root/descendant PDF and HTML).\n"; }
