<?php
/**
 * Run with php tests/object_detail_metadata_test.php (PHP DOM required).
 * Actual theme helper; synthetic loaded-object, bundle ACL and attribute boundaries; no DB.
 */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('__CA_BUNDLE_ACCESS_READONLY__', 1);
$GLOBALS['metadataAssertions'] = 0;
$GLOBALS['metadataBundleAccess'] = __CA_BUNDLE_ACCESS_READONLY__;
$GLOBALS['metadataAccessReads'] = 0;
function checkMetadata($condition, $message) {
	$GLOBALS['metadataAssertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function caGetUserAccessValues($request) {
	$GLOBALS['metadataAccessReads']++;
	return $request->access;
}
function caGetBundleAccessLevel($table, $bundle) {
	checkMetadata($table === 'ca_objects' && $bundle === 'lctgm', 'Bundle permission checked for the wrong field.');
	return $GLOBALS['metadataBundleAccess'];
}
class MetadataRequest {
	public function __construct(public array $access = array(1)) {}
}
class MetadataObject {
	public array $calls = array();
	public function __construct(public $labels, public $ids, private int $objectId = 42) {}
	public function getPrimaryKey() { return $this->objectId; }
	public function get($bundle, $options = array()) {
		$this->calls[] = array('bundle' => $bundle, 'options' => $options);
		$values = ($options['text'] ?? false) ? $this->labels : $this->ids;
		// Loaded BaseModel delegates to SearchResult: returnAsArray flattens values.
		// returnWithStructure preserves [object ID][attribute ID][element code],
		// allowing the label and native `n` term ID to be paired across separate reads.
		if ($options['returnWithStructure'] ?? false) {
			return array($this->objectId => $values, 999 => array(700 => array('lctgm' => 'Unrelated synthetic object')));
		}
		$flat = is_array($values) ? array_map(function ($row) { return $row['lctgm'] ?? ''; }, array_values($values)) : array();
		return ($options['returnAsArray'] ?? false) ? $flat : join('; ', $flat);
	}
}
require getenv('TADL_TEST_DETAIL_HELPERS') ?: dirname(__DIR__).'/views/Details/detail_field_helpers.php';

class MetadataTemplateItem {
	public array $calls = [];
	public function __construct(private array $values) {}
	public function getWithTemplate($template, $options = []) {
		$this->calls[] = ['template' => $template, 'options' => $options];
		return $this->values[$template] ?? '';
	}
}
foreach ([null, '', " \t\n", '<p><br/></p>', '<span>&nbsp;</span>', '&#160;', "\u{00A0}\u{2009}\u{200B}\u{FEFF}"] as $value) {
	foreach (['Date', 'Description', 'Related places'] as $heading) {
		$item = new MetadataTemplateItem(['synthetic-template' => $value]);
		checkMetadata(tadlDetailField(new MetadataRequest(), $item, $heading, 'synthetic-template') === '', $heading.': empty content emitted its heading.');
		checkMetadata($item->calls[0]['options']['checkAccess'] === [1], 'Empty field lost native access filtering.');
	}
}
foreach (['0', '1930', '<p>Synthetic description &amp; context</p>', '<a href="/synthetic/place">Synthetic place</a>'] as $value) {
	$item = new MetadataTemplateItem(['synthetic-template' => $value]);
	$html = tadlDetailField(new MetadataRequest([1, 2]), $item, 'Synthetic field', 'synthetic-template');
	checkMetadata(str_contains($html, '<label>Synthetic field</label>'.$value), 'Populated field lost its heading or native markup.');
	checkMetadata($item->calls[0]['options'] === ['convertCodesToDisplayText' => true, 'checkAccess' => [1, 2]], 'Populated field lost native display/access options.');
}
$item = new MetadataTemplateItem(['blank' => '<p>&nbsp;</p>', 'fallback' => 'Synthetic fallback']);
$html = tadlDetailFirstAvailableField(new MetadataRequest(), $item, 'Description', ['blank', 'fallback']);
checkMetadata(str_contains($html, '<label>Description</label>Synthetic fallback'), 'Blank rich text blocked a populated fallback.');
checkMetadata(count($item->calls) === 2, 'Fallback field did not try both templates.');
checkMetadata(tadlDetailField(new MetadataRequest(), null, 'Date', 'synthetic-template') === '', 'Missing record emitted a field.');

function metadataValues($values) {
	$result = array();
	foreach ($values as $attributeId => $value) { $result[$attributeId] = array('lctgm' => $value); }
	return $result;
}
function metadataDocument($html) {
	$document = new DOMDocument();
	$document->loadHTML('<!doctype html><html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	return $document;
}
function checkMetadataReads($object, $request, $caseName) {
	checkMetadata(count($object->calls) === 2, $caseName.': labels and IDs were not read exactly once each.');
	foreach ($object->calls as $i => $call) {
		$options = $call['options'];
		checkMetadata($call['bundle'] === 'ca_objects.lctgm', $caseName.': wrong attribute bundle.');
		checkMetadata(($options['returnWithStructure'] ?? null) === true, $caseName.': attribute keys were flattened.');
		checkMetadata(($options['checkAccess'] ?? null) === $request->access, $caseName.': request access values were not passed through.');
		checkMetadata(($options[$i === 0 ? 'text' : 'n'] ?? null) === true, $caseName.': native display option was omitted.');
		checkMetadata(!isset($options[$i === 0 ? 'n' : 'text']), $caseName.': text and ID options were mixed.');
		checkMetadata(!isset($options['asHTML']) && !isset($options['returnAsLink']), $caseName.': native untrusted HTML was requested.');
	}
}
function checkMetadataOutput($html, $terms, $heading, $caseName) {
	if (!$terms) {
		checkMetadata($html === '', $caseName.': empty terms emitted a label or wrapper.');
		return;
	}
	$document = metadataDocument($html);
	$xpath = new DOMXPath($document);
	$units = $xpath->query('//body/div[@class="unit"]');
	checkMetadata($units->length === 1, $caseName.': metadata did not render one unit.');
	$unit = $units->item(0);
	$labels = $xpath->query('./label', $unit);
	checkMetadata($labels->length === 1 && $labels->item(0)->textContent === $heading, $caseName.': heading was missing, repeated or escaped incorrectly.');
	checkMetadata($xpath->query('./br', $unit)->length === count($terms) - 1, $caseName.': term separators were incorrect.');
	$expectedLinks = array_values(array_filter($terms, function ($term) { return isset($term['id']); }));
	$links = $xpath->query('./a', $unit);
	checkMetadata($links->length === count($expectedLinks), $caseName.': wrong number of linked terms.');
	foreach ($expectedLinks as $i => $term) {
		checkMetadata($links->item($i)->textContent === $term['text'], $caseName.': linked label was repeated or paired to another attribute.');
		checkMetadata($links->item($i)->getAttribute('href') === 'https://id.loc.gov/vocabulary/graphicMaterials/'.$term['id'], $caseName.': link did not use the canonical TGM ID.');
	}
	$labels->item(0)->parentNode->removeChild($labels->item(0));
	checkMetadata($unit->textContent === join('', array_column($terms, 'text')), $caseName.': labels changed order, repeated, or displayed raw IDs.');
	checkMetadata($xpath->query('//body//script | //body//img | //body//*[@onclick or @onerror or @onload]')->length === 0, $caseName.': label HTML became executable markup.');
	checkMetadata(strpos($html, 'Unrelated synthetic object') === false, $caseName.': another object\'s attribute escaped the native structure boundary.');
}

// Native LCSHAttributeValue::getDisplayValue(text=true) removes the bracketed
// URI; n=true returns its final path component. These fixtures are that output,
// rather than invented raw BaseModel arrays or HTML supplied by asHTML=true.
$cases = array(
	array('name' => 'single native term label rendered once', 'labels' => array(700 => 'Synthetic photographs'), 'ids' => array(700 => 'tgm000001'), 'terms' => array(array('text' => 'Synthetic photographs', 'id' => 'tgm000001'))),
	array('name' => 'two values paired by attribute keys despite reordered IDs', 'labels' => array(702 => 'Synthetic bridges', 701 => 'Synthetic boats'), 'ids' => array(701 => 'tgm000003', 702 => 'tgm000002'), 'terms' => array(array('text' => 'Synthetic bridges', 'id' => 'tgm000002'), array('text' => 'Synthetic boats', 'id' => 'tgm000003'))),
	array('name' => 'multiple sparse attributes preserve display order', 'labels' => array(901 => 'Synthetic posters', 13 => 'Synthetic maps', 407 => 'Synthetic houses'), 'ids' => array(407 => 'tgm000006', 901 => 'tgm000004', 13 => 'tgm000005'), 'terms' => array(array('text' => 'Synthetic posters', 'id' => 'tgm000004'), array('text' => 'Synthetic maps', 'id' => 'tgm000005'), array('text' => 'Synthetic houses', 'id' => 'tgm000006'))),
	array('name' => 'trimmed native values use a canonical link', 'labels' => array(700 => '  Synthetic railroads  '), 'ids' => array(700 => ' tgm000007 '), 'terms' => array(array('text' => 'Synthetic railroads', 'id' => 'tgm000007'))),
	array('name' => 'missing ID retains plain text', 'labels' => array(700 => 'Synthetic unidentified term'), 'ids' => array(), 'terms' => array(array('text' => 'Synthetic unidentified term'))),
	array('name' => 'blank ID retains plain text', 'labels' => array(700 => 'Synthetic unlinked term'), 'ids' => array(700 => '  '), 'terms' => array(array('text' => 'Synthetic unlinked term'))),
	array('name' => 'partial ID map preserves linked and unlinked labels', 'labels' => array(700 => 'Synthetic first', 701 => 'Synthetic middle', 702 => 'Synthetic last'), 'ids' => array(702 => 'tgm000009', 700 => 'tgm000008'), 'terms' => array(array('text' => 'Synthetic first', 'id' => 'tgm000008'), array('text' => 'Synthetic middle'), array('text' => 'Synthetic last', 'id' => 'tgm000009'))),
	array('name' => 'empty native attribute result emits no field', 'labels' => array(), 'ids' => array(), 'terms' => array()),
	array('name' => 'whitespace labels emit no field even with IDs', 'labels' => array(700 => '', 701 => " \t\n "), 'ids' => array(700 => 'tgm000001', 701 => 'tgm000002'), 'terms' => array()),
	array('name' => 'empty repeats are skipped without empty lines', 'labels' => array(700 => '', 701 => 'Synthetic surviving term', 702 => ' '), 'ids' => array(702 => 'tgm000003', 700 => 'tgm000001', 701 => 'tgm000002'), 'terms' => array(array('text' => 'Synthetic surviving term', 'id' => 'tgm000002'))),
	array('name' => 'malformed stored URI cannot override destination', 'labels' => array(700 => 'Synthetic canonical destination'), 'ids' => array(700 => 'tgm000010'), 'terms' => array(array('text' => 'Synthetic canonical destination', 'id' => 'tgm000010'))),
	array('name' => 'HTML and quote characters remain label text', 'labels' => array(700 => '<img src=x onerror="synthetic()"> & "Collector\'s" term', 701 => '<script>synthetic()</script>'), 'ids' => array(701 => 'invalid', 700 => 'tgm000011'), 'heading' => 'Terms & "headings" <synthetic>', 'terms' => array(array('text' => '<img src=x onerror="synthetic()"> & "Collector\'s" term', 'id' => 'tgm000011'), array('text' => '<script>synthetic()</script>'))),
	array('name' => 'request can carry multiple native access values', 'labels' => array(700 => 'Synthetic accessible term'), 'ids' => array(700 => 'tgm000012'), 'access' => array(1, 2), 'terms' => array(array('text' => 'Synthetic accessible term', 'id' => 'tgm000012'))),
	array('name' => 'request access list is not replaced with public defaults', 'labels' => array(700 => 'Synthetic term'), 'ids' => array(700 => 'tgm000013'), 'access' => array(2), 'terms' => array(array('text' => 'Synthetic term', 'id' => 'tgm000013'))),
);
foreach (array('javascript:synthetic()', 'data:text/html,<script>synthetic()</script>', 'https://example.org/tgm000001', '//example.org/tgm000001', 'sh000001', 'TGM000001', 'tgm', 'tgm000001?next=synthetic', 'tgm000001/synthetic', 'tgm000001" onclick="synthetic()') as $invalidId) {
	$cases[] = array('name' => 'invalid or unsafe ID remains plain text: '.$invalidId, 'labels' => array(700 => 'Synthetic plain label'), 'ids' => array(700 => $invalidId), 'terms' => array(array('text' => 'Synthetic plain label')));
}
foreach ($cases as $case) {
	$name = $case['name'];
	$heading = $case['heading'] ?? 'Thesaurus terms';
	$request = new MetadataRequest($case['access'] ?? array(1));
	$object = new MetadataObject(metadataValues($case['labels']), metadataValues($case['ids']));
	$GLOBALS['metadataAccessReads'] = 0;
	$html = tadlObjectThesaurusTerms($request, $object, $heading);
	checkMetadataReads($object, $request, $name);
	checkMetadata($GLOBALS['metadataAccessReads'] === 1, $name.': request access values were read more than once.');
	checkMetadataOutput($html, $case['terms'], $heading, $name);
}

foreach (array(0, -1) as $deniedAccess) {
	$GLOBALS['metadataBundleAccess'] = $deniedAccess;
	$GLOBALS['metadataAccessReads'] = 0;
	$object = new MetadataObject(metadataValues(array(700 => 'Synthetic denied term')), metadataValues(array(700 => 'tgm000001')));
	checkMetadata(tadlObjectThesaurusTerms(new MetadataRequest(), $object) === '', 'Denied bundle rendered attribute values.');
	checkMetadata($object->calls === array(), 'Denied bundle performed an attribute read.');
	checkMetadata($GLOBALS['metadataAccessReads'] === 0, 'Denied bundle requested downstream access values.');
}
$GLOBALS['metadataBundleAccess'] = __CA_BUNDLE_ACCESS_READONLY__;
$unloaded = new MetadataObject(metadataValues(array(700 => 'Synthetic unloaded term')), metadataValues(array(700 => 'tgm000001')), 0);
checkMetadata(tadlObjectThesaurusTerms(new MetadataRequest(), $unloaded) === '', 'Unloaded object rendered attributes.');
checkMetadata($unloaded->calls === array(), 'Unloaded object performed attribute reads.');
checkMetadata(tadlObjectThesaurusTerms(new MetadataRequest(), null) === '', 'Missing object rendered a field.');

// Missing element keys are a valid empty repeat in the structured boundary.
$emptyRepeat = new MetadataObject(array(700 => array()), array(700 => array()));
checkMetadata(tadlObjectThesaurusTerms(new MetadataRequest(), $emptyRepeat) === '', 'Missing element values rendered an empty unit.');
checkMetadataReads($emptyRepeat, new MetadataRequest(), 'missing element values');
echo 'Passed '.$GLOBALS['metadataAssertions']." object detail metadata assertions.\n";
