<?php
/** Dispatch actual theme details against synthetic native boundaries; no app or database. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('pString', 1); define('pInteger', 2);
$checks = 0;
function detailViewCheck($condition, $message) {
	$GLOBALS['checks']++;
	if (!$condition) { throw new RuntimeException($message); }
}
class DetailViewConfig {
	function __construct(public array $values = []) {}
	function get($name) { return $this->values[$name] ?? null; }
	function getAssoc($name) { return $this->get($name); }
}
class DetailViewRequest {
	public $config;
	function __construct(public string $action, public array $parameters = [], public bool $loggedIn = false) { $this->config = new DetailViewConfig(); }
	function isLoggedIn() { return $this->loggedIn; }
	function getActionExtra() { return '14'; }
	function getParameter($name, $type) { foreach ((array)$name as $key) { if (isset($this->parameters[$key])) { return $this->parameters[$key]; } } return null; }
	function getRequestUrl($absolute = false) { return '/Detail/'.$this->action.'/14'; }
}
class DetailViewResponse {
	public $redirect = null, $status = 200;
	public $headers = [];
	function setHTTPResponseCode($status, $message) { $this->status = $status; }
	function addHeader($name, $value) { $this->headers[$name] = $value; }
	function setRedirect($url) { $this->redirect = $url; }
}
class DetailViewView {
	public $vars = [];
	function setVar($name, $value) { $this->vars[$name] = $value; }
	function getVar($name) { return $this->vars[$name] ?? null; }
}
class DetailViewNotification { function addNotification($message, $type) {} }
class FindController {
	public $request, $response, $view, $notification;
	protected $opa_access_values = [1], $ops_tablename;
	public $rendered = null;
	function __construct(&$request, &$response, $paths = null) {
		$this->request = $request; $this->response = $response;
		$this->view = new DetailViewView(); $this->notification = new DetailViewNotification();
	}
	function viewExists($path) { return false; }
	function render($path) { $this->rendered = $path; }
}
class DetailViewModel {
	const TABLE = '';
	static public $views = 0, $locked = true, $access = 1;
	static function find($params, $options) { return new static(); }
	function tableName() { return static::TABLE; }
	function primaryKey() { return str_replace('ca_', '', static::TABLE) === 'objects' ? 'object_id' : 'synthetic_id'; }
	function getPrimaryKey() { return 14; }
	function hasElement($element) { return false; }
	function hasField($field) { return $field === 'access'; }
	function getProperty($name) { return $name === 'ID_NUMBERING_ID_FIELD' ? 'idno' : null; }
	function autoConvertLineBreaks($enabled) {}
	function get($field) { return ['access' => self::$access, 'idno' => 'SYN-14', 'preferred_labels' => 'Synthetic record'][$field] ?? null; }
	function getTypeCode() { return 'synthetic'; }
	function getTypeName() { return 'Synthetic type'; }
	function doHighlighting($enabled) {}
	function registerItemView() {
		self::$views++;
		if (self::$locked) { throw new RuntimeException('Synthetic catalogue row is locked by an import.'); }
	}
}
class ca_collections extends DetailViewModel { const TABLE = 'ca_collections'; }
class ca_entities extends DetailViewModel { const TABLE = 'ca_entities'; }
class ca_places extends DetailViewModel { const TABLE = 'ca_places'; }
class ca_objects extends DetailViewModel {
	const TABLE = 'ca_objects';
	function getPrimaryRepresentationInstance($options) { return new DetailViewRepresentation(); }
}
class DetailViewRepresentation {
	function get($name) { return 31; }
	function getMediaInfo($field, $version, $name) { return 'image/jpeg'; }
}
class Datamodel { static public $loads = 0; static function getInstance($table, $cached = false) { self::$loads++; return new $table(); } }
class AssetLoadManager { static function register($name) {} }
class MetaTagManager { static function setWindowTitle($title) {} }
class ResultContext {
	static function getResultContextForLastFind($request, $table) { return new self(); }
	function getResultDesc() { return []; }
	function findType() { return 'browse'; }
	function getPreviousID($id) { return 13; }
	function getNextID($id) { return 15; }
	static function getResultsLinkForLastFind($request, $table, $label, $class, $params, $attributes) { return '<a href="/Browse/objects/s/18">Back to results</a>'; }
	static function getResultsUrlForLastFind($request, $table, $params) { return '/Browse/objects/s/18'; }
}
function caGetDetailConfig() { return $GLOBALS['detailConfig']; }
function caGetSearchConfig() { return new DetailViewConfig(); }
function caSetPageCSSClasses($classes) {}
function caAddPageCSSClasses($classes) {}
function caUseAltIdentifierInUrls($table) { return false; }
function caUseIdentifiersInUrls() { return false; }
function caGetOption($names, $options, $default = null) { foreach ((array)$names as $name) { if (is_array($options) && array_key_exists($name, $options)) { return $options[$name]; } } return $default; }
function caACLIsEnabled($subject, $options) { return false; }
function caDetailUrl($request, $table, $id) { return '/Detail/'.substr($table, 3).'/'.$id; }
function caDetailLink($request, $label, $class, $table, $id, $params, $attributes) { return '<a href="'.caDetailUrl($request, $table, $id).'">'.$label.'</a>'; }
function caGetMediaDisplayInfo($display, $mimetype) { return []; }
function caRepresentationViewer($request, $subject, $context, $options) { return '<div id="synthetic-native-media-viewer"></div>'; }
function caObjectRepresentationThumbnails($request, $id, $subject, $options) { return 'Synthetic native thumbnails'; }
function caNavUrl($request, $module, $controller, $action, $params = []) { return '/'.$controller.'/'.$action; }
function caMakeTypeList($table, $types, $options = []) { return null; }
function caDoTemplateTagSubstitution($view, $subject, $path, $options) { $view->setVar('substitutionAccess', $options['checkAccess']); }
function _t($message) { return $message; }
define('__NOTIFICATION_TYPE_INFO__', 1);

$directory = sys_get_temp_dir().'/tadl-detail-view-count-'.bin2hex(random_bytes(8));
$nativeFiles = ['lib/BaseSearchController.php','lib/MediaContentLocationIndexer.php','lib/Media/MediaViewerManager.php','lib/ApplicationPluginManager.php','controllers/FindController.php','helpers/printHelpers.php','helpers/exportHelpers.php','lib/Parsers/ZipStream.php'];
foreach ($nativeFiles as $file) {
	if (!is_dir(dirname($directory.'/'.$file))) { mkdir(dirname($directory.'/'.$file), 0700, true); }
	file_put_contents($directory.'/'.$file, '<?php');
}
define('__CA_APP_DIR__', $directory); define('__CA_LIB_DIR__', $directory.'/lib');
$theme = dirname(__DIR__);
require $theme.'/controllers/DetailController.php';
function detailViewDispatch($type, $tracking, $loggedIn = false) {
	$values = ['detailTypes' => []];
	foreach (['objects','collections','entities','places'] as $name) {
		$values['detailTypes'][$name] = ['table' => 'ca_'.$name, 'disableRelatedItemsBrowse' => true,
			'options' => ['disableExport' => true, 'enableComments' => false, 'enableShare' => false, 'shareLabel' => '', 'enablePDF' => true]];
	}
	if ($tracking !== null) { $values['record_detail_views'] = $tracking; }
	$GLOBALS['detailConfig'] = new DetailViewConfig($values);
	$request = new DetailViewRequest($type, [], $loggedIn); $response = new DetailViewResponse();
	$controller = new DetailController($request, $response);
	$controller->$type();
	return [$controller, $response];
}
try {
	// Optional native parity checks all retained catalogue/media paths. The only
	// permitted changes remove usage writes, reject mutation actions, and avoid
	// deleting original media during ZIP cleanup.
	if ($reference = getenv('TADL_NATIVE_DETAIL_CONTROLLER')) {
		detailViewCheck(is_file($reference) && is_readable($reference), 'Native parity reference must be a readable source file.');
		$native = file_get_contents($reference);
		foreach (["require_once(__CA_LIB_DIR__.'/Logging/Downloadlog.php');", '$t_subject->registerItemView();', 'if ($vs_path) { unlink($vs_path); }'] as $removed) {
			$native = str_replace($removed, '', $native, $count);
			detailViewCheck($count === 1, 'Native parity must remove exactly one known usage/unsafe-cleanup operation.');
		}
		$native = str_replace('$t_download_log = new Downloadlog();', '', $native, $count);
		detailViewCheck($count === 2, 'Native parity must remove both download logger instances.');
		$native = preg_replace('/\\$t_download_log->log\\(array\\(.*?\\)\\);/s', '', $native, -1, $count);
		detailViewCheck($count === 2, 'Native parity must remove both download log writes.');
		$native = str_replace("\$vals = ['idno' => \$vs_idno_proc];", "\$vals = ['idno' => \$t_instance->get('idno')];", $native, $count);
		detailViewCheck($count === 1, 'Native parity fixes the native undefined filename variable.');
		$tokens = static function ($source) {
			$tokens = token_get_all($source); $values = [];
			$mutation = false; $function = false; $depth = 0;
			foreach ($tokens as $token) {
				if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) { continue; }
				if ($depth) {
					if ($token === '{') { $depth++; }
					elseif ($token === '}' && --$depth === 0) { $values[] = '}'; $mutation = false; }
					continue;
				}
				if (is_array($token) && $token[0] === T_FUNCTION) { $function = true; }
				elseif ($function && is_array($token) && $token[0] === T_STRING) {
					$mutation = in_array($token[1], ['CommentForm', 'SaveCommentTagging', 'SaveAnnotations'], true); $function = false;
				}
				$values[] = is_array($token) ? [$token[0], $token[1]] : $token;
				if ($mutation && $token === '{') { $depth = 1; }
			}
			return $values;
		};
		detailViewCheck($tokens(file_get_contents($theme.'/controllers/DetailController.php')) === $tokens($native), 'Retained native detail/viewer/download logic must stay intact.');
	}
	foreach (['objects','collections','entities','places'] as $type) {
		foreach ([null, 0, '0', 1, '1'] as $setting) {
			DetailViewModel::$views = 0; DetailViewModel::$locked = true; DetailViewModel::$access = 1;
			[$controller] = detailViewDispatch($type, $setting);
			detailViewCheck(DetailViewModel::$views === 0, 'Catalogue detail must never touch a locked row, including legacy opt-in configurations.');
			detailViewCheck($controller->rendered === 'Details/ca_'.$type.'_default_html.php', 'A locked optional counter must not prevent normal detail rendering.');
			detailViewCheck($controller->view->getVar('substitutionAccess') === [1], 'Native template access scope must remain intact.');
			detailViewCheck(str_ends_with($controller->view->getVar('previousURL'), '/13') && str_ends_with($controller->view->getVar('nextURL'), '/15'), 'Native result context navigation must remain intact.');
			detailViewCheck($controller->view->getVar('resultsURL') === '/Browse/objects/s/18', 'Native Back target must remain intact.');
			if ($type === 'objects') {
				detailViewCheck(str_contains($controller->view->getVar('representationViewer'), 'synthetic-native-media-viewer'), 'Native object media viewer rendering must remain intact.');
				detailViewCheck($controller->view->getVar('representation_id') === 31, 'The permitted native representation must remain selected.');
			}
		}
		DetailViewModel::$views = 0; DetailViewModel::$access = 0;
		[$controller, $response] = detailViewDispatch($type, 0);
		detailViewCheck($controller->rendered === null && $response->redirect !== null && DetailViewModel::$views === 0, 'Disabling statistics must preserve native record access rejection.');
	}
	foreach ([false, true] as $loggedIn) {
		foreach (['CommentForm', 'SaveCommentTagging', 'SaveAnnotations'] as $action) {
			DetailViewModel::$access = 1;
			[$controller, $response] = detailViewDispatch('objects', 1, $loggedIn);
			$controller->rendered = null; $loads = Datamodel::$loads;
			$controller->$action();
			detailViewCheck($response->status === 404 && $response->headers['Cache-Control'] === 'private, no-store', 'Catalogue mutation routes must be unavailable to anonymous and staff sessions.');
			detailViewCheck(Datamodel::$loads === $loads && $controller->rendered === null, 'Rejected mutation must not load models or render a mutation response.');
		}
	}
	foreach (['GetMediaInline','GetMediaOverlay','GetMediaData','DownloadRepresentation','DownloadMedia'] as $method) {
		detailViewCheck(method_exists(DetailController::class, $method), 'Native viewer/download endpoints must remain available.');
	}
	echo "Detail view counts passed: {$checks} assertions (locked rows, ignored legacy opt-in, rejected mutations, access, result navigation and native viewer rendering).\n";
} finally {
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
	rmdir($directory);
}
