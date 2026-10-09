<?php
/** Real theme FAQ loader/view with synthetic native Site Page ORM and optional real purifier. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	if ($severity === E_DEPRECATED && getenv('TADL_TEST_COMPOSER_AUTOLOAD') && str_contains($file, '/vendor/')) { return true; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$assertions = 0;
function checkFAQ($condition, $message) { $GLOBALS['assertions']++; if (!$condition) { throw new RuntimeException($message); } }
function _t($text) { return $text; }
class FAQPage {
	function __construct(public int $id, public array $values, public bool $readable = true) {}
	function getPrimaryKey() { return $this->id; }
	function get($field) { return $this->values[$field] ?? null; }
	function isReadable($request, $bundle = null) { checkFAQ($bundle === 'ca_site_pages_content', 'FAQ must respect content bundle permissions.'); return $this->readable; }
}
class FAQTemplates {
	function find($criteria, $options) {
		checkFAQ($criteria === ['template_code' => 'faq_entry', 'deleted' => 0] && $options['returnAs'] === 'firstModelInstance', 'FAQ template lookup changed.');
		return $GLOBALS['faqTemplate'];
	}
}
class FAQPages {
	function find($criteria, $options) {
		checkFAQ($criteria === ['template_id' => 7, 'access' => 1, 'deleted' => 0] && $options === ['returnAs' => 'modelInstances', 'sort' => 'rank', 'checkAccess' => [1]], 'FAQ query must fetch only published entries with deterministic native rank ordering.');
		return $GLOBALS['faqPages'];
	}
}
class Datamodel { static function getInstance($table, $initialize) { return $table === 'ca_site_templates' ? new FAQTemplates() : new FAQPages(); } }
class ca_locales { static function getDefaultCataloguingLocaleID() { return 1; } }
if ($autoload = getenv('TADL_TEST_COMPOSER_AUTOLOAD')) { require $autoload; }
if (!class_exists('HTMLPurifier_Config')) {
	class HTMLPurifier_Config {
		private array $settings = ['HTML.ForbiddenAttributes' => []];
		static function createDefault() { return new self(); }
		static function inherit($config) { return clone $config; }
		function get($key) { return $this->settings[$key] ?? null; }
		function set($key, $value) { $this->settings[$key] = $value; }
	}
}
class FAQPurifier {
	function __construct(public HTMLPurifier_Config $config) {}
	function purify($html, $config) {
		checkFAQ($config->get('HTML.ForbiddenAttributes') === ['title' => true, 'style' => true], 'FAQ must extend native forbidden attributes with inline styles.');
		checkFAQ($config->get('URI.DisableExternalResources') === true, 'FAQ must preserve native URI restrictions.');
		$html = preg_replace('~<script\b[^>]*>.*?</script>~is', '', $html);
		return preg_replace('~\s+(?:style|title)="[^"]*"~i', '', $html);
	}
}
function caGetHTMLPurifier() {
	$config = HTMLPurifier_Config::createDefault();
	$config->set('Cache.DefinitionImpl', null);
	$config->set('URI.DisableExternalResources', true);
	$config->set('HTML.ForbiddenAttributes', ['title' => true]);
	$purifier = class_exists('HTMLPurifier') ? new HTMLPurifier($config) : new FAQPurifier($config);
	$GLOBALS['lastFAQPurifier'] = $purifier;
	return $purifier;
}
require dirname(__DIR__).'/helpers/home_faq.php';
class FAQView {
	public object $request;
	function __construct() { $this->request = new stdClass(); }
	function render() { ob_start(); include dirname(__DIR__).'/views/Front/faq_html.php'; return ob_get_clean(); }
}
$GLOBALS['faqTemplate'] = null;
checkFAQ((new FAQView())->render() === '', 'Unconfigured FAQ must not show empty headings or placeholders.');
$GLOBALS['faqTemplate'] = new FAQPage(7, []);
$base = ['access' => 1, 'deleted' => 0, 'rank' => 10, 'locale_id' => null, 'content' => ['faq_category' => 'Accessing', 'faq_question' => 'Synthetic question?', 'faq_answer' => '<p>Synthetic answer.</p>']];
$GLOBALS['faqPages'] = [];
checkFAQ((new FAQView())->render() === '', 'Empty FAQ must stay hidden.');
$GLOBALS['faqPages'] = [
	new FAQPage(12, array_replace($base, ['rank' => 20, 'content' => ['faq_category' => 'Use & citations', 'faq_question' => 'Synthetic second category?', 'faq_answer' => '<p>Answer with <a href="https://example.org/guide">a link</a>.</p>']])),
	new FAQPage(2, $base),
	new FAQPage(1, array_replace($base, ['access' => 0])),
	new FAQPage(3, array_replace($base, ['deleted' => 1])),
	new FAQPage(4, $base, false),
	new FAQPage(5, array_replace($base, ['locale_id' => 2])),
	new FAQPage(6, array_replace($base, ['content' => 'invalid JSON data'])),
	new FAQPage(7, array_replace($base, ['content' => ['faq_question' => '', 'faq_answer' => 'Answer']])),
	new FAQPage(8, array_replace($base, ['content' => ['faq_question' => 'Empty answer?', 'faq_answer' => '<p>&nbsp;</p>']])),
	new FAQPage(9, array_replace($base, ['content' => ['faq_category' => '<script>category</script>', 'faq_question' => '<img onerror="bad()"> Synthetic <question>', 'faq_answer' => '<script>bad()</script><p>Safe answer.</p>']])),
];
$view = new FAQView(); $html = $view->render();
$document = new DOMDocument(); $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($document);
checkFAQ($xpath->query('//details')->length === 3, 'Private/deleted/restricted/foreign-locale/incomplete FAQ entries must not render.');
checkFAQ($xpath->query('//script|//img')->length === 0 && str_contains($html, '&lt;question&gt;'), 'FAQ labels must escape HTML and answers must pass through the purifier.');
checkFAQ($xpath->query('//a[@href="https://example.org/guide"]')->length === 1, 'Safe answer links should survive purification.');
checkFAQ($xpath->query('//details/summary')->item(0)->textContent === 'Synthetic question?', 'FAQ rank/page-ID ordering changed.');
$g_ui_locale_id = 2;
$groups = tadlHomeFAQGroups($view->request);
checkFAQ(count($groups['Accessing']) === 2, 'FAQ locale filtering must use the active UI locale.');
$g_ui_locale_id = null;
$styledAnswer = '<h3 style="color:#222">Synthetic heading</h3><p style="font-family:Arial;font-size:12px"><span style="color:#222;background-color:white">Café &amp; text with <a href="https://example.org/guide?a=1&amp;b=2" style="color:#222;text-decoration:none" title="Hidden native attribute"><span style="color:#222;font-family:Arial">a nested link</span></a>, <strong>bold</strong> and <em>italic</em>.</span></p><ul><li>First item</li><li>Second item</li></ul><blockquote><p>A quotation.</p></blockquote><p>Literal &lt;example&gt; and <a href="mailto:alice@example.com">email</a>.</p>';
$GLOBALS['faqPages'] = [new FAQPage(11, array_replace($base, ['content' => ['faq_question' => 'Styled answer?', 'faq_answer' => $styledAnswer]]))];
$html = $view->render();
$document = new DOMDocument(); $document->loadHTML('<meta charset="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($document);
checkFAQ($xpath->query('//*[@style or @title]')->length === 0, 'FAQ rendering must strip inline styles and preserve native forbidden attributes.');
checkFAQ($xpath->query('//a[@href="https://example.org/guide?a=1&b=2"]/span')->length === 1, 'Nested link text and query parameters must survive style removal.');
checkFAQ($xpath->query('//a[@href="mailto:alice@example.com"]')->length === 1, 'Email links must remain usable.');
checkFAQ($xpath->query('//strong|//em|//ul/li|//blockquote|//div[@class="tadl-faq-answer"]/h3')->length === 6, 'Semantic emphasis, lists, quotations and answer headings must remain.');
checkFAQ(str_contains($document->textContent, 'Café & text') && str_contains($document->textContent, 'Literal <example>'), 'Unicode and escaped literal markup must remain text.');
checkFAQ($GLOBALS['faqPages'][0]->values['content']['faq_answer'] === $styledAnswer, 'Rendering must not modify stored answers.');
checkFAQ($GLOBALS['lastFAQPurifier']->config->get('HTML.ForbiddenAttributes') === ['title' => true], 'FAQ-specific restrictions must not mutate native purifier configuration.');
if (class_exists('HTMLPurifier')) {
	$GLOBALS['faqPages'] = [new FAQPage(11, array_replace($base, ['content' => ['faq_question' => 'Unsafe links?', 'faq_answer' => '<p onclick="bad()">Answer <a href="javascript:bad()">unsafe link</a><iframe src="https://example.org/"></iframe></p>']]))];
	$html = $view->render();
	checkFAQ(!str_contains($html, 'javascript:') && !str_contains($html, 'onclick') && !str_contains($html, '<iframe'), 'Real purifier must remove active content and unsafe links.');
}
checkFAQ(str_contains(file_get_contents(dirname(__DIR__).'/views/Front/front_page_html.php'), "render('Front/faq_html.php')"), 'FAQ partial must be wired into the home page.');
echo 'Home FAQ passed: '.$assertions.' assertions (synthetic ORM; '.(class_exists('HTMLPurifier') ? 'real HTMLPurifier' : 'synthetic purifier boundary').').'.PHP_EOL;
