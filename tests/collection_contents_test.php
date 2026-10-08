<?php
/** Actual scope helper/controller, parameterized SQLite query; synthetic native search boundary. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('pString', 1);
define('pInteger', 2);
define('__CA_ACL_READONLY_ACCESS__', 1);
$assertions = 0;
function contentsCheck($condition, $message) {
	$GLOBALS['assertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function caGetUserAccessValues($request) { return $request->access; }
function caACLIsEnabled($record, $options) {
	contentsCheck($options === ['forPawtucket' => true], 'Collection ACL must use the Pawtucket configuration.');
	return $record->get('acl');
}
function caGetBrowseConfig() { return new ContentsConfig(); }
class ContentsConfig {
	function get($key) { return false; }
	function getAssoc($key) { return ['objects' => ['sortBy' => ['Identifier' => 'ca_objects.idno', 'Title' => 'ca_object_labels.name']]]; }
}
class ContentsRequest {
	public array $access = [1];
	public object $user;
	public object $config;
	public array $get = [];
	function __construct(public array $path = [], public string $method = 'GET', public bool $loggedIn = false) {
		$this->user = new stdClass(); $this->config = new ContentsConfig();
	}
	function getParameter($key, $type, $options = []) { return $this->get[$key] ?? $this->path[$key] ?? null; }
	function setParameter($key, $value, $source) {
		contentsCheck($source === 'GET', 'Canonical parameters must override caller PATH/GET state.');
		$this->get[$key] = $value;
	}
	function getRequestMethod() { return $this->method; }
	function isLoggedIn() { return $this->loggedIn; }
}
class ContentsResponse {
	public int $code = 200;
	public array $headers = [];
	function addHeader($key, $value) { $this->headers[$key] = $value; }
	function setHTTPResponseCode($code, $message) { $this->code = $code; }
}
class Datamodel {
	private static array $instances = [];
	static function getInstance($table, $useCache = false) {
		contentsCheck($table === 'ca_collections', 'Traversal must load collection models, never object records.');
		// Native Datamodel returns the same mutable model when use_cache is true.
		return $useCache ? (self::$instances[$table] ??= new ContentsCollection()) : new ContentsCollection();
	}
}
class ContentsCollection {
	private array $values = [];
	function load($id) {
		$query = Db::$pdo->prepare('SELECT * FROM ca_collections WHERE collection_id = ?');
		$query->execute([$id]); $this->values = $query->fetch(PDO::FETCH_ASSOC) ?: [];
		return (bool)$this->values;
	}
	function getPrimaryKey() { return (int)($this->values['collection_id'] ?? 0); }
	function get($key) { return $this->values[$key] ?? null; }
	function isReadable($request, $bundle = null) {
		return (bool)$this->get(match ($bundle) { 'hierarchy' => 'hierarchy_readable', 'ca_objects' => 'objects_readable', default => 'readable' });
	}
	function checkACLAccessForUser($user) { return $this->get('acl_allowed') ? 1 : 0; }
}
class Db {
	static PDO $pdo;
	static array $queries = [];
	function query($sql, $params) {
		$membership = $sql === 'SELECT DISTINCT object_id, collection_id FROM ca_objects_x_collections WHERE collection_id IN (?)';
		contentsCheck($membership || $sql === 'SELECT collection_id FROM ca_collections WHERE parent_id IN (?) AND access IN (?) AND deleted = 0', 'Traversal/membership query must stay scoped.');
		contentsCheck(count($params) === ($membership ? 1 : 2) && count($params[0]) > 0 && count($params[0]) <= 500 && ($membership || $params[1]), 'Traversal/membership query must be bounded and fail closed.');
		self::$queries[] = $params;
		$index = 0;
		$sql = preg_replace_callback('/\?/', static function () use ($params, &$index) {
			return join(',', array_fill(0, count($params[$index++]), '?'));
		}, $sql);
		$query = self::$pdo->prepare($sql); $query->execute(array_merge(...$params));
		return new class($query) {
			private array $row = [];
			function __construct(private PDOStatement $query) {}
			function nextRow() { $this->row = $this->query->fetch(PDO::FETCH_ASSOC) ?: []; return (bool)$this->row; }
			function get($key) { return $this->row[$key]; }
		};
	}
}
class ContentsObjectResult {
	function __construct(public array $ids) {}
	function getPrimaryKeyValues($limit) {
		contentsCheck($limit === PHP_INT_MAX, 'Branch counts must read all native hits, without the related-item cap.');
		return $this->ids;
	}
}
class ContentsBrowse {
	static int $calls = 0;
	private array $scope = [];
	private array $access = [];
	function addCriteria($facet, $terms) {
		contentsCheck($facet === '_search' && count($terms) === 1, 'Counts must use a single native object union.');
		preg_match_all('/collection_id:(\d+)/', $terms[0], $matches);
		$this->scope = array_map('intval', $matches[1]);
	}
	function execute($options) {
		self::$calls++;
		contentsCheck($options['request'] instanceof ContentsRequest && $options['noCache'] === true, 'Counts must honor the current request and not reuse stale visibility.');
		$this->access = $options['checkAccess'];
	}
	function getResults() {
		$query = Db::$pdo->prepare('SELECT DISTINCT o.object_id FROM objects o JOIN ca_objects_x_collections m ON m.object_id = o.object_id WHERE m.collection_id IN ('.join(',', array_fill(0, count($this->scope), '?')).') AND o.access IN ('.join(',', array_fill(0, count($this->access), '?')).') AND o.deleted = 0 AND o.readable = 1 ORDER BY o.object_id');
		$query->execute(array_merge($this->scope, $this->access));
		return new ContentsObjectResult($query->fetchAll(PDO::FETCH_COLUMN));
	}
}
function caGetBrowseInstance($table) {
	contentsCheck($table === 'ca_objects', 'Counts must delegate object visibility to native browse.');
	return new ContentsBrowse();
}
function tadlFilterMediaResult($request, $result) {
	if (($request->path['media'] ?? 'all') !== 'only') { return; }
	$ids = Db::$pdo->query('SELECT object_id FROM objects WHERE media = 1')->fetchAll(PDO::FETCH_COLUMN);
	$result->ids = array_values(array_intersect($result->ids, $ids));
}
// The parent boundary captures exactly what the actual controller delegates to native search.
class SearchController {
	public array $calls = [];
	function __construct(public ContentsRequest $request, public ContentsResponse $response) {}
	function __call($action, $args) { $this->calls[] = [$action, $args, $this->request->get]; }
}
$directory = sys_get_temp_dir().'/tadl-contents-test-'.bin2hex(random_bytes(8));
mkdir($directory.'/controllers', 0700, true);
file_put_contents($directory.'/controllers/SearchController.php', '<?php');
define('__CA_APP_DIR__', $directory);
try {
	require dirname(__DIR__).'/controllers/CollectionContentsController.php';
	Db::$pdo = new PDO('sqlite::memory:'); Db::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	Db::$pdo->exec('CREATE TABLE ca_collections (collection_id INTEGER PRIMARY KEY, parent_id INTEGER, access INTEGER DEFAULT 1, deleted INTEGER DEFAULT 0, readable INTEGER DEFAULT 1, hierarchy_readable INTEGER DEFAULT 1, objects_readable INTEGER DEFAULT 1, acl INTEGER DEFAULT 0, acl_allowed INTEGER DEFAULT 1)');
	Db::$pdo->exec('CREATE INDEX collection_parent_access ON ca_collections(parent_id, access, deleted)');
	function contentsNode($id, $parent = null, $extra = []) {
		$values = ['collection_id' => $id, 'parent_id' => $parent] + $extra;
		$query = Db::$pdo->prepare('INSERT INTO ca_collections ('.join(',', array_keys($values)).') VALUES ('.join(',', array_fill(0, count($values), '?')).')');
		$query->execute(array_values($values));
	}
	function contentsRecord($id) { $record = new ContentsCollection(); $record->load($id); return $record; }
	contentsNode(1); contentsNode(2, 1); contentsNode(3, 1);
	foreach ([4 => 2, 5 => 4, 6 => 5, 7 => 6] as $id => $parent) { contentsNode($id, $parent); }
	contentsNode(8, 1, ['access' => 0]); contentsNode(9, 8);
	contentsNode(10, 1, ['deleted' => 1]); contentsNode(11, 10);
	contentsNode(12, 1, ['acl' => 1, 'acl_allowed' => 0]); contentsNode(13, 12);
	contentsNode(14, 1, ['readable' => 0]);
	contentsNode(15, 1, ['objects_readable' => 0]); contentsNode(16, 15);
	contentsNode(17, 1, ['hierarchy_readable' => 0]); contentsNode(18, 17);
	contentsNode(20); contentsNode(21, 20); contentsNode(22, 1, ['access' => 2]);
	$cached = Datamodel::getInstance('ca_collections', true); $cached->load(2);
	$cached_again = Datamodel::getInstance('ca_collections', true); $cached_again->load(3);
	contentsCheck($cached === $cached_again && $cached->getPrimaryKey() === 3, 'Native cached models must share their loaded state in the test boundary.');
	$request = new ContentsRequest(); $root = contentsRecord(1);
	$ids = tadlCollectionContentsIDs($request, $root); sort($ids);
	contentsCheck($ids === [1,2,3,4,5,6,7,16,17], 'Flat contents must include deep descendants and prune private/deleted/ACL/type/bundle-hidden branches.');
	contentsCheck(count(Db::$queries) === 6, 'Traversal must batch siblings by hierarchy level rather than querying every object or collection.');
	$nodes = tadlCollectionContentsCollections($request, $root);
	contentsCheck(count(array_unique(array_map('spl_object_id', $nodes))) === count($nodes), 'Every retained collection must have independent loaded state.');
	foreach ($nodes as $id => $node) {
		contentsCheck($node->getPrimaryKey() === $id && $node->get('parent_id') === contentsRecord($id)->get('parent_id'), 'Traversal must retain each actual collection ID and parent.');
	}
	contentsCheck(tadlCollectionContentsIDs($request, $root, false) === [1], 'Hierarchy contents must stay directly attached to the selected collection.');
	$ids = tadlCollectionContentsIDs($request, contentsRecord(2)); sort($ids);
	contentsCheck($ids === [2,4,5,6,7], 'Selected subcollection must exclude its ancestors, siblings and unrelated roots.');
	contentsCheck(tadlCollectionHasHierarchy($request, $root) && !tadlCollectionHasHierarchy($request, contentsRecord(7)) && !tadlCollectionHasHierarchy($request, contentsRecord(17)), 'Hierarchy link must require accessible direct children and hierarchy bundle permission.');
	foreach ([8,10,12,14,999] as $id) {
		contentsCheck(tadlCollectionContentsIDs($request, contentsRecord($id)) === [] && !tadlCollectionHasHierarchy($request, contentsRecord($id)), 'Unavailable root must not expose any branch.');
	}
	$request->access = [];
	contentsCheck(tadlCollectionContentsIDs($request, $root) === [], 'Empty access mask must deny the whole scope.');
	$request->access = [1,2];
	contentsCheck(in_array(22, tadlCollectionContentsIDs($request, $root), true), 'Current native access values must be honored.');
	contentsCheck(tadlCollectionContentsSearch([]) === 'ca_objects.object_id:0' && tadlCollectionContentsSearch([0,-1,2,2,3]) === 'collection_id:2 OR collection_id:3', 'Empty scope must never fall back to every object; repeated memberships need one numeric term.');
	// Corrupt cyclic hierarchy must terminate; wide siblings must survive chunk boundaries.
	contentsNode(100, 102); contentsNode(101, 100); contentsNode(102, 101);
	$cycle = tadlCollectionContentsIDs(new ContentsRequest(), contentsRecord(100)); sort($cycle);
	contentsCheck($cycle === [100,101,102], 'Traversal must guard against a cycle without duplicating collection terms.');
	contentsNode(200);
	for ($id = 201; $id <= 802; $id++) { contentsNode($id, 200); contentsNode($id + 1000, $id); }
	Db::$queries = [];
	contentsCheck(count(tadlCollectionContentsIDs(new ContentsRequest(), contentsRecord(200))) === 1205 && count(Db::$queries) === 5, 'Wide hierarchy must include every child and grandchild in bounded batches without a native related-item cap.');
	$plan = Db::$pdo->query('EXPLAIN QUERY PLAN SELECT collection_id FROM ca_collections WHERE parent_id IN (1,2) AND access IN (1) AND deleted = 0')->fetchAll(PDO::FETCH_ASSOC);
	contentsCheck(str_contains(json_encode($plan), 'collection_parent_access'), 'Scoped hierarchy lookup should use its parent/access index.');
	// Objects attached to root and several descendants occur once; private/deleted records stay out.
	Db::$pdo->exec('CREATE TABLE objects (object_id INTEGER PRIMARY KEY, access INTEGER DEFAULT 1, deleted INTEGER DEFAULT 0)');
	Db::$pdo->exec('CREATE TABLE memberships (object_id INTEGER, collection_id INTEGER)');
	Db::$pdo->exec('INSERT INTO objects VALUES (1,1,0),(2,1,0),(3,0,0),(4,1,1),(5,1,0)');
	Db::$pdo->exec('INSERT INTO memberships VALUES (1,1),(1,2),(1,7),(2,7),(3,2),(4,3),(5,21)');
	$scope = tadlCollectionContentsIDs(new ContentsRequest(), $root);
	$query = Db::$pdo->prepare('SELECT DISTINCT o.object_id FROM objects o JOIN memberships m ON m.object_id = o.object_id WHERE m.collection_id IN ('.join(',', array_fill(0, count($scope), '?')).') AND o.access = 1 AND o.deleted = 0 ORDER BY o.object_id');
	$query->execute($scope);
	contentsCheck($query->fetchAll(PDO::FETCH_COLUMN) === [1,2], 'Scoped union must deduplicate memberships and exclude unavailable/unrelated objects.');
	// Actual count rollups over SQLite relationships and synthetic native visibility.
	Db::$pdo->exec('ALTER TABLE objects ADD COLUMN readable INTEGER DEFAULT 1');
	Db::$pdo->exec('ALTER TABLE objects ADD COLUMN media INTEGER DEFAULT 1');
	Db::$pdo->exec('ALTER TABLE memberships RENAME TO ca_objects_x_collections');
	Db::$pdo->exec('CREATE INDEX membership_collection ON ca_objects_x_collections(collection_id)');
	Db::$pdo->exec('INSERT INTO objects VALUES (6,1,0,1,1),(7,1,0,1,1),(8,1,0,0,1),(9,1,0,1,1)');
	Db::$pdo->exec('INSERT INTO ca_objects_x_collections VALUES (6,3),(1,3),(6,15),(7,9),(8,2),(9,18),(1,2),(2,7)');
	Db::$pdo->exec('UPDATE objects SET media = 0 WHERE object_id = 2');
	$before = ContentsBrowse::$calls;
	$counts = tadlCollectionContentsCounts(new ContentsRequest(), $root);
	contentsCheck(ContentsBrowse::$calls === $before + 1, 'One branch traversal must share one native object search, not search separately for each title.');
	contentsCheck($counts[1] === 3 && $counts[2] === 2 && $counts[3] === 2 && $counts[7] === 2 && $counts[15] === 0 && $counts[17] === 0, 'Counts must include deep descendants, deduplicate overlaps, exclude unavailable objects/ancestors and respect bundle pruning.');
	contentsCheck(!isset($counts[8], $counts[9], $counts[10], $counts[12], $counts[14], $counts[18], $counts[21]), 'Unavailable and unrelated collections must never enter the count map.');
	$counts = tadlCollectionContentsCounts(new ContentsRequest(['media' => 'only']), $root);
	contentsCheck($counts[1] === 2 && $counts[2] === 1 && $counts[3] === 2 && $counts[7] === 1, 'Media filtering must precede rollup counts.');
	$counts = tadlCollectionContentsCounts(new ContentsRequest(), contentsRecord(2));
	contentsCheck($counts[2] === 2 && !isset($counts[1], $counts[3]), 'A selected branch must not include ancestor/sibling counts.');
	$before = ContentsBrowse::$calls;
	contentsCheck(tadlCollectionContentsCounts(new ContentsRequest(), contentsRecord(8)) === [] && ContentsBrowse::$calls === $before, 'Unavailable roots must not invoke an unrestricted object search.');
	Db::$pdo->exec('INSERT INTO ca_objects_x_collections VALUES (1,100),(1,102),(2,101)');
	$counts = tadlCollectionContentsCounts(new ContentsRequest(), contentsRecord(100));
	contentsCheck($counts === [100=>2,101=>2,102=>2], 'Count rollup must terminate and deduplicate cyclic ancestors.');
	// More than a page/native related-item cap, with a wide collection batch boundary.
	$query = Db::$pdo->prepare('INSERT INTO objects VALUES (?,1,0,1,1)');
	$link = Db::$pdo->prepare('INSERT INTO ca_objects_x_collections VALUES (?,?)');
	for ($id = 1000; $id <= 1601; $id++) { $query->execute([$id]); $link->execute([$id, $id + 201]); }
	Db::$queries = []; $before = ContentsBrowse::$calls;
	$counts = tadlCollectionContentsCounts(new ContentsRequest(), contentsRecord(200));
	contentsCheck($counts[200] === 602 && $counts[201] === 1 && $counts[802] === 1 && count(Db::$queries) === 8 && ContentsBrowse::$calls === $before + 1, 'Wide/deep branches must keep full unique counts with bounded hierarchy and membership queries.');
	foreach (['flat', 'hierarchy', 'unknown'] as $mode) {
		$request = new ContentsRequest(['collection_id' => 1, 'collection_view' => $mode, 'view' => 'list', 'sort' => 'Title', 'direction' => 'desc', 's' => 24,
			'search' => '*', 'key' => 'unrelated', 'facets' => 'unrelated', 'facet' => '_search', 'id' => '*', '_advanced' => 1, 'n' => 99999]);
		$response = new ContentsResponse(); $controller = new CollectionContentsController($request, $response); $controller->Objects();
		$call = $controller->calls[0]; $params = $call[2];
		contentsCheck(count($controller->calls) === 1 && $call[0] === 'objects' && $call[1] === [[]], 'Controller must delegate to the native object search once.');
		contentsCheck($params['search'] === tadlCollectionContentsSearch(tadlCollectionContentsIDs($request, $root, $mode !== 'hierarchy')), 'Caller query/key must never replace selected branch scope.');
		contentsCheck($params['collection_view'] === ($mode === 'hierarchy' ? 'hierarchy' : 'flat') && $params['tadl_collection_id'] === 1 && $params['tadl_collection_controls'] === 1, 'Loader must publish canonical collection mode and controls.');
		contentsCheck($params['view'] === 'list' && $params['n'] === 24 && $params['sort'] === 'Title' && $params['direction'] === 'desc' && $params['s'] === 24, 'Sorting, paging and list view must survive canonicalization.');
		contentsCheck($params['key'] === '' && $params['facets'] === '' && $params['facet'] === '' && $params['id'] === '' && $params['_advanced'] === 0, 'Native stale/refinement/advanced state must be cleared.');
		contentsCheck($response->headers === ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, follow'], 'Collection result endpoint must be private and non-indexable.');
	}
	$request = new ContentsRequest(['collection_id' => 1, 'view' => 'invalid', 'sort' => 'invalid', 'direction' => 'invalid', 's' => -10]);
	$controller = new CollectionContentsController($request, new ContentsResponse()); $controller->Objects();
	contentsCheck($request->get['collection_view'] === 'flat' && $request->get['view'] === 'images' && $request->get['n'] === 9 && $request->get['sort'] === 'Identifier' && $request->get['direction'] === 'asc' && $request->get['s'] === 0, 'Default and invalid display state must use the supported first tile page.');
	foreach ([['collection_id' => 0, 'expected' => 400], ['collection_id' => 999, 'expected' => 404], ['collection_id' => 8, 'expected' => 404], ['collection_id' => 12, 'expected' => 404]] as $params) {
		$response = new ContentsResponse(); $controller = new CollectionContentsController(new ContentsRequest($params), $response); $controller->Objects();
		contentsCheck($response->code === $params['expected'] && !$controller->calls, 'Invalid or unreadable root must fail before native search.');
	}
	$response = new ContentsResponse(); $controller = new CollectionContentsController(new ContentsRequest(['collection_id' => 1], 'POST'), $response); $controller->Objects();
	contentsCheck($response->code === 405 && $response->headers['Allow'] === 'GET' && !$controller->calls, 'Endpoint must reject mutations.');
	$request = new ContentsRequest(['collection_id' => 1]);
	$request->config = new class { function get($key) { return true; } };
	$controller = new CollectionContentsController($request, new ContentsResponse()); $controller->Objects();
	contentsCheck(!$controller->calls, 'Required login must not be bypassed.');
} finally {
	unlink($directory.'/controllers/SearchController.php'); rmdir($directory.'/controllers'); rmdir($directory);
}
print 'Collection contents: '.$assertions." assertions passed (SQLite traversal; native object search remains delegated).\n";
