<?php
/** Actual place-scope SQL and controller; synthetic native access/search boundaries. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
define('pInteger', 1); define('pString', 2); define('__CA_BUNDLE_ACCESS_READONLY__', 1);
$checks = 0; $deniedBundles = []; $aclDenied = []; $allowedTypes = null; $typeChecking = false; $searchBase = [];
function placeCheck($value, $message) { $GLOBALS['checks']++; if (!$value) { throw new RuntimeException($message); } }
function caGetUserAccessValues($request) { return $request->access; }
function caGetBundleAccessLevel($table, $bundle) { return in_array($table.':'.$bundle, $GLOBALS['deniedBundles'], true) ? 0 : 1; }
function caGetTypeRestrictionsForUser($table) { return $GLOBALS['allowedTypes']; }
function caGetSourceRestrictionsForUser($table) { return null; }
function caSourceAccessControlIsEnabled($model) { return false; }
function caACLIsEnabled($model, $options) { placeCheck($options === ['forPawtucket'=>true], 'Place scopes must honor front-end ACLs.'); return (bool)$GLOBALS['aclDenied']; }
function caGetBrowseInstance($table) { return new PlaceBrowse(); }
function caGetBrowseConfig() { return new class { function getAssoc($key) { return ['objects'=>['table'=>'ca_objects','sortBy'=>['Identifier'=>'ca_objects.idno','Title'=>'ca_object_labels.name']]]; } }; }
function caGetSearchConfig() { return new class { function get($key) { return $GLOBALS['searchBase']; } }; }
class PlaceBrowse {
    static array $executed = []; public array $criteria = []; public array $filters = []; public array $types = []; public string $context = '';
    function filterHitsByACL($ids, $table, $user) { return array_values(array_diff($ids, $GLOBALS['aclDenied'])); }
    function setContext($value) { $this->context=$value; }
    function addCriteria($facet, $values) { $this->criteria[$facet]=$values; return true; }
    function addResultFilter($field, $operator, $value) { $this->filters[]=[$field,$operator,$value]; return true; }
    function setTypeRestrictions($types, $options) { $this->types=[$types,$options]; }
    function execute($options) { self::$executed[]=[$this,$options]; return true; }
    function getResults($options) { return (object)['browse'=>$this,'sort'=>$options]; }
}
class PlaceDB {
    static PDO $pdo; static array $queries = [];
    function query($sql, $params) {
        self::$queries[] = [$sql,$params]; $values = []; $index = 0;
        $sql = preg_replace_callback('/\?/', static function () use ($params, &$values, &$index) { $batch = (array)$params[$index++]; array_push($values, ...$batch); return join(',', array_fill(0, count($batch), '?')); }, $sql);
        $statement = self::$pdo->prepare($sql); $statement->execute($values);
        return new class($statement) {
            private array $row = [];
            function __construct(private PDOStatement $statement) {}
            function nextRow() { $this->row = $this->statement->fetch(PDO::FETCH_ASSOC) ?: []; return (bool)$this->row; }
            function get($key) { return $this->row[$key] ?? null; }
        };
    }
}
class PlaceModel {
    function __construct(private string $table) {}
    function getDb() { return new PlaceDB(); }
    function getAppConfig() { return new class { function get($key) { return $GLOBALS['typeChecking']; } }; }
    function getTypeFieldName() { return 'type_id'; }
    function tableNum() { return 72; }
    function load($id) { $query = PlaceDB::$pdo->prepare('SELECT place_id FROM ca_places WHERE place_id=?'); $query->execute([$id]); return (bool)$query->fetchColumn(); }
}
class Datamodel {
    static function getInstanceByTableName($table, $cached = false) { placeCheck(in_array($table,['ca_places','ca_objects'],true) && !$cached, 'Place scopes must not mutate a shared loaded model.'); return new PlaceModel($table); }
    static function getInstance($table, $cached = false) { return self::getInstanceByTableName($table, $cached); }
}
class PlaceRequest {
    public array $access = [1]; public object $config; public object $user;
    function __construct(public array $params = [], public string $method = 'GET', public bool $requiresLogin = false) {
        $this->config = new class($requiresLogin) { function __construct(private bool $required) {} function get($key) { return $this->required; } };
        $this->user = new class { function getTypeAccessLevel($table,$id) { return $id === 2 ? 0 : 1; } };
    }
    function getUserID() { return 7; }
    function getController() { return $this->params['controller'] ?? 'PlaceContents'; }
    function getAction() { return $this->params['action'] ?? 'Objects'; }
    function isLoggedIn() { return false; }
    function getRequestMethod() { return $this->method; }
    function getParameter($key,$type) { return $this->params[$key] ?? null; }
    function setParameter($key,$value,$source) { placeCheck($source === 'GET', 'Scope must replace stale caller state.'); $this->params[$key]=$value; }
}
class PlaceResponse {
    public int $code=200; public array $headers=[];
    function setHTTPResponseCode($code,$message) { $this->code=$code; }
    function addHeader($key,$value) { $this->headers[$key]=$value; }
}
class SearchController {
    public array $calls=[];
    function __construct(public $request,public $response) {}
    function __call($action,$args) {
        $result=null; $info=caGetBrowseConfig()->getAssoc('browseTypes')['objects'];
        // Native SearchController consumes the result by reference at this hook.
        $GLOBALS['pluginRequest']=$this->request;
        (new tadlPlaceContentsPlugin())->hookReplaceSearch(['search'=>$action,'browseInfo'=>&$info,'result'=>&$result]);
        $this->calls[]=[$action,$args,$this->request->params,$result];
    }
}
class BaseApplicationPlugin { function getRequest() { return $GLOBALS['pluginRequest'] ?? null; } }
class ApplicationPluginManager {
    static bool $available = true;
    static function checkPluginStatus($name) { return ['available'=>self::$available]; }
}
$directory = sys_get_temp_dir().'/tadl-places-'.bin2hex(random_bytes(8)); mkdir($directory.'/controllers',0700,true);
file_put_contents($directory.'/controllers/SearchController.php','<?php // Synthetic parent defined by the test.'); define('__CA_APP_DIR__',$directory);
PlaceDB::$pdo = new PDO('sqlite::memory:'); PlaceDB::$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
PlaceDB::$pdo->exec('CREATE TABLE ca_places (place_id INTEGER PRIMARY KEY,parent_id INTEGER,hierarchy_id INTEGER,hier_left INTEGER,hier_right INTEGER,access INTEGER DEFAULT 1,deleted INTEGER DEFAULT 0,type_id INTEGER DEFAULT 1)');
PlaceDB::$pdo->exec('CREATE INDEX place_hierarchy ON ca_places(hierarchy_id,hier_left,hier_right)');
PlaceDB::$pdo->exec('INSERT INTO ca_places VALUES (1,0,1,1,20,1,0,1),(2,1,1,2,7,1,0,1),(3,2,1,3,4,1,0,1),(4,1,1,8,11,0,0,1),(5,4,1,9,10,1,0,1),(6,1,1,12,15,1,1,1),(7,6,1,13,14,1,0,1),(8,0,2,1,20,1,0,1),(9,8,2,2,3,1,0,1),(10,1,1,16,19,1,0,2),(11,10,1,17,18,1,0,1),(12,0,0,0,0,1,0,1)');
require dirname(__DIR__).'/controllers/PlaceContentsController.php';
require dirname(__DIR__).'/plugins/tadlPlaceContents/tadlPlaceContentsPlugin.php';
try {
    $request=new PlaceRequest();
    placeCheck(tadlPlaceScopes($request,[1,8,12,0,'1 OR 1=1']) === [1=>[1,2,3,10,11],8=>[8,9],12=>[12]], 'Deep places must stay inside their hierarchy and prune private/deleted ancestors; unindexed roots retain self.');
    $GLOBALS['aclDenied']=[2]; placeCheck(tadlPlaceScopes($request,[1])[1] === [1,10,11], 'ACL-hidden parents must prune their complete branch.');
    $GLOBALS['aclDenied']=[1]; placeCheck(tadlPlaceScopes($request,[1]) === [], 'ACL-hidden roots must fail closed.');
    $GLOBALS['aclDenied']=[]; $GLOBALS['typeChecking']=true; placeCheck(tadlPlaceScopes($request,[1])[1] === [1,2,3], 'Denied per-type parents must prune their descendants.');
    $GLOBALS['typeChecking']=false; $GLOBALS['allowedTypes']=[2]; placeCheck(tadlPlaceScopes($request,[1]) === [], 'Root type restrictions must be enforced.');
    $GLOBALS['allowedTypes']=null; $GLOBALS['deniedBundles']=['ca_places:hierarchy']; placeCheck(tadlPlaceScopes($request,[1])[1] === [1], 'Denied hierarchy must retain only direct items.');
    $GLOBALS['deniedBundles']=['ca_places:ca_objects']; placeCheck(tadlPlaceScopes($request,[1]) === [], 'Denied relationship bundle must expose no items.'); $GLOBALS['deniedBundles']=[];
    PlaceDB::$pdo->exec('CREATE TABLE ca_objects (object_id INTEGER PRIMARY KEY,access INTEGER,deleted INTEGER,type_id INTEGER DEFAULT 1)');
    PlaceDB::$pdo->exec('CREATE TABLE ca_objects_x_places (object_id INTEGER,place_id INTEGER)');
    PlaceDB::$pdo->exec('CREATE INDEX object_places ON ca_objects_x_places(place_id,object_id)');
    PlaceDB::$pdo->exec('INSERT INTO ca_objects(object_id,access,deleted) VALUES (100,1,0),(101,1,0),(102,1,0),(103,0,0),(104,1,1),(105,1,0)');
    PlaceDB::$pdo->exec('INSERT INTO ca_objects_x_places VALUES (100,1),(100,3),(101,2),(102,3),(103,3),(104,1),(105,5)');
    $scope=tadlPlaceScopes($request,[1])[1];
    placeCheck(tadlPlaceObjectIDs($request,$scope) === [100,101,102], 'Direct and deep objects must be unique; private/deleted/hidden-branch objects must stay excluded.');
    placeCheck(tadlPlaceObjectIDs($request,[]) === [] && tadlPlaceObjectIDs($request,[-1,'1 OR 1=1']) === [], 'Empty or invalid scopes must fail closed.');
    $GLOBALS['aclDenied']=[101]; placeCheck(tadlPlaceObjectIDs($request,$scope)===[100,102], 'Object Pawtucket ACLs must be applied before native results/counts.'); $GLOBALS['aclDenied']=[];
    $GLOBALS['deniedBundles']=['ca_objects:ca_places']; placeCheck(tadlPlaceObjectIDs($request,$scope)===[], 'Denied object relationships must expose no items.'); $GLOBALS['deniedBundles']=[];
    $request=new PlaceRequest(['place_id'=>1,'view'=>'list','sort'=>'Title','direction'=>'desc','s'=>24,'search'=>'*','key'=>'stale','facet'=>'_search','id'=>'*']);
    $response=new PlaceResponse(); $controller=new PlaceContentsController($request,$response); $controller->Objects(); $params=$controller->calls[0][2];
    placeCheck($params['search'] === 'place_id:1' && $params['view']==='list' && $params['s']===24 && $params['sort']==='Title' && $params['direction']==='desc' && $params['n']===24, 'Place controller must delegate a short root reference, paging, view and sorting.');
    $result=$controller->calls[0][3]; [$browse,$options]=end(PlaceBrowse::$executed);
    placeCheck($result->browse===$browse && $browse->criteria===['_search'=>['*']] && $browse->filters===[['ca_objects.object_id','in','100,101,102']], 'Native replacement must use the exact relationship IDs, never a compound Lucene search.');
    placeCheck($options['noCache'] && $options['dontCheckFacetAvailability'] && $options['request']===$request && $options['checkAccess']===[1] && str_starts_with($browse->context,'tadl_place_contents:1:7:'), 'Native access must remain request-scoped without result-cache reuse or unused facets.');
    placeCheck($result->sort===['sort'=>'ca_object_labels.name','sort_direction'=>'desc'], 'Native sort must run on all scoped results before paging.');
    placeCheck($params['key']==='' && $params['facet']==='' && $params['id']==='' && $params['_advanced']===0 && $response->headers===['Cache-Control'=>'private, no-store','X-Robots-Tag'=>'noindex, follow'], 'Stale queries/refinements must not escape the scope; responses must be private/nonindexable.');
    foreach ([0=>400,999=>404,4=>404,6=>404] as $id=>$code) { $response=new PlaceResponse(); $controller=new PlaceContentsController(new PlaceRequest(['place_id'=>$id]),$response); $controller->Objects(); placeCheck($response->code===$code && !$controller->calls,'Invalid/private/deleted roots must fail before search.'); }
    $response=new PlaceResponse(); $controller=new PlaceContentsController(new PlaceRequest(['place_id'=>1],'POST'),$response); $controller->Objects(); placeCheck($response->code===405 && !$controller->calls,'Place results must use GET.');
    $controller=new PlaceContentsController(new PlaceRequest(['place_id'=>1],'GET',true),new PlaceResponse()); $controller->Objects(); placeCheck(!$controller->calls,'Required site login must remain enforced.');
    ApplicationPluginManager::$available=false; $failedClosed=false;
    try { (new PlaceContentsController(new PlaceRequest(['place_id'=>1]),new PlaceResponse()))->Objects(); } catch (RuntimeException $e) { $failedClosed=true; }
    placeCheck($failedClosed,'An incomplete plugin deployment must fail closed, never fall back to a different scope.'); ApplicationPluginManager::$available=true;
    $GLOBALS['deniedBundles']=['ca_places:ca_objects']; $empty=tadlPlaceContentsResult(new PlaceRequest(['place_id'=>1]),['sortBy'=>['Identifier'=>'ca_objects.idno']]);
    placeCheck($empty->browse->filters===[['ca_objects.object_id','in','0']], 'An empty/denied scope must use an impossible filter, never an unconstrained wildcard.'); $GLOBALS['deniedBundles']=[];
    $GLOBALS['searchBase']=['ca_objects'=>['configured_facet'=>['base']]];
    $configured=tadlPlaceContentsResult(new PlaceRequest(['place_id'=>1]),['sortBy'=>['Identifier'=>'ca_objects.idno'],'restrictToTypes'=>['image'],'dontExpandTypesHierarchically'=>true,'dontShowChildren'=>true,'omitChildRecords'=>true,'baseCriteria'=>['another_facet'=>['value']]]);
    placeCheck($configured->browse->criteria===['_search'=>['*'],'configured_facet'=>['base'],'another_facet'=>['value']] && $configured->browse->types===[['image'],['dontExpandHierarchically'=>true]] && end($configured->browse->filters)===['ca_objects.parent_id','is','null'] && end(PlaceBrowse::$executed)[1]['rootRecordsOnly'], 'Configured native type/base criteria/child restrictions must be retained.'); $GLOBALS['searchBase']=[];
    $plugin=new tadlPlaceContentsPlugin(); $unchanged=(object)['sentinel'=>true];
    foreach ([null,new PlaceRequest(['controller'=>'Search']),new PlaceRequest(['action'=>'other'])] as $unrelated) {
        $GLOBALS['pluginRequest']=$unrelated; $params=['search'=>'objects','browseInfo'=>['table'=>'ca_objects'],'result'=>&$unchanged];
        $plugin->hookReplaceSearch($params); placeCheck($unchanged->sentinel,'Other routes and non-request includes must remain untouched.');
    }
    $insert=PlaceDB::$pdo->prepare('INSERT INTO ca_places(place_id,parent_id,hierarchy_id,hier_left,hier_right) VALUES (?,0,?,1,2)'); foreach (range(1000,1601) as $id) { $insert->execute([$id,$id]); }
    PlaceDB::$queries=[]; $scopes=tadlPlaceScopes(new PlaceRequest(),range(1000,1601)); placeCheck(count($scopes)===602 && $scopes[1601]===[1601] && count(PlaceDB::$queries)===6,'Wide browse scopes must batch permission/hierarchy reads instead of querying per place.');
    $plan=PlaceDB::$pdo->query('EXPLAIN QUERY PLAN SELECT place_id FROM ca_places WHERE hierarchy_id=1 AND hier_left>=1 AND hier_right<=20')->fetchAll(PDO::FETCH_ASSOC); placeCheck(str_contains(json_encode($plan),'place_hierarchy'),'Descendant lookups must use native hierarchy indexes.');
    // One connected branch with thousands of descendants and overlapping object
    // links catches the parser blow-up missed by many independent small roots.
    PlaceDB::$pdo->exec('INSERT INTO ca_places(place_id,parent_id,hierarchy_id,hier_left,hier_right) VALUES (2000,0,2000,1,12004)');
    $insert=PlaceDB::$pdo->prepare('INSERT INTO ca_places(place_id,parent_id,hierarchy_id,hier_left,hier_right) VALUES (?,2000,2000,?,?)');
    $objects=PlaceDB::$pdo->prepare('INSERT INTO ca_objects(object_id,access,deleted) VALUES (?,1,0)');
    $links=PlaceDB::$pdo->prepare('INSERT INTO ca_objects_x_places VALUES (?,?)');
    foreach (range(1,6000) as $n) { $insert->execute([2000+$n,$n*2,$n*2+1]); $objects->execute([10000+$n]); $links->execute([10000+$n,2000+$n]); $links->execute([10000+$n,2000]); }
    PlaceDB::$queries=[]; $controller=new PlaceContentsController(new PlaceRequest(['place_id'=>2000,'s'=>9,'view'=>'images']),new PlaceResponse()); $controller->Objects();
    $result=$controller->calls[0][3]; $ids=explode(',',$result->browse->filters[0][2]);
    placeCheck(count($ids)===6000 && count(array_unique($ids))===6000 && $controller->calls[0][2]['search']==='place_id:2000', 'Large connected branches must retain every unique object without building an OR query.');
    $relationshipQueries=array_filter(PlaceDB::$queries,static fn($q)=>str_contains($q[0],'FROM ca_objects_x_places'));
    placeCheck(count($relationshipQueries)===13 && max(array_map(static fn($q)=>count($q[1][0]),$relationshipQueries))===500, 'Large relationship scopes must use bounded batches, not one SQL query per place.');
    echo "Place contents passed: {$checks} assertions (actual SQLite hierarchy and controller; native search/access boundaries).\n";
} finally { unlink($directory.'/controllers/SearchController.php'); rmdir($directory.'/controllers'); rmdir($directory); }
