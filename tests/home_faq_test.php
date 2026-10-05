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
class FAQPurifier {
	function purify($html) { return preg_replace('~<script\b[^>]*>.*?</script>~is', '', $html); }
}
if ($autoload = getenv('TADL_TEST_COMPOSER_AUTOLOAD')) { require $autoload; }
function caGetHTMLPurifier() {
	if (class_exists('HTMLPurifier')) {
		$config = HTMLPurifier_Config::createDefault(); $config->set('Cache.DefinitionImpl', null);
		return new HTMLPurifier($config);
	}
	return new FAQPurifier();
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
if (class_exists('HTMLPurifier')) {
	$GLOBALS['faqPages'] = [new FAQPage(11, array_replace($base, ['content' => ['faq_question' => 'Unsafe links?', 'faq_answer' => '<p onclick="bad()">Answer <a href="javascript:bad()">unsafe link</a><iframe src="https://example.org/"></iframe></p>']]))];
	$html = $view->render();
	checkFAQ(!str_contains($html, 'javascript:') && !str_contains($html, 'onclick') && !str_contains($html, '<iframe'), 'Real purifier must remove active content and unsafe links.');
}
checkFAQ(str_contains(file_get_contents(dirname(__DIR__).'/views/Front/front_page_html.php'), "render('Front/faq_html.php')"), 'FAQ partial must be wired into the home page.');
echo 'Home FAQ passed: '.$assertions.' assertions (synthetic ORM; '.(class_exists('HTMLPurifier') ? 'real HTMLPurifier' : 'synthetic purifier boundary').').'.PHP_EOL;
