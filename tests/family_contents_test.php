<?php
/** Actual scoped SQL/controller with synthetic native type/access/search boundaries. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('pInteger', 1); define('pString', 2); define('__CA_BUNDLE_ACCESS_READONLY__', 1);
$checks = 0;
function familyCheck($condition, $message) {
	$GLOBALS['checks']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function caGetUserAccessValues($request) { return $request->access; }
function caGetBundleAccessLevel($table, $bundle) { return in_array($table.':'.$bundle, $GLOBALS['deniedBundles'], true) ? 0 : 1; }
function caSourceAccessControlIsEnabled($model) { return $GLOBALS['sourceCheck']; }
function caGetTypeRestrictionsForUser($table) { return $GLOBALS['allowedTypes']; }
function caGetSourceRestrictionsForUser($table) { return null; }
function caACLIsEnabled($model, $options) {
	familyCheck($options === ['forPawtucket' => true], 'Family scopes must check front-end ACLs.');
	return (bool)$GLOBALS['aclDenied'];
}
function caMakeTypeIDList($table, $codes, $options = []) {
	familyCheck($table === 'ca_entities' && !$options, 'Types must use native hierarchical expansion.');
	return $codes === ['fam'] ? [30, 31] : [10, 11, 20, 21];
}
function caGetBrowseInstance($table) {
	return new class {
		function filterHitsByACL($ids, $tableNum, $userID) { return array_values(array_diff($ids, $GLOBALS['aclDenied'])); }
	};
}
function caGetBrowseConfig() {
	return new class { function getAssoc($key) { return ['objects' => ['sortBy' => ['Identifier' => 'ca_objects.idno', 'Title' => 'ca_object_labels.name']]]; } };
}
class FamilyDB {
	static public PDO $pdo;
	static public array $queries = [];
	function query($sql, $params) {
		self::$queries[] = [$sql, $params];
		$index = 0; $values = [];
		$sql = preg_replace_callback('/\?/', static function () use ($params, &$index, &$values) {
			$chunk = (array)$params[$index++]; array_push($values, ...$chunk);
			return join(',', array_fill(0, count($chunk), '?'));
		}, $sql);
		$query = self::$pdo->prepare($sql); $query->execute($values);
		return new class($query) {
			private array $row = [];
			function __construct(private PDOStatement $query) {}
			function nextRow() { $this->row = $this->query->fetch(PDO::FETCH_ASSOC) ?: []; return (bool)$this->row; }
			function get($field) { return $this->row[$field] ?? null; }
		};
	}
}
class FamilyModel {
	private array $row = [];
	function load($id) {
		$query = FamilyDB::$pdo->prepare('SELECT * FROM ca_entities WHERE entity_id = ?'); $query->execute([$id]);
		$this->row = $query->fetch(PDO::FETCH_ASSOC) ?: []; return (bool)$this->row;
	}
	function get($field) { return $this->row[$field] ?? null; }
	function getTypeList() { return [10=>['idno'=>'ind'],11=>['idno'=>'synthetic_person','parent_id'=>10],20=>['idno'=>'org'],21=>['idno'=>'synthetic_org','parent_id'=>20],30=>['idno'=>'fam'],31=>['idno'=>'synthetic_family','parent_id'=>30]]; }
	function getDb() { return new FamilyDB(); }
	function getAppConfig() { return new class { function get($key) { return $GLOBALS['typeCheck']; } }; }
	function getTypeFieldName() { return 'type_id'; }
	function getSourceFieldName() { return 'source_id'; }
	function tableNum() { return 20; }
}
class Datamodel {
	static function getInstance($table, $cached = false) {
		familyCheck($table === 'ca_entities' && !$cached, 'Family work must use independent entity models.'); return new FamilyModel();
	}
	static function getInstanceByTableName($table, $cached = false) { return self::getInstance($table, $cached); }
}
class FamilyRequest {
	public array $access = [1]; public object $user; public object $config;
	function __construct(public array $params = [], public string $method = 'GET', public bool $loggedIn = false) {
		$this->user = new class {
			function getTypeAccessLevel($table, $id) { return $id === 21 ? 0 : 1; }
			function getSourceAccessLevel($table, $id) { return $id === 2 ? 0 : 1; }
		};
		$this->config = new class { function get($key) { return false; } };
	}
	function getUserID() { return 7; }
	function getController() { return 'FamilyContents'; }
	function getParameter($key, $type, $options = []) { return $this->params[$key] ?? null; }
	function setParameter($key, $value, $source) { familyCheck($source === 'GET', 'Scope must override caller request state.'); $this->params[$key] = $value; }
	function getRequestMethod() { return $this->method; }
	function isLoggedIn() { return $this->loggedIn; }
}
class FamilyResponse {
	public int $code = 200; public array $headers = [];
	function addHeader($key, $value) { $this->headers[$key] = $value; }
	function setHTTPResponseCode($code, $message) { $this->code = $code; }
}
class SearchController {
	public array $calls = [];
	function __construct(public $request, public $response) {}
	function __call($name, $args) { $this->calls[] = [$name, $args, $this->request->params]; }
}
$directory = sys_get_temp_dir().'/tadl-family-'.bin2hex(random_bytes(8));
mkdir($directory.'/controllers', 0700, true);
file_put_contents($directory.'/controllers/SearchController.php', '<?php // Synthetic parent already defined by the test.');
define('__CA_APP_DIR__', $directory);
foreach (['deniedBundles'=>[], 'aclDenied'=>[], 'allowedTypes'=>null, 'sourceCheck'=>false, 'typeCheck'=>false] as $key=>$value) { $GLOBALS[$key] = $value; }
FamilyDB::$pdo = new PDO('sqlite::memory:');
FamilyDB::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
FamilyDB::$pdo->exec('CREATE TABLE ca_entities (entity_id INTEGER PRIMARY KEY, type_id INTEGER, access INTEGER DEFAULT 1, deleted INTEGER DEFAULT 0, source_id INTEGER DEFAULT 1)');
FamilyDB::$pdo->exec('CREATE TABLE ca_entities_x_entities (entity_left_id INTEGER, entity_right_id INTEGER)');
FamilyDB::$pdo->exec('CREATE INDEX entity_left ON ca_entities_x_entities(entity_left_id)');
FamilyDB::$pdo->exec('CREATE INDEX entity_right ON ca_entities_x_entities(entity_right_id)');
FamilyDB::$pdo->exec('INSERT INTO ca_entities VALUES (1,30,1,0,1),(2,11,1,0,1),(3,20,1,0,1),(4,10,0,0,1),(5,10,1,1,1),(6,31,1,0,1),(7,10,1,0,1),(8,30,0,0,1),(9,21,1,0,2)');
FamilyDB::$pdo->exec('INSERT INTO ca_entities_x_entities VALUES (1,2),(2,1),(1,3),(1,4),(1,5),(1,6),(6,1),(2,7),(1,9),(8,7)');
require dirname(__DIR__).'/controllers/FamilyContentsController.php';
try {
	$request = new FamilyRequest();
	$scopes = tadlFamilyEntityScopes($request, [1,6,8,2,'1 OR 1=1',0]);
	$members = $scopes[1]; sort($members);
	familyCheck($members === [1,2,3,9] && $scopes[6] === [6] && !isset($scopes[8], $scopes[2]), 'Scopes must include direct family/person/organization links in both directions, deduplicate them and exclude private/deleted/indirect/other-family records.');
	$GLOBALS['aclDenied'] = [2];
	familyCheck(tadlFamilyEntityScopes($request, [1])[1] === [1,3,9], 'ACL-hidden members must not qualify family items.');
	$GLOBALS['aclDenied'] = [1]; familyCheck(tadlFamilyEntityScopes($request, [1]) === [], 'ACL-hidden family roots must fail closed.');
	$GLOBALS['aclDenied'] = []; $GLOBALS['typeCheck'] = true;
	familyCheck(tadlFamilyEntityScopes($request, [1])[1] === [1,2,3], 'Native per-type access must exclude restricted organization subtypes.');
	$GLOBALS['typeCheck'] = false; $GLOBALS['sourceCheck'] = true;
	familyCheck(tadlFamilyEntityScopes($request, [1])[1] === [1,2,3], 'Native source access must exclude hidden members.');
	$GLOBALS['sourceCheck'] = false; $GLOBALS['allowedTypes'] = [30,31,20];
	familyCheck(tadlFamilyEntityScopes($request, [1])[1] === [1,3], 'Native type restrictions must apply to root and member records.');
	$GLOBALS['allowedTypes'] = null; $GLOBALS['deniedBundles'] = ['ca_entities:ca_entities'];
	familyCheck(tadlFamilyEntityScopes($request, [1])[1] === [1], 'A denied member relationship bundle must retain only directly attached family items.');
	$GLOBALS['deniedBundles'] = ['ca_entities:ca_objects'];
	familyCheck(tadlFamilyEntityScopes($request, [1]) === [], 'A denied object relationship bundle must expose no family object scope.');
	$GLOBALS['deniedBundles'] = [];
	familyCheck(tadlFamilyContentsSearch([]) === 'ca_objects.object_id:0' && tadlFamilyContentsSearch([1,2,2,-1,0]) === 'entity_id:1 OR entity_id:2', 'Empty scopes must never become unrestricted searches.');
	FamilyDB::$pdo->exec('CREATE TABLE ca_objects (object_id INTEGER PRIMARY KEY, access INTEGER, deleted INTEGER)');
	FamilyDB::$pdo->exec('CREATE TABLE ca_objects_x_entities (object_id INTEGER, entity_id INTEGER)');
	FamilyDB::$pdo->exec('INSERT INTO ca_objects VALUES (100,1,0),(101,1,0),(102,1,0),(103,0,0),(104,1,1),(105,1,0)');
	FamilyDB::$pdo->exec('INSERT INTO ca_objects_x_entities VALUES (100,1),(100,2),(101,2),(102,3),(103,3),(104,1),(105,7)');
	$query = FamilyDB::$pdo->prepare('SELECT DISTINCT o.object_id FROM ca_objects o JOIN ca_objects_x_entities r ON r.object_id=o.object_id WHERE r.entity_id IN (?,?,?,?) AND o.access=1 AND o.deleted=0 ORDER BY o.object_id');
	$query->execute(tadlFamilyEntityScopes($request, [1])[1]);
	familyCheck($query->fetchAll(PDO::FETCH_COLUMN) === [100,101,102], 'Combined scope must return each visible object once and exclude indirect member objects.');
	$request = new FamilyRequest(['entity_id'=>1,'view'=>'list','sort'=>'Title','direction'=>'desc','s'=>24,'search'=>'*','key'=>'stale','facet'=>'_search','id'=>'*','_advanced'=>1]);
	$response = new FamilyResponse(); $controller = new FamilyContentsController($request, $response); $controller->Objects();
	$params = $controller->calls[0][2];
	familyCheck($controller->calls[0][0] === 'objects' && $params['search'] === tadlFamilyContentsSearch([1,2,3,9]), 'Endpoint must replace caller search/key with the current family scope and delegate native object search.');
	familyCheck($params['view'] === 'list' && $params['sort'] === 'Title' && $params['direction'] === 'desc' && $params['s'] === 24 && $params['n'] === 25, 'Family paging and explicit display/sort choices must survive.');
	familyCheck($params['key'] === '' && $params['facet'] === '' && $params['id'] === '' && $params['_advanced'] === 0, 'Stale scope/refinement state must not override a family.');
	familyCheck($response->headers === ['Cache-Control'=>'private, no-store','X-Robots-Tag'=>'noindex, follow'], 'Family results must be private and non-indexable.');
	FamilyDB::$pdo->exec('DELETE FROM ca_entities_x_entities WHERE entity_left_id=1 AND entity_right_id=3');
	$controller = new FamilyContentsController(new FamilyRequest(['entity_id'=>1,'key'=>'stale']), new FamilyResponse()); $controller->Objects();
	familyCheck(!str_contains($controller->calls[0][2]['search'], 'entity_id:3'), 'Later result requests must reflect removed family relationships.');
	foreach ([0=>400,999=>404,8=>404,2=>404] as $id=>$code) {
		$response = new FamilyResponse(); $controller = new FamilyContentsController(new FamilyRequest(['entity_id'=>$id]), $response); $controller->Objects();
		familyCheck($response->code === $code && !$controller->calls, 'Invalid, missing, private and non-family IDs must fail before search.');
	}
	$response = new FamilyResponse(); $controller = new FamilyContentsController(new FamilyRequest(['entity_id'=>1], 'POST'), $response); $controller->Objects();
	familyCheck($response->code === 405 && !$controller->calls, 'Family endpoint must be read-only.');
	$request = new FamilyRequest(['entity_id'=>1]); $request->config = new class { function get($key) { return true; } };
	$controller = new FamilyContentsController($request, new FamilyResponse()); $controller->Objects();
	familyCheck(!$controller->calls, 'Required login must remain enforced.');
	$insert = FamilyDB::$pdo->prepare('INSERT INTO ca_entities VALUES (?,30,1,0,1)');
	$link = FamilyDB::$pdo->prepare('INSERT INTO ca_entities_x_entities VALUES (?,2)');
	foreach (range(1000,1601) as $id) { $insert->execute([$id]); $link->execute([$id]); }
	FamilyDB::$queries = [];
	$scopes = tadlFamilyEntityScopes(new FamilyRequest(), range(1000,1601));
	familyCheck(count($scopes) === 602 && $scopes[1601] === [1601,2] && count(FamilyDB::$queries) === 8, 'Wide family browses must batch roots and memberships instead of querying per family.');
	$plan = FamilyDB::$pdo->query('EXPLAIN QUERY PLAN SELECT entity_right_id FROM ca_entities_x_entities WHERE entity_left_id IN (1000,1001)')->fetchAll(PDO::FETCH_ASSOC);
	familyCheck(str_contains(json_encode($plan), 'entity_left'), 'Relationship lookups should use the existing left/right indexes.');
	echo "Family contents passed: {$checks} assertions (actual SQLite scope queries and controller; synthetic native search/access boundaries).\n";
} finally {
	unlink($directory.'/controllers/SearchController.php'); rmdir($directory.'/controllers'); rmdir($directory);
}
