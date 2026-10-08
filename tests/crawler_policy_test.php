<?php
/** Synthetic crawler-policy boundaries; no catalogue, credentials or external requests. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
require dirname(__DIR__).'/helpers/crawler_policy.php';
$assertions = 0;
function crawlerCheck($condition, $message) {
	$GLOBALS['assertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}

$request = new class {
	public string $controller;
	function getController() { return $this->controller; }
};
foreach (['Search', 'MultiSearch', 'Browse', 'CollectionContents', 'search'] as $controller) {
	$request->controller = $controller;
	crawlerCheck(tadlCrawlerPageDirectives($request) === 'noindex, follow', $controller.': results must not be indexed, but public record links remain discoverable.');
}
foreach (['LoginReg', 'Lightbox', 'AccountProfile', 'MediaPreference'] as $controller) {
	$request->controller = $controller;
	crawlerCheck(tadlCrawlerPageDirectives($request) === 'noindex, nofollow', $controller.': account page policy is missing.');
}
foreach (['Front', 'Detail', 'Collections', 'Gallery', 'About', 'Contact', 'Page', ''] as $controller) {
	$request->controller = $controller;
	crawlerCheck(tadlCrawlerPageDirectives($request) === '', $controller.': public content must not be deindexed.');
}
$header = file_get_contents(dirname(__DIR__).'/views/pageFormat/pageHeader.php');
crawlerCheck(str_contains($header, "tadlCrawlerPageDirectives(\$this->request)") && str_contains($header, "MetaTagManager::addMeta('robots', \$crawler_directives)"), 'Shared header must apply the scoped policy.');

// Quoted callback text must not be mistaken for attributes or a tag boundary.
$links = [
	'<a href="/Detail/DownloadRepresentation/id/42" onclick="if (1 > 0) nativeDownload(); return false;">Download</a>',
	"<a href='/original' rel='noopener noreferrer' data-rel='unchanged'>PDF</a>",
	'<a href="/original" REL = "NoFollow noopener">TIFF</a>',
	'<a href="/original" rel=noopener onclick="var rel=\'original\';">Video</a>',
	'<a href="#" rel id="caMediaDownloadFormButton">Submit</a>',
	'<a href="/original" title=" rel=\'leave me alone\'" rel="noopener&#32;noreferrer">File</a>'
];
foreach ($links as $link) {
	$document = new DOMDocument();
	$document->loadHTML(tadlNofollowMediaLinks($link), LIBXML_NOERROR | LIBXML_NOWARNING);
	$anchor = $document->getElementsByTagName('a')->item(0);
	$tokens = preg_split('/\s+/', strtolower($anchor->getAttribute('rel')));
	crawlerCheck(count(array_filter($tokens, static fn($token) => $token === 'nofollow')) === 1, 'Download must have exactly one nofollow token.');
	$original = new DOMDocument(); $original->loadHTML($link, LIBXML_NOERROR | LIBXML_NOWARNING);
	$originalAnchor = $original->getElementsByTagName('a')->item(0);
	foreach (['href', 'onclick', 'title', 'data-rel', 'id'] as $attribute) {
		crawlerCheck($anchor->getAttribute($attribute) === $originalAnchor->getAttribute($attribute), 'Media control '.$attribute.' changed.');
	}
	foreach (preg_split('/\s+/', strtolower(trim($originalAnchor->getAttribute('rel'))), -1, PREG_SPLIT_NO_EMPTY) as $token) {
		crawlerCheck(in_array($token, $tokens, true), 'Existing rel token was removed.');
	}
	crawlerCheck($anchor->textContent === $originalAnchor->textContent, 'Control label changed.');
	crawlerCheck(tadlNofollowMediaLinks(tadlNofollowMediaLinks($link)) === tadlNofollowMediaLinks($link), 'Rel updates must be idempotent.');
}
$notLink = '<form action="/Detail/DownloadMedia"><button title="download > image">Download</button></form>';
crawlerCheck(tadlNofollowMediaLinks($notLink) === $notLink, 'Forms or non-link controls were modified.');
crawlerCheck(tadlNofollowMediaLinks(null) === '', 'Empty native viewer controls must remain empty.');

// Match the actual root policy using case-sensitive robots prefix/wildcard rules.
$policy = file_get_contents(dirname(__DIR__).'/support/site-root/robots.txt');
preg_match_all('/^Disallow:\s*(\S+)/m', $policy, $matches);
$patterns = $matches[1];
$blocked = static function ($url) use ($patterns) {
	foreach ($patterns as $pattern) {
		$regex = str_replace('\\*', '.*', preg_quote($pattern, '~'));
		if (preg_match('~^'.$regex.'~', $url)) { return true; }
	}
	return false;
};
foreach (['', '/index.php'] as $prefix) {
	foreach ([
		'/Search', '/Search/objects/search/forest/view/images', '/Search/advanced', '/MultiSearch/Index?search=forest',
		'/CollectionContents/Objects/collection_id/42',
		'/Browse/people', '/Browse/places/facet/place_facet/id/42', '/Browse/objects/view/pdf/download/1',
		'/ImageDownload/Download/object_id/42/representation_id/101/format/jpg',
		'/CollectionFindingAid/Download/collection_id/42', '/FindingAid/Download/collection_id/42',
		'/Detail/DownloadRepresentation/object_id/42', '/Detail/DownloadMedia/object_id/42',
		'/Detail/DownloadAttributeFile/value_id/42', '/Detail/DownloadAttributeMedia/value_id/42',
		'/Detail/objects/DownloadRepresentation/id/42', '/Detail/collections/downloadSummary/collection_id/42',
		'/Detail/downloadSummary/collection_id/42', '/Detail/GetRepresentationInfo/id/42',
		'/Detail/GetMediaOverlay/id/42', '/Detail/GetMediaData/id/42', '/Detail/objects/GetRepresentationInfo/id/42',
		'/Detail/SearchWithinMedia/id/42', '/Detail/ViewerHelp',
		'/LoginReg/LoginForm', '/Lightbox/Index', '/AccountProfile/Save', '/MediaPreference/Set'
	] as $path) { crawlerCheck($blocked($prefix.$path), 'Uncovered dynamic/download path: '.$prefix.$path); }
	foreach ([
		'/', '/Front/Index', '/Detail/objects/42', '/Detail/collections/42/s/9', '/Detail/entities/42',
		'/Detail/places/42', '/Detail/occurrences/42', '/Collections/Index', '/Gallery/Index',
		'/Gallery/42', '/About/Index', '/Page/faq'
	] as $path) { crawlerCheck(!$blocked($prefix.$path), 'Public archive path must remain crawlable: '.$prefix.$path); }
}
foreach (['/media/collectiveaccess/images/0/preview.jpg', '/media/collectiveaccess/tilepics/0/image.tpc',
	'/themes/tadl/assets/pawtucket/css/theme.css?v=synthetic', '/themes/tadl/assets/pawtucket/js/image-downloads.js',
	'/robots.txt', '/favicon.ico', '/tilepic.php?image=synthetic'] as $path) {
	crawlerCheck(!$blocked($path), 'Preview/asset must remain crawlable: '.$path);
}
crawlerCheck(!preg_match('/^Noindex:/mi', $policy), 'Robots policy must not use unsupported Noindex rules.');
crawlerCheck(count(array_unique($patterns)) === count($patterns), 'Duplicate robots directives.');

// Exercise real HTTP response headers, including views that stream and exit().
$directory = sys_get_temp_dir().'/tadl-crawler-test-'.bin2hex(random_bytes(8));
mkdir($directory, 0700);
$bytes = "Synthetic download bytes\x00\x01\xff";
file_put_contents($directory.'/fixture.bin', $bytes);
$theme = dirname(__DIR__);
$router = '<?php $theme = '.var_export($theme, true).';'. <<<'PHP'

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
function _t($text) { return $text; }
class ApplicationException extends Exception {}
$views = [
 'image' => 'Details/image_download_binary.php', 'finding' => 'Details/finding_aid_binary.php',
 'representation' => 'Details/object_representation_download_binary.php',
 'archive' => 'Details/download_file_binary.php', 'attribute' => 'bundles/download_file_binary.php'
];
$route = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
if (!isset($views[$route])) { http_response_code(404); exit; }
$view = new class {
 function getVar($key) {
  $path = __DIR__.'/fixture.bin';
  return match ($key) {
   'image_download' => ['path' => $path, 'mime' => 'image/jpeg'],
   'image_download_name' => 'synthetic.jpg', 'finding_aid_bytes' => file_get_contents($path),
   'finding_aid_name' => 'synthetic.pdf', 'version' => 'original', 'version_path', 'archive_path' => $path,
   'version_download_name', 'archive_name' => 'synthetic.bin',
   'zip_stream' => isset($_GET['zip']) ? new class { function stream() { print file_get_contents(__DIR__.'/fixture.bin'); } } : null,
   default => null
  };
 }
};
// Native file views flush their output buffer while streaming.
ob_start();
(function ($file) { include $file; })->call($view, $theme.'/views/'.$views[$route]);
PHP;
file_put_contents($directory.'/router.php', $router);
$process = null;
try {
	$socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
	crawlerCheck(is_resource($socket), 'Could not allocate a local HTTP test port.');
	$address = stream_socket_get_name($socket, false); fclose($socket);
	$process = proc_open([PHP_BINARY, '-S', $address, $directory.'/router.php'],
		[0 => ['pipe', 'r'], 1 => ['file', $directory.'/server.log', 'a'], 2 => ['file', $directory.'/server.log', 'a']], $pipes);
	crawlerCheck(is_resource($process), 'Could not start a local PHP HTTP server.');
	fclose($pipes[0]);
	$ready = false;
	for ($attempt = 0; $attempt < 60; $attempt++) {
		$connection = @stream_socket_client('tcp://'.$address, $errorCode, $errorMessage, 0.1);
		if ($connection) { fclose($connection); $ready = true; break; }
		usleep(50000);
	}
	crawlerCheck($ready, 'Local HTTP server did not become ready.');
	foreach (['image', 'finding', 'representation', 'archive', 'attribute', 'archive?zip=1', 'attribute?zip=1'] as $route) {
		$connection = stream_socket_client('tcp://'.$address, $errorCode, $errorMessage, 3);
		stream_set_timeout($connection, 3);
		fwrite($connection, "GET /".$route." HTTP/1.0\r\nHost: localhost\r\nConnection: close\r\n\r\n");
		$response = stream_get_contents($connection); fclose($connection);
		[$headers, $body] = explode("\r\n\r\n", $response, 2);
		crawlerCheck(str_starts_with($headers, 'HTTP/1.0 200'), $route.': response failed: '.$response);
		crawlerCheck(str_contains($headers, 'X-Robots-Tag: noindex, nofollow'), $route.': noindex response header is missing.');
		crawlerCheck(str_contains($headers, 'Content-Disposition: attachment; filename='), $route.': existing attachment header was lost.');
		crawlerCheck(stripos($headers, 'Cache-Control:') !== false, $route.': existing cache policy was lost.');
		crawlerCheck($body === $bytes, $route.': download bytes were altered.');
	}
} finally {
	if (is_resource($process)) { proc_terminate($process); proc_close($process); }
	foreach (glob($directory.'/*') as $file) { unlink($file); }
	rmdir($directory);
}
print 'Crawler policy: '.$assertions." assertions passed.\n";
