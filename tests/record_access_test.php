<?php
/** Real bulk permission SQL, synthetic native restriction and ACL boundaries. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('__CA_BUNDLE_ACCESS_READONLY__', 1);
$checks = 0;
function accessCheck($condition, $message) { $GLOBALS['checks']++; if (!$condition) { throw new RuntimeException($message); } }
function caGetBundleAccessLevel($table, $bundle) { return $GLOBALS['deniedBundle'] === $table.':'.$bundle ? 0 : 1; }
function caGetTypeRestrictionsForUser($table) { return $GLOBALS['typeRestriction']; }
function caGetSourceRestrictionsForUser($table) { return $GLOBALS['sourceRestriction']; }
function caSourceAccessControlIsEnabled($model) { return $GLOBALS['sourceChecking']; }
function caACLIsEnabled($model, $options) {
	accessCheck($options === ['forPawtucket' => true], 'Front-only ACL context is required.');
	return $GLOBALS['aclChecking'];
}
function caGetBrowseInstance($table) { return new AccessBrowse(); }
class AccessBrowse {
	function filterHitsByACL($ids, $tableNum, $userID) {
		accessCheck($tableNum === 57 && $userID === 7, 'ACL filtering must use the current user and native table.');
		return array_values(array_diff($ids, $GLOBALS['aclDenied']));
	}
}
class AccessConfig { function get($key) { return $key === 'perform_type_access_checking' && $GLOBALS['typeChecking']; } }
class AccessModel {
	function getDb() { return new AccessDb(); }
	function getAppConfig() { return new AccessConfig(); }
	function getTypeFieldName() { return 'type_id'; }
	function getSourceFieldName() { return 'source_id'; }
	function tableNum() { return 57; }
}
class Datamodel { static function getInstanceByTableName($table, $cached) { accessCheck(!$cached, 'Permission metadata must not mutate a cached loaded model.'); return new AccessModel(); } }
class AccessDb {
	static public PDO $pdo;
	static public array $queries = [];
	function query($sql, $params) {
		$values = []; $index = 0;
		$sql = preg_replace_callback('/\?/', static function () use ($params, &$index, &$values) {
			$chunk = (array)$params[$index++]; array_push($values, ...$chunk);
			return join(',', array_fill(0, count($chunk), '?'));
		}, $sql);
		self::$queries[] = $params;
		$query = self::$pdo->prepare($sql); $query->execute($values);
		return new AccessRows($query->fetchAll(PDO::FETCH_ASSOC));
	}
}
class AccessRows {
	private int $position = -1;
	function __construct(private array $rows) {}
	function nextRow() { return ++$this->position < count($this->rows); }
	function get($field) { return $this->rows[$this->position][$field] ?? null; }
}
class AccessUser {
	function getTypeAccessLevel($table, $id) { return $id === 2 ? 0 : 1; }
	function getSourceAccessLevel($table, $id) { return $id === 2 ? 0 : 1; }
}
class AccessRequest {
	public object $user;
	function __construct() { $this->user = new AccessUser(); }
	function getUserID() { return 7; }
}
foreach (['typeRestriction' => null, 'sourceRestriction' => null, 'typeChecking' => false,
	'sourceChecking' => false, 'aclChecking' => false, 'aclDenied' => [], 'deniedBundle' => ''] as $key => $value) { $GLOBALS[$key] = $value; }
AccessDb::$pdo = new PDO('sqlite::memory:');
AccessDb::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
AccessDb::$pdo->exec('CREATE TABLE ca_objects (object_id INTEGER PRIMARY KEY, access INTEGER, deleted INTEGER, type_id INTEGER, source_id INTEGER)');
AccessDb::$pdo->exec('CREATE TABLE ca_collections (collection_id INTEGER PRIMARY KEY, parent_id INTEGER, hier_collection_id INTEGER, hier_left INTEGER, hier_right INTEGER, access INTEGER, deleted INTEGER, type_id INTEGER, source_id INTEGER)');
AccessDb::$pdo->exec('CREATE TABLE ca_object_representations (representation_id INTEGER PRIMARY KEY, access INTEGER, deleted INTEGER, type_id INTEGER, source_id INTEGER)');
AccessDb::$pdo->exec('INSERT INTO ca_objects VALUES (1,1,0,1,1),(2,0,0,1,1),(3,1,1,1,1),(4,1,0,2,1),(5,1,0,1,2),(6,1,0,1,1)');
AccessDb::$pdo->exec('INSERT INTO ca_object_representations VALUES (10,1,0,1,1),(11,1,0,1,1)');
require dirname(__DIR__).'/helpers/record_access.php';
$request = new AccessRequest();
accessCheck(tadlReadableIDs($request, 'ca_objects', [6,5,4,3,2,1,1,'1 OR 1=1',0,-1], [1]) === [6,5,4,1], 'Public/deleted checks, ordering, duplicates and ID validation failed.');
$GLOBALS['typeChecking'] = true;
accessCheck(tadlReadableIDs($request, 'ca_objects', range(1,6), [1]) === [1,5,6], 'Native per-type access must also apply without a type restriction list.');
$GLOBALS['sourceChecking'] = true;
accessCheck(tadlReadableIDs($request, 'ca_objects', range(1,6), [1]) === [1,6], 'Native source access must exclude denied sources.');
$GLOBALS['typeChecking'] = $GLOBALS['sourceChecking'] = false;
$GLOBALS['typeRestriction'] = [1]; $GLOBALS['sourceRestriction'] = [1];
accessCheck(tadlReadableIDs($request, 'ca_objects', range(1,6), [1]) === [1,6], 'Configured native restrictions must filter SQL candidates.');
$GLOBALS['typeRestriction'] = [];
$queries = count(AccessDb::$queries);
accessCheck(tadlReadableIDs($request, 'ca_objects', [1], [1]) === [] && count(AccessDb::$queries) === $queries, 'An empty allowed type list must deny without querying.');
$GLOBALS['typeRestriction'] = $GLOBALS['sourceRestriction'] = null;
$GLOBALS['aclChecking'] = true; $GLOBALS['aclDenied'] = [6,11];
accessCheck(tadlReadableIDs($request, 'ca_objects', [1,6], [1]) === [1], 'Pawtucket-only ACLs must reject publicly flagged records.');
$rows = [['object_id'=>1,'representation_id'=>10], ['object_id'=>1,'representation_id'=>11], ['object_id'=>6,'representation_id'=>10]];
accessCheck(count(tadlReadableMediaRows($request, $rows, [1])) === 1, 'Media eligibility must check both owners and representations.');
$GLOBALS['deniedBundle'] = 'ca_object_representations:media';
accessCheck(tadlReadableMediaRows($request, $rows, [1]) === [], 'Denied media bundle must return no media.');
$GLOBALS['deniedBundle'] = ''; $GLOBALS['aclDenied'] = [];
AccessDb::$pdo->exec('INSERT INTO ca_collections VALUES (20,0,20,1,12,1,0,1,1),(21,20,20,2,7,0,0,1,1),(22,21,20,3,4,1,0,1,1),(23,20,20,8,11,1,0,1,1),(24,23,20,9,10,1,0,1,1)');
accessCheck(array_keys(tadlReadableCollectionBranches($request, [20], [1])[20]) === [20,23,24], 'Readable descendants of private intermediate collections must stay hidden.');
$GLOBALS['aclDenied'] = [23];
accessCheck(array_keys(tadlReadableCollectionBranches($request, [20], [1])[20]) === [20], 'ACL-denied ancestors must prune the complete branch.');
$GLOBALS['aclChecking'] = false;
$query = AccessDb::$pdo->prepare('INSERT INTO ca_objects VALUES (?,1,0,1,1)');
foreach (range(1000,1600) as $id) { $query->execute([$id]); }
$queries = count(AccessDb::$queries);
accessCheck(tadlReadableIDs($request, 'ca_objects', range(1000,1600), [1]) === range(1000,1600), 'Bounded permission checks dropped records.');
accessCheck(count(AccessDb::$queries) - $queries === 2 && max(array_map(static fn($p) => count($p[0]), array_slice(AccessDb::$queries, $queries))) <= 500, 'Permission checks must use two bounded batches for 601 records.');
accessCheck(tadlReadableIDs(null, 'ca_objects', [1], [1]) === [] && tadlReadableIDs($request, 'ca_objects;invalid', [1], [1]) === [] && tadlReadableIDs($request, 'ca_objects', [1], []) === [], 'Invalid context, table and empty mask must fail closed.');
// The native representation inventory does not filter Pawtucket-only ACLs.
class ca_objects {
 function __construct(private int $id) {}
 function getPrimaryKey() { return $this->id; }
 function getRepresentations($versions, $sizes, $options) {
  $rows = [];
  foreach ([11,10] as $id) { $rows[] = ['representation_id'=>$id, 'is_primary'=>$id===11, 'rank'=>0,
   'info'=>['small'=>['MIMETYPE'=>'image/jpeg']], 'tags'=>['small'=>'<img src="/synthetic/'.$id.'.jpg">']]; }
  return $rows;
 }
}
$GLOBALS['aclChecking'] = true; $GLOBALS['aclDenied'] = [11];
require dirname(__DIR__).'/helpers/object_thumbnails.php';
accessCheck(str_contains(tadlObjectThumbnail(1, 'small', [1], '<img src="/synthetic/11.jpg">', $request), '/10.jpg'), 'Primary tags must not bypass front-only representation ACLs.');
$GLOBALS['aclChecking'] = false; $GLOBALS['typeChecking'] = true;
AccessDb::$pdo->exec('UPDATE ca_object_representations SET type_id = 2 WHERE representation_id = 11');
accessCheck(str_contains(tadlObjectThumbnail(1, 'small', [1], '<img src="/synthetic/11.jpg">', $request), '/10.jpg'), 'Primary tags must not bypass per-type access when no restriction list exists.');
$GLOBALS['typeChecking'] = false; $GLOBALS['sourceChecking'] = true;
AccessDb::$pdo->exec('UPDATE ca_object_representations SET source_id = 2 WHERE representation_id = 11');
accessCheck(str_contains(tadlObjectThumbnail(1, 'small', [1], '<img src="/synthetic/11.jpg">', $request), '/10.jpg'), 'Primary tags must not bypass per-source access when no restriction list exists.');
$GLOBALS['deniedBundle'] = 'ca_object_representations:media';
accessCheck(tadlObjectThumbnail(1, 'small', [1], '<img src="/synthetic/11.jpg">', $request) === '', 'A primary tag must not bypass the media bundle.');
echo "Record access passed: {$checks} assertions (real batched SQL, type/source/bundle/public access and front-only ACLs).\n";
