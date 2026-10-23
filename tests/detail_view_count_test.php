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
	function __construct(public string $action, public array $parameters = []) { $this->config = new DetailViewConfig(); }
	function isLoggedIn() { return false; }
	function getActionExtra() { return '14'; }
	function getParameter($name, $type) { foreach ((array)$name as $key) { if (isset($this->parameters[$key])) { return $this->parameters[$key]; } } return null; }
	function getRequestUrl($absolute = false) { return '/Detail/'.$this->action.'/14'; }
}
class DetailViewResponse {
	public $redirect = null;
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
class Datamodel { static function getInstance($table, $cached = false) { return new $table(); } }
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
$nativeFiles = ['lib/BaseSearchController.php','lib/MediaContentLocationIndexer.php','lib/Media/MediaViewerManager.php','lib/ApplicationPluginManager.php','controllers/FindController.php','helpers/printHelpers.php','helpers/exportHelpers.php','lib/Logging/Downloadlog.php','lib/Parsers/ZipStream.php'];
foreach ($nativeFiles as $file) {
	if (!is_dir(dirname($directory.'/'.$file))) { mkdir(dirname($directory.'/'.$file), 0700, true); }
	file_put_contents($directory.'/'.$file, '<?php');
}
define('__CA_APP_DIR__', $directory); define('__CA_LIB_DIR__', $directory.'/lib');
$theme = dirname(__DIR__);
require $theme.'/controllers/DetailController.php';
function detailViewDispatch($type, $tracking) {
	$values = ['detailTypes' => []];
	foreach (['objects','collections','entities','places'] as $name) {
		$values['detailTypes'][$name] = ['table' => 'ca_'.$name, 'disableRelatedItemsBrowse' => true,
			'options' => ['disableExport' => true, 'enableComments' => false, 'enableShare' => false, 'shareLabel' => '', 'enablePDF' => true]];
	}
	if ($tracking !== null) { $values['record_detail_views'] = $tracking; }
	$GLOBALS['detailConfig'] = new DetailViewConfig($values);
	$request = new DetailViewRequest($type); $response = new DetailViewResponse();
	$controller = new DetailController($request, $response);
	$controller->$type();
	return [$controller, $response];
}
try {
	// Optional upstream reference proves all native endpoints still have identical
	// PHP tokens, apart from this one guarded tracking call. Normal standalone
	// checks do not require an adjacent Pawtucket checkout.
	if ($reference = getenv('TADL_NATIVE_DETAIL_CONTROLLER')) {
		detailViewCheck(is_file($reference) && is_readable($reference), 'Native parity reference must be a readable source file.');
		$guard = <<<'GUARD'
if ((int)$this->config->get('record_detail_views') === 1) {
			$t_subject->registerItemView();
		}
GUARD;
		$source = str_replace($guard, '$t_subject->registerItemView();', file_get_contents($theme.'/controllers/DetailController.php'), $replaced);
		detailViewCheck($replaced === 1, 'Native parity must reverse exactly one tracking guard.');
		$tokens = static function ($source) {
			return array_values(array_filter(token_get_all($source), static function ($token) {
				return !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
			}));
		};
		$values = static function ($tokens) { return array_map(static fn($token) => is_array($token) ? [$token[0], $token[1]] : $token, $tokens); };
		detailViewCheck($values($tokens($source)) === $values($tokens(file_get_contents($reference))), 'The complete native controller must differ only in optional view tracking.');
	}
	$config = file_get_contents($theme.'/conf/detail.conf');
	detailViewCheck(preg_match('/^record_detail_views\s*=\s*0\s*$/m', $config) === 1, 'The shipped theme must disable detail writes.');
	foreach (['objects','collections','entities','places'] as $type) {
		foreach ([null, 0, '0'] as $setting) {
			DetailViewModel::$views = 0; DetailViewModel::$locked = true; DetailViewModel::$access = 1;
			[$controller] = detailViewDispatch($type, $setting);
			detailViewCheck(DetailViewModel::$views === 0, 'Missing/disabled tracking must never touch the locked catalogue row.');
			detailViewCheck($controller->rendered === 'Details/ca_'.$type.'_default_html.php', 'A locked optional counter must not prevent normal detail rendering.');
			detailViewCheck($controller->view->getVar('substitutionAccess') === [1], 'Native template access scope must remain intact.');
			detailViewCheck(str_ends_with($controller->view->getVar('previousURL'), '/13') && str_ends_with($controller->view->getVar('nextURL'), '/15'), 'Native result context navigation must remain intact.');
			detailViewCheck($controller->view->getVar('resultsURL') === '/Browse/objects/s/18', 'Native Back target must remain intact.');
			if ($type === 'objects') {
				detailViewCheck(str_contains($controller->view->getVar('representationViewer'), 'synthetic-native-media-viewer'), 'Native object media viewer rendering must remain intact.');
				detailViewCheck($controller->view->getVar('representation_id') === 31, 'The permitted native representation must remain selected.');
			}
		}
		DetailViewModel::$views = 0; DetailViewModel::$locked = false;
		[$controller] = detailViewDispatch($type, 1);
		detailViewCheck(DetailViewModel::$views === 1 && $controller->rendered !== null, 'Explicit opt-in must invoke native tracking once and render normally.');
		DetailViewModel::$views = 0; DetailViewModel::$locked = true;
		$blocked = false;
		try { detailViewDispatch($type, 1); } catch (RuntimeException $error) { $blocked = str_contains($error->getMessage(), 'locked'); }
		detailViewCheck($blocked && DetailViewModel::$views === 1, 'The synthetic lock must detect accidental counter execution.');
		DetailViewModel::$views = 0; DetailViewModel::$access = 0;
		[$controller, $response] = detailViewDispatch($type, 0);
		detailViewCheck($controller->rendered === null && $response->redirect !== null && DetailViewModel::$views === 0, 'Disabling statistics must preserve native record access rejection.');
	}
	foreach (['GetMediaInline','GetMediaOverlay','GetMediaData','DownloadRepresentation','DownloadMedia'] as $method) {
		detailViewCheck(method_exists(DetailController::class, $method), 'Native viewer/download endpoints must remain available.');
	}
	echo "Detail view counts passed: {$checks} assertions (locked rows, default/optional tracking, access, result navigation and native viewer rendering).\n";
} finally {
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
	rmdir($directory);
}
