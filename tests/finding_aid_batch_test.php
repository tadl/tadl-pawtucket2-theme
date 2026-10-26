<?php
/** Actual batched finding-aid helper with SQLite hierarchy/access and native formatting boundaries. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('__CA_BUNDLE_ACCESS_READONLY__', 1);
define('__CA_ACL_READONLY_ACCESS__', 1);
$assertions = 0;
function batchCheck($condition, $message) {
	$GLOBALS['assertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function _t($value) { return $value; }
function caGetUserAccessValues($request) { return $request->access; }
function caGetBundleAccessLevel($table, $bundle) { return $GLOBALS['batchRequest']->user->getBundleAccessLevel($table, $bundle); }
function caGetTypeRestrictionsForUser($table) { return $GLOBALS['batchRequest']->types[$table] ?? null; }
function caGetSourceRestrictionsForUser($table) { return $GLOBALS['batchRequest']->sources[$table] ?? null; }
function caSourceAccessControlIsEnabled($model) { return true; }
function caACLIsEnabled($model, $options) {
	batchCheck($options === ['forPawtucket' => true], 'Finding-aid ACL checks require the Pawtucket context.');
	return true;
}
function caMakeRelationshipTypeIDList($table, $types) {
	batchCheck($table === 'ca_entities_x_collections', 'Creator roles must use the native relationship vocabulary.');
	return $types === ['creator'] ? [21] : [];
}
function caGetBrowseInstance($table) {
	return new class($table) {
		function __construct(private $table) {}
		function filterHitsByACL($ids, $table_num, $user_id) {
			batchCheck($user_id === 7, 'Bulk ACL checks require the current user.');
			return array_values(array_filter($ids, fn($id) => !in_array($id, $GLOBALS['batchRequest']->aclDenied[$this->table] ?? [], true)));
		}
	};
}
class ca_metadata_elements {
	static function getElementDatatype($root) { return 3; }
	static function getElementID($root) { return ['description' => 31, 'extent_text' => 32, 'extent' => 33, 'language' => 34, 'external_link' => 35, 'biography' => 36, 'url_line2' => 56, 'url_entry' => 57][$root] ?? false; }
}
class BatchUser {
	public array $denied = [];
	function getBundleAccessLevel($table, $bundle) { return in_array($bundle, $this->denied[$table] ?? [], true) ? 0 : 1; }
	function getTypeAccessLevel($table, $type) { return in_array((int)$type, $GLOBALS['batchRequest']->deniedTypes[$table] ?? [], true) ? 0 : 1; }
	function getSourceAccessLevel($table, $source) { return in_array((int)$source, $GLOBALS['batchRequest']->deniedSources[$table] ?? [], true) ? 0 : 1; }
}
class BatchRequest {
	public array $access = [1], $types = [], $sources = [], $aclDenied = [], $deniedTypes = [], $deniedSources = [];
	public BatchUser $user;
	function __construct() { $this->user = new BatchUser(); }
	function getUserID() { return 7; }
}
class BatchConfig {
	public array $fields = [
		'level' => ['label' => 'Level', 'bundles' => ['ca_collections.type_id']],
		'description' => ['label' => 'Description', 'bundles' => ['ca_collections.description']],
		'extent' => ['label' => 'Extent', 'bundles' => ['ca_collections.extent_text', 'ca_collections.extent']],
		'language' => ['label' => 'Language', 'display_language_labels' => 1, 'bundles' => ['ca_collections.language']],
		'related_links' => ['label' => 'Related links', 'bundles' => ['ca_collections.external_link.url_line2.url_entry']],
		'creators' => ['label' => 'Creators', 'related_table' => 'ca_entities', 'relationship_types' => ['creator'], 'bundles' => ['ca_entities.preferred_labels.displayname']],
		'creator_details' => ['label' => 'Creator details', 'related_table' => 'ca_entities', 'relationship_types' => ['creator'], 'bundles' => ['ca_entities.biography.biographical_text']],
		'missing' => ['bundles' => ['ca_collections.missing']],
		'unresolved' => ['bundles' => ['ca_collections.unresolved']],
		'invalid' => ['bundles' => ['ca_objects.description', 'ca_collections.description%skipAccessControl=1']]
	];
	public string $sort = 'rank';
	function getAssoc($key) { return $this->fields; }
	function get($key) { return $key === 'hierarchy_sort' ? $this->sort : false; }
}
class Db {
	static PDO $pdo;
	static array $queries = [];
	function query($sql, $params = []) {
		batchCheck(str_starts_with($sql, 'SELECT ') && !str_contains($sql, 'ca_objects'), 'Finding aids must read descriptive records only, never write or query objects.');
		self::$queries[] = [$sql, $params]; $index = 0; $flat = [];
		$sql = preg_replace_callback('/\?/', static function () use ($params, &$index, &$flat) {
			$value = $params[$index++];
			if (!is_array($value)) { $flat[] = $value; return '?'; }
			batchCheck(count($value) > 0 && count($value) <= 500, 'SQL array parameters must remain nonempty and bounded.');
			$flat = array_merge($flat, $value);
			return join(',', array_fill(0, count($value), '?'));
		}, $sql);
		$query = self::$pdo->prepare($sql); $query->execute($flat);
		return new class($query) {
			private array $row = [];
			function __construct(private PDOStatement $query) {}
			function nextRow() { $this->row = $this->query->fetch(PDO::FETCH_ASSOC) ?: []; return (bool)$this->row; }
			function get($key) { return $this->row[$key]; }
			function getRow() { return $this->row; }
		};
	}
}
class Datamodel {
	static function getInstance($table, $cached) { return new BatchModel($table); }
	static function getInstanceByTableName($table, $cached) { return new BatchModel($table); }
}
class BatchModel {
	public array $row = [];
	static int $loads = 0, $relationships = 0;
	function __construct(private string $table) { batchCheck(in_array($table, ['ca_collections', 'ca_entities'], true), 'Only collections and creators may be loaded.'); }
	function tableName() { return $this->table; }
	function tableNum() { return $this->table === 'ca_collections' ? 13 : 20; }
	function primaryKey() { return $this->table === 'ca_collections' ? 'collection_id' : 'entity_id'; }
	function getPrimaryKey() { return (int)($this->row[$this->primaryKey()] ?? 0); }
	function get($key) { return $this->row[$key] ?? null; }
	function getTypeFieldName() { return 'type_id'; }
	function getSourceFieldName() { return 'source_id'; }
	function getDb() { return new Db(); }
	function getAppConfig() { return new class { function get($key) { return $key === 'perform_type_access_checking'; } }; }
	function load($id) {
		self::$loads++;
		$result = $this->getDb()->query('SELECT * FROM '.$this->table.' WHERE '.$this->primaryKey().' = ?', [$id]);
		$this->row = $result->nextRow() ? $result->getRow() : [];
		return (bool)$this->row;
	}
	function hasField($field) { return in_array($field, ['idno', 'type_id', 'rank'], true); }
	function hasElement($element, $type = null) {
		$type = $type ?? $this->get('type_id');
		return in_array($element, $GLOBALS['batchElements'][$this->table][(int)$type] ?? [], true);
	}
	function isReadable($request, $bundle = null) {
		return in_array((int)$this->get('access'), $request->access, true)
			&& $request->user->getTypeAccessLevel($this->table, $this->get('type_id')) >= 1
			&& $request->user->getSourceAccessLevel($this->table, $this->get('source_id')) >= 1
			&& $this->checkACLAccessForUser($request->user) >= 1
			&& (!$bundle || $request->user->getBundleAccessLevel($this->table, $bundle) >= 1);
	}
	function checkACLAccessForUser($user) { return in_array($this->getPrimaryKey(), $GLOBALS['batchRequest']->aclDenied[$this->table] ?? [], true) ? 0 : 1; }
	function getFieldValuesForIDs($ids, $fields, $options = null) {
		batchCheck($fields === ['type_id', 'rank'] && $options === null, 'Batch intrinsic reads require native type/rank fields only.');
		$result = $this->getDb()->query('SELECT '.$this->primaryKey().', type_id, rank FROM '.$this->table.' WHERE '.$this->primaryKey().' IN (?) AND deleted = 0', [$ids]);
		$rows = []; while ($result->nextRow()) { $rows[(int)$result->get($this->primaryKey())] = $result->getRow(); }
		return $rows;
	}
	function getHierarchyChildren($id, $options) {
		$result = $this->getDb()->query('SELECT collection_id FROM ca_collections WHERE parent_id = ? AND deleted = 0', [$id]);
		$ids = []; while ($result->nextRow()) { $ids[] = (int)$result->get('collection_id'); } return $ids;
	}
	function getWithTemplate($template, $options) { return batchValue($this->table, $this->getPrimaryKey(), $template, $options); }
	function getRelatedItems($table, $options) {
		self::$relationships++;
		batchCheck($table === 'ca_entities' && $options['checkAccess'] === [1] && $options['user_id'] === 7
			&& $options['restrictToRelationshipTypes'] === [21] && $options['limit'] === PHP_INT_MAX, 'Creator lookup must retain access, current user, role and unlimited native result scope.');
		$owners = $options['row_ids'] ?? [$this->getPrimaryKey()];
		batchCheck(count($owners) <= 500, 'Creator lookups must be bounded.');
		$rows = array_filter($GLOBALS['batchRelationships'], static fn($row) => in_array($row['row_id'], $owners, true) && $row['relationship_type'] === 21);
		return isset($options['idsOnly']) ? array_column($rows, 'entity_id') : $rows;
	}
}
function batchValue($table, $id, $template, $options) {
	batchCheck(($options['makeLink'] ?? null) === false && ($options['dontReturnDefault'] ?? null) === true
		&& ($options['convertCodesToDisplayText'] ?? null) === true && $options['delimiter'] === "\n", 'Native template formatting and no-default/plain-text options must remain unchanged.');
	$language = $template === '^ca_collections.language';
	batchCheck($options['checkAccess'] === ($language ? [] : [1]), 'Only the selected language List label may skip term access filtering.');
	return $GLOBALS['batchValues'][$table][$id][substr($template, 1)] ?? '';
}
function caProcessTemplateForIDs($template, $table, $ids, $options) {
	batchCheck(count($ids) > 0 && count($ids) <= 500 && ($options['returnAsArray'] ?? false)
		&& ($options['indexWithIDs'] ?? false) && ($options['includeBlankValuesInTopLevelForPrefetch'] ?? false), 'Multi-record templates require bounded IDs and stable blank/ID indexing.');
	$GLOBALS['batchTemplates'][] = [$table, $ids, $template];
	$values = []; foreach (array_reverse($ids) as $id) { $values[$id] = batchValue($table, $id, $template, $options); }
	return $values; // Deliberately reverse results to detect accidental positional association.
}
require dirname(__DIR__).'/helpers/finding_aid.php';
Db::$pdo = new PDO('sqlite::memory:'); Db::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach (['ca_collections' => 'collection_id', 'ca_entities' => 'entity_id'] as $table => $key) {
	Db::$pdo->exec('CREATE TABLE '.$table.' ('.$key.' INTEGER PRIMARY KEY, parent_id INTEGER, type_id INTEGER DEFAULT 1, source_id INTEGER DEFAULT 1, rank INTEGER DEFAULT 0, access INTEGER DEFAULT 1, deleted INTEGER DEFAULT 0)');
}
Db::$pdo->exec('CREATE TABLE ca_attributes (table_num INTEGER, row_id INTEGER, element_id INTEGER)');
function batchNode($table, $id, $parent = null, $extra = []) {
	$key = $table === 'ca_collections' ? 'collection_id' : 'entity_id';
	$values = [$key => $id, 'parent_id' => $parent] + $extra;
	$query = Db::$pdo->prepare('INSERT INTO '.$table.' ('.join(',', array_keys($values)).') VALUES ('.join(',', array_fill(0, count($values), '?')).')');
	$query->execute(array_values($values));
	$GLOBALS['batchValues'][$table][$id] = [$table.'.preferred_labels.'.($table === 'ca_collections' ? 'name' : 'displayname') => 'Synthetic '.$id, $table.'.idno' => 'SYN.'.$id, $table.'.type_id' => 'Synthetic type'];
}
$request = $GLOBALS['batchRequest'] = new BatchRequest(); $config = new BatchConfig();
$GLOBALS['batchElements'] = ['ca_collections' => [1 => ['description', 'extent', 'language', 'external_link', 'unresolved'], 2 => ['extent_text', 'extent', 'language']], 'ca_entities' => [1 => ['biography']]];
$GLOBALS['batchTemplates'] = []; $GLOBALS['batchRelationships'] = [];
batchNode('ca_collections', 1);
batchNode('ca_collections', 2, 1, ['rank' => 2]);
batchNode('ca_collections', 3, 1, ['type_id' => 2, 'rank' => 1]);
batchNode('ca_collections', 4, 3);
batchNode('ca_collections', 5, 1, ['access' => 0]); batchNode('ca_collections', 6, 5);
batchNode('ca_collections', 7, 1, ['deleted' => 1]); batchNode('ca_collections', 8, 7);
batchNode('ca_collections', 9, 1); batchNode('ca_collections', 10, 9);
batchNode('ca_collections', 11, 1, ['type_id' => 3]); batchNode('ca_collections', 12, 11);
batchNode('ca_collections', 13, 1, ['source_id' => 3]); batchNode('ca_collections', 14, 13);
$request->aclDenied['ca_collections'] = [9]; $request->deniedTypes['ca_collections'] = [3]; $request->deniedSources['ca_collections'] = [3];
$GLOBALS['batchValues']['ca_collections'][1]['ca_collections.description'] = '<p>Synthetic root.</p>';
$GLOBALS['batchValues']['ca_collections'][2]['ca_collections.extent_text'] = 'PRIVATE inapplicable metadata';
$GLOBALS['batchValues']['ca_collections'][2]['ca_collections.extent'] = 'Two boxes';
$GLOBALS['batchValues']['ca_collections'][3]['ca_collections.description'] = 'PRIVATE wrong-type metadata';
$GLOBALS['batchValues']['ca_collections'][3]['ca_collections.extent_text'] = '<p>&nbsp;</p>';
$GLOBALS['batchValues']['ca_collections'][3]['ca_collections.extent'] = 'One folder';
$GLOBALS['batchValues']['ca_collections'][4]['ca_collections.description'] = '<p>Folder description.</p><p>Two paragraphs.</p>';
$GLOBALS['batchValues']['ca_collections'][4]['ca_collections.language'] = 'English';
$GLOBALS['batchValues']['ca_collections'][4]['ca_collections.external_link.url_line2.url_entry'] = 'https://example.org/synthetic-finding-aid';
foreach ($GLOBALS['batchValues']['ca_collections'] as $id => $values) {
	foreach ($values as $bundle => $value) {
		$root_code = explode('.', $bundle)[1];
		if ($element = ca_metadata_elements::getElementID($root_code)) {
			Db::$pdo->prepare('INSERT INTO ca_attributes (table_num, row_id, element_id) VALUES (13, ?, ?)')->execute([$id, $element]);
		}
	}
}
foreach (range(101, 106) as $id) { batchNode('ca_entities', $id, null, $id === 104 ? ['access' => 0] : []); }
$GLOBALS['batchValues']['ca_entities'][101]['ca_entities.biography.biographical_text'] = '<p>Synthetic creator history.</p>';
Db::$pdo->exec('INSERT INTO ca_attributes (table_num, row_id, element_id) VALUES (20, 101, 36)');
$request->aclDenied['ca_entities'] = [105]; $request->deniedSources['ca_entities'] = [3];
Db::$pdo->exec('UPDATE ca_entities SET source_id = 3 WHERE entity_id = 106');
$GLOBALS['batchRelationships'] = [
	['row_id' => 1, 'entity_id' => 103, 'relationship_type' => 21], ['row_id' => 1, 'entity_id' => 101, 'relationship_type' => 21],
	['row_id' => 1, 'entity_id' => 103, 'relationship_type' => 21], ['row_id' => 3, 'entity_id' => 102, 'relationship_type' => 21],
	['row_id' => 3, 'entity_id' => 101, 'relationship_type' => 99], ['row_id' => 4, 'entity_id' => 104, 'relationship_type' => 21],
	['row_id' => 4, 'entity_id' => 105, 'relationship_type' => 21], ['row_id' => 4, 'entity_id' => 106, 'relationship_type' => 21]
];
$root = new BatchModel('ca_collections'); $root->load(1);
function batchCanonical($request, $root, $config) {
	$batch = tadlFindingAidData($request, $root, $config);
	$single = tadlFindingAidSingleData($request, $root, $config);
	unset($batch['generated'], $single['generated']);
	batchCheck($batch === $single, 'Batch metadata, hierarchy, fallback values and creator order must match canonical single-record output.');
	return $batch;
}
$data = batchCanonical($request, $root, $config);
batchCheck(array_keys($data['collections']) === [1, 3, 4, 2], 'Unreadable/deleted/type/source/ACL branches and their descendants must be pruned.');
batchCheck($data['fields']['creators']['value'] === "Synthetic 103\nSynthetic 101", 'Creators retain relation order while duplicates are removed.');
batchCheck($data['collections'][4]['fields']['related_links']['value'] === 'https://example.org/synthetic-finding-aid'
	&& (int)Db::$pdo->query('SELECT COUNT(*) FROM ca_attributes WHERE table_num = 13 AND row_id = 4 AND element_id = 35')->fetchColumn() === 1
	&& (int)Db::$pdo->query('SELECT COUNT(*) FROM ca_attributes WHERE element_id IN (56, 57)')->fetchColumn() === 0,
	'Nested container values must use presence of the root element, without requiring subelement rows.');
batchCheck($data['fields']['creator_details']['value'] === 'Synthetic creator history.'
	&& !isset($data['collections'][3]['fields']['creator_details']), 'Custom creator attributes require their own native entity presence and formatting path.');
foreach (Db::$queries as [$sql, $params]) {
	if (!str_contains($sql, 'FROM ca_attributes')) { continue; }
	batchCheck(!array_intersect([56, 57], $params[2]), 'Container presence queries must request root elements only.');
}
batchCheck($data['collections'][3]['fields']['extent']['value'] === 'One folder'
	&& $data['collections'][2]['fields']['extent']['value'] === 'Two boxes'
	&& !isset($data['collections'][3]['fields']['description']), 'Element applicability and first populated fallback must be evaluated for each record type.');
$request->user->denied['ca_entities'] = ['preferred_labels']; batchCanonical($request, $root, $config);
$request->user->denied['ca_entities'] = ['biography'];
batchCheck(!isset(batchCanonical($request, $root, $config)['fields']['creator_details']), 'A denied creator attribute must be omitted independently of creator label access.');
$request->user->denied = ['ca_collections' => ['description', 'rank', 'preferred_labels']]; Db::$queries = [];
batchCanonical($request, $root, $config);
foreach (Db::$queries as [$sql, $params]) {
	if (!str_contains($sql, 'FROM ca_attributes')) { continue; }
	batchCheck(!in_array(31, $params[2], true) && !in_array(0, $params[2], true), 'Denied, missing and unresolved attribute roots must never be queried.');
}
$request->user->denied = ['ca_collections' => ['hierarchy']];
batchCheck(count(batchCanonical($request, $root, $config)['collections']) === 1, 'Denied hierarchy bundle must retain only the selected unit.');
$request->user->denied = []; $config->sort = 'name'; batchCanonical($request, $root, $config);
$config->sort = 'rank';
$request->types['ca_collections'] = [1, 2]; $request->sources['ca_collections'] = [1];
batchCanonical($request, $root, $config);
for ($id = 1000; $id < 2205; $id++) { batchNode('ca_collections', $id, $id === 2204 ? 1000 : 1, ['type_id' => $id % 2 + 1]); }
BatchModel::$loads = BatchModel::$relationships = 0; Db::$queries = []; $GLOBALS['batchTemplates'] = [];
$large = tadlFindingAidData($request, $root, $config);
batchCheck(count($large['collections']) === 1209, 'Large exports must retain every descriptive unit, including metadata-only descendants beyond 500 IDs.');
batchCheck(BatchModel::$loads === 0 && BatchModel::$relationships === 6, 'The batch path must avoid per-unit models and batch each creator field membership read.');
batchCheck(count($GLOBALS['batchTemplates']) < 30 && count(Db::$queries) < 45, 'Large exports must use bounded template/hierarchy batches rather than one query per field per unit.');
$request->aclDenied['ca_collections'][] = 1000;
$changed = tadlFindingAidData($request, $root, $config);
batchCheck(!isset($changed['collections'][1000], $changed['collections'][2204]), 'Changed current permissions must immediately prune a branch without cached access decisions.');
echo 'Finding aid batches passed: '.$assertions." assertions.\n";
