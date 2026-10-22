<?php
/** Real snapshot/cache helpers with synthetic native/SQLite boundaries; no app bootstrap. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) { return false; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$directory = sys_get_temp_dir().'/tadl-facet-cache-'.bin2hex(random_bytes(6));
mkdir($directory.'/Browse', 0700, true);
file_put_contents($directory.'/Browse/ObjectBrowse.php', '<?php');
file_put_contents($directory.'/Browse/BrowseEngine.php', '<?php // synthetic native source');
mkdir($directory.'/helpers');
foreach (['utilityHelpers','displayHelpers'] as $file) { file_put_contents($directory.'/helpers/'.$file.'.php', '<?php'); }
define('__CA_LIB_DIR__', $directory); define('__CA_APP_DIR__', $directory); define('__CA_BASE_DIR__', 'synthetic-site');
define('__CA_BUNDLE_ACCESS_READONLY__', 1);
register_shutdown_function(static function () use ($directory) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($directory);
});
class FacetRows {
    function __construct(private $result) {}
    function nextRow() { return (bool)($this->row = $this->result->fetch(PDO::FETCH_ASSOC)); }
    private $row;
    function getRow() { return $this->row; }
}
class FacetDb {
    public $pdo; public int $snapshots = 0; public bool $fail = false; public $duringRead = null;
    function __construct() { $this->pdo = new PDO('sqlite::memory:'); $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
    function query($sql) {
        $this->snapshots++;
        if ($this->fail) { throw new RuntimeException('Synthetic snapshot unavailable'); }
        if ($this->duringRead) { ($this->duringRead)(); }
        return new FacetRows($this->pdo->query($sql));
    }
}
class FacetConfig { function get($key) { return $GLOBALS['additional'] ?? false; } }
class FacetModel {
    function __construct(private $table) {}
    function primaryKey() { return array_key_first(Datamodel::$definitions[$this->table]); }
    function getFieldsArray() { return Datamodel::$definitions[$this->table]; }
    function hasField($name) { return isset(Datamodel::$definitions[$this->table][$name]); }
    function getAppConfig() { return new FacetConfig(); }
    function getProperty($name) { return null; }
    function getLabelTableName() { return $this->table === 'ca_entities' ? 'ca_entity_labels' : 'ca_list_item_labels'; }
}
class Datamodel {
    static $definitions = [];
    static function getInstanceByTableName($name, $unused) { return isset(self::$definitions[$name]) ? new FacetModel($name) : null; }
}
function caSourceAccessControlIsEnabled($model) { return false; }
function caGetTypeRestrictionsForUser($table) { return null; }
function caGetSourceRestrictionsForUser($table) { return null; }
function caACLIsEnabled($table, $options = null) { return $GLOBALS['acl'] ?? false; }
function caGetBundleAccessLevel($table, $bundle) { return $GLOBALS['bundle'] ?? 1; }
function caGetUserLocaleRules() { return [$GLOBALS['g_ui_locale']]; }
function caGetBlankLabelText($table) { return $GLOBALS['blankLabel'] ?? 'BLANK'; }
function caGetArticlesForLocale($locale) { return $GLOBALS['articles'] ?? []; }
class FacetRequest { function __construct(public bool $loggedIn = false) {} function isLoggedIn() { return $this->loggedIn; } }
class ExternalCache {
    static $values = []; static $fail = false; static $writes = 0;
    static function fetch($key, $namespace) {
        if (self::$fail) { throw new RuntimeException('Synthetic cache unavailable'); }
        return self::$values[$namespace][$key] ?? null;
    }
    static function save($key, $value, $namespace, $ttl) { self::$writes++; self::$values[$namespace][$key] = $value; }
}
class ObjectBrowse {
    protected $opo_db;
    static $computations = 0; static $mutateDuringNative = null; static $availabilityOverride = null;
    public $info = ['type'=>'authority','table'=>'ca_entities','relationship_table'=>'ca_objects_x_entities','groupings'=>['label'=>'Name']];
    public $ids; public $types = null; public $criteria = ['_search'=>['*'=>true]];
    function __construct($id = null, $context = '') { $this->opo_db = $GLOBALS['db']; $this->ids = range(1, 180); }
    function execute($options = null) { return true; }
    function getInfoForFacet($name) { return $this->info; }
    function getResults() { return new class($this->ids) { function __construct(private $ids) {} function getPrimaryKeyValues() { return $this->ids; } }; }
    function getCriteria() { return $this->criteria; }
    function getTypeRestrictionList() { return $this->types; }
    function getSourceRestrictionList() { return null; }
    function getResultFilters() { return []; }
    function getFacetContent($name, $options = null) {
        self::$computations++;
        if (self::$mutateDuringNative) { (self::$mutateDuringNative)(); }
        $rows = $this->opo_db->pdo->query('SELECT e.entity_id id, l.name label, COUNT(*) content_count
            FROM ca_entities e JOIN ca_entity_labels l USING(entity_id) JOIN ca_objects_x_entities r USING(entity_id)
            JOIN ca_objects o USING(object_id) WHERE e.access=1 AND e.deleted=0 AND o.access=1 AND o.deleted=0
            GROUP BY e.entity_id ORDER BY l.name')->fetchAll(PDO::FETCH_ASSOC);
        return !empty($options['checkAvailabilityOnly']) ? (self::$availabilityOverride ?? (bool)$rows) : $rows;
    }
}
$db = new FacetDb();
$schema = [
    'ca_objects'=>['object_id','access','deleted','type_id','view_count'],
    'ca_entities'=>['entity_id','access','deleted','type_id'],
    'ca_entity_labels'=>['label_id','entity_id','name','locale_id','is_preferred'],
    'ca_objects_x_entities'=>['relation_id','object_id','entity_id','type_id'],
    'ca_lists'=>['list_id','list_code'], 'ca_list_items'=>['item_id','list_id','access','deleted','is_enabled'],
    'ca_list_item_labels'=>['label_id','item_id','name','sdatetime','edatetime'],
    'ca_locales'=>['locale_id','name'], 'ca_relationship_types'=>['type_id','type_code'],
    'ca_relationship_type_labels'=>['label_id','type_id','name'],
];
foreach ($schema as $table=>$fields) {
    Datamodel::$definitions[$table] = array_fill_keys($fields, ['FIELD_TYPE'=>'synthetic']);
    $db->pdo->exec('CREATE TABLE '.$table.' ('.join(',', array_map(static fn($field) => $field.' '.($field === 'name' || str_ends_with($field, 'code') ? 'TEXT' : 'INTEGER'), $fields)).')');
}
unset(Datamodel::$definitions['ca_list_item_labels']['sdatetime'], Datamodel::$definitions['ca_list_item_labels']['edatetime']);
Datamodel::$definitions['ca_list_item_labels']['effective_date'] = ['START'=>'sdatetime','END'=>'edatetime'];
$db->pdo->exec("INSERT INTO ca_entities VALUES(1,1,0,1),(2,1,0,1); INSERT INTO ca_entity_labels VALUES(1,1,'Synthetic Alpha',1,1),(2,2,'Synthetic Beta',1,1)");
foreach (range(1,180) as $id) {
    $db->pdo->exec('INSERT INTO ca_objects VALUES('.$id.',1,0,1,0); INSERT INTO ca_objects_x_entities VALUES('.$id.','.$id.','.($id%2+1).',1)');
}
require dirname(__DIR__).'/helpers/catalogue_browse.php';
$g_ui_locale = 'en_US'; $assertions = 0;
function facetCheck($condition, $message) { $GLOBALS['assertions']++; if (!$condition) { throw new RuntimeException($message); } }
function facetBrowse($loggedIn = false) { return new TadlObjectBrowse(null, '', new FacetRequest($loggedIn)); }
$options = ['checkAccess'=>[1]];
$first = facetBrowse(); $first->execute();
facetCheck($first->getFacet('entity', $options+['checkAvailabilityOnly'=>true,'showAllForNoCriteriaBrowse'=>true]), 'Availability must remain boolean.');
$expected = $first->getFacet('entity', $options);
facetCheck(count($expected) === 2 && ObjectBrowse::$computations === 2, 'The native availability probe and full content should each compute once on a miss.');
facetCheck($first->getFacet('entity', $options+['start'=>1,'limit'=>1]) === [$expected[1]], 'Native pagination must preserve order.');
facetCheck(facetBrowse()->getFacet('entity', $options) === $expected && ObjectBrowse::$computations === 2, 'A fresh request should reuse unchanged native content.');
$db->pdo->exec("UPDATE ca_entity_labels SET name='Synthetic Renamed' WHERE entity_id=1");
$renamed = facetBrowse()->getFacet('entity', $options);
facetCheck(ObjectBrowse::$computations === 4 && $renamed !== $expected, 'A label edit must invalidate the cache immediately.');
$db->pdo->exec('UPDATE ca_entities SET access=0 WHERE entity_id=1');
facetCheck(count(facetBrowse()->getFacet('entity', $options)) === 1 && ObjectBrowse::$computations === 6, 'Private authorities must disappear without waiting for expiry.');
$db->pdo->exec('UPDATE ca_entities SET access=1 WHERE entity_id=1; UPDATE ca_objects SET deleted=1 WHERE object_id=1');
$deleted = facetBrowse()->getFacet('entity', $options);
facetCheck(array_sum(array_column($deleted,'content_count')) === 179 && ObjectBrowse::$computations === 8, 'Object deletion must update counts.');
$db->pdo->exec('DELETE FROM ca_objects_x_entities WHERE object_id=2');
facetCheck(array_sum(array_column(facetBrowse()->getFacet('entity',$options),'content_count')) === 178, 'Relationship removal must update counts.');
$before = ObjectBrowse::$computations; $db->pdo->exec('UPDATE ca_objects SET view_count=42');
facetBrowse()->getFacet('entity',$options);
facetCheck(ObjectBrowse::$computations === $before, 'View counters must not defeat otherwise unchanged facets.');
foreach (['access','deleted','is_enabled'] as $field) {
    $db->pdo->exec('INSERT INTO ca_list_items(item_id,'.$field.') VALUES('.(++$before).',1)');
    $count = ObjectBrowse::$computations; facetBrowse()->getFacet('entity',$options);
    facetCheck(ObjectBrowse::$computations === $count+2, 'List visibility/type changes must invalidate facets.');
}
foreach ([['g_ui_locale','fr_FR'],['articles',['the']],['blankLabel','Untitled']] as [$name,$value]) {
    $count=ObjectBrowse::$computations; $GLOBALS[$name]=$value; facetBrowse()->getFacet('entity',$options);
    facetCheck(ObjectBrowse::$computations === $count+2, 'Locale and collation inputs must isolate cached content.');
}
$count=ObjectBrowse::$computations; $restricted=facetBrowse(); $restricted->types=[2]; $restricted->getFacet('entity',$options);
facetCheck(ObjectBrowse::$computations === $count+2, 'Browse type restrictions must isolate cached content.');
foreach (['loggedIn','acl','additional','bundle','missingAccess','relative','template','attributeGrouping','small','filtered'] as $case) {
    $browse=facetBrowse($case === 'loggedIn'); $testOptions=$options;
    if ($case === 'acl' || $case === 'additional') { $GLOBALS[$case]=true; }
    if ($case === 'bundle') { $GLOBALS['bundle']=0; }
    if ($case === 'missingAccess') { $testOptions=[]; }
    if ($case === 'relative') { $browse->info['relative_to']='ca_collections'; }
    if ($case === 'template') { $browse->info['template']='^ca_entities.description'; }
    if ($case === 'attributeGrouping') { $browse->info['groupings']=['ca_attribute_123'=>'Synthetic']; }
    if ($case === 'filtered') { $browse->criteria['entity']=[1=>true]; }
    if ($case === 'small') { $browse->ids=[1,2]; }
    $count=ObjectBrowse::$computations; $writes=ExternalCache::$writes;
    $browse->getFacet('entity',$testOptions);
    facetCheck(ObjectBrowse::$computations === $count+1 && ExternalCache::$writes === $writes, $case.': unsupported scopes must retain native computation.');
    unset($GLOBALS['acl'],$GLOBALS['additional'],$GLOBALS['bundle']);
}
ExternalCache::$fail=true; $count=ObjectBrowse::$computations;
facetBrowse()->getFacet('entity',$options);
facetCheck(ObjectBrowse::$computations === $count+2, 'Cache outages must retain native content.'); ExternalCache::$fail=false;
$db->fail=true; $count=ObjectBrowse::$computations; facetBrowse()->getFacet('entity',$options);
facetCheck(ObjectBrowse::$computations === $count+1, 'Snapshot failures must retain native content.'); $db->fail=false;
ExternalCache::$values=[]; $writes=ExternalCache::$writes;
ObjectBrowse::$mutateDuringNative=static function () use ($db) { $db->pdo->exec("UPDATE ca_entity_labels SET name=name||' changed'"); };
facetBrowse()->getFacet('entity',$options); ObjectBrowse::$mutateDuringNative=null;
facetCheck(ExternalCache::$writes === $writes, 'Concurrent input changes must not publish an inconsistent cache entry.');
foreach ([null,[],['schema'=>1,'data'=>'bad'],['schema'=>2,'data'=>''],['schema'=>1,'data'=>base64_encode(gzcompress('not json'))],
    ['schema'=>1,'data'=>base64_encode(gzcompress('{"wrong":true}'))],
    ['schema'=>1,'data'=>base64_encode(gzcompress('[{"id":1,"label":[]} ]'))]] as $bad) {
    facetCheck(tadlDecodeFacetSnapshot($bad) === null, 'Malformed cache payload must be ignored.');
}
facetCheck(tadlDecodeFacetSnapshot(tadlEncodeFacetSnapshot(['content'=>$expected,'available'=>true])) === ['content'=>$expected,'available'=>true], 'Serialization must preserve ordered native facet values.');
facetCheck(tadlEncodeFacetSnapshot([str_repeat('x',17*1024*1024)]) === null, 'Oversized cache entries must be bounded.');
$db->pdo->exec('UPDATE ca_entities SET access=0');
ObjectBrowse::$availabilityOverride=true;
$empty=facetBrowse();
facetCheck($empty->getFacet('entity',$options) === [] && $empty->getFacet('entity',$options+['checkAvailabilityOnly'=>true]) === true, 'Native availability must not be derived from empty full content.');
facetCheck(facetBrowse()->getFacet('entity',$options+['checkAvailabilityOnly'=>true]) === true, 'Cached empty groups must preserve the independent native availability boolean.');
echo json_encode(['status'=>'passed','assertions'=>$assertions,'records'=>'synthetic SQLite only']),"\n";
