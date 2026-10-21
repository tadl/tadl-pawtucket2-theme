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
define('__CA_BUNDLE_ACCESS_READONLY__', 1);
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
function caSourceAccessControlIsEnabled($model) { return false; }
function caGetTypeRestrictionsForUser($table) { return null; }
function caGetSourceRestrictionsForUser($table) { return null; }
function caMakeTypeIDList($table, $types, $options = []) {
    testAssert($table === 'ca_entities', 'Family scope must resolve native entity types.');
    return $types === ['fam'] ? [30, 31] : [10, 11, 20, 21];
}
function caGetOption($name, $options, $default = null, $types = null) { return $options[$name] ?? $default; }
function caGetUserAccessValues($request) { return $request->access ?? [1]; }
function caGetBundleAccessLevel($table, $bundle) { return $GLOBALS['mediaBundleAccess'][$table.':'.$bundle] ?? 1; }
function caACLIsEnabled($table, $options = []) {
    testAssert(($options['forPawtucket'] ?? false) === true, 'Related media ACL checks must use Pawtucket configuration.');
    $table = is_object($table) ? $table->tableName() : $table;
    return $GLOBALS['mediaACL'][$table] ?? false;
}
function caGetBrowseInstance($table) { return new TestMediaBrowse($table); }
class TestMediaBrowse {
    private array $criteria = [], $filters = []; private $request;
    function setContext($context) {}
    function addCriteria($facet, $values) { $this->criteria[$facet] = $values; return true; }
    function addResultFilter($field, $operator, $value) { $this->filters[$field] = array_map('intval', explode(',', $value)); return true; }
    function setTypeRestrictions($types, $options) {}
    function execute($options) { testAssert($options['noCache'] && $options['checkAccess'] === [1], 'Embedded scopes must use fresh native visibility.'); $this->request = $options['request']; return true; }
    function getResults($options) {
        $ids = $this->filters['ca_objects.object_id'] ?? null;
        if ($ids === null) {
            $ids = [];
            preg_match_all('/(entity|occurrence)_id:(\d+)/', $this->criteria['_search'][0], $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $table = $match[1] === 'entity' ? 'entities' : 'occurrences';
                $query = Db::$pdo->prepare('SELECT object_id FROM ca_objects_x_'.$table.' WHERE '.$match[1].'_id=?'); $query->execute([(int)$match[2]]);
                $ids = array_merge($ids, $query->fetchAll(PDO::FETCH_COLUMN));
            }
        }
        $ids = tadlReadableIDs($this->request, 'ca_objects', array_map('intval', array_unique($ids)), [1]);
        sort($ids); if ($options['sort_direction'] === 'desc') { $ids = array_reverse($ids); }
        return new SearchResult('ca_objects', $ids);
    }
    function __construct(private string $table) {}
    function filterHitsByACL($ids, $tableNum, $userID) {
        testAssert($tableNum === Datamodel::getInstanceByTableName($this->table)->tableNum(), 'Related media ACL check used the wrong table.');
        $GLOBALS['mediaACLCalls'][] = [$this->table, $ids, $userID];
        $blocked = $GLOBALS['mediaACLBlocked'][$userID][$this->table] ?? [];
        return array_values(array_diff($ids, $blocked));
    }
}
function caDisplayLightbox($request) { return false; }
function caDetailLink($request, $label, $class, $table, $id) { return '<a href="'.caDetailUrl($request, $table, $id).'">'.$label.'</a>'; }
function caGetIconsConfig() { return new TestConfig(['placeholder_media_icon' => '<span>Media</span>']); }
class ca_list_items {}
class ExternalCache {
    static array $values = [];
    static function fetch($key, $group = null) { return self::$values[$group][$key] ?? null; }
    static function contains($key, $group = null) { return false; }
    static function save($key, $value, $group = null, $timeout = null) { self::$values[$group][$key] = $value; }
}
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
    public bool $ajax = false;
    public int $userID = 0;
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
    function setController($value) { $this->controller = $value; }
    function setAction($value) { $this->action = $value; }
    function setActionExtra($value) {}
    function getUserID() { return $this->userID; }
    function getAction() { return $this->action; }
    function getModulePath() { return ''; }
    function getActionExtra() { return ''; }
    function getBaseUrlPath() { return ''; }
    function isAjax() { return $this->ajax; }
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
    $attrs = '';
    foreach ($attributes as $key => $value) { $attrs .= ' '.htmlspecialchars($key, ENT_QUOTES, 'UTF-8').'="'.htmlspecialchars($value, ENT_QUOTES, 'UTF-8').'"'; }
    return '<a class="'.htmlspecialchars($class, ENT_QUOTES, 'UTF-8').'" href="'.htmlspecialchars(caNavUrl($request, $module, $controller, $action, $params, $options), ENT_QUOTES, 'UTF-8').'"'.$attrs.'>'.$label.'</a>';
}
function caDetailUrl($request, $table, $id, $asPieces = false, $params = [], $options = []) {
    $url = '/Detail/'.substr($table, 3).'/'.(int)$id;
    foreach ($params as $key => $value) { $url .= '/'.$key.'/'.rawurlencode((string)$value); }
    return $url;
}
function caUcFirstUTF8Safe($text) { return ucfirst($text); }
function tadlGetDescendantCollectionImages($ids, $options = []) { return []; }
function tadlGetCollectionImages($ids, $options = []) { return []; }
function caGetDisplayImagesForAuthorityItems($table, $ids, $options = []) { return []; }
class ca_objects {
    function getPrimaryMediaForIDs($ids, $versions, $options = []) {
        return array_fill_keys($ids, ['urls' => ['medium' => 'https://example.org/synthetic-preview.jpg'], 'tags'=>['small'=>'<img src="/synthetic-preview.jpg" alt="">','medium'=>'<img src="/synthetic-preview.jpg" alt="">'], 'representation_id'=>1]);
    }
}
class TestMediaUrl {
    function embedTag($url) { return $url === 'https://example.org/synthetic-embeddable' ? '<iframe title="Synthetic media"></iframe>' : null; }
}
class_alias(TestMediaUrl::class, 'CA\\MediaUrl');
class ResultContext {
    static public array $saved = [];
    static public array $lastFind = [];
    private array $values = [];
    function __construct($request, private string $table, private string $type, private ?string $block = null) {}
    function setResultList($ids) { $this->values['ids'] = $ids; $this->setSearchHistory(count($ids)); }
    function setSearchHistory($count) { $this->values['count'] = $count; $this->values['history_search'] = $this->values['search'] ?? null; }
    function setParameter($key, $value) { $this->values[$key] = $value; }
    function setItemsPerPage($count) { $this->values['per_page'] = $count; }
    function setCurrentView($view) { $this->values['view'] = $view; }
    function setSearchExpression($value) { $this->values['search'] = $value; }
    function setCurrentSort($value) { $this->values['sort'] = $value; }
    function setCurrentSortDirection($value) { $this->values['direction'] = $value; }
    function setAsLastFind($setAction = true) { self::$lastFind[$this->table] = [$this->type, $this->block, $setAction]; }
    function saveContext() { self::$saved[$this->table.':'.$this->type.':'.$this->block] = $this->values; }
    static function getResultContextForLastFind($request, $table) {
        [$type, $block] = self::$lastFind[$table];
        $context = new self($request, $table, $type, $block);
        $context->values = self::$saved[$table.':'.$type.':'.$block];
        return $context;
    }
    function getResultList() { return $this->values['ids']; }
    function findType() { return $this->type; }
    function getParameter($name, $storedOnly = false) {
        testAssert($storedOnly, 'Return navigation must use stored context, not request overrides.');
        return $this->values[$name] ?? null;
    }
}
class View extends TestView {
    function __construct($request, $paths) { parent::__construct($request, []); }
}
class TestConfig {
    function __construct(private array $values) {}
    function get($key) { return $this->values[$key] ?? null; }
    function getAssoc($key) { return (array)$this->get($key); }
}
class TestView {
    function __construct(public TestRequest $request, private array $values) {}
    function getVar($key) { return $this->values[$key] ?? null; }
    function setVar($key, $value) { $this->values[$key] = $value; }
    function getViewPaths() { return [TEST_THEME.'/views']; }
    function render($file) {
        ob_start();
        try { include str_starts_with($file, '/') ? $file : TEST_THEME.'/views/'.$file; return ob_get_clean(); } catch (Throwable $error) { ob_end_clean(); throw $error; }
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
    static public int $mediaRows = 0;
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
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        self::$mediaRows += count(array_filter($rows, static fn($row) => array_key_exists('media', $row)));
        return new TestDbResult($rows);
    }
}
class TestModel {
    private array $row = [];
    function load($id) {
        $query = Db::$pdo->prepare('SELECT * FROM '.$this->table.' WHERE '.$this->primaryKey().'=?');
        $query->execute([$id]); $this->row = $query->fetch(PDO::FETCH_ASSOC) ?: []; return (bool)$this->row;
    }
    function getPrimaryKey() { return $this->row[$this->primaryKey()] ?? null; }
    function get($field) { return $this->row[$field] ?? null; }
    function isReadable($request, $bundle = null) { return true; }
    function __construct(private string $table) {}
    function getAppConfig() { return new TestConfig([]); }
    function getDb() { return new Db(); }
    function tableName() { return $this->table; }
    // Synthetic table numbers only identify models within this test adapter.
    function tableNum() { return ['ca_objects' => 57, 'ca_collections' => 67, 'ca_entities' => 20, 'ca_places' => 72, 'ca_occurrences' => 68, 'ca_object_representations' => 56][$this->table]; }
    function primaryKey() { return ['ca_objects' => 'object_id', 'ca_collections' => 'collection_id', 'ca_entities' => 'entity_id', 'ca_places' => 'place_id', 'ca_occurrences' => 'occurrence_id'][$this->table]; }
}
class Datamodel {
    static function getInstance($table, $cached = null) { return new TestModel($table); }
    static function getInstanceByTableName($table, $cached = null) { return new TestModel($table); }
    static function getInstanceByTableNum($number, $cached = null) { return new TestModel([57 => 'ca_objects', 67 => 'ca_collections', 20 => 'ca_entities', 72 => 'ca_places', 68 => 'ca_occurrences'][$number]); }
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
    function getMediaTag($field, $version, $options = []) { return '<img src="/synthetic-preview.jpg" alt="">'; }
    function getWithTemplate($template, $options = []) { return ''; }
}
Db::$pdo = class_exists('Pdo\Sqlite') ? new Pdo\Sqlite('sqlite::memory:') : new PDO('sqlite::memory:');
$sqliteFunctionMethod = method_exists(Db::$pdo, 'createFunction') ? 'createFunction' : 'sqliteCreateFunction';
Db::$pdo->$sqliteFunctionMethod('SHA2', static fn($data, $bits) => hash('sha256', (string)$data), 2);
Db::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
Db::$pdo->exec('CREATE TABLE ca_objects (object_id INTEGER PRIMARY KEY, access INTEGER, deleted INTEGER)');
Db::$pdo->exec('CREATE TABLE ca_object_representations (representation_id INTEGER PRIMARY KEY, access INTEGER, deleted INTEGER, media BLOB, mimetype TEXT, original_filename TEXT, md5 TEXT)');
Db::$pdo->exec('CREATE TABLE ca_objects_x_object_representations (relation_id INTEGER PRIMARY KEY, object_id INTEGER, representation_id INTEGER, is_primary INTEGER)');
Db::$pdo->exec('CREATE TABLE ca_collections (parent_id INTEGER DEFAULT 0, collection_id INTEGER PRIMARY KEY, hier_collection_id INTEGER, hier_left INTEGER, hier_right INTEGER, access INTEGER, deleted INTEGER)');
Db::$pdo->exec('CREATE TABLE ca_objects_x_collections (relation_id INTEGER PRIMARY KEY, object_id INTEGER, collection_id INTEGER)');
Db::$pdo->exec('CREATE TABLE ca_collections_x_object_representations (relation_id INTEGER PRIMARY KEY, collection_id INTEGER, representation_id INTEGER, is_primary INTEGER)');
Db::$pdo->exec('CREATE TABLE ca_entities (entity_id INTEGER PRIMARY KEY, access INTEGER, deleted INTEGER, type_id INTEGER DEFAULT 10)');
Db::$pdo->exec('CREATE TABLE ca_entities_x_entities (entity_left_id INTEGER, entity_right_id INTEGER)');
Db::$pdo->exec('CREATE TABLE ca_objects_x_entities (relation_id INTEGER PRIMARY KEY, object_id INTEGER, entity_id INTEGER)');
Db::$pdo->exec('CREATE TABLE ca_entities_x_object_representations (relation_id INTEGER PRIMARY KEY, entity_id INTEGER, representation_id INTEGER)');
Db::$pdo->exec('CREATE TABLE ca_places (place_id INTEGER PRIMARY KEY, access INTEGER, deleted INTEGER, parent_id INTEGER DEFAULT 0, hierarchy_id INTEGER DEFAULT 0, hier_left INTEGER DEFAULT 0, hier_right INTEGER DEFAULT 0)');
Db::$pdo->exec('CREATE TABLE ca_objects_x_places (relation_id INTEGER PRIMARY KEY, object_id INTEGER, place_id INTEGER)');
Db::$pdo->exec('CREATE TABLE ca_occurrences (occurrence_id INTEGER PRIMARY KEY, access INTEGER, deleted INTEGER)');
Db::$pdo->exec('CREATE TABLE ca_objects_x_occurrences (relation_id INTEGER PRIMARY KEY, object_id INTEGER, occurrence_id INTEGER)');
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
        'pdf' => ['INPUT' => ['MIMETYPE' => 'application/pdf', 'MD5' => str_repeat('d', 32)], 'original' => ['VOLUME' => 'archive', 'DIRECTORY' => '/example/', 'FILENAME' => 'synthetic-'.$id.'.pdf']],
        'embed' => ['INPUT' => ['MIMETYPE' => null, 'MD5' => null], 'IS_EMBEDDED' => 1, 'original' => ['EXTERNAL_URL' => 'https://example.org/synthetic-media']],
        'empty' => [],
        'stub' => ['INPUT' => ['MIMETYPE' => null, 'MD5' => null, 'FILESIZE' => null], 'ORIGINAL_FILENAME' => '', 'IS_EMBEDDED' => 0, '_CENTER' => []],
    };
    if (isset($media['original']['FILENAME'])) { $media['original'] += ['HASH' => 'a/b', 'MAGIC' => '123']; }
    testInsert('ca_object_representations', ['representation_id' => $id, 'access' => $access, 'deleted' => $deleted, 'media' => caSerializeForDatabase($media, true), 'mimetype' => $media['INPUT']['MIMETYPE'] ?? '', 'original_filename' => $kind === 'file' ? 'synthetic.jpg' : '', 'md5' => $media['INPUT']['MD5'] ?? '']);
}
function testRelation($object, $representation, $primary = 1) { testInsert('ca_objects_x_object_representations', ['object_id' => $object, 'representation_id' => $representation, 'is_primary' => $primary]); }
function testCollection($id, $hierarchy, $left, $right, $access = 1, $deleted = 0) {
    testInsert('ca_collections', ['collection_id' => $id, 'hier_collection_id' => $hierarchy, 'hier_left' => $left, 'hier_right' => $right, 'access' => $access, 'deleted' => $deleted]);
    Db::$pdo->exec('UPDATE ca_collections SET parent_id = COALESCE((SELECT p.collection_id FROM ca_collections p WHERE p.hier_collection_id = ca_collections.hier_collection_id AND p.hier_left < ca_collections.hier_left AND p.hier_right > ca_collections.hier_right ORDER BY p.hier_left DESC LIMIT 1),0)');
}

function testCollectionObject($collection, $object) { testInsert('ca_objects_x_collections', ['collection_id' => $collection, 'object_id' => $object]); }
function testPerson($id, $access = 1, $deleted = 0) { testInsert('ca_entities', ['entity_id' => $id, 'access' => $access, 'deleted' => $deleted]); }
function testPersonObject($person, $object) { testInsert('ca_objects_x_entities', ['entity_id' => $person, 'object_id' => $object]); }

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

// Each person mirrors one object-media case, including private/deleted/stub media.
foreach (range(1, 13) as $id) { testPerson(20000 + $id); testPersonObject(20000 + $id, $id); }
testPerson(20014, 0); testPersonObject(20014, 1);
testPerson(20015, 1, 1); testPersonObject(20015, 1);
testPerson(20016); testPersonObject(20016, 2); testPersonObject(20016, 1); testPersonObject(20016, 1);
testPerson(20017); // A directly attached portrait alone must not qualify a person.
testInsert('ca_entities_x_object_representations', ['entity_id' => 20017, 'representation_id' => 1]);
$authorityBrowses = [
    'people' => ['ca_entities', 'entity_id', 'person', 'people'],
    'organizations' => ['ca_entities', 'entity_id', 'organization', 'organizations'],
    'places' => ['ca_places', 'place_id', 'place', 'places'],
    'occurrences' => ['ca_occurrences', 'occurrence_id', 'event', 'events']
];
foreach (['ca_places' => 'place_id', 'ca_occurrences' => 'occurrence_id'] as $table => $primaryKey) {
    foreach (range(1, 17) as $id) {
        testInsert($table, [$primaryKey => 20000 + $id, 'access' => $id === 14 ? 0 : 1, 'deleted' => $id === 15 ? 1 : 0]);
        $objects = $id <= 13 ? [$id] : ($id === 16 ? [2, 1, 1] : ($id === 17 ? [] : [1]));
        foreach ($objects as $objectID) { testInsert('ca_objects_x_'.substr($table, 3), [$primaryKey => 20000 + $id, 'object_id' => $objectID]); }
    }
}

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
testAssert(tadlMediaPreference($request) === 'all', 'New visitor must default to All items.');
testAssert(tadlMediaPreference(new TestRequest([], 'Collections', 'index')) === 'all', 'Default must apply to collection navigation.');
testAssert(tadlMediaPreference(testMediaRequest('invalid')) === 'all', 'Invalid cookie without a saved choice must use All items.');
testAssert(tadlMediaPreference(testMediaRequest(['only'])) === 'all', 'Array cookie without a saved choice must use All items.');
foreach (['invalid', ['only']] as $invalidSession) {
    Session::$values = ['tadlMediaPreference' => $invalidSession];
    testAssert(tadlMediaPreference(new TestRequest()) === 'all', 'Invalid session preference must use All items.');
}
Session::$values = [];
$defaultToggle = new DOMDocument();
$defaultToggle->loadHTML(tadlRenderMediaPreferenceToggle(new TestRequest()), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
$defaultXPath = new DOMXPath($defaultToggle);
testAssert($defaultXPath->query('//button[@aria-pressed="true"]')->length === 1
    && $defaultXPath->query('//button[@aria-pressed="true"]')->item(0)->getAttribute('value') === 'all', 'New visitor toggle must select All items uniquely.');
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
$collectionRequest = new TestRequest(['collection_id' => 42, 'collection_view' => 'hierarchy', 'view' => 'list', 'sort' => 'Title', 's' => 24]);
parse_str(parse_url(tadlMediaPreferenceUrl($collectionRequest), PHP_URL_QUERY), $collectionParams);
testAssert($collectionParams['collection_view'] === 'hierarchy' && $collectionParams['collection_id'] === '42' && !isset($collectionParams['s']), 'Media preference toggle must retain collection mode while resetting paging.');
$html = tadlRenderMediaPreferenceToggle(testMediaRequest('only'));
$dom = new DOMDocument();
$previous = libxml_use_internal_errors(true);
$dom->loadHTML($html);
libxml_clear_errors(); libxml_use_internal_errors($previous);
$xpath = new DOMXPath($dom);
testAssert($xpath->query('//form[not(@role) and @aria-label and @method="post" and @action="/MediaPreference/Set"]')->length === 1, 'Toggle needs a labelled POST form.');
testAssert($xpath->query('//button[@type="submit" and @name="tadlMediaPreference"]')->length === 2, 'Toggle needs exactly two native submit buttons.');
testAssert($xpath->query('//button[@aria-pressed="true"]')->length === 1, 'Selected toggle state is not unique.');
testAssert($xpath->query('//button[@aria-pressed="true"]')->item(0)->textContent === '✓Only items with media', 'Wrong selected label.');
testAssert($xpath->query('//input[@name="csrfToken" and @value="synthetic-csrf-token"]')->length === 1, 'Toggle must include the native CSRF token.');
testAssert($xpath->query('//input[@name="media"]')->length === 0, 'Toggle leaked preference into query-style input.');

$GLOBALS['g_request'] = testMediaRequest('only');
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

// People browse eligibility and paging use related object media, not portraits.
$peopleRequest = testMediaRequest('only', [], 'Browse', 'people');
$peopleCandidates = range(20001, 20017);
$peopleExpected = [20001, 20008, 20009, 20010, 20011, 20013, 20016];
testAssert(tadlAuthorityMediaEligibleIDs($peopleRequest, 'ca_entities', $peopleCandidates) === $peopleExpected, 'People retained inaccessible/deleted objects, unavailable media, or a portrait without related objects.');
testAssert(tadlAuthorityMediaEligibleIDs($peopleRequest, 'ca_entities', [20016, 20002, 20001, 20016, '20008', null, '20001 OR 1=1', -1]) === [20016, 20001, 20008], 'People eligibility must validate IDs and preserve native order without duplicates.');
$queryCount = count(Db::$queries);
testAssert(tadlAuthorityMediaEligibleIDs($peopleRequest, 'ca_entities', $peopleCandidates) === $peopleExpected && count(Db::$queries) === $queryCount, 'People eligibility did not reuse its request cache.');
$privateAccessRequest = testMediaRequest('only', [], 'Browse', 'people');
$privateAccessRequest->access = [0, 1];
testAssert(tadlAuthorityMediaEligibleIDs($privateAccessRequest, 'ca_entities', [20002, 20003, 20014, 20015]) === [20002, 20003, 20014], 'People cache mixed access masks or admitted a deleted person.');
$deniedAccessRequest = testMediaRequest('only', [], 'Browse', 'people');
$deniedAccessRequest->access = [];
testAssert(tadlAuthorityMediaEligibleIDs($deniedAccessRequest, 'ca_entities', $peopleCandidates) === [], 'People eligibility with an empty access mask must deny.');
$GLOBALS['mediaACL'] = ['ca_objects' => true, 'ca_object_representations' => true];
$GLOBALS['mediaACLBlocked'][11] = ['ca_objects' => [1], 'ca_object_representations' => [18]];
$aclPeopleRequest = testMediaRequest('only', [], 'Browse', 'people');
$aclPeopleRequest->userID = 11;
$beforeMediaRows = Db::$mediaRows;
testAssert(tadlAuthorityMediaEligibleIDs($aclPeopleRequest, 'ca_entities', $peopleCandidates) === [20009, 20010, 20011, 20013], 'People qualified through ACL-denied objects or representations.');
testAssert(array_column($GLOBALS['mediaACLCalls'], 2) === [11, 11], 'People ACL filtering did not use the current user in one batch per table.');
testAssert(Db::$mediaRows - $beforeMediaRows <= 7, 'People ACL-denied media was decoded or shared descriptors were repeatedly fetched.');
$GLOBALS['mediaACL'] = [];
foreach (['ca_objects:ca_object_representations', 'ca_object_representations:media'] as $bundle) {
    $GLOBALS['mediaBundleAccess'][$bundle] = 0;
    $queryCount = count(Db::$queries);
    testAssert(tadlAuthorityMediaEligibleIDs($peopleRequest, 'ca_entities', $peopleCandidates) === [] && count(Db::$queries) === $queryCount, 'Denied media bundle must suppress people without reading media, including cached eligibility.');
    $GLOBALS['mediaBundleAccess'] = [];
}
$peopleResult = new SearchResult('ca_entities', array_reverse($peopleCandidates));
tadlFilterMediaResult($peopleRequest, $peopleResult);
testAssert($peopleResult->getPrimaryKeyValues() === array_reverse($peopleExpected), 'People result filtering changed the requested native sort order.');
testAssert($peopleResult->seek(3) && $peopleResult->nextHit() && $peopleResult->getPrimaryKey() === 20010, 'People page offset was applied before eligibility.');
$peopleContextView = new TestView($peopleRequest, ['start' => 0]);
tadlMediaResultContext($peopleContextView, $peopleResult, 'browse');
testAssert(ResultContext::$saved['ca_entities:browse:']['ids'] === array_reverse($peopleExpected) && ResultContext::$saved['ca_entities:browse:']['count'] === 7, 'People detail navigation retained excluded people.');
foreach ([['Browse', 'people', 'all'], ['Browse', 'Index', 'only']] as [$controller, $action, $mode]) {
    $result = new SearchResult('ca_entities', $peopleCandidates);
    $queryCount = count(Db::$queries);
    tadlFilterMediaResult(testMediaRequest($mode, [], $controller, $action), $result);
    testAssert($result->getPrimaryKeyValues() === $peopleCandidates && count(Db::$queries) === $queryCount, 'People browse policy leaked into All items or another authority route.');
}
$ajaxPeopleResult = new SearchResult('ca_entities', $peopleCandidates);
tadlFilterMediaResult(testMediaRequest('only', ['browseType' => 'people'], 'Browse', 'getFacetHierarchyLevel'), $ajaxPeopleResult);
testAssert($ajaxPeopleResult->getPrimaryKeyValues() === $peopleExpected, 'People AJAX route lost its browse type.');

foreach (['Search', 'MultiSearch'] as $controller) {
    $result = new SearchResult('ca_entities', $peopleCandidates);
    tadlFilterMediaResult(testMediaRequest('only', [], $controller, 'people'), $result);
    testAssert($result->getPrimaryKeyValues() === $peopleExpected, 'Authority search must share media eligibility before paging and counts.');
    $result = new SearchResult('ca_entities', $peopleCandidates);
    tadlFilterMediaResult(testMediaRequest('only', [], $controller, 'Index'), $result, 'people');
    testAssert($result->getPrimaryKeyValues() === $peopleExpected, 'Multisearch must use the block type rather than the Index action.');
}
// All authority routes qualify through the correct related-object table.
foreach ($authorityBrowses as $route => [$table, $primaryKey, $singular, $plural]) {
    $request = testMediaRequest('only', [], 'Browse', $route);
    testAssert(tadlMediaAuthorityBrowseTable($request) === $table, $route.': authority browse resolves to the wrong table.');
    testAssert(tadlAuthorityMediaEligibleIDs($request, $table, $peopleCandidates) === $peopleExpected, $route.': related-object access, deletion or usable-media rules differ from People.');
    $privateRequest = testMediaRequest('only', [], 'Browse', $route);
    $privateRequest->access = [0, 1];
    testAssert(tadlAuthorityMediaEligibleIDs($privateRequest, $table, [20002, 20003, 20014, 20015]) === [20002, 20003, 20014], $route.': access masks or deleted authorities are mishandled.');
    $GLOBALS['mediaACL'] = ['ca_objects' => true, 'ca_object_representations' => true];
    $aclRequest = testMediaRequest('only', [], 'Browse', $route);
    $aclRequest->userID = 11;
    testAssert(tadlAuthorityMediaEligibleIDs($aclRequest, $table, $peopleCandidates) === [20009, 20010, 20011, 20013], $route.': authority qualified through ACL-denied media.');
    $GLOBALS['mediaACL'] = [];
    foreach (['ca_objects:ca_object_representations', 'ca_object_representations:media'] as $bundle) {
        $GLOBALS['mediaBundleAccess'][$bundle] = 0;
        $queryCount = count(Db::$queries);
        testAssert(tadlAuthorityMediaEligibleIDs($request, $table, $peopleCandidates) === [] && count(Db::$queries) === $queryCount, $route.': denied media bundle must suppress cached eligibility without queries.');
        $GLOBALS['mediaBundleAccess'] = [];
    }
    $result = new SearchResult($table, array_reverse($peopleCandidates));
    tadlFilterMediaResult($request, $result);
    testAssert($result->getPrimaryKeyValues() === array_reverse($peopleExpected), $route.': filter changed the native sort order.');
    $ajaxResult = new SearchResult($table, $peopleCandidates);
    tadlFilterMediaResult(testMediaRequest('only', ['browseType' => $route], 'Browse', 'getFacetHierarchyLevel'), $ajaxResult);
    testAssert($ajaxResult->getPrimaryKeyValues() === $peopleExpected, $route.': AJAX browse type was lost.');
    foreach (['Browse' => 'all'] as $controller => $mode) {
        $result = new SearchResult($table, $peopleCandidates);
        $queryCount = count(Db::$queries);
        $unfilteredRequest = testMediaRequest($mode, [], $controller, $route);
        tadlFilterMediaResult($unfilteredRequest, $result);
        testAssert($result->getPrimaryKeyValues() === $peopleCandidates && count(Db::$queries) === $queryCount, $route.': authority policy changed All items, Search or MultiSearch.');
        $facet = tadlMediaFacetItems($unfilteredRequest, [['id' => 1, 'content_count' => 100]], ['tadl_subject_table' => $table, 'type' => 'fieldList']);
        testAssert(($facet[0]['content_count'] ?? null) === 100, $route.': unrelated facet count was removed.');
    }
    $facet = tadlMediaFacetItems($request, [['id' => 1, 'label' => 'Synthetic facet', 'content_count' => 100]], ['tadl_subject_table' => $table, 'type' => 'fieldList']);
    testAssert(count($facet) === 1 && !isset($facet[0]['content_count']), $route.': facet displays inaccurate unfiltered counts.');
    $emptyResult = new SearchResult($table, [20002, 20003, 20004, 20005, 20006, 20007, 20012, 20014, 20015, 20017]);
    tadlFilterMediaResult($request, $emptyResult);
    testAssert($emptyResult->numHits() === 0, $route.': all-excluded result did not become empty.');
}
// Families include directly attached objects and one hop of readable individuals/organizations.
foreach (range(90001,90008) as $id) { testInsert('ca_entities', ['entity_id'=>$id,'type_id'=>30,'access'=>$id===90007?0:1,'deleted'=>0]); }
foreach ([91001=>[11,1,0,1],91002=>[20,1,0,8],91003=>[10,0,0,1],91004=>[10,1,1,1],91005=>[10,1,0,12],91006=>[10,1,0,1]] as $id=>[$type,$access,$deleted,$object]) {
    testInsert('ca_entities', ['entity_id'=>$id,'type_id'=>$type,'access'=>$access,'deleted'=>$deleted]);
    testPersonObject($id,$object);
}
testPersonObject(90001,1);
foreach ([[90002,91001],[91001,90002],[91002,90003],[90004,91003],[90004,91004],[90005,90002],[90006,91005],[90007,91001],[90008,91006]] as [$left,$right]) {
    testInsert('ca_entities_x_entities', ['entity_left_id'=>$left,'entity_right_id'=>$right]);
}
$familyCandidates = range(90001,90008);
$familyRequest = testMediaRequest('only', [], 'Browse', 'families');
testAssert(tadlFamilyMediaEligibleIDs($familyRequest,$familyCandidates) === [90001,90002,90003,90008], 'Families must qualify through direct/member media while excluding private/deleted/non-media members and other-family traversal.');
$GLOBALS['mediaACL']['ca_entities'] = true;
$GLOBALS['mediaACLBlocked'][52]['ca_entities'] = [91006];
$aclFamilyRequest = testMediaRequest('only', [], 'Browse', 'families'); $aclFamilyRequest->userID = 52;
testAssert(tadlFamilyMediaEligibleIDs($aclFamilyRequest,$familyCandidates) === [90001,90002,90003], 'Family eligibility must exclude ACL-hidden member media.');
$GLOBALS['mediaACL'] = ['ca_objects'=>true]; $GLOBALS['mediaACLBlocked'][53]['ca_objects'] = [1];
$aclFamilyRequest->userID = 53;
testAssert(tadlFamilyMediaEligibleIDs($aclFamilyRequest,$familyCandidates) === [90003], 'Family eligibility must retain object ACL checks across member scopes.');
$GLOBALS['mediaACL'] = []; $GLOBALS['mediaBundleAccess'] = ['ca_entities:ca_entities'=>0];
testAssert(tadlFamilyMediaEligibleIDs($familyRequest,$familyCandidates) === [90001], 'Denied member relationships must retain only directly attached family media.');
$GLOBALS['mediaBundleAccess'] = [];
$familyResult = new SearchResult('ca_entities',array_reverse($familyCandidates));
tadlFilterMediaResult($familyRequest,$familyResult);
testAssert($familyResult->getPrimaryKeyValues() === [90008,90003,90002,90001], 'Family media filtering must preserve the requested native identifier order.');
$ajaxFamilyResult = new SearchResult('ca_entities',$familyCandidates);
tadlFilterMediaResult(testMediaRequest('only',['browseType'=>'families'],'Browse','getFacetHierarchyLevel'),$ajaxFamilyResult);
testAssert($ajaxFamilyResult->getPrimaryKeyValues() === [90001,90002,90003,90008], 'Family AJAX browsing must use the same expanded media scope.');
$allFamilyResult = new SearchResult('ca_entities',$familyCandidates);
tadlFilterMediaResult(testMediaRequest('all',[],'Browse','families'),$allFamilyResult);
testAssert($allFamilyResult->getPrimaryKeyValues() === $familyCandidates, 'All items must retain families without digital media.');
tadlMediaResultContext(new TestView($familyRequest,['start'=>0]),$familyResult,'browse');
testAssert(ResultContext::$saved['ca_entities:browse:']['ids'] === [90008,90003,90002,90001], 'Family Prev/Next context must contain exactly the eligible families in browse order.');

// Same numeric IDs can have different eligibility in each authority table.
foreach (['ca_entities' => ['entity_id', 1], 'ca_places' => ['place_id', 12], 'ca_occurrences' => ['occurrence_id', 2]] as $table => [$primaryKey, $objectID]) {
    testInsert($table, [$primaryKey => 80000, 'access' => 1, 'deleted' => 0]);
    testInsert('ca_objects_x_'.substr($table, 3), [$primaryKey => 80000, 'object_id' => $objectID]);
    testAssert(tadlAuthorityMediaEligibleIDs($peopleRequest, $table, [80000]) === ($table === 'ca_entities' ? [80000] : []), 'Authority eligibility cache mixed tables with the same ID.');
}
$queryCount = count(Db::$queries);
testAssert(tadlAuthorityMediaEligibleIDs($peopleRequest, 'ca_places; invalid SQL', [20001]) === [] && count(Db::$queries) === $queryCount, 'Unsupported SQL identifiers must be rejected before querying.');
$mismatchedResult = new SearchResult('ca_places', $peopleCandidates);
tadlFilterMediaResult($peopleRequest, $mismatchedResult);
testAssert($mismatchedResult->getPrimaryKeyValues() === $peopleCandidates, 'Browse request filtered an unrelated authority table.');

// Large authority lists share descriptors and filter before actual Tiles/List paging.
foreach (range(30000, 30600) as $id) { testPerson($id); testPersonObject($id, $id % 2 ? 12 : 1); }
$largePeopleExpected = range(30000, 30600, 2);
$queryCount = count(Db::$queries); $beforeMediaRows = Db::$mediaRows;
testAssert(tadlAuthorityMediaEligibleIDs($peopleRequest, 'ca_entities', range(30000, 30600)) === $largePeopleExpected, 'Chunked people eligibility dropped results.');
testAssert(count(Db::$queries) - $queryCount === 7 && Db::$mediaRows - $beforeMediaRows === 0, 'People eligibility must batch relationships and decode shared media once, rather than query per person.');
foreach (['ca_places' => 'place_id', 'ca_occurrences' => 'occurrence_id'] as $table => $primaryKey) {
    foreach (range(30000, 30600) as $id) {
        testInsert($table, [$primaryKey => $id, 'access' => 1, 'deleted' => 0]);
        $hasMedia = $table === 'ca_places' ? $id % 2 !== 0 : $id % 3 === 0;
        testInsert('ca_objects_x_'.substr($table, 3), [$primaryKey => $id, 'object_id' => $hasMedia ? 1 : 12]);
    }
}
$largeAuthorityExpected = ['ca_entities' => $largePeopleExpected, 'ca_places' => range(30001, 30599, 2), 'ca_occurrences' => range(30000, 30600, 3)];
// Native ACL filtering remains batched even across multiple authority SQL chunks.
$GLOBALS['mediaACL'] = ['ca_objects' => true, 'ca_object_representations' => true];
$aclPlacesRequest = testMediaRequest('only', [], 'Browse', 'places');
$aclPlacesRequest->userID = 12;
$beforeACLCalls = count($GLOBALS['mediaACLCalls']);
$queryCount = count(Db::$queries); $beforeMediaRows = Db::$mediaRows;
testAssert(tadlAuthorityMediaEligibleIDs($aclPlacesRequest, 'ca_places', range(30000, 30600)) === $largeAuthorityExpected['ca_places'], 'Chunked Places eligibility with ACLs lost IDs or changed SQL tables.');
testAssert(count($GLOBALS['mediaACLCalls']) - $beforeACLCalls === 4 && count(Db::$queries) - $queryCount === 7 && Db::$mediaRows - $beforeMediaRows === 0, 'Places must batch ACL checks and reuse shared media across chunks.');
$GLOBALS['mediaACL'] = [];
foreach ($authorityBrowses as $route => [$table, $primaryKey, $singular, $plural]) {
    foreach (['images', 'list'] as $displayView) {
        foreach ($displayView === 'list' ? [25, 50, 100] : [9, 18, 72] as $pageSize) {
            foreach (['only', 'all'] as $mode) {
                $expected = $mode === 'only' ? $largeAuthorityExpected[$table] : range(30000, 30600);
                $count = count($expected);
                $result = new SearchResult($table, range(30000, 30600));
                $request = testMediaRequest($mode, ['view' => $displayView, 's' => $pageSize, 'per_page' => $pageSize], 'Browse', $route);
                if ($mode === 'all' && $pageSize === ($displayView === 'list' ? 25 : 18)) {
                    $request->params = ['s' => $pageSize];
                    $request->cookies['tadlDisplayPreferences'] = json_encode(['view' => $displayView, ($displayView === 'list' ? 'list_per_page' : 'images_per_page') => (string)$pageSize]);
                }
                $view = new TestView($request, [
                    'result' => $result, 'criteria' => [], 'facets' => [], 'table' => $table, 'primaryKey' => $primaryKey,
                    't_instance' => new TestModel($table), 'browse_type' => $route,
                    'browseInfo' => ['table' => $table, 'labelSingular' => $singular, 'labelPlural' => $plural],
                    'config' => new TestConfig(['cache_timeout' => 0]), 'access_values' => [1], 'options' => [],
                    'view' => 'images', 'views' => ['images' => [], 'list' => []], 'key' => 'synthetic-authority-key',
                    'sort' => 'Name', 'sort_direction' => 'asc', 'sortBy' => ['Name' => $table.'.preferred_labels'],
                    'start' => $pageSize, 'hits_per_block' => $pageSize, 'export_formats' => []
                ]);
                $html = $view->render('Browse/browse_results_html.php');
                testAssert(str_contains($html, $count.' '.$plural) && str_contains($html, 'Page 2 of '.ceil($count / $pageSize)), $route.' '.$mode.': actual heading/pager retained pre-filter counts.');
                $document = new DOMDocument(); $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
                $xpath = new DOMXPath($document);
                $cards = $xpath->query('//div[@id and starts-with(@id,"row")]');
                $firstID = $expected[$pageSize];
                testAssert($cards->length === $pageSize && $cards->item(0)->getAttribute('id') === 'row'.$firstID, $route.' '.$mode.': actual cards use the wrong page offset or size.');
                testAssert(ResultContext::$saved[$table.':browse:']['count'] === $count && ResultContext::$saved[$table.':browse:']['ids'] === $expected, $route.' '.$mode.': actual detail context has the wrong count or IDs.');
                testAssert(ResultContext::$saved[$table.':browse:']['view'] === $displayView && ResultContext::$saved[$table.':browse:']['per_page'] === $pageSize, $route.': detail/back context must reflect the rendered view and page size, including remembered preferences.');
                testAssert($xpath->query('//div[@id="row'.$firstID.'"]//a[starts-with(@href,"/Detail/'.substr($table, 3).'/'.$firstID.'")]')->length > 0, $route.': cards link to the wrong detail table.');
                testAssert(!str_contains($html, 'media=') && !str_contains($html, '/media/'), $route.': pager leaked the preference into URLs.');
            }
        }
    }
}

testPerson(70000); testObject(70000); testPersonObject(70000, 70000);
testRepresentation(70000, 1, 0, 'stub'); testRelation(70000, 70000);
testRepresentation(70001, 1, 0, 'pdf'); testRelation(70000, 70001, 0);
$beforeMediaRows = Db::$mediaRows;
testAssert(tadlAuthorityMediaEligibleIDs($peopleRequest, 'ca_entities', [70000]) === [70000] && Db::$mediaRows - $beforeMediaRows === 2, 'People must try a usable secondary PDF after an invalid primary descriptor.');
testPerson(70002); testPersonObject(70002, 6); testPersonObject(70002, 7);
testAssert(tadlAuthorityMediaEligibleIDs($peopleRequest, 'ca_entities', [70002]) === [], 'All-invalid related descriptors must terminate without qualifying a person.');

// Self matching is essential while new collection bounds have not yet been built.
testCollection(500, 0, 0, 0); testCollectionObject(500, 1);
testAssert(tadlMediaEligibleIDs('ca_collections', [500], [1]) === [500], 'Uninitialized hierarchy bounds prevent direct object qualification.');
// Chunking avoids a single unbounded bind parameter list.
foreach (range(1000,1600) as $id) { testObject($id); testRelation($id, 1); }
$queryCount = count(Db::$queries);
testAssert(tadlMediaEligibleIDs('ca_objects', range(1000,1600), [1]) === range(1000,1600), 'Chunked candidate batch dropped results.');
testAssert(count(Db::$queries) - $queryCount === 7, '601 candidates should use bounded relationship/permission batches and one shared descriptor.');

// A large collection needs one playable descriptor, not every attached media blob.
testCollection(7000, 7000, 1, 2);
foreach (range(7000,7999) as $id) { testObject($id); testRepresentation($id); testRelation($id,$id); testCollectionObject(7000,$id); }
$mediaRows = Db::$mediaRows;
testAssert(tadlMediaEligibleIDs('ca_collections',[7000],[1]) === [7000], 'Large collection did not qualify.');
testAssert(Db::$mediaRows - $mediaRows === 1, 'Collection eligibility fetched all media descriptors instead of stopping at the first playable one.');
// Invalid descriptors must be exhausted without treating them as playable.
testCollection(8000,8000,1,2); testCollectionObject(8000,6); testCollectionObject(8000,7); testCollectionObject(8000,1);
testAssert(tadlMediaEligibleIDs('ca_collections',[8000],[1]) === [8000], 'Invalid initial descriptors blocked a later usable file.');
testCollection(8001,8001,1,2); testCollectionObject(8001,6); testCollectionObject(8001,7);
testAssert(tadlMediaEligibleIDs('ca_collections',[8001],[1]) === [], 'All-invalid descriptors did not terminate as an empty result.');

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
    $view = new TestView(testMediaRequest($mode, ['page' => 2, 'view' => 'tiles', 'per_page' => 9], 'Collections', 'Index'), ['collection_results' => $result, 'collections_config' => $collectionConfig, 'section_name' => 'Example collections']);
    $html = $view->render(TEST_THEME.'/views/Collections/index_html.php');
    testAssert(strpos($html, $expected[0].' collections') !== false, $mode.': collection index uses unfiltered count.');
    testAssert(substr_count($html, 'data-collection-item') === $expected[1], $mode.': collection index rendered wrong page size.');
    testAssert(strpos($html, '/Detail/collections/'.$expected[2]) !== false, $mode.': collection page starts at unfiltered offset.');
    testAssert(strpos($html, '/media/') === false && strpos($html, 'media=') === false, $mode.': collection navigation leaked preference into URLs.');
    testAssert(strpos($html, '/Collections/Index/page/') !== false, $mode.': collection canonical URL lost page state.');
    testAssert(strpos($html, '&lt;sample&gt; &amp; archive') !== false, $mode.': collection label escaping failed.');
    testAssert(count(ResultContext::$saved['ca_collections:collections:']['ids']) === $expected[0], $mode.': detail navigation context contains wrong IDs.');
    $document = new DOMDocument(); $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $pagers = $xpath->query('//nav[@class="tadl-collections-pagination"]');
    testAssert($pagers->length === 2, $mode.': collections require matching top and bottom pagers.');
    testAssert($xpath->query('//div[@class="tadl-collections-tools"]/nav[@class="tadl-collections-pagination"]')->length === 1, $mode.': top pager must sit with the count and view controls.');
    testAssert($pagers->item(0)->getAttribute('aria-label') !== $pagers->item(1)->getAttribute('aria-label'), $mode.': top and bottom pagination landmarks need distinguishable names.');
    $pagers->item(1)->setAttribute('aria-label', $pagers->item(0)->getAttribute('aria-label'));
    testAssert($document->saveHTML($pagers->item(0)) === $document->saveHTML($pagers->item(1)), $mode.': pagers disagree about page/view state.');
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
// AJAX collection loads bypass native setAsLastFind(); saving IDs alone leaves
// object details using no context, or a stale unrelated search/browse context.
foreach (['CollectionContents', 'FamilyContents', 'PlaceContents', 'Search', 'Browse'] as $controller) {
    foreach ([false, true] as $ajax) {
        $request = testMediaRequest('all', [], $controller, 'objects');
        $request->ajax = $ajax;
        ResultContext::$lastFind['ca_objects'] = ['browse', 'stale-results', true];
        $orderedIDs = [601, 603, 602];
        $result = new SearchResult('ca_objects', $orderedIDs);
        $findType = $controller === 'Browse' ? 'browse' : 'search';
        tadlMediaResultContext(new TestView($request, ['start'=>0]), $result, $findType, 'objects');
        testAssert(ResultContext::$lastFind['ca_objects'] === ($ajax && $findType === 'search' ? ['search', 'objects', false] : ['browse', 'stale-results', true]), $controller.': AJAX searches must activate their exact type/subtype without replacing ordinary native action tracking.');
        testAssert(ResultContext::$saved['ca_objects:'.$findType.':objects']['ids'] === $orderedIDs, $controller.': activating navigation must retain the displayed result order.');
    }
}
$request->ajax = true;
$lastFind = ResultContext::$lastFind;
tadlMediaResultContext(new TestView($request, []), $result, 'multisearch', 'objects');
testAssert(ResultContext::$lastFind === $lastFind, 'Copying result IDs to a multisearch context must not replace the active full-results context.');
testAssert(ResultContext::$saved['ca_objects:multisearch:objects']['tadl_result_return'] === null, 'A multisearch overview must use its native return URL rather than inherit a full-results destination.');
foreach (['dontSetFind', 'getFacet'] as $parameter) {
    $request->params = [$parameter=>1];
    tadlMediaResultContext(new TestView($request, []), $result, 'search', 'objects');
    testAssert(ResultContext::$lastFind === $lastFind, 'Explicit native navigation suppression must remain effective for '.$parameter.'.');
}
// Logical name/identifier order can differ completely from database ID order.
foreach (['people' => 'ca_entities', 'organizations' => 'ca_entities', 'places' => 'ca_places', 'occurrences' => 'ca_occurrences'] as $route => $table) {
    $orderedIDs = [13, 8, 2, 11];
    $orderedResult = new SearchResult($table, $orderedIDs);
    $orderedView = new TestView(testMediaRequest('all', [], 'Browse', $route), ['start' => 0]);
    tadlFilterMediaResult($orderedView->request, $orderedResult);
    tadlMediaResultContext($orderedView, $orderedResult, 'browse');
    testAssert(ResultContext::$saved[$table.':browse:']['ids'] === $orderedIDs, $route.': Prev/Next context must preserve logical browse order rather than sort database IDs.');
}
$latePageView = new TestView(testMediaRequest('all', ['page' => 62, 'view' => 'tiles'], 'Collections', 'Index'), ['collection_results' => new SearchResult('ca_collections', range(2000,3999)), 'collections_config' => $collectionConfig, 'section_name' => 'Example collections']);
$html = $latePageView->render(TEST_THEME.'/views/Collections/index_html.php');
testAssert(strpos($html, '/Detail/collections/3098') !== false, 'Late collection index rendered the wrong page.');
testAssert(in_array(3098, ResultContext::$saved['ca_collections:collections:']['ids'], true), 'Collection index saved detail context before calculating the page offset.');
$collectionGridRequest = testMediaRequest('all', ['page'=>2, 'view'=>'tiles'], 'Collections', 'Index');
$collectionGridRequest->cookies['tadlDisplayPreferences'] = json_encode(['collections_images_per_page'=>'90','images_per_page'=>'27']);
$collectionGridView = new TestView($collectionGridRequest, ['collection_results'=>new SearchResult('ca_collections',range(2000,2199)), 'collections_config'=>$collectionConfig, 'section_name'=>'Synthetic collections']);
$html = $collectionGridView->render('Collections/index_html.php');
testAssert(substr_count($html,'data-collection-item') === 90 && str_contains($html,'/Detail/collections/2090') && !str_contains($html,'/Detail/collections/2089'), 'The actual collection index must use its independent 90-record page size and offset.');
testAssert(str_contains($html,'data-tadl-page-size-key="collections_images_per_page"') && str_contains($html,'/Collections/Index/page/3/view/tiles/per_page/90'), 'Collection picker and both pagers must retain the collection-specific setting.');
testAssert(ResultContext::$saved['ca_collections:collections:']['per_page'] === 90, 'Native collection detail navigation must save the displayed 90-record page size.');

// Native multisearch captures counts before preview rendering; verify full filtered IDs
// drive both the actual preview and the overview navigation/counts.
$counts = [];
foreach ([
    'objects' => ['ca_objects', range(1,13)],
    'collections' => ['ca_collections', $collectionCandidates],
    'people' => ['ca_entities', array_slice($peopleExpected, 0, 3)],
    'organizations' => ['ca_entities', array_slice($peopleExpected, 3, 2)],
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
    $document = new DOMDocument(); $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    $fullResults = (new DOMXPath($document))->query('//a[@class="tadl-search-view-all"]')->item(0)->getAttribute('href');
    parse_str(parse_url($fullResults, PHP_URL_QUERY), $fullParams);
    testAssert(($fullParams['search'] ?? null) === 'example <sample> & archive' && $fullParams['source'] === 'multisearch' && $fullParams['_advanced'] === '0', 'Full results must retain the exact query and basic-search context.');
    testAssert($table === 'ca_objects' ? (($fullParams['sort'] ?? null) === 'Relevance' && ($fullParams['direction'] ?? null) === 'asc') : (!isset($fullParams['sort']) && !isset($fullParams['direction'])), 'Only object View all links must explicitly select the shared relevance default.');
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

// Parent place browse entries qualify through the same readable descendant scope.
foreach ([[50000,0,10,1,8,1],[50001,50000,10,2,3,1],[50002,50000,10,4,7,0],[50003,50002,10,5,6,1],[50004,0,11,1,8,1]] as [$id,$parent,$hierarchy,$left,$right,$access]) {
    testInsert('ca_places', ['place_id'=>$id,'parent_id'=>$parent,'hierarchy_id'=>$hierarchy,'hier_left'=>$left,'hier_right'=>$right,'access'=>$access,'deleted'=>0]);
}
testInsert('ca_objects_x_places', ['relation_id'=>50000,'object_id'=>1,'place_id'=>50001]);
testInsert('ca_objects_x_places', ['relation_id'=>50001,'object_id'=>1,'place_id'=>50003]);
$request = testMediaRequest('only', [], 'Browse', 'places'); $request->userID = 77;
$result = new SearchResult('ca_places', [50004,50000,50001]);
tadlFilterMediaResult($request, $result);
testAssert($result->getPrimaryKeyValues() === [50000,50001], 'Parent places must qualify through descendant media without crossing hierarchy boundaries.');
$GLOBALS['mediaACL'] = ['ca_places'=>true];
$GLOBALS['mediaACLBlocked'][78] = ['ca_places'=>[50001]];
$request->userID = 78;
testAssert(tadlPlaceMediaEligibleIDs($request, [50000,50001]) === [], 'Denied place children and public descendants under private parents must not qualify their ancestors.');
$GLOBALS['mediaACL'] = [];
$request = testMediaRequest('all', [], 'Browse', 'places');
$result = new SearchResult('ca_places', [50004,50000]); $before = count(Db::$queries);
tadlFilterMediaResult($request, $result);
testAssert($result->getPrimaryKeyValues() === [50004,50000] && count(Db::$queries) === $before, 'All-items place browsing must stay unfiltered and avoid eligibility queries.');



// A new candidate pass reuses descriptor validity while still reading current scalar identity.
testRepresentation(990000);
$known = []; $before = Db::$mediaRows;
tadlReadMediaUsability(new Db(), [990000], [1], $known);
testAssert($known[990000] && Db::$mediaRows === $before + 1, 'Cold usability must inspect the actual native descriptor.');
$known = []; $before = Db::$mediaRows;
tadlReadMediaUsability(new Db(), [990000], [1], $known);
testAssert($known[990000] && Db::$mediaRows === $before, 'Warm usability must avoid fetching/decoding the blob.');
$replace = Db::$pdo->prepare('UPDATE ca_object_representations SET media = ? WHERE representation_id = 990000'); $replace->execute([caSerializeForDatabase([])]);
$known = []; tadlReadMediaUsability(new Db(), [990000], [1], $known);
testAssert(!$known[990000] && Db::$mediaRows === $before + 1, 'A descriptor change must immediately invalidate usability.');

require __DIR__.'/support/server_results_checks.php';
require __DIR__.'/support/result_navigation_checks.php';
if (($argv[1] ?? '') === '--server-render') {
    $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    print testServerCollectionHTML($input['params'] ?? [], $input['cookies'] ?? []);
} else {
echo json_encode(['status' => 'passed', 'assertions' => $GLOBALS['assertions'], 'sql_queries' => count(Db::$queries), 'database' => 'SQLite in memory with actual helper SQL', 'result_adapter' => 'synthetic SearchResult boundary', 'records' => 'synthetic only'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
}
