<?php
/** Actual scope SQL against synthetic SQLite; no catalogue or external services. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) { return false; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
define('pInteger', 1); define('pString', 2); define('__CA_BUNDLE_ACCESS_READONLY__', 1);
$checks = 0;
function scopeCheck($condition, $message) { $GLOBALS['checks']++; if (!$condition) { throw new RuntimeException($message); } }
class ScopeRows {
    private $row;
    function __construct(private PDOStatement $statement) {}
    function nextRow() { return ($this->row = $this->statement->fetch(PDO::FETCH_ASSOC)) !== false; }
    function get($key) { return $this->row[$key]; }
    function getRow() { return $this->row; }
}
class ScopeDB {
    public int $queries = 0;
    public PDO $pdo;
    function __construct() { $this->pdo = new PDO('sqlite::memory:'); $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
    function query($sql, $params = []) {
        scopeCheck(preg_match('/^\s*SELECT\b/i', $sql), 'Theme membership reads must not write to the database.');
        $this->queries++;
        $flat = []; $offset = 0;
        $sql = preg_replace_callback('/\?/', static function () use ($params, &$offset, &$flat) {
            $value = $params[$offset++];
            if (!is_array($value)) { $flat[] = $value; return '?'; }
            array_push($flat, ...$value); return join(',', array_fill(0, count($value), '?'));
        }, $sql);
        $statement = $this->pdo->prepare($sql); $statement->execute($flat);
        return new ScopeRows($statement);
    }
}
class ScopeConfig {
    function __construct(public string $root = 'alpha') {}
    function get($key) { return $key === 'tadl_catalogue_root_idno' ? $this->root : null; }
}
class ScopeRequest {
    public $config;
    function __construct(string $root = 'alpha', public array $params = []) { $this->config = new ScopeConfig($root); }
    function getUserID() { return 0; }
    function getParameters($sources) { return []; }
    function getParameter($key, $type = null) { return $this->params[$key] ?? null; }
    function getController() { return 'Detail'; }
    function getAction() { return 'objects'; }
}
class ScopeModel {
    function __construct(private string $table) {}
    function getDb() { return $GLOBALS['scopeDB']; }
    function getAppConfig() { return new ScopeConfig(); }
    function tableNum() { return 57; }
}
class Datamodel {
    static function getInstanceByTableName($table, $cached = false) { return new ScopeModel($table); }
    static function getTableName($number) { return $number === 57 ? 'ca_objects' : 'ca_entities'; }
}
function caGetBundleAccessLevel($table, $bundle) { return 1; }
function caGetTypeRestrictionsForUser($table) { return null; }
function caGetSourceRestrictionsForUser($table) { return null; }
function caSourceAccessControlIsEnabled($model) { return false; }
function caACLIsEnabled($model, $options = []) { return false; }
function caGetUserAccessValues($request) { return [1]; }
function caParseMediaIdentifier($identifier, $options = []) {
    scopeCheck(($options['checkAccess'] ?? null) === [1], 'Media identifier parsing must retain native publication checks.');
    return [
        'representation:1000'=>['type'=>'representation','id'=>1000,'subject'=>'ca_object_representations','subject_id'=>1000],
        'representation:2000'=>['type'=>'representation','id'=>2000,'subject'=>'ca_object_representations','subject_id'=>2000],
        'attribute:9'=>['type'=>'attribute','id'=>9,'subject'=>'ca_objects','subject_id'=>200]
    ][$identifier] ?? null;
}
class Session {
    static public array $comparisonList = [];
    static function getVar($key) { return $key === 'comparison_list' ? self::$comparisonList : ($GLOBALS['scopePreference'] ?? 'all'); }
    static function setVar($key, $value) { scopeCheck($key === 'comparison_list', 'Scope guards must only update their own session state.'); self::$comparisonList = $value; }
}
class SearchResult {
    function __construct(public array $ids, private string $table) {}
    function tableName() { return $this->table; }
    function tableNum() { return 57; }
    function getDb() { return $GLOBALS['scopeDB']; }
    function getPrimaryKeyValues() { return $this->ids; }
    function init($engine, $terms, $options) { $this->ids = $engine->ids; }
}
class WLPlugSearchEngineBrowseEngine { function __construct(public array $ids, $terms, $table) {} }
require dirname(__DIR__).'/helpers/media_preferences.php';
require dirname(__DIR__).'/helpers/media_filter_helpers.php';

$db = $GLOBALS['scopeDB'] = new ScopeDB();
$db->pdo->exec('CREATE TABLE ca_collections (collection_id INTEGER PRIMARY KEY, idno TEXT, parent_id INTEGER, hier_collection_id INTEGER, deleted INTEGER, access INTEGER);
CREATE TABLE ca_objects (object_id INTEGER PRIMARY KEY, deleted INTEGER, access INTEGER);
CREATE TABLE ca_objects_x_collections (object_id INTEGER, collection_id INTEGER);
CREATE TABLE ca_object_representations (representation_id INTEGER PRIMARY KEY, deleted INTEGER, access INTEGER);
CREATE TABLE ca_objects_x_object_representations (object_id INTEGER, representation_id INTEGER);
INSERT INTO ca_collections VALUES (1,"alpha",NULL,1,0,0),(2,"beta",NULL,2,0,0),(10,"alpha.series",1,1,0,1),(11,"alpha.folder",10,1,0,1),(20,"beta.series",2,2,0,1),(12,"alpha.deleted",1,1,1,1);
INSERT INTO ca_objects VALUES (100,0,1),(200,0,1),(300,0,1),(400,0,1),(500,1,1),(600,0,1),(700,0,0);
INSERT INTO ca_objects_x_collections VALUES (100,11),(200,20),(300,10),(300,20),(500,10),(600,12),(700,10);
INSERT INTO ca_object_representations VALUES (1000,0,1),(2000,0,1),(3000,0,1),(4000,0,1);
INSERT INTO ca_objects_x_object_representations VALUES (100,1000),(200,2000),(300,3000),(400,4000);');
$request = new ScopeRequest();
scopeCheck(tadlCatalogueIDs($request, 'ca_collections', [20,11,2,10,1,12]) === [11,10,1], 'Scope must include the private structural root and all nondeleted descendants, never its sibling.');
scopeCheck(tadlCatalogueIDs($request, 'ca_objects', [200,400,300,100,500,600,700]) === [300,100,700], 'Shared objects qualify through their allowed membership; unassigned/deleted/outside objects do not.');
scopeCheck(tadlCatalogueIDs($request, 'ca_object_representations', [4000,2000,3000,1000]) === [3000,1000], 'Representations must belong to an in-scope object.');
$queries = $db->queries;
for ($i = 0; $i < 20; $i++) { scopeCheck(tadlCatalogueAllows($request, 'ca_objects', 100), 'Repeated record checks changed membership.'); }
scopeCheck($db->queries === $queries, 'A page must reuse membership reads rather than query once per result.');
foreach (['ca_entities','ca_places','ca_occurrences','ca_list_items'] as $table) {
    scopeCheck(tadlCatalogueIDs($request, $table, [200,100]) === [200,100], 'Shared authorities must remain shared.');
}
scopeCheck(tadlReadableIDs($request, 'ca_objects', [200,100,700,500], [1]) === [100], 'Scope must supplement native publication/deletion filtering.');
scopeCheck(tadlReadableIDs($request, 'ca_collections', [1,10,20], [1]) === [10], 'Private grouping roots do not become public records.');
scopeCheck(tadlReadableMediaRows($request, [['object_id'=>200,'representation_id'=>2000],['object_id'=>100,'representation_id'=>1000]], [1], true)
    === [1=>['object_id'=>100,'representation_id'=>1000]], 'Optimized media rows must not bypass institution membership.');
$result = new SearchResult([200,300,400,100], 'ca_objects');
tadlFilterMediaResult($request, $result);
scopeCheck($result->ids === [300,100], 'All items must still enforce institution scope in result order.');
$GLOBALS['scopePreference'] = 'only';
$result = new SearchResult([200,400], 'ca_objects'); tadlFilterMediaResult($request, $result);
scopeCheck($result->ids === [], 'Only items with media must not reintroduce outside objects.');
$GLOBALS['scopePreference'] = 'all';
$facets = [10=>['id'=>10,'label'=>'Synthetic allowed collection'],20=>['id'=>20,'label'=>'Synthetic other collection']];
$facets = tadlMediaFacetItems($request, $facets, ['table'=>'ca_collections','type'=>'authority','tadl_subject_table'=>'ca_entities']);
scopeCheck(array_keys($facets) === [10], 'Collection facets must be scoped even on shared-authority pages.');
foreach (['missing', "alpha' OR 1=1 --"] as $root) {
    scopeCheck(tadlCatalogueIDs(new ScopeRequest($root), 'ca_objects', [100,200]) === [], 'Unknown or malformed roots must fail closed.');
}
scopeCheck(tadlCatalogueIDs(new ScopeRequest('alpha.series'), 'ca_collections', [10,11]) === [], 'A non-root identifier must fail closed.');
$largeRequest = new ScopeRequest();
scopeCheck(tadlCatalogueIDs($largeRequest, 'ca_objects', range(1,3000)) === [100,300,700], 'A large result set must use the same membership rules.');
$queries = $db->queries;
scopeCheck(tadlCatalogueIDs($largeRequest, 'ca_objects', [100,200,9999]) === [100] && $db->queries === $queries, 'The complete cohort must reuse negative lookups too.');
scopeCheck(tadlCatalogueIDs(new ScopeRequest(''), 'ca_objects', [100,200]) === [100,200], 'Explicitly disabled scope must retain native behavior for other installations.');
$db->pdo->exec('INSERT INTO ca_collections VALUES (3,"alpha",NULL,3,0,1)');
scopeCheck(tadlCatalogueIDs(new ScopeRequest(), 'ca_objects', [100,200]) === [], 'Duplicate root identifiers must fail closed.');
$db->pdo->exec('DELETE FROM ca_collections WHERE collection_id=3; UPDATE ca_collections SET hier_collection_id=2,parent_id=20 WHERE collection_id=11');
scopeCheck(tadlCatalogueIDs(new ScopeRequest(), 'ca_objects', [100,200,300]) === [300], 'A later request must observe hierarchy moves instead of stale membership.');
$db->pdo->exec('UPDATE ca_collections SET hier_collection_id=1,parent_id=10 WHERE collection_id=11');
$scopeTemplate = tadlCatalogueTemplate($request, '<unit relativeTo="ca_collections" delimiter="<br/>"><l>^ca_collections.preferred_labels.name</l></unit>');
scopeCheck(str_contains($scopeTemplate, 'skipIfExpression="^ca_collections.hier_collection_id != 1"'), 'Related collection units must filter before native rendering.');
scopeCheck($scopeTemplate === '<unit relativeTo="ca_collections" delimiter="<br/>" skipIfExpression="^ca_collections.hier_collection_id != 1"><l>^ca_collections.preferred_labels.name</l></unit>', 'Markup inside a quoted unit delimiter must survive scope rewriting intact.');
scopeCheck(tadlCatalogueAncestorRows($request, [['NODE'=>['collection_id'=>20]], ['NODE'=>['collection_id'=>10]]], ['table'=>'ca_collections']) === [['NODE'=>['collection_id'=>10]]], 'Native ancestor JSON/HTML must exclude the other hierarchy.');
tadlRequireCatalogueMediaIdentifier($request, 'representation%3A1000');
foreach (['representation:2000','attribute:9','invalid:1'] as $identifier) {
    try { tadlRequireCatalogueMediaIdentifier($request, $identifier); scopeCheck(false, 'An outside/invalid media identifier was allowed.'); }
    catch (TadlPublicNotFoundException $error) { scopeCheck(true, 'Direct media identifiers fail closed.'); }
}
require dirname(__DIR__).'/helpers/finding_aid.php';
$sharedCreator = new class {
    function getPrimaryKey() { return 200; }
    function tableName() { return 'ca_entities'; }
    function get($key) { return $key === 'access' ? 1 : 0; }
    function isReadable($request) { return true; }
};
scopeCheck(tadlFindingAidReadable($request, $sharedCreator), 'Finding-aid creators remain shared even when their numeric ID matches an outside object.');
$compareRequest = new class extends ScopeRequest { function getController() { return 'Compare'; } };
$allowedComparison = ['id'=>'representation:1000','resolved_id'=>'representation:1000','display'=>'Synthetic allowed image'];
Session::$comparisonList = [['id'=>'representation:2000','resolved_id'=>'representation:2000','display'=>'Synthetic outside image'], $allowedComparison];
tadlGuardCatalogueCompareRequest($compareRequest);
scopeCheck(Session::$comparisonList === [$allowedComparison], 'Old comparison sessions must drop outside images and labels.');
$compareRequest->params['id'] = 'representation:2000';
try { tadlGuardCatalogueCompareRequest($compareRequest); scopeCheck(false, 'New outside comparison was allowed.'); }
catch (TadlPublicNotFoundException $error) { scopeCheck(true, 'Outside comparison requests fail before native dispatch.'); }
$listingResult = new class([20,10], 'ca_collections') extends SearchResult {
    private int $position = -1;
    function nextHit() { return isset($this->ids[++$this->position]); }
    function getWithTemplate($template) { return 'Synthetic collection '.$this->ids[$this->position]; }
};
$listingView = new class($request, $listingResult) {
    function __construct(public $request, private $result) {}
    function getVar($key) { return ['lists'=>[1=>$this->result], 'typeInfo'=>[], 'listingInfo'=>['displayName'=>'Synthetic collections']][$key]; }
    function render() { ob_start(); include dirname(__DIR__).'/views/Listing/ca_collections_listing_subview_html.php'; return ob_get_clean(); }
};
$listing = $listingView->render();
scopeCheck(str_contains($listing, 'Synthetic collection 10') && !str_contains($listing, 'Synthetic collection 20'), 'Legacy listings must render only scoped collection links.');
try { tadlRequireCatalogueRecord($request, 'ca_objects', 200); scopeCheck(false, 'Direct outside record was allowed.'); }
catch (TadlPublicNotFoundException $error) { scopeCheck(tadlPublicErrorStatus($error) === 404, 'Direct outside records need the normal not-found response.'); }
scopeCheck(preg_match('/^tadl_catalogue_root_idno\s*=\s*tadl\s*$/m', file_get_contents(dirname(__DIR__).'/conf/app.conf')), 'The shipped TADL theme must enable its institution boundary.');
echo 'Catalogue scope passed: '.$checks.' assertions (actual read-only SQL, membership, native access, both media modes, shared authorities, facets, root failures and hierarchy changes).'.PHP_EOL;
