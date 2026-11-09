<?php
/** Dispatch the actual gallery controller with synthetic native set/model boundaries. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) { return false; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
define('pInteger', 1); define('pString', 2);
$checks = 0;
function galleryScopeCheck($condition, $message) { $GLOBALS['checks']++; if (!$condition) { throw new RuntimeException($message); } }
class GalleryScopeConfig {
    function get($key) { return ['tadl_catalogue_root_idno'=>'alpha', 'gallery_set_type'=>'public_presentation', 'gallery_sort_by'=>'name'][$key] ?? null; }
}
class GalleryScopeRequest {
    public $config;
    function __construct(public array $params) { $this->config = new GalleryScopeConfig(); }
    function getController() { return 'Detail'; } // Includes static native navigation helpers.
    function getAction() { return 'objects'; }
    function getParameter($key, $type = null) { return $this->params[$key] ?? null; }
    function isLoggedIn() { return false; }
    function isAjax() { return false; }
}
class GalleryScopeRows {
    private int $position = -1;
    function __construct(private array $rows) {}
    function nextRow() { return isset($this->rows[++$this->position]); }
    function getRow() { return $this->rows[$this->position]; }
    function get($key) { return $this->getRow()[$key]; }
}
class GalleryScopeDB {
    function query($sql, $params) {
        if (str_contains($sql, 'WHERE idno = ?')) { return new GalleryScopeRows([['collection_id'=>1,'parent_id'=>null,'hier_collection_id'=>1]]); }
        galleryScopeCheck(str_starts_with($sql, 'SELECT DISTINCT'), 'Gallery membership must remain read-only.');
        $ids = str_contains($sql, 'ca_object_representations r') ? [1000,3000] : [100,300];
        return new GalleryScopeRows(array_map(static fn($id)=>['scope_id'=>$id], $ids));
    }
}
class GalleryScopeModel {
    function getDb() { return new GalleryScopeDB(); }
    function load($id) { return true; }
    function getLabelForDisplay() { return 'Synthetic object'; }
}
class Datamodel {
    static function getTableName($id) { return 'ca_objects'; }
    static function getInstanceByTableName($table, $cached = true) { return new GalleryScopeModel(); }
    static function getInstanceByTableNum($table, $cached = true) { return new GalleryScopeModel(); }
}
class ca_lists { function getItemIDFromList($list, $code) { return 8; } }
class ca_sets {
    public int $id = 0;
    function __construct($id = 0) { if ($id) { $this->load($id); } }
    function load($id) { $this->id = $id; return in_array($id, [42,43], true); }
    function get($key, $options = []) { return ['table_num'=>57,'access'=>1,'type_id'=>8,'deleted'=>0][$key] ?? ''; }
    function isReadable($request) { return true; }
    function getLabelForDisplay() { return 'Synthetic presentation'; }
    function getSets($options = []) { return [42=>['name'=>'Synthetic mixed','set_code'=>'mixed','table_num'=>57],43=>['name'=>'Synthetic outside','set_code'=>'outside','table_num'=>57]]; }
    function items($options = []) {
        $items = [21=>['item_id'=>21,'row_id'=>200,'representation_id'=>2000,'representation_tag'=>'OUTSIDE'],
            22=>['item_id'=>22,'row_id'=>100,'representation_id'=>1000,'representation_tag'=>'ALLOWED A'],
            23=>['item_id'=>23,'row_id'=>300,'representation_id'=>3000,'representation_tag'=>'ALLOWED B']];
        if ($this->id === 43) { $items = [21=>$items[21]]; }
        if (isset($options['row_ids'])) { $items = array_filter($items, static fn($item)=>in_array($item['row_id'], $options['row_ids'], true)); }
        if (isset($options['limit'])) { $items = array_slice($items, $options['start'] ?? 0, $options['limit'], true); }
        return $items;
    }
    function getItemRowIDs($options = []) { return array_fill_keys(array_column($this->items($options), 'row_id'), true); }
    function getItemIDs($options = []) { return array_fill_keys(array_keys($this->items($options)), true); }
    function getItems($options = []) { return $this->items($options); }
    function getPrimaryItemsFromSets($ids, $options = []) { throw new RuntimeException('Scoped galleries must not load an unfiltered cover first.'); }
}
class ca_set_items {
    function __construct(private int $id) {}
    function isLoaded() { return in_array($this->id, [21,22,23], true); }
    function get($key, $options = []) { return ['ca_set_items.set_id'=>42,'table_num'=>57,'row_id'=>[21=>200,22=>100,23=>300][$this->id]][$key] ?? ''; }
}
class ca_object_representations {
    function __construct(private int $id) {}
    function get($key) { return $key === 'access' ? 1 : $this->id; }
    function getMediaTag($field, $version) { return 'ALLOWED MEDIA '.$this->id; }
}
class GalleryScopeView {
    public array $vars = [];
    function setVar($key, $value) { $this->vars[$key] = $value; }
    function getAllVars() { return $this->vars; }
}
class BasePawtucketController {
    public $request, $response, $view, $config, $rendered;
    protected array $opa_access_values = [1];
    function __construct(&$request, &$response, $paths = null) { $this->request = $request; $this->response = $response; $this->view = new GalleryScopeView(); }
    function render($view) { $this->rendered = $view; }
    function getTagListForView($view) { return []; }
}
class AppController { static function getInstance() { return new self(); } function getRequest() { return new GalleryScopeRequest([]); } }
class AssetLoadManager { static function register($name) {} }
class Session { static function getVar($name) { return 21; } static function setVar($name, $value) {} static function save() {} }
class ResultContext {
    static public array $ids = [];
    function __construct($request, $table, $kind) {}
    function setAsLastFind() {}
    function setResultList($ids) { self::$ids = $ids; }
    function saveContext() {}
}
class MetaTagManager { static function setWindowTitle($title) {} }
class ApplicationException extends RuntimeException {}
function caGetGalleryConfig() { return new GalleryScopeConfig(); }
function caGetFrontConfig() { return new GalleryScopeConfig(); }
function caGetUserAccessValues($request) { return [1]; }
function caSetPageCSSClasses($classes) {}
function caExtractValuesByUserLocale($values) { return $values; }
function caRepToolbar($request, $rep, $id, $options) { return 'Synthetic toolbar'; }
function _t($value) { return $value; }
$directory = sys_get_temp_dir().'/tadl-gallery-scope-'.bin2hex(random_bytes(8));
mkdir($directory.'/models', 0700, true); mkdir($directory.'/lib/pawtucket', 0700, true);
define('__CA_MODELS_DIR__', $directory.'/models'); define('__CA_LIB_DIR__', $directory.'/lib');
foreach (['ca_sets','ca_lists','ca_objects','ca_object_representations'] as $model) { file_put_contents($directory.'/models/'.$model.'.php', '<?php'); }
file_put_contents($directory.'/lib/pawtucket/BasePawtucketController.php', '<?php');
try {
    require dirname(__DIR__).'/controllers/GalleryController.php';
    $request = new GalleryScopeRequest(['set_id'=>42,'item_id'=>22]); $response = new stdClass();
    $controller = new GalleryController($request, $response);
    $controller->Index();
    galleryScopeCheck(array_keys($controller->view->vars['sets']) === [42], 'The gallery index must omit outside-only sets.');
    galleryScopeCheck(reset($controller->view->vars['first_items_from_sets'][42])['row_id'] === 100, 'The gallery index must filter covers before native limits.');
    $controller->{'42'}();
    galleryScopeCheck(array_keys($controller->view->vars['set_items']) === [22,23] && ResultContext::$ids === [100,300], 'Gallery thumbnails and native result contexts must contain only scoped material.');
    galleryScopeCheck($controller->view->vars['set_item_id'] === 22, 'A stored outside gallery selection must fall back to the first scoped item.');
    $controller->getSetInfo();
    galleryScopeCheck($controller->view->vars['num_items'] === 2, 'Gallery count must omit outside records.');
    galleryScopeCheck($controller->view->vars['set_item']['row_id'] === 100, 'Gallery cover must use the first scoped item rather than its outside primary item.');
    $controller->getSetItemInfo();
    galleryScopeCheck($controller->view->vars['set_num_items'] === 2 && $controller->view->vars['set_item_num'] === 1, 'Gallery counters must use filtered positions.');
    $controller->getSetItemRep();
    galleryScopeCheck($controller->view->vars['previous_item_id'] === 0 && $controller->view->vars['next_item_id'] === 23, 'Gallery navigation must skip outside records and stop at the first scoped item.');
    galleryScopeCheck($controller->view->vars['rep'] === 'ALLOWED MEDIA 1000', 'Gallery media must belong to the requested scoped item.');
    $request->params['item_id'] = 23; $controller->getSetItemRep();
    galleryScopeCheck($controller->view->vars['previous_item_id'] === 22 && $controller->view->vars['next_item_id'] === 0, 'Gallery navigation must stop at the last scoped item.');
    foreach ([['set_id'=>42,'item_id'=>21],['set_id'=>43,'item_id'=>21]] as $params) {
        $request->params = $params;
        try { $controller->getSetItemRep(); galleryScopeCheck(false, 'Direct outside gallery item/set must be denied.'); }
        catch (TadlPublicNotFoundException $error) { galleryScopeCheck(true, 'Outside gallery returned normal 404.'); }
    }
    echo 'Catalogue gallery scope passed: '.$checks.' assertions (actual controller covers, counts, positions, media, navigation and direct-request rejection).'.PHP_EOL;
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($directory);
}
