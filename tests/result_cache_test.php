<?php
/** Real result cache and browse adapter, synthetic native boundaries and in-memory SQL only. */
error_reporting(E_ALL);
set_error_handler(static function ($severity,$message,$file,$line) {
    if (!(error_reporting() & $severity)) { return false; }
    throw new ErrorException($message,0,$severity,$file,$line);
});
$directory = sys_get_temp_dir().'/tadl-result-cache-'.bin2hex(random_bytes(6));
foreach (['Browse','Search','helpers'] as $folder) { mkdir($directory.'/'.$folder,0700,true); }
foreach (['Browse/ObjectBrowse.php','Browse/BrowseEngine.php','BaseFindEngine.php','Search/SearchEngine.php','helpers/utilityHelpers.php','helpers/displayHelpers.php'] as $file) {
    file_put_contents($directory.'/'.$file,'<?php // synthetic native source');
}
define('__CA_LIB_DIR__',$directory); define('__CA_APP_DIR__',$directory); define('__CA_BASE_DIR__','synthetic-site');
define('__CA_BUNDLE_ACCESS_READONLY__',1);
define('pString',1);
$checks=0; $g_ui_locale='en_US';
function resultCheck($value,$message) { $GLOBALS['checks']++; if (!$value) { throw new RuntimeException($message); } }
class ResultRows {
    private array $row=[];
    function __construct(private array $rows) {}
    function nextRow() { $this->row=array_shift($this->rows) ?? []; return (bool)$this->row; }
    function get($key) { return $this->row[$key] ?? null; }
    function getRow() { return $this->row; }
}
class ResultConfig {
    function get($key) {
        if ($key==='disable_tadl_result_cache') { return $GLOBALS['disable_cache'] ?? false; }
        return $key==='db_type' ? 'mysqli' : ($GLOBALS['additional'] ?? false);
    }
    function toJson() { return json_encode(['synthetic_config'=>$GLOBALS['configuration'] ?? 1]); }
}
class Configuration { static function load($path) { return new ResultConfig(); } }
class ResultDb {
    public PDO $pdo; public bool $fail=false; public int $released=0;
    public bool $lockOwned=true; public $onLock=null; public int $mediaQueries=0;
    function __construct() {
        $this->pdo=class_exists('Pdo\\Sqlite') ? new Pdo\Sqlite('sqlite::memory:') : new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $register=method_exists($this->pdo,'createFunction') ? 'createFunction' : 'sqliteCreateFunction';
        $this->pdo->$register('SHA2',static fn($value,$bits)=>hash('sha256',$value ?? ''),2);
    }
    function getConfig() { return new ResultConfig(); }
    function query($sql,$params=[]) {
        if ($this->fail) { throw new RuntimeException('Synthetic SQL outage'); }
        if (str_contains($sql,'information_schema')) { return new ResultRows([]); }
        if (str_contains($sql,'GET_LOCK')) {
            if ($this->onLock) { ($this->onLock)(); }
            return new ResultRows([['acquired'=>(int)$this->lockOwned]]);
        }
        if (str_contains($sql,'RELEASE_LOCK')) { $this->released++; return new ResultRows([]); }
        if (str_contains($sql,'SELECT DISTINCT o.object_id candidate_id')) { $this->mediaQueries++; }
        $values=[];
        $sql=preg_replace_callback('/\?/',static function()use(&$params,&$values){
            $value=array_shift($params); $batch=is_array($value)?$value:[$value]; array_push($values,...$batch);
            return join(',',array_fill(0,count($batch),'?'));
        },$sql);
        $statement=$this->pdo->prepare($sql); $statement->execute($values);
        return new ResultRows($statement->fetchAll(PDO::FETCH_ASSOC));
    }
}
class ResultModel {
    function __construct(private string $table) {}
    function primaryKey() { return array_key_first(Datamodel::$definitions[$this->table]); }
    function getFieldsArray() { return Datamodel::$definitions[$this->table]; }
    function getAppConfig() { return new ResultConfig(); }
    function getProperty($key) { return null; }
    function getLabelTableName() { return Datamodel::$labels[$this->table]; }
    function getDb() { return $GLOBALS['db']; }
}
class Datamodel {
    static array $definitions=[]; static array $labels=[];
    static function getInstanceByTableName($name,$cached=false) { return new ResultModel($name); }
}
function caSourceAccessControlIsEnabled($model) { return false; }
function caGetTypeRestrictionsForUser($table) { return null; }
function caGetSourceRestrictionsForUser($table) { return null; }
function caACLIsEnabled($table,$options=null) { return $GLOBALS['acl'] ?? false; }
function caGetBundleAccessLevel($table,$bundle) { return $GLOBALS['bundle'] ?? 1; }
function caUserIsPrivileged($request) { return $request->privileged; }
function caGetUserAccessValues($request) { return $request->access; }
function caGetUserLocaleRules() { return [$GLOBALS['g_ui_locale']]; }
function caGetArticlesForLocale($locale) { return $GLOBALS['articles'] ?? []; }
function caGetBlankLabelText($table) { return $GLOBALS['blankLabel'] ?? 'Untitled'; }
function tadlMediaPreference($request) { return $request->media; }
class ResultRequest {
    public $config; public array $access=[1]; public string $media='all'; public bool $privileged=false; public int $user=0;
    function __construct() { $this->config=new ResultConfig(); }
    function isLoggedIn() { return $this->user>0; }
    function getUserID() { return $this->user; }
    function getParameter($key,$type) { return null; } function getAction() { return 'objects'; }
}
class ExternalCache {
    static array $values=[]; static array $ttls=[]; static array $expired=[]; static bool $fail=false;
    static function fetch($key,$namespace) {
        if (self::$fail) { throw new RuntimeException('Synthetic cache outage'); }
        return empty(self::$expired[$key]) ? (self::$values[$namespace][$key] ?? null) : null;
    }
    static function save($key,$value,$namespace,$ttl) {
        if (self::$fail) { throw new RuntimeException('Synthetic cache outage'); }
        self::$values[$namespace][$key]=$value; self::$ttls[]=$ttl;
    }
}
class BrowseCache {
    static array $stored=[]; public array $parameters=[]; public array $ids=[]; public array $facets=[];
    function __construct($key=null) { if ($key && isset(self::$stored[$key])) { foreach (get_object_vars(self::$stored[$key]) as $name=>$value) { $this->$name=$value; } } }
    function getResults() { return $this->ids; }
    function getParameters() { return $this->parameters; }
    function getFacets() { return $this->facets; }
    function getTypeRestrictions() { return []; } function getSourceRestrictions() { return []; }
    function setParameter($name,$value) { $this->parameters[$name]=$value; }
    function setResults($ids) { $this->ids=$ids; }
    function setTypeRestrictions($types) {} function setSourceRestrictions($sources) {}
    function setFacets(&$facets) { $this->facets=$facets; }
    function getCacheKey() { return hash('sha256',serialize($this->parameters['criteria'])); }
    function save() { self::$stored[$this->getCacheKey()]=clone $this; }
}
class WLPlugSearchEngineBrowseEngine { function __construct(public array $ids,$unused,$table) {} }
class SearchResult {
    public array $ids=[];
    function init($engine,$unused,$options) { $this->ids=$engine->ids; }
    function getPrimaryKeyValues($limit=null) { return $this->ids; }
    function tableName() { return 'ca_objects'; } function tableNum() { return 57; }
    function getDb() { return $GLOBALS['db']; }
}
class ObjectBrowseResult extends SearchResult {}
function tadlMediaAuthorityBrowseTable($request,$type) { return null; }
function caGetBrowseInstance($table) { return new ResultCountBrowse(); }
class ResultCountBrowse {
    static int $executions=0; private array $ids=[];
    function setContext($context) {} function addFacetConfiguration($name,$info) {}
    function addCriteria($name,$values) { return true; }
    function addResultFilter($field,$operator,$ids) { $this->ids=explode(',',$ids); return true; }
    function execute($options) { self::$executions++; return true; }
    function getFacetContent($name,$options) { return [['id'=>1,'content_count'=>count($this->ids)]]; }
}
class ObjectBrowse {
    static int $executions=0; static int $sorts=0; static bool $reloadFails=false; static bool $reloadRace=false;
    protected $searched_terms=[]; protected $seach_result_desc=[]; protected $opo_db;
    private BrowseCache $cache; private string $context='';
    public array $criteria=['_search'=>['*'=>true]];
    function __construct($id=null,$context='') { $this->cache=new BrowseCache(); $this->context=$context; $this->opo_db=$GLOBALS['db']; }
    function getSubject() { return 57; } function getContext() { return $this->context; }
    function setContext($context) { $this->context=$context; }
    function getCriteria() { return $this->criteria; }
    function getTypeRestrictionList() { return []; } function getSourceRestrictionList() { return []; }
    function getResultFilters() { return []; } function getInfoForFacets() { return []; }
    function execute($options=null) {
        self::$executions++;
        $this->cache->parameters=['criteria'=>$this->criteria,'context'=>$this->context,'table_num'=>57];
        $this->cache->ids=array_map('intval',$this->opo_db->pdo->query('SELECT object_id FROM ca_objects WHERE access=1 AND deleted=0 ORDER BY object_id')->fetchAll(PDO::FETCH_COLUMN));
        if ($this->criteria!==['_search'=>['*'=>true]]) { $this->cache->ids=array_slice($this->cache->ids,0,$GLOBALS['searchSize'] ?? 5); }
        $this->cache->save(); return true;
    }
    function getBrowseID() { return $this->cache->getCacheKey(); }
    function reload($key) {
        if (self::$reloadFails) { return false; }
        $this->cache=new BrowseCache($key); $this->criteria=$this->cache->parameters['criteria'];
        if (self::$reloadRace) { $this->cache->ids=[999999]; }
        return true;
    }
    function removeAllCriteria() { $this->criteria=[]; }
    function addCriteria($name,$values) { $this->criteria[$name]=array_fill_keys($values,true); }
    function getResults($options=null) {
        $ids=$this->cache->ids;
        if (!empty($options['sort'])) {
            self::$sorts++; sort($ids,SORT_NUMERIC);
            if (($options['sort_direction'] ?? '')==='desc') { $ids=array_reverse($ids); }
        }
        $result=new ObjectBrowseResult(); $result->ids=$ids; return $result;
    }
}
$db=new ResultDb();
$schema=[
    'ca_objects'=>['object_id','access','deleted','type_id','view_count'], 'ca_object_representations'=>['representation_id','access','deleted','media'],
    'ca_lists'=>['list_id','list_code'],'ca_list_items'=>['item_id','list_id','access','deleted','is_enabled'],
    'ca_locales'=>['locale_id','name'],'ca_relationship_types'=>['type_id','type_code'],
    'ca_relationship_type_labels'=>['label_id','type_id','name'],
];
foreach (['ca_objects'=>'ca_object_labels','ca_entities'=>'ca_entity_labels','ca_places'=>'ca_place_labels','ca_occurrences'=>'ca_occurrence_labels','ca_collections'=>'ca_collection_labels','ca_list_items'=>'ca_list_item_labels'] as $table=>$label) {
    $key=['ca_objects'=>'object_id','ca_entities'=>'entity_id','ca_places'=>'place_id','ca_occurrences'=>'occurrence_id','ca_collections'=>'collection_id','ca_list_items'=>'item_id'][$table];
    $schema[$table] ??= [$key,'access','deleted','type_id','parent_id','hier_left','hier_right'];
    $schema[$label]=['label_id',$key,'name']; Datamodel::$labels[$table]=$label;
}
foreach (['ca_objects_x_entities'=>'entity_id','ca_objects_x_places'=>'place_id','ca_objects_x_occurrences'=>'occurrence_id','ca_objects_x_collections'=>'collection_id','ca_objects_x_vocabulary_terms'=>'item_id','ca_objects_x_object_representations'=>'representation_id'] as $table=>$key) {
    $schema[$table]=['relation_id','object_id',$key,'type_id'];
}
foreach ($schema as $table=>$fields) {
    Datamodel::$definitions[$table]=array_fill_keys($fields,[]);
    $db->pdo->exec('CREATE TABLE '.$table.' ('.join(',',array_map(static fn($field)=>$field.' '.(in_array($field,['name','list_code','type_code','media'])?'TEXT':'INTEGER'),$fields)).')');
}
$db->pdo->exec('CREATE TABLE ca_change_log (log_id INTEGER PRIMARY KEY)');
foreach (range(1,180) as $id) { $db->pdo->exec('INSERT INTO ca_objects VALUES('.$id.',1,0,1,0)'); }
$db->pdo->exec("INSERT INTO ca_object_representations VALUES(1,1,0,'synthetic-descriptor'); INSERT INTO ca_entities VALUES(1,1,0,1,0,1,2)");
require dirname(__DIR__).'/helpers/catalogue_browse.php';
function resultBrowse($request=null) { $request ??= new ResultRequest(); $browse=new TadlObjectBrowse(null,'synthetic-browse',$request); $browse->execute(); return [$browse,$request]; }
function cacheKey() { return (new TadlResultCache($GLOBALS['db'],new ResultRequest()))->key('synthetic',[]); }
try {
    [$browse,$request]=resultBrowse(); $cache=tadlResultCache($request);
    $asc=['sort'=>'Identifier','sort_direction'=>'asc'];
    resultCheck($browse->getResults($asc)->ids===range(1,180),'Cold browse must preserve every native result.');
    $cache->flush(); $executions=ObjectBrowse::$executions; $sorts=ObjectBrowse::$sorts;
    [$warm,$warmRequest]=resultBrowse();
    resultCheck(ObjectBrowse::$executions===$executions,'A new request must reuse wildcard execution.');
    resultCheck($warm->getResults($asc+['start'=>72,'limit'=>18])->ids===range(73,90) && ObjectBrowse::$sorts===$sorts,'Another page must reuse the complete sorted set and slice afterwards.');
    resultCheck($warm->getResults()->ids===range(1,180),'Implicit ordering must follow the most recent explicit order.');
    resultCheck($warm->getResults(['sort'=>'Identifier','sort_direction'=>'desc'])->ids===array_reverse(range(1,180)),'Sort directions must have separate ordered entries.');
    tadlResultCache($warmRequest)->flush();
    resultCheck(!array_diff(ExternalCache::$ttls,[3600]) && $db->released>0,'All result entries need one-hour expiry and released worker locks.');
    foreach ([['UPDATE ca_objects SET access=0 WHERE object_id=1',179],['UPDATE ca_objects SET deleted=1 WHERE object_id=2',178]] as [$sql,$total]) {
        $db->pdo->exec($sql); [$changed,$changedRequest]=resultBrowse();
        resultCheck(count($changed->getResults($asc)->ids)===$total && ObjectBrowse::$executions>$executions,'Direct visibility edits must invalidate cached result IDs immediately.');
        tadlResultCache($changedRequest)->flush(); $executions=ObjectBrowse::$executions;
    }
    foreach (["UPDATE ca_object_representations SET access=0","UPDATE ca_object_representations SET deleted=1","UPDATE ca_object_representations SET media='changed synthetic descriptor'","UPDATE ca_entities SET access=0","INSERT INTO ca_entity_labels VALUES(1,1,'Synthetic renamed')","INSERT INTO ca_objects_x_entities VALUES(1,3,1,1)","INSERT INTO ca_list_items VALUES(1,1,1,0,1)","INSERT INTO ca_change_log VALUES(9)","INSERT INTO ca_change_log VALUES(2)"] as $sql) {
        $before=cacheKey(); $db->pdo->exec($sql);
        resultCheck(cacheKey()!==$before,'Each security/media/label/relationship/native-log edit must change the revision, including an older committed log ID.');
    }
    $before=cacheKey(); $db->pdo->exec('UPDATE ca_objects SET view_count=20');
    resultCheck(cacheKey()===$before,'View counters must not invalidate shared data.');
    $scope=new TadlResultCache($db,new ResultRequest()); $base=$scope->key('synthetic',[]);
    foreach (['access','media','locale','config','articles','blank'] as $variant) {
        $r=new ResultRequest(); if ($variant==='access') { $r->access=[1,2]; } if ($variant==='media') { $r->media='only'; }
        if ($variant==='locale') { $g_ui_locale='fr_FR'; } if ($variant==='config') { $GLOBALS['configuration']=2; }
        if ($variant==='articles') { $GLOBALS['articles']=['the']; } if ($variant==='blank') { $GLOBALS['blankLabel']='Synthetic blank'; }
        resultCheck((new TadlResultCache($db,$r))->key('synthetic',[])!==$base,'Access/media/locale/configuration and label settings must isolate keys.');
        $g_ui_locale='en_US'; unset($GLOBALS['configuration'],$GLOBALS['articles'],$GLOBALS['blankLabel']);
    }
    resultCheck($scope->key('other',[])!==$base && $scope->key('synthetic',['other'])!==$base,'Cache purpose and exact input IDs/options must isolate values.');
    $compute=0; $valid='tadlCachedIDsValid';
    $scope->remember('synthetic',[1],function()use(&$compute){$compute++;return [3,4];},$valid); $scope->flush();
    $next=new TadlResultCache($db,new ResultRequest());
    resultCheck($next->remember('synthetic',[1],function()use(&$compute){$compute++;return [3,4];},$valid)===[3,4] && $compute===1,'Independent requests must reuse data through the native backend.');
    $key=$next->key('synthetic',[1]); ExternalCache::$values['tadlResultData'][$key]=['schema'=>1,'data'=>base64_encode(gzcompress('{"value":[3,3]}'))];
    $fresh=new TadlResultCache($db,new ResultRequest()); $fresh->remember('synthetic',[1],function()use(&$compute){$compute++;return [3,4];},$valid);
    resultCheck($compute===2,'Malformed but decodable IDs must trigger native recomputation.');
    $fresh->flush(); ExternalCache::$expired[$key]=true;
    (new TadlResultCache($db,new ResultRequest()))->remember('synthetic',[1],function()use(&$compute){$compute++;return [3,4];},$valid);
    resultCheck($compute===3,'Expired entries must recompute through the native path.'); unset(ExternalCache::$expired[$key]);
    $contended=new TadlResultCache($db,new ResultRequest()); $contendedKey=$contended->key('another_worker',[]);
    $db->lockOwned=false; $db->onLock=static function()use($contendedKey){
        ExternalCache::$values['tadlResultData'][$contendedKey]=['schema'=>1,'data'=>base64_encode(gzcompress('{"value":[3,4]}'))];
    };
    resultCheck($contended->remember('another_worker',[],static function(){throw new RuntimeException('Duplicate native computation');},$valid)===[3,4],'A contending worker must reuse the owner\'s published entry.');
    $db->lockOwned=true; $db->onLock=null;
    $stale=new TadlResultCache($db,new ResultRequest()); $staleKey=$stale->key('during',[]);
    $stale->remember('during',[],static fn()=>[3],$valid); $db->pdo->exec('INSERT INTO ca_change_log VALUES(10)'); $stale->flush();
    resultCheck(!isset(ExternalCache::$values['tadlResultData'][$staleKey]),'A concurrent catalogue change must prevent publication.');
    ExternalCache::$fail=true; $outage=new TadlResultCache($db,new ResultRequest());
    resultCheck($outage->remember('outage',[],static fn()=>[3],$valid)===[3],'Cache outages must return native results.'); $outage->flush(); ExternalCache::$fail=false;
    foreach (['user','privileged','access','acl','bundle','additional','database','disable_cache'] as $case) {
        $r=new ResultRequest(); if ($case==='user') { $r->user=1; } if ($case==='privileged') { $r->privileged=true; } if ($case==='access') { $r->access=[]; }
        if (in_array($case,['acl','additional','disable_cache'],true)) { $GLOBALS[$case]=true; } if ($case==='bundle') { $GLOBALS['bundle']=0; } if ($case==='database') { $db->fail=true; }
        resultCheck(tadlResultCache($r)===null,'Unsupported/restricted/unavailable scopes must bypass the cache.');
        unset($GLOBALS['acl'],$GLOBALS['bundle'],$GLOBALS['additional'],$GLOBALS['disable_cache']); $db->fail=false;
    }
    foreach ([false,true] as $raced) {
        [$seed,$r]=resultBrowse(); $seed->getResults($asc); tadlResultCache($r)->flush();
        ObjectBrowse::$reloadFails=!$raced; ObjectBrowse::$reloadRace=$raced; $before=ObjectBrowse::$executions;
        [$fallback,$r]=resultBrowse();
        resultCheck(ObjectBrowse::$executions===$before+1 && !in_array(999999,$fallback->getResults($asc)->ids,true),'Hydration failure or a native-cache overwrite must fall back without leaking foreign IDs.');
        ObjectBrowse::$reloadFails=ObjectBrowse::$reloadRace=false;
    }
    $r=new ResultRequest(); $search=new TadlObjectBrowse(null,'synthetic-search',$r); $search->criteria=['_search'=>['synthetic'=>true]];
    $search->execute(); resultCheck(count($search->getResults($asc)->ids)===5,'Full-text search must retain native execution.'); tadlResultCache($r)->flush();
    $r=new ResultRequest(); $search=new TadlObjectBrowse(null,'synthetic-search',$r); $search->criteria=['_search'=>['synthetic'=>true]]; $GLOBALS['searchSize']=6; $before=ObjectBrowse::$executions;
    $search->execute(); resultCheck(ObjectBrowse::$executions===$before+1 && count($search->getResults($asc)->ids)===6,'Search-index changes without metadata writes must scope ordered caches to freshly executed IDs.');
    require dirname(__DIR__).'/helpers/media_filter_helpers.php';
    $db->pdo->exec("UPDATE ca_object_representations SET access=1,deleted=0,media='synthetic-usable'; INSERT INTO ca_objects_x_object_representations VALUES(1,3,1,1)");
    $decoder=hash_file('sha256',dirname(__DIR__).'/helpers/media_filter_helpers.php').hash_file('sha256',dirname(__DIR__).'/helpers/media_usability.php');
    $bucket=hash('sha256',__CA_BASE_DIR__.':media-usability-bucket-v2:'.$decoder.':0');
    ExternalCache::$values['tadlMediaUsability'][$bucket]=['entries'=>[1=>['fingerprint'=>hash('sha256','synthetic-usable'),'usable'=>true]]];
    for ($pass=0;$pass<2;$pass++) {
        $r=new ResultRequest(); $r->media='only'; $result=new ObjectBrowseResult(); $result->ids=range(3,180);
        resultCheck(tadlFilterMediaResult($r,$result)->ids===[3],'Media filtering must retain only eligible objects, in original order.');
        $items=[['id'=>1,'label'=>'Synthetic type','content_count'=>178]];
        $counts=tadlMediaCountedFacet($r,$items,['type'=>'fieldList','field'=>'type_id'],new ObjectBrowse(),'type_facet');
        resultCheck(array_column($counts,'content_count','id')===[1=>1],'Shared adjusted counts must still match the displayed cohort.');
        tadlResultCache($r)->flush();
    }
    resultCheck($db->mediaQueries===1 && ResultCountBrowse::$executions===1,'The next anonymous request must reuse eligibility and adjusted counts without their native SQL.');
    resultCheck(!tadlCachedIDsValid([0]) && !tadlCachedIDsValid(['1 OR 1=1']) && !tadlCachedIDsValid([2=>3]),'Invalid ID shapes must be refused.');
    // Flush before removing synthetic source files; registered request shutdowns then have no work.
    foreach (ExternalCache::$values as $namespace=>$values) { resultCheck(in_array($namespace,['tadlResultData','tadlMediaUsability'],true),'No page, request, session or rendered-HTML cache is introduced.'); }
    echo json_encode(['status'=>'passed','assertions'=>$checks,'records'=>'synthetic SQLite only']).PHP_EOL;
} finally {
    // Pending request instances run before this cleanup on normal shutdown.
    register_shutdown_function(static function()use($directory) {
        $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());} rmdir($directory);
    });
}
