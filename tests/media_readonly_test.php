<?php
/** Execute native-compatible download paths against synthetic records and files, without a database. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('pString', 1); define('pInteger', 2);
$checks = 0;
function mediaReadCheck($condition, $message) {
	$GLOBALS['checks']++;
	if (!$condition) { throw new RuntimeException($message); }
}
class MediaReadConfig {
	function get($name) { return null; }
	function getAssoc($name) { return ['objects' => ['table' => 'ca_objects']]; }
}
class MediaReadRequest {
	public $config;
	public $allowed = true, $versions = ['original'];
	function __construct(public array $params, public bool $loggedIn) { $this->config = new MediaReadConfig(); }
	function isLoggedIn() { return $this->loggedIn; }
	function getParameter($name, $type) { return $this->params[$name] ?? null; }
}
class MediaReadResponse {}
class MediaReadView {
	public $vars = [];
	function setVar($name, $value) { $this->vars[$name] = $value; }
	function getVar($name) { return $this->vars[$name] ?? null; }
}
class FindController {
	public $request, $response, $view;
	public $rendered = null, $error = null;
	protected $opa_access_values = [1];
	function __construct(&$request, &$response, $paths = null) { $this->request = $request; $this->response = $response; $this->view = new MediaReadView(); }
	function render($path, $direct = false) { $this->rendered = $path; return true; }
	function postError($code, $message, $context) { $this->error = $message; }
}
class Downloadlog {
	function __construct() { throw new RuntimeException('Public downloads must never create a database logger.'); }
}
class ca_objects {
	static public $children = [], $access = 1;
	function __construct(public $id = null) {}
	function load($id) { $this->id = $id; return true; }
	function isLoaded() { return (bool)$this->id; }
	function getPrimaryKey() { return $this->id; }
	function get($field, $options = []) { return ['access' => self::$access, 'idno' => 'SYN-'.$this->id, 'ca_objects.children.object_id' => self::$children][$field] ?? null; }
	function getRepresentations($versions, $sizes, $options) {
		mediaReadCheck($versions === ['original'] && $options === ['checkAccess' => [1]], 'ZIP must preserve native version/access selection.');
		$id = $this->id + 100;
		return [$id => ['representation_id' => $id, 'info' => ['original' => ['EXTENSION' => 'pdf'], 'original_filename' => 'synthetic.pdf']]];
	}
	function getHierarchyAncestors($id, $options) { return [$this->id]; }
}
class ca_object_representations {
	static public $access = 1;
	function __construct(public $id) {}
	function get($name) { return $name === 'access' ? self::$access : null; }
	function getMediaPath($field, $version) { return $GLOBALS['mediaReadDirectory'].'/original-'.$this->id.'.pdf'; }
	function getMediaVersions($field) { return ['original']; }
	function getMediaInfo($field, $version = null, $key = null) {
		if ($key === 'MIMETYPE') { return 'application/pdf'; }
		return ['EXTENSION' => 'pdf', 'ORIGINAL_FILENAME' => 'synthetic.pdf'];
	}
}
class Datamodel { static function getInstance($table, $cached = false) { return new $table(); } }
class ApplicationPluginManager {
	function hookDetailDownloadMediaObjectIDs($ids) { return $ids; }
	function hookDetailDownloadMediaFilePaths($paths) { return $paths; }
}
class ZipStream {
	public $files = [];
	function addFile($path, $name) { mediaReadCheck(is_readable($path), 'ZIP source must remain readable.'); $this->files[$path] = $name; }
}
function caGetDetailConfig() { return new MediaReadConfig(); }
function caSetPageCSSClasses($classes) {}
function caGetOption($names, $options, $default = null) { return $default; }
function caObjectsDisplayDownloadLink($request) { return $request->allowed; }
function caGetAvailableDownloadVersions($request, $mime) { return $request->versions; }
function caGetRepresentationDownloadFileName($table, $options) { return $options['idno'].'-'.$options['representation_id'].'.pdf'; }
function caEmbedMediaMetadataIntoFile($representation, $version) {
	// PDF originals do not support native image metadata embedding. This must not
	// make the archive cleanup delete the last original instead of a scratch file.
	if (!$GLOBALS['embedTemporary']) { return false; }
	$path = $GLOBALS['mediaReadDirectory'].'/embedded-'.$representation->id.'.pdf';
	copy($representation->getMediaPath('media', $version), $path);
	return $path;
}
function _t($message) { return $message; }

$mediaReadDirectory = sys_get_temp_dir().'/tadl-media-readonly-'.bin2hex(random_bytes(8));
$nativeFiles = ['lib/BaseSearchController.php','lib/MediaContentLocationIndexer.php','lib/Media/MediaViewerManager.php','lib/ApplicationPluginManager.php','controllers/FindController.php','helpers/printHelpers.php','helpers/exportHelpers.php','lib/Parsers/ZipStream.php'];
foreach ($nativeFiles as $file) {
	if (!is_dir(dirname($mediaReadDirectory.'/'.$file))) { mkdir(dirname($mediaReadDirectory.'/'.$file), 0700, true); }
	file_put_contents($mediaReadDirectory.'/'.$file, '<?php');
}
define('__CA_APP_DIR__', $mediaReadDirectory); define('__CA_LIB_DIR__', $mediaReadDirectory.'/lib');
require dirname(__DIR__).'/controllers/DetailController.php';
function mediaReadController($loggedIn, $extra = []) {
	$request = new MediaReadRequest($extra + ['object_id' => 42, 'representation_id' => 142, 'context' => 'objects', 'version' => 'original', 'exclude_ancestors' => 1], $loggedIn);
	$response = new MediaReadResponse(); return new DetailController($request, $response);
}
try {
	foreach ([142, 143] as $id) { file_put_contents($mediaReadDirectory.'/original-'.$id.'.pdf', '%PDF-1.4 Synthetic original '.$id); }
	$hashes = [];
	foreach ([142, 143] as $id) { $hashes[$id] = hash_file('sha256', $mediaReadDirectory.'/original-'.$id.'.pdf'); }
	foreach ([false, true] as $loggedIn) {
		foreach ([false, true] as $embedTemporary) {
			foreach ([[], [43]] as $children) {
				ca_objects::$children = $children;
				$controller = mediaReadController($loggedIn); $controller->DownloadMedia();
				mediaReadCheck($controller->rendered === 'Details/download_file_binary.php', 'Anonymous and staff archive/single downloads must remain available.');
				$zip = $controller->view->getVar('zip_stream');
				mediaReadCheck($children ? $zip instanceof ZipStream && count($zip->files) === 2 : !$zip && is_readable($controller->view->getVar('archive_path')), 'Native ZIP versus single-file selection changed.');
				unset($controller);
				foreach ($hashes as $id => $hash) {
					mediaReadCheck(is_file($mediaReadDirectory.'/original-'.$id.'.pdf') && hash_file('sha256', $mediaReadDirectory.'/original-'.$id.'.pdf') === $hash, 'ZIP cleanup must never delete or change an original, including non-embeddable PDFs.');
				}
			}
			$controller = mediaReadController($loggedIn); $controller->DownloadRepresentation();
			mediaReadCheck($controller->rendered === 'Details/object_representation_download_binary.php', 'Individual native representation downloads must remain available.');
			mediaReadCheck(is_readable($controller->view->getVar('version_path')) && str_ends_with($controller->view->getVar('version_download_name'), '.pdf'), 'Native representation path/name missing.');
			unset($controller);
		}
		foreach (['object', 'representation', 'version', 'permission'] as $denied) {
			$controller = mediaReadController($loggedIn);
			ca_objects::$access = $denied === 'object' ? 0 : 1;
			ca_object_representations::$access = $denied === 'representation' ? 0 : 1;
			if ($denied === 'version') { $controller->request->versions = []; }
			if ($denied === 'permission') { $controller->request->allowed = false; }
			$controller->DownloadRepresentation();
			mediaReadCheck($controller->rendered === null, 'Download read-only change must preserve native access/version/permission rejection.');
		}
		ca_objects::$access = ca_object_representations::$access = 1;
	}
	echo "Media read-only paths passed: {$checks} assertions (anonymous/staff downloads, ZIP/single files, original preservation, permissions and no database logger).\n";
} finally {
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($mediaReadDirectory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
	rmdir($mediaReadDirectory);
}
