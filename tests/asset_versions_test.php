<?php
/** Synthetic files and native asset HTML boundaries; no application/database. */
error_reporting(E_ALL);
require_once dirname(__DIR__).'/helpers/asset_versions.php';
$checks = 0;
function assetCheck($condition, $message) {
	global $checks;
	if (!$condition) { throw new RuntimeException($message); }
	$checks++;
}
function assetUrl($html, $tag, $attribute) {
	$document = new DOMDocument();
	$document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	return $document->getElementsByTagName($tag)->item(0)->getAttribute($attribute);
}
class AssetLoadManager {
	static $html;
	static $options;
	static function getLoadHTML($request, $options = null) { self::$options = $options; return self::$html; }
}
class AssetVersionRequest {
	public $directory;
	function getThemeUrlPath() { return '/catalog/themes/tadl'; }
	function getThemeDirectoryPath() { return $this->directory; }
}
$directory = sys_get_temp_dir().'/tadl-asset-test-'.bin2hex(random_bytes(8));
mkdir($directory.'/assets/pawtucket/css', 0700, true);
mkdir($directory.'/assets/pawtucket/js', 0700, true);
$css = $directory.'/assets/pawtucket/css/theme.css';
$js = $directory.'/assets/pawtucket/js/example.js';
$outside = $directory.'/outside.css';
try {
	file_put_contents($css, 'body { color: black; }');
	file_put_contents($js, 'const example = true;');
	file_put_contents($outside, 'private synthetic data');
	symlink($outside, $directory.'/assets/pawtucket/css/outside.css');
	$base = '/catalog/themes/tadl/assets/pawtucket/';
	AssetLoadManager::$html = "<link rel='stylesheet' href='{$base}css/theme.css' type='text/css' media='all'/>\n<script src=\"{$base}js/example.js\" type='text/javascript'></script>";
	$request = new AssetVersionRequest(); $request->directory = $directory;
	$html = tadlAssetLoadHTML($request, ['outputTarget' => 'footer']);
	$css_url = assetUrl($html, 'link', 'href');
	$js_url = assetUrl($html, 'script', 'src');
	assetCheck($css_url === $base.'css/theme.css?v='.substr(hash_file('sha256', $css), 0, 16), 'CSS URL must include its content hash.');
	assetCheck($js_url === $base.'js/example.js?v='.substr(hash_file('sha256', $js), 0, 16), 'JS URL must include its content hash.');
	assetCheck(AssetLoadManager::$options === ['outputTarget' => 'footer'], 'Native load options must pass through.');
	assetCheck(tadlAssetLoadHTML($request) === $html, 'Unchanged files must keep stable URLs.');
	assetCheck(tadlVersionThemeAssets($html, '/catalog/themes/tadl', $directory) === $html, 'Versioning must be idempotent.');
	$mtime = filemtime($css);
	file_put_contents($css, 'body { color: green; }'); touch($css, $mtime);
	$updated = tadlAssetLoadHTML($request);
	assetCheck(assetUrl($updated, 'link', 'href') !== $css_url, 'Changed CSS must change URLs even with preserved modification times.');
	assetCheck(assetUrl($updated, 'script', 'src') === $js_url, 'Unchanged JS must retain its URL when CSS changes.');
	touch($css, $mtime + 10);
	assetCheck(tadlAssetLoadHTML($request) === $updated, 'Timestamp-only changes must not evict unchanged contents.');
	file_put_contents($js, 'const example = false;');
	assetCheck(assetUrl(tadlAssetLoadHTML($request), 'script', 'src') !== $js_url, 'Changed JS must change URLs.');
	$input = "<link href='{$base}css/theme.css?rev=release&amp;v=old&amp;flag=1#styles'>";
	$output = tadlVersionThemeAssets($input, '/catalog/themes/tadl', $directory);
	$url = assetUrl($output, 'link', 'href');
	assetCheck(strpos($url, '?rev=release&flag=1&v=') !== false && substr($url, -7) === '#styles', 'Preserve existing parameters and fragment, replacing the old version.');
	assetCheck(strpos($output, '&amp;') !== false, 'Version URLs must be attribute-escaped.');
	$inline = "<script>const example = `<script src=\"{$base}js/example.js\">`; const href = \"{$base}css/theme.css\";</script>";
	foreach ([
		"<link href='https://example.com{$base}css/theme.css'>",
		"<link href='/assets/app.css'>",
		"<link href='/catalog/themes/other/assets/pawtucket/css/theme.css'>",
		"<link href='{$base}graphics/logo.svg'>",
		"<link href='{$base}css/missing.css'>",
		"<link href='{$base}css/outside.css'>",
		"<link href='{$base}css/../../../outside.css'>",
		"<link href='{$base}css/%2e%2e/outside.css'>",
		"<link data-href='{$base}css/theme.css'>",
		"<!-- <link href='{$base}css/theme.css'> -->",
		$inline,
		"<style>/* <link href='{$base}css/theme.css'> */</style>"
	] as $unchanged) {
		assetCheck(tadlVersionThemeAssets($unchanged, '/catalog/themes/tadl', $directory) === $unchanged, 'Unrelated, unsafe, missing, or inline URLs must remain untouched.');
	}
	$script = "<script src='{$base}js/example.js'>const literal = ` src=\"{$base}js/example.js\"`;</script>";
	$output = tadlVersionThemeAssets($script, '/catalog/themes/tadl', $directory);
	assetCheck(strpos($output, "const literal = ` src=\"{$base}js/example.js\"`;") !== false, 'External script body must remain byte-for-byte intact.');
	assetCheck(tadlVersionThemeAssets($input, '/catalog/themes/tadl', $directory.'/missing') === $input, 'Missing asset directory must preserve native HTML.');
	$absolute = tadlVersionThemeAssets("<link href='https://static.example/themes/tadl/assets/pawtucket/css/theme.css'>", 'https://static.example/themes/tadl', $directory);
	assetCheck(strpos(assetUrl($absolute, 'link', 'href'), '?v=') !== false, 'Configured absolute theme URLs must work.');
	foreach (['views/pageFormat/pageHeader.php', 'views/Lightbox/present_html.php'] as $view) {
		$source = file_get_contents(dirname(__DIR__).'/'.$view);
		assetCheck(strpos($source, 'tadlAssetLoadHTML($this->request)') !== false && strpos($source, '/helpers/asset_versions.php') !== false, 'Every theme document must use the versioned loader.');
	}
	echo "Asset versions passed: {$checks} assertions.\n";
} finally {
	foreach ([$css, $js, $outside, $directory.'/assets/pawtucket/css/outside.css'] as $file) { unlink($file); }
	foreach (['/assets/pawtucket/css', '/assets/pawtucket/js', '/assets/pawtucket', '/assets', ''] as $path) { rmdir($directory.$path); }
}
