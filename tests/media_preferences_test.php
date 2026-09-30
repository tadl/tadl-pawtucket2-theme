<?php
/**
 * Standalone regression checks: php tests/media_preferences_test.php
 * Uses an in-memory SQLite schema and synthetic records only. No app setup or DB is loaded.
 */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) { return false; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
define('pString', 1);
define('pInteger', 2);
define('pArray', 3);
define('TEST_THEME', dirname(__DIR__));
if (!extension_loaded('pdo_sqlite')) { throw new RuntimeException('Run this test with the PDO SQLite extension enabled.'); }
$GLOBALS['assertions'] = 0;
function testAssert($condition, $message) {
    $GLOBALS['assertions']++;
    if (!$condition) { throw new RuntimeException($message); }
}
function _t($text, ...$values) {
    foreach ($values as $i => $value) { $text = str_replace('%'.($i + 1), (string)$value, $text); }
    return $text;
}
function caGetOption($name, $options, $default = null, $types = null) { return $options[$name] ?? $default; }
function caGetUserAccessValues($request) { return $request->access ?? [1]; }
function caUnserializeForDatabase($data) {
    if (is_array($data)) { return $data; }
    if ($raw = @gzuncompress($data)) { return unserialize($raw); }
    return unserialize(base64_decode($data));
}
function caSerializeForDatabase($data, $compress = false) { return $compress ? gzcompress(serialize($data)) : base64_encode(serialize($data)); }
class Session {
    static public array $values = [];
    static function getVar($name) { return self::$values[$name] ?? null; }
    static function setVar($name, $value) { self::$values[$name] = $value; }
}
class TestRequest {
    public array $access = [1];
    public TestConfig $config;
    function __construct(public array $params = [], public string $controller = 'Browse', public string $action = 'objects', public array $cookies = []) { $this->config = new TestConfig(['maximum_find_result_list_values' => 1000]); }
    function getParameter($name, $type = null, $method = null, $options = []) {
        $value = $this->params[$name] ?? null;
        return $type === pInteger ? (int)$value : $value;
    }
    function getParameters($methods = null) { return $methods === ['COOKIE'] ? $this->cookies : $this->params; }
    function parameterExists($name, $method = null) { return array_key_exists($name, $this->params); }
    function setParameter($name, $value, $method = null) {
        if ($method === 'COOKIE') { $this->cookies[$name] = $value; }
        else { $this->params[$name] = $value; }
    }
    function getController() { return $this->controller; }
    function getAction() { return $this->action; }
    function getModulePath() { return ''; }
    function getActionExtra() { return ''; }
    function getBaseUrlPath() { return ''; }
    function isAjax() { return false; }
}
function testMediaRequest($mode, $params = [], $controller = 'Browse', $action = 'objects') {
    return new TestRequest($params, $controller, $action, ['tadlMediaPreference' => $mode]);
}
function caGenerateCSRFToken($request) { return 'synthetic-csrf-token'; }
function caNavUrl($request, $module, $controller, $action, $params = [], $options = []) {
    if ($controller === '*') { $controller = $request->getController(); }
    if ($action === '*') { $action = $request->getAction(); }
    $url = '/'.$controller.'/'.$action;
    if ($options['useQueryString'] ?? false) { return $url.($params ? '?'.http_build_query($params) : ''); }
    foreach ($params as $key => $value) { $url .= '/'.$key.'/'.rawurlencode((string)$value); }
    return $url;
}
function caNavLink($request, $label, $class, $module, $controller, $action, $params = [], $attributes = [], $options = []) {
    return '<a class="'.htmlspecialchars($class, ENT_QUOTES, 'UTF-8').'" href="'.htmlspecialchars(caNavUrl($request, $module, $controller, $action, $params, $options), ENT_QUOTES, 'UTF-8').'">'.$label.'</a>';
}
function caDetailUrl($request, $table, $id, $asPieces = false, $params = [], $options = []) {
    $url = '/Detail/'.substr($table, 3).'/'.(int)$id;
    foreach ($params as $key => $value) { $url .= '/'.$key.'/'.rawurlencode((string)$value); }
    return $url;
}
function caUcFirstUTF8Safe($text) { return ucfirst($text); }
function tadlGetDescendantCollectionImages($ids, $options = []) { return []; }
function caGetDisplayImagesForAuthorityItems($table, $ids, $options = []) { return []; }
class ca_objects {
    function getPrimaryMediaForIDs($ids, $versions, $options = []) {
        return array_fill_keys($ids, ['urls' => ['medium' => 'https://example.org/synthetic-preview.jpg']]);
    }
}
class TestMediaUrl {
    function embedTag($url) { return $url === 'https://example.org/synthetic-embeddable' ? '<iframe title="Synthetic media"></iframe>' : null; }
}
class_alias(TestMediaUrl::class, 'CA\\MediaUrl');
class ResultContext {
    static public array $saved = [];
    private array $values = [];
    function __construct($request, private string $table, private string $type, private ?string $block = null) {}
    function setResultList($ids) { $this->values['ids'] = $ids; $this->setSearchHistory(count($ids)); }
    function setSearchHistory($count) { $this->values['count'] = $count; }
    function setParameter($key, $value) { $this->values[$key] = $value; }
    function saveContext() { self::$saved[$this->table.':'.$this->type.':'.$this->block] = $this->values; }
}
class TestConfig {
    function __construct(private array $values) {}
    function get($key) { return $this->values[$key] ?? null; }
}
class TestView {
    function __construct(public TestRequest $request, private array $values) {}
    function getVar($key) { return $this->values[$key] ?? null; }
    function setVar($key, $value) { $this->values[$key] = $value; }
    function render($file) {
        ob_start();
        try { include $file; return ob_get_clean(); } catch (Throwable $error) { ob_end_clean(); throw $error; }
    }
}
class TestDbResult {
    private int $position = -1;
    function __construct(private array $rows) {}
    function nextRow() { return ++$this->position < count($this->rows); }
    function get($field) { return $this->rows[$this->position][$field] ?? null; }
    function numRows() { return count($this->rows); }
    function getAllFieldValues($field) { return array_column($this->rows, $field); }
    function getMediaInfo($field, $version = null, $key = null) {
        $data = caUnserializeForDatabase($this->get($field));
        $value = $version === null ? $data : ($data[$version] ?? null);
        return $key === null ? $value : ($value[$key] ?? null);
    }
    function hasMedia($field) { return (bool)$this->getMediaInfo($field); }
    function getMediaUrl($field, $version) {
        $data = $this->getMediaInfo($field, $version);
        if (!$data || !empty($data['QUEUED'])) { return ''; }
        return $data['EXTERNAL_URL'] ?? (!empty($data['FILENAME']) ? '/synthetic-media/'.$data['FILENAME'] : '');
    }
}
class Db {
    static public PDO $pdo;
    static public array $queries = [];
    function query($sql, $params = [], $options = null) {
        self::$queries[] = ['sql' => $sql, 'params' => $params];
        // CollectiveAccess permits array values for a single IN (?) placeholder.
        $bindings = [];
        $index = 0;
        $sql = preg_replace_callback('/\?/', function () use (&$index, &$bindings, $params) {
            $value = $params[$index++] ?? null;
            $values = is_array($value) ? $value : [$value];
            array_push($bindings, ...$values);
            return $values ? implode(',', array_fill(0, count($values), '?')) : 'NULL';
        }, $sql);
        $statement = self::$pdo->prepare($sql);
        $statement->execute($bindings);
        return new TestDbResult($statement->fetchAll(PDO::FETCH_ASSOC));
    }
}
class TestModel {
    function __construct(private string $table) {}
    function getDb() { return new Db(); }
    function tableName() { return $this->table; }
    function tableNum() { return ['ca_objects' => 57, 'ca_collections' => 67, 'ca_entities' => 20][$this->table]; }
    function primaryKey() { return ['ca_objects' => 'object_id', 'ca_collections' => 'collection_id', 'ca_entities' => 'entity_id'][$this->table]; }
}
class Datamodel {
    static function getInstanceByTableName($table, $cached = null) { return new TestModel($table); }
    static function getInstanceByTableNum($number, $cached = null) { return new TestModel([57 => 'ca_objects', 67 => 'ca_collections', 20 => 'ca_entities'][$number]); }
}
// SearchResult adapter boundary; production uses the native BrowseResult engine.
class WLPlugSearchEngineBrowseEngine {
    private array $ids;
    private int $position = -1;
    function __construct($ids, $description, private int $tableNum) { $this->ids = $ids; }
    function getHits($limit = null) { return $limit > 0 ? array_slice($this->ids, $this->position + 1, $limit) : $this->ids; }
    function numHits() { return count($this->ids); }
    function seek($position) { if ($position < 0 || $position >= count($this->ids)) { return false; } $this->position = $position - 1; return true; }
    function nextHit() { return ++$this->position < count($this->ids); }
    function currentRow() { return $this->position; }
    function get($field) { return $this->ids[$this->position] ?? null; }
}
class SearchResult {
    protected $engine;
    function __construct(private string $table, array $ids) { $this->engine = new WLPlugSearchEngineBrowseEngine($ids, [], $this->tableNum()); }
    function getDb() { return new Db(); }
    function tableName() { return $this->table; }
    function tableNum() { return Datamodel::getInstanceByTableName($this->table)->tableNum(); }
    function numHits() { return $this->engine->numHits(); }
    function getPrimaryKeyValues($limit = null) { return $this->engine->getHits($limit); }
    function getPrimaryKey() { return $this->engine->get(Datamodel::getInstanceByTableName($this->table)->primaryKey()); }
    function init($engine, $tables, $options = null) { $this->engine = $engine; }
    function seek($position) { return $this->engine->seek($position); }
    function nextHit() { return $this->engine->nextHit(); }
    function currentIndex() { return $this->engine->currentRow(); }
    function get($field, $options = []) {
        if (strpos($field, 'preferred_labels') !== false) { return 'Synthetic item '.$this->getPrimaryKey().' <sample> & archive'; }
        return $this->getPrimaryKey();
    }
    function getWithTemplate($template, $options = []) { return ''; }
}
Db::$pdo = new PDO('sqlite::memory:');
Db::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
Db::$pdo->exec('CREATE TABLE ca_objects (object_id INTEGER PRIMARY KEY, access INTEGER, deleted INTEGER)');
Db::$pdo->exec('CREATE TABLE ca_object_representations (representation_id INTEGER PRIMARY KEY, access INTEGER, deleted INTEGER, media BLOB, mimetype TEXT, original_filename TEXT, md5 TEXT)');
Db::$pdo->exec('CREATE TABLE ca_objects_x_object_representations (relation_id INTEGER PRIMARY KEY, object_id INTEGER, representation_id INTEGER, is_primary INTEGER)');
Db::$pdo->exec('CREATE TABLE ca_collections (collection_id INTEGER PRIMARY KEY, hier_collection_id INTEGER, hier_left INTEGER, hier_right INTEGER, access INTEGER, deleted INTEGER)');
Db::$pdo->exec('CREATE TABLE ca_objects_x_collections (relation_id INTEGER PRIMARY KEY, object_id INTEGER, collection_id INTEGER)');
Db::$pdo->exec('CREATE TABLE ca_collections_x_object_representations (relation_id INTEGER PRIMARY KEY, collection_id INTEGER, representation_id INTEGER, is_primary INTEGER)');
function testInsert($table, $row) {
    $columns = array_keys($row);
    $statement = Db::$pdo->prepare('INSERT INTO '.$table.' ('.implode(',', $columns).') VALUES ('.implode(',', array_fill(0, count($row), '?')).')');
    $statement->execute(array_values($row));
}
function testObject($id, $access = 1, $deleted = 0) { testInsert('ca_objects', ['object_id' => $id, 'access' => $access, 'deleted' => $deleted]); }
function testRepresentation($id, $access = 1, $deleted = 0, $kind = 'file') {
    $media = match ($kind) {
        'file' => ['INPUT' => ['MIMETYPE' => 'image/jpeg', 'MD5' => str_repeat('a', 32)], 'original' => ['VOLUME' => 'archive', 'DIRECTORY' => '/example/', 'FILENAME' => 'synthetic-'.$id.'.jpg']],
        'video' => ['INPUT' => ['MIMETYPE' => 'video/mp4', 'MD5' => str_repeat('b', 32)], 'original' => ['VOLUME' => 'archive', 'DIRECTORY' => '/example/', 'FILENAME' => 'synthetic-'.$id.'.mp4']],
        'audio' => ['INPUT' => ['MIMETYPE' => 'audio/mpeg', 'MD5' => str_repeat('c', 32)], 'original' => ['VOLUME' => 'archive', 'DIRECTORY' => '/example/', 'FILENAME' => 'synthetic-'.$id.'.mp3']],
        'embed' => ['INPUT' => ['MIMETYPE' => null, 'MD5' => null], 'IS_EMBEDDED' => 1, 'original' => ['EXTERNAL_URL' => 'https://example.org/synthetic-media']],
        'empty' => [],
        'stub' => ['INPUT' => ['MIMETYPE' => null, 'MD5' => null, 'FILESIZE' => null], 'ORIGINAL_FILENAME' => '', 'IS_EMBEDDED' => 0, '_CENTER' => []],
    };
    if (isset($media['original']['FILENAME'])) { $media['original'] += ['HASH' => 'a/b', 'MAGIC' => '123']; }
    testInsert('ca_object_representations', ['representation_id' => $id, 'access' => $access, 'deleted' => $deleted, 'media' => caSerializeForDatabase($media, true), 'mimetype' => $media['INPUT']['MIMETYPE'] ?? '', 'original_filename' => $kind === 'file' ? 'synthetic.jpg' : '', 'md5' => $media['INPUT']['MD5'] ?? '']);
}
function testRelation($object, $representation, $primary = 1) { testInsert('ca_objects_x_object_representations', ['object_id' => $object, 'representation_id' => $representation, 'is_primary' => $primary]); }
function testCollection($id, $hierarchy, $left, $right, $access = 1, $deleted = 0) { testInsert('ca_collections', ['collection_id' => $id, 'hier_collection_id' => $hierarchy, 'hier_left' => $left, 'hier_right' => $right, 'access' => $access, 'deleted' => $deleted]); }
function testCollectionObject($collection, $object) { testInsert('ca_objects_x_collections', ['collection_id' => $collection, 'object_id' => $object]); }

// Separate failures distinguish subject access, representation access and deletion.
foreach (range(1, 13) as $id) { testObject($id, $id === 3 ? 0 : 1, $id === 4 ? 1 : 0); }
testRepresentation(1); testRelation(1, 1);                 // Public image.
testRepresentation(2, 0); testRelation(2, 2);             // Private representation.
testRepresentation(3); testRelation(3, 3);                // Private object.
testRepresentation(4); testRelation(4, 4);                // Deleted object.
testRepresentation(5, 1, 1); testRelation(5, 5);          // Deleted representation.
testRepresentation(6, 1, 0, 'stub'); testRelation(6, 6);   // Media-less serialized descriptor.
testRepresentation(7, 1, 0, 'empty'); testRelation(7, 7);  // Serialized empty array.
testRepresentation(8, 0); testRelation(8, 8);             // Private primary...
testRepresentation(18); testRelation(8, 18, 0);          // ...but public secondary image.
testRepresentation(9, 1, 0, 'video'); testRelation(9, 9); // Media extends beyond images.
testRepresentation(10, 1, 0, 'audio'); testRelation(10, 10);
testRepresentation(11, 1, 0, 'embed'); testRelation(11, 11);
// Object 12 has no representations.
testRepresentation(13); testRelation(13, 13);            // Duplicate links must not duplicate IDs.
testRelation(13, 13, 0);

// Root 100 has image/media objects only in grandchildren; 106 is an empty sibling.
testCollection(100, 100, 1, 20);
testCollection(101, 100, 2, 9);
testCollection(102, 100, 3, 4); testCollectionObject(102, 1);
testCollection(103, 100, 5, 6); testCollectionObject(103, 8);
testCollection(104, 100, 7, 8); testCollectionObject(104, 12);
testCollection(105, 100, 10, 11); testCollectionObject(105, 2);
testCollection(106, 100, 12, 13);
testCollection(107, 100, 14, 15, 0); testCollectionObject(107, 9);
testCollection(108, 100, 16, 17, 1, 1); testCollectionObject(108, 10);
testCollection(109, 100, 18, 19); testCollectionObject(109, 3);

// Matching interval values in an unrelated hierarchy must not leak into root 200.
testCollection(200, 200, 1, 20);
testCollection(201, 200, 2, 3); testCollectionObject(201, 12);
testCollection(202, 200, 4, 5); testCollectionObject(202, 6);
testCollection(203, 200, 6, 7); testCollectionObject(203, 5);
testCollection(204, 200, 8, 9); testCollectionObject(204, 4);
testCollection(205, 200, 10, 11);
testInsert('ca_collections_x_object_representations', ['collection_id' => 205, 'representation_id' => 1, 'is_primary' => 1]);

// Private and deleted roots cannot be made eligible by their public children.
testCollection(300, 300, 1, 6, 0);
testCollection(301, 300, 2, 3); testCollectionObject(301, 1);
testCollection(400, 400, 1, 6, 1, 1);
testCollection(401, 400, 2, 3); testCollectionObject(401, 1);

$GLOBALS['expected_object_ids'] = [1, 8, 9, 10, 11, 13];
$GLOBALS['expected_collection_ids'] = [100, 101, 102, 103, 301, 401];

require_once TEST_THEME.'/helpers/media_preferences.php';
require_once TEST_THEME.'/helpers/media_filter_helpers.php';
require_once TEST_THEME.'/views/pageFormat/media_preference_toggle.php';

Session::$values = [];
$request = new TestRequest();
testAssert(tadlMediaPreference($request) === 'only', 'New visitor must default to Only items with media.');
testAssert(tadlMediaPreference(testMediaRequest('only')) === 'only', 'Cookie Only mode not selected.');
testAssert(tadlMediaPreference(new TestRequest()) === 'only', 'Site-wide session does not persist Only mode.');
testAssert(tadlMediaPreference(new TestRequest([], 'Collections', 'index')) === 'only', 'Preference lost on collection navigation.');
testAssert(tadlMediaPreference(testMediaRequest('invalid')) === 'only', 'Invalid cookie should retain preference.');
testAssert(tadlMediaPreference(testMediaRequest(['only'])) === 'only', 'Array cookie should not become a valid new preference.');
testAssert(tadlMediaPreference(testMediaRequest('all')) === 'all', 'Cookie All mode must beat session Only.');
testAssert(tadlMediaPreference(new TestRequest()) === 'all', 'All preference does not persist.');
testAssert(tadlMediaPreference(new TestRequest(['media' => 'only'])) === 'all', 'Legacy URL must not override the site-wide preference.');
Session::$values = [];
testAssert(tadlMediaPreference(testMediaRequest('all')) === 'all', 'Cookie must survive a fresh server session.');
testAssert(tadlSetMediaPreference(new TestRequest(), 'invalid') === false, 'Invalid mode must not be persisted.');
$cookieRequest = new TestRequest();
testAssert(tadlSetMediaPreference($cookieRequest, 'only') === true, 'Valid preference failed to persist.');
testAssert(tadlMediaPreference($cookieRequest) === 'only', 'Saved preference was not visible in the current request.');
$cookieOptions = tadlMediaPreferenceCookieOptions(new TestRequest());
testAssert($cookieOptions['path'] === '/' && $cookieOptions['httponly'] && $cookieOptions['samesite'] === 'Lax', 'Cookie scope/security attributes are incorrect.');
testAssert($cookieOptions['expires'] >= time() + 31535990, 'Preference cookie must persist for one year.');
$subpathRequest = new class extends TestRequest { function getBaseUrlPath() { return '/archive'; } };
testAssert(tadlMediaPreferenceCookieOptions($subpathRequest)['path'] === '/archive/', 'Cookie must stay within the application path.');
define('__CA_SITE_PROTOCOL__', 'https');
testAssert(tadlMediaPreferenceCookieOptions(new TestRequest())['secure'] === true, 'HTTPS preference cookie must use Secure.');

$request = new TestRequest(['search' => 'example "quotes" & <sample>', 'key' => 'synthetic-cache', 'sort' => 'name', 'direction' => 'asc', 'view' => 'list', 'n' => 24, 'page' => 5, 's' => 96, 'row_id' => 55, 'facets' => 'collection_facet:42', '_advanced' => 0, 'media' => 'all', 'password' => 'synthetic-secret', 'token' => 'synthetic-token']);
$url = tadlMediaPreferenceUrl($request);
$parts = parse_url($url);
parse_str($parts['query'], $params);
testAssert($parts['path'] === '/Browse/objects', 'Toggle changed current route.');
foreach (['search', 'key', 'sort', 'direction', 'view', 'n', 'facets', '_advanced'] as $field) {
    testAssert($params[$field] === (string)$request->params[$field], 'Toggle failed to preserve '.$field.'.');
}
foreach (['s', 'page', 'row_id', 'password', 'token'] as $field) {
    testAssert(!isset($params[$field]), 'Toggle retained forbidden/stale parameter '.$field.'.');
}
testAssert(!isset($params['media']), 'Toggle return URL must not carry the preference.');
testAssert(!str_contains($url, 'synthetic-secret') && !str_contains($url, 'synthetic-token'), 'Toggle URL leaked unrelated sensitive state.');
$html = tadlRenderMediaPreferenceToggle(testMediaRequest('only'));
$dom = new DOMDocument();
$previous = libxml_use_internal_errors(true);
$dom->loadHTML($html);
libxml_clear_errors(); libxml_use_internal_errors($previous);
$xpath = new DOMXPath($dom);
testAssert($xpath->query('//form[@role="group" and @aria-label and @method="post" and @action="/MediaPreference/Set"]')->length === 1, 'Toggle needs a labelled POST form.');
testAssert($xpath->query('//button[@type="submit" and @name="tadlMediaPreference"]')->length === 2, 'Toggle needs exactly two native submit buttons.');
testAssert($xpath->query('//button[@aria-pressed="true"]')->length === 1, 'Selected toggle state is not unique.');
testAssert($xpath->query('//button[@aria-pressed="true"]')->item(0)->textContent === '✓Only items with media', 'Wrong selected label.');
testAssert($xpath->query('//input[@name="csrfToken" and @value="synthetic-csrf-token"]')->length === 1, 'Toggle must include the native CSRF token.');
testAssert($xpath->query('//input[@name="media"]')->length === 0, 'Toggle leaked preference into query-style input.');

$objectIDs = tadlMediaEligibleIDs('ca_objects', range(1, 13), [1]);
testAssert($objectIDs === $GLOBALS['expected_object_ids'], 'Object eligibility mismatch: '.json_encode($objectIDs));
$collectionCandidates = [100,101,102,103,104,105,106,107,108,109,200,201,202,203,204,205,300,301,400,401];
$collectionIDs = tadlMediaEligibleIDs('ca_collections', $collectionCandidates, [1]);
testAssert($collectionIDs === $GLOBALS['expected_collection_ids'], 'Collection eligibility mismatch: '.json_encode($collectionIDs));
testAssert(tadlMediaEligibleIDs('ca_collections', [205], [1]) === [], 'Direct collection representation incorrectly qualified without an object.');
testAssert(tadlMediaEligibleIDs('ca_objects', [13,9,1,8,10,11,1], [1]) === [13,9,1,8,10,11], 'Eligibility changed order or duplicated IDs.');
testAssert(tadlMediaEligibleIDs('ca_objects', [null, '1 OR 1=1', -1, '1', 0, '8', 'x'], [1]) === [1,8], 'Candidate IDs were not validated.');
testAssert(tadlMediaEligibleIDs('ca_objects', [1], []) === [], 'Empty access mask must deny.');
testAssert(tadlMediaEligibleIDs('ca_entities', [1], [1]) === [], 'Unsupported subject unexpectedly queried.');
testAssert(tadlMediaEligibleIDs('ca_objects', [1], ['1 OR 1=1', -1, null]) === [], 'Invalid access mask accepted.');
$queryCount = count(Db::$queries);
testAssert(tadlMediaEligibleIDs('ca_objects', range(1,13), [1]) === $objectIDs, 'Repeated lookup changed IDs.');
testAssert(count(Db::$queries) === $queryCount, 'Request eligibility cache did not reuse ID/access scope.');
testAssert(tadlMediaEligibleIDs('ca_objects', [1,2,3], [0,1]) === [1,2,3], 'Access masks share incorrect eligibility cache.');

// Filtering all IDs before pagination prevents empty pages when early records lack media.
$ordered = [12,7,6,5,4,3,2,1,8,9,10,11,13];
$result = new SearchResult('ca_objects', $ordered);
$same = tadlFilterMediaResult(testMediaRequest('only'), $result);
testAssert($same === $result, 'Filtering did not mutate the upstream result reference.');
testAssert($result->numHits() === 6, 'Filtered count includes unavailable media.');
testAssert($result->getPrimaryKeyValues() === [1,8,9,10,11,13], 'Filtered result IDs/order mismatch.');
testAssert($result->seek(0) && $result->nextHit() && $result->getPrimaryKey() === 1, 'First page begins with excluded item.');
testAssert($result->seek(3) && $result->nextHit() && $result->getPrimaryKey() === 10, 'Second page offset uses unfiltered IDs.');
$allResult = new SearchResult('ca_objects', $ordered);
tadlFilterMediaResult(testMediaRequest('all'), $allResult);
testAssert($allResult->getPrimaryKeyValues() === $ordered, 'All mode modified native result IDs.');
$authorityResult = new SearchResult('ca_entities', [1,2,3]);
tadlFilterMediaResult(testMediaRequest('only'), $authorityResult);
testAssert($authorityResult->getPrimaryKeyValues() === [1,2,3], 'Authority results unexpectedly filtered.');
$emptyResult = new SearchResult('ca_objects', [2,3,4,5,6,7,12]);
tadlFilterMediaResult(testMediaRequest('only'), $emptyResult);
testAssert($emptyResult->numHits() === 0 && $emptyResult->getPrimaryKeyValues() === [], 'All-excluded results did not become empty.');

// Self matching is essential while new collection bounds have not yet been built.
testCollection(500, 0, 0, 0); testCollectionObject(500, 1);
testAssert(tadlMediaEligibleIDs('ca_collections', [500], [1]) === [500], 'Uninitialized hierarchy bounds prevent direct object qualification.');
// Chunking avoids a single unbounded bind parameter list.
foreach (range(1000,1600) as $id) { testObject($id); testRelation($id, 1); }
$queryCount = count(Db::$queries);
testAssert(tadlMediaEligibleIDs('ca_objects', range(1000,1600), [1]) === range(1000,1600), 'Chunked candidate batch dropped results.');
testAssert(count(Db::$queries) - $queryCount === 2, '601 candidates should execute two bounded batches.');

// Native embedding can be playable even when no original derivative exists.
foreach ([1700 => ['IS_EMBEDDED' => 1, 'INPUT' => ['FETCHED_FROM' => 'https://example.org/synthetic-embeddable']], 1701 => ['IS_EMBEDDED' => 1, 'INPUT' => ['FETCHED_FROM' => 'https://example.org/unsupported']], 1702 => ['IS_EMBEDDED' => 0, 'INPUT' => ['FETCHED_FROM' => 'https://example.org/synthetic-embeddable']]] as $id => $media) {
    testObject($id);
    testInsert('ca_object_representations', ['representation_id' => $id, 'access' => 1, 'deleted' => 0, 'media' => caSerializeForDatabase($media, true)]);
    testRelation($id, $id);
}
testAssert(tadlMediaEligibleIDs('ca_objects', [1700,1701,1702], [1]) === [1700], 'Only native playable embeds should qualify without originals.');

$facetItems = [100 => ['id' => 100, 'label' => 'Synthetic root', 'content_count' => 20], 200 => ['id' => 200, 'label' => 'Synthetic empty root', 'content_count' => 20]];
$facetInfo = ['tadl_subject_table' => 'ca_objects', 'table' => 'ca_collections', 'type' => 'authority'];
$filteredFacet = tadlMediaFacetItems(testMediaRequest('only'), $facetItems, $facetInfo);
testAssert(array_keys($filteredFacet) === [100], 'Media-only collection facet retained an empty hierarchy.');
testAssert(!isset($filteredFacet[100]['content_count']), 'Media-only facet displays an unfiltered count.');
testAssert(tadlMediaFacetItems(testMediaRequest('all'), $facetItems, $facetInfo) === $facetItems, 'All-mode facets were changed.');
$facetInfo['tadl_subject_table'] = 'ca_entities';
testAssert(tadlMediaFacetItems(testMediaRequest('only'), $facetItems, $facetInfo) === $facetItems, 'Authority subjects unrelated to media were changed.');
$facetInfo = ['tadl_subject_table' => 'ca_collections', 'table' => 'ca_entities', 'type' => 'authority'];
$filteredFacet = tadlMediaFacetItems(testMediaRequest('only'), $facetItems, $facetInfo);
testAssert(array_keys($filteredFacet) === [100,200] && !isset($filteredFacet[100]['content_count']), 'Unrelated facet choices must remain while inaccurate counts are removed.');

// Exercise the actual collection index: qualification happens before page count/seek.
foreach (range(600,629) as $id) { testCollection($id, 0, 0, 0); testCollectionObject($id, $id < 610 ? 1 : 12); }
$collectionConfig = new TestConfig(['collections_intro_text' => 'Synthetic introduction']);
foreach (['only' => [10, 1, 609], 'all' => [30, 9, 609]] as $mode => $expected) {
    $result = new SearchResult('ca_collections', range(600,629));
    $view = new TestView(testMediaRequest($mode, ['page' => 2, 'view' => 'tiles'], 'Collections', 'Index'), ['collection_results' => $result, 'collections_config' => $collectionConfig, 'section_name' => 'Example collections']);
    $html = $view->render(TEST_THEME.'/views/Collections/index_html.php');
    testAssert(strpos($html, $expected[0].' collections') !== false, $mode.': collection index uses unfiltered count.');
    testAssert(substr_count($html, 'data-collection-item') === $expected[1], $mode.': collection index rendered wrong page size.');
    testAssert(strpos($html, '/Detail/collections/'.$expected[2]) !== false, $mode.': collection page starts at unfiltered offset.');
    testAssert(strpos($html, '/media/') === false && strpos($html, 'media=') === false, $mode.': collection navigation leaked preference into URLs.');
    testAssert(strpos($html, '/Collections/Index/page/') !== false, $mode.': collection canonical URL lost page state.');
    testAssert(strpos($html, '&lt;sample&gt; &amp; archive') !== false, $mode.': collection label escaping failed.');
    testAssert(count(ResultContext::$saved['ca_collections:collections:']['ids']) === $expected[0], $mode.': detail navigation context contains wrong IDs.');
}
$emptyView = new TestView(testMediaRequest('only', [], 'Collections', 'Index'), ['collection_results' => new SearchResult('ca_collections', [200,201,202,203,204,205]), 'collections_config' => $collectionConfig, 'section_name' => 'Example collections']);
$html = $emptyView->render(TEST_THEME.'/views/Collections/index_html.php');
testAssert(strpos($html, 'No collections available') !== false && strpos($html, 'data-collection-item') === false, 'Collection index does not handle all-excluded results.');

$windowResult = new SearchResult('ca_objects', range(1,2400));
$windowView = new TestView(testMediaRequest('only'), ['start' => 1100]);
tadlMediaResultContext($windowView, $windowResult, 'browse');
$windowIDs = ResultContext::$saved['ca_objects:browse:']['ids'];
testAssert(count($windowIDs) === 1000, 'Detail context exceeded the native result-list limit.');
testAssert(in_array(1101, $windowIDs, true), 'Detail context window does not include the currently visible page.');
testAssert(ResultContext::$saved['ca_objects:browse:']['count'] === 2400, 'Bounded context replaced full result count with window length.');
testAssert(ResultContext::$saved['ca_objects:browse:']['media'] === null, 'Saved result context retained a URL preference.');
testAssert($windowResult->currentIndex() === -1, 'Saving detail context did not reset the result cursor.');
$latePageView = new TestView(testMediaRequest('all', ['page' => 123, 'view' => 'tiles'], 'Collections', 'Index'), ['collection_results' => new SearchResult('ca_collections', range(2000,3999)), 'collections_config' => $collectionConfig, 'section_name' => 'Example collections']);
$html = $latePageView->render(TEST_THEME.'/views/Collections/index_html.php');
testAssert(strpos($html, '/Detail/collections/3098') !== false, 'Late collection index rendered the wrong page.');
testAssert(in_array(3098, ResultContext::$saved['ca_collections:collections:']['ids'], true), 'Collection index saved detail context before calculating the page offset.');

// Native multisearch captures counts before preview rendering; verify full filtered IDs
// drive both the actual preview and the overview navigation/counts.
$counts = [];
foreach ([
    'objects' => ['ca_objects', range(1,13)],
    'collections' => ['ca_collections', $collectionCandidates],
    'people' => ['ca_entities', [1,2,3]],
    'organizations' => ['ca_entities', [4,5]],
] as $block => $definition) {
    [$table, $ids] = $definition;
    $result = new SearchResult($table, $ids);
    $originalCount = $result->numHits();
    $view = new TestView(testMediaRequest('only', ['search' => 'example <sample> & archive'], 'MultiSearch', 'Index'), ['result' => $result, 'block' => $block, 'blockInfo' => ['table' => $table, 'displayName' => ucfirst($block)], 'search' => 'example <sample> & archive', 'itemsPerPage' => 6]);
    $html = $view->render(TEST_THEME.'/views/Search/tadl_search_results_subview_html.php');
    $expectedCount = in_array($table, ['ca_objects','ca_collections'], true) ? 6 : count($ids);
    testAssert(strpos($html, ucfirst($block).' ('.$expectedCount.')') !== false, $block.': actual preview retained old count.');
    testAssert(substr_count($html, '<li>') === min(6, $expectedCount), $block.': actual preview paged before filtering.');
    testAssert(strpos($html, 'media=') === false && strpos($html, '/media/') === false, $block.': search navigation leaked preference into URLs.');
    testAssert(strpos($html, '/Detail/'.substr($table, 3).'/'.$result->getPrimaryKeyValues()[0]) !== false, $block.': detail link missing.');
    $counts[$block] = ['count' => $originalCount, 'ids' => $result->getPrimaryKeyValues(), 'table' => $table, 'displayName' => ucfirst($block), 'html' => $html];
}
$counts['_info_'] = ['totalCount' => 38];
$view = new TestView(testMediaRequest('only', [], 'MultiSearch', 'Index'), ['results' => $counts, 'blockNames' => ['objects','collections','people','organizations'], 'searchForDisplay' => 'example <sample> & archive']);
$html = $view->render(TEST_THEME.'/views/Search/multisearch_results_html.php');
testAssert(strpos($html, 'Objects <span>6</span>') !== false && strpos($html, 'Collections <span>6</span>') !== false, 'Overview navigation retained native pre-filter counts.');
testAssert(ResultContext::$saved['ca_entities:multisearch:']['count'] === 5, 'Multisearch authority table count did not sum separate categories.');
testAssert(ResultContext::$saved['ca_objects:multisearch:']['count'] === 6, 'Multisearch search history retained pre-filter count.');
testAssert(strpos($html, '<sample>') === false && strpos($html, '&lt;sample&gt; &amp; archive') !== false, 'Overview search/labels were not escaped.');

$counts = ['objects' => ['count' => 13, 'ids' => [], 'table' => 'ca_objects', 'displayName' => 'Objects', 'html' => ''], '_info_' => ['totalCount' => 13]];
$view = new TestView(testMediaRequest('only', [], 'MultiSearch', 'Index'), ['results' => $counts, 'blockNames' => ['objects'], 'searchForDisplay' => 'synthetic']);
$html = $view->render(TEST_THEME.'/views/Search/multisearch_results_html.php');
testAssert(strpos($html, 'returned no results') !== false && strpos($html, '<section') === false, 'Overview all-excluded results retained empty category.');

$allSubview = new TestView(testMediaRequest('all', [], 'MultiSearch', 'Index'), ['result' => new SearchResult('ca_objects', range(1,13)), 'block' => 'objects', 'blockInfo' => ['table' => 'ca_objects', 'displayName' => 'Objects'], 'search' => 'synthetic', 'itemsPerPage' => 6]);
$html = $allSubview->render(TEST_THEME.'/views/Search/tadl_search_results_subview_html.php');
testAssert(strpos($html, '/Detail/objects/1') !== false && strpos($html, '/media/') === false && strpos($html, 'media=') === false, 'All-mode search links must stay clean.');

echo json_encode(['status' => 'passed', 'assertions' => $GLOBALS['assertions'], 'sql_queries' => count(Db::$queries), 'database' => 'SQLite in memory with actual helper SQL', 'result_adapter' => 'synthetic SearchResult boundary', 'records' => 'synthetic only'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
