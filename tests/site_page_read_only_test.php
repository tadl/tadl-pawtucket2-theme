<?php
/** Dispatch real Site Page routes with a counter that rejects writes; no app/database. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$assertions = 0;
function sitePageCheck($condition, $message) {
	$GLOBALS['assertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}
class SitePageRequest {
	function __construct(public array $access = [1]) {}
}
class SitePageResponse {
	public string $content = '';
	public int $status = 200;
	function addContent($value) { $this->content .= $value; }
	function setHTTPResponseCode($status, $message) { $this->status = $status; }
}
class SitePageView {
	public array $vars = [];
	function setVar($name, $value) { $this->vars[$name] = $value; }
}
class BasePawtucketController {
	public $request, $response, $view;
	public array $viewPaths = [];
	public ?string $rendered = null;
	function __construct(&$request, &$response, $paths = null) {
		$this->request = $request; $this->response = $response;
		$this->view = new SitePageView(); $this->viewPaths = $paths ?? [];
	}
	function viewExists($path) { return in_array($path, $this->viewPaths, true); }
	function render($path, $useLayout) { $this->rendered = $path; }
}
class ca_site_pages {
	public static array $pages = [], $requests = [];
	public static int $writes = 0;
	static function renderPageForPath($controller, $path, $options) {
		self::$requests[] = [$path, $options];
		if ($options['incrementViewCount'] ?? false) {
			self::$writes++;
			throw new RuntimeException('Synthetic Site Page row is locked by an import.');
		}
		$page = self::$pages[$path] ?? null;
		return $page && in_array($page['access'], $options['checkAccess'], true) ? $page['content'] : null;
	}
}
function caSetPageCSSClasses($classes) { $GLOBALS['sitePageClasses'] = $classes; }
function caGetUserAccessValues($request) { return $request->access; }
function _t($message) { return $message; }

$directory = sys_get_temp_dir().'/tadl-site-page-read-only-'.bin2hex(random_bytes(8));
foreach (['lib/ApplicationError.php', 'helpers/accessHelpers.php', 'lib/pawtucket/BasePawtucketController.php'] as $file) {
	if (!is_dir(dirname($directory.'/'.$file))) { mkdir(dirname($directory.'/'.$file), 0700, true); }
	file_put_contents($directory.'/'.$file, '<?php');
}
define('__CA_APP_DIR__', $directory); define('__CA_LIB_DIR__', $directory.'/lib');
$theme = dirname(__DIR__);
require $theme.'/controllers/DefaultController.php';
function sitePageDispatch($method, $path = [], $access = [1], $viewPaths = []) {
	ca_site_pages::$requests = [];
	$request = new SitePageRequest($access); $response = new SitePageResponse();
	$controller = new DefaultController($request, $response, $viewPaths);
	$controller->$method($path);
	return [$controller, $response];
}
try {
	$appConfig = file_get_contents($theme.'/conf/app.conf');
	sitePageCheck(preg_match('/^dont_use_search_log\s*=\s*1\s*$/m', $appConfig) === 1, 'Searches must not insert analytics into the catalogue database.');
	sitePageCheck(preg_match('/^always_use_session_based_storage_for_find_result_contexts\s*=\s*1\s*$/m', $appConfig) === 1, 'Signed-in result navigation must use sessions rather than updating ca_users.');
	if ($reference = getenv('TADL_NATIVE_DEFAULT_CONTROLLER')) {
		sitePageCheck(is_file($reference) && is_readable($reference), 'Native parity reference must be readable.');
		$source = str_replace("'incrementViewCount' => false", "'incrementViewCount' => true", file_get_contents($theme.'/controllers/DefaultController.php'), $replacements);
		sitePageCheck($replacements === 2, 'Both native Site Page render paths must disable counters.');
		$tokens = static function ($source) {
			$values = [];
			foreach (token_get_all($source) as $token) {
				if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) { continue; }
				$values[] = $token;
			}
			return array_map(static fn($token) => is_array($token) ? [$token[0], $token[1]] : $token, $values);
		};
		sitePageCheck($tokens($source) === $tokens(file_get_contents($reference)), 'Native dispatch must differ only in disabled view counting.');
	}
	$g_ui_locale_id = 2;
	ca_site_pages::$pages = [
		'/guide' => ['access' => 1, 'content' => '<h1>Synthetic public guide</h1>'],
		'/research/help' => ['access' => 1, 'content' => '<p>Synthetic nested page</p>'],
		'/staff' => ['access' => 0, 'content' => '<p>Synthetic staff page</p>'],
	];
	[$controller, $response] = sitePageDispatch('guide');
	sitePageCheck($response->content === '<h1>Synthetic public guide</h1>' && $response->status === 200, 'Published Site Pages must render without waiting for a counter write.');
	sitePageCheck(ca_site_pages::$requests === [['/guide', ['locale_id' => 2, 'incrementViewCount' => false, 'checkAccess' => [1]]]], 'Primary page lookup must retain the locale/access scope and skip counters.');
	sitePageCheck($controller->view->vars['response'] === $response && $GLOBALS['sitePageClasses'] === ['staticPage'], 'Native view setup must remain intact.');
	[, $response] = sitePageDispatch('research', ['help', '_default']);
	sitePageCheck($response->content === '<p>Synthetic nested page</p>', 'The native _default fallback must continue resolving its parent page.');
	sitePageCheck(ca_site_pages::$requests === [
		['/research/help/_default', ['locale_id' => 2, 'incrementViewCount' => false, 'checkAccess' => [1]]],
		['/research/help', ['incrementViewCount' => false, 'checkAccess' => [1]]],
	], 'Fallback lookup must also skip counters and retain its native access scope.');
	[, $response] = sitePageDispatch('staff');
	sitePageCheck($response->status === 404 && $response->content === 'Page is not available', 'A private page must not become visible to an anonymous visitor.');
	[, $response] = sitePageDispatch('staff', [], [0, 1]);
	sitePageCheck($response->status === 200 && $response->content === '<p>Synthetic staff page</p>', 'Native staff access must remain available without counter updates.');
	[$controller, $response] = sitePageDispatch('About', ['Index'], [1], ['About/Index.php']);
	sitePageCheck($controller->rendered === 'About/Index.php' && $response->status === 200, 'Theme view fallback must remain available when no Site Page matches.');
	[$controller, $response] = sitePageDispatch('missing', ['page']);
	sitePageCheck($controller->rendered === null && $response->status === 404, 'Missing pages must keep native 404 behavior.');
	sitePageCheck(ca_site_pages::$writes === 0, 'All page routes must perform zero catalogue counter writes.');
	echo "Site Page read-only checks passed: {$assertions} assertions (locked counters, locale/access, native fallback and theme views).\n";
} finally {
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
	rmdir($directory);
}
