<?php
/** Actual parameterized SQLite reads, a native-result boundary and shared rendered paging/context. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$GLOBALS['omekaSortAssertions'] = 0;
function omekaSortCheck($condition, $message) {
	$GLOBALS['omekaSortAssertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}

if (($argv[1] ?? '') === '--render') {
	// Reuse the existing actual result view and media/access/context boundaries in a separate process.
	ob_start(); require __DIR__.'/media_preferences_test.php'; ob_end_clean();
	Db::$pdo->exec('CREATE TABLE ca_metadata_elements (element_id INTEGER PRIMARY KEY, element_code TEXT, datatype INTEGER)');
	Db::$pdo->exec('CREATE TABLE ca_attributes (attribute_id INTEGER PRIMARY KEY, table_num INTEGER, row_id INTEGER)');
	Db::$pdo->exec('CREATE TABLE ca_attribute_values (attribute_id INTEGER, element_id INTEGER, value_integer1 INTEGER, value_longtext1 TEXT)');
	testInsert('ca_metadata_elements', ['element_id'=>1, 'element_code'=>'omeka_id', 'datatype'=>11]);
	foreach (range(1000, 1012) as $id) {
		testInsert('ca_attributes', ['attribute_id'=>$id, 'table_num'=>57, 'row_id'=>$id]);
		testInsert('ca_attribute_values', ['attribute_id'=>$id, 'element_id'=>1,
			'value_integer1'=>$id === 1011 ? 0 : ($id === 1012 ? null : $id), 'value_longtext1'=>$id === 1012 ? '' : (string)$id]);
	}
	testInsert('ca_attributes', ['attribute_id'=>20000, 'table_num'=>57, 'row_id'=>1005]);
	testInsert('ca_attribute_values', ['attribute_id'=>20000, 'element_id'=>1, 'value_integer1'=>null, 'value_longtext1'=>'']);
	$info = caGetInfoForBrowseType('objects');
	$info['sortBy']['Omeka ID'] = 'ca_objects.omeka_id';
	foreach (['all', 'only'] as $mode) {
		foreach (['asc', 'desc'] as $direction) {
			$numbered = $direction === 'asc' ? range(1000, 1010) : range(1010, 1000);
			$misordered = array_values(array_diff($numbered, [1005]));
			$native = array_merge([1011, 1012, 12, 1005], $misordered, [1013]);
			$expected = array_merge($numbered, $mode === 'all' ? [1011, 1012, 12, 1013] : [1011, 1012, 1013]);
			foreach ([0, 9] as $start) {
				$request = testMediaRequest($mode, ['sort'=>'Omeka ID', 'direction'=>$direction, 'view'=>'images',
					'per_page'=>'9', 's'=>$start, 'collection_id'=>888888, 'tadl_collection_controls'=>1,
					'tadl_collection_id'=>888888], 'CollectionContents', 'objects');
				$html = tadlServerObjectResults(new TestView($request, []), $request, new SearchResult('ca_objects', $native),
					'collection', $info, 'collection_id:888888');
				$document = new DOMDocument(); $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
				$cards = (new DOMXPath($document))->query('//div[@id and starts-with(@id,"row")]');
				$shown = [];
				foreach ($cards as $card) { $shown[] = (int)substr($card->getAttribute('id'), 3); }
				omekaSortCheck($shown === array_slice($expected, $start, 9), 'Rendered pages must order the complete result numerically before slicing in either direction/media mode.');
				$context = ResultContext::$saved['ca_objects:search:objects'];
				omekaSortCheck($context['ids'] === $expected && $context['count'] === count($expected)
					&& $context['sort'] === 'Omeka ID' && $context['direction'] === $direction,
					'Detail navigation must save the displayed order and full filtered membership.');
				omekaSortCheck(str_contains($context['tadl_result_return']['url'], '/sort/Omeka%20ID/'), 'Return links must retain the active Omeka sort.');
			}
		}
	}
	echo "Omeka sort render integration passed: {$GLOBALS['omekaSortAssertions']} assertions.\n";
	exit;
}

require __DIR__.'/../helpers/omeka_sort.php';
class OmekaSortRows {
	private int $position = -1;
	function __construct(private array $rows) {}
	function nextRow() { return ++$this->position < count($this->rows); }
	function getRow() { return $this->rows[$this->position]; }
}
class OmekaSortDb {
	public PDO $pdo;
	public array $queries = [];
	public bool $fail = false;
	function __construct() {
		$this->pdo = new PDO('sqlite::memory:');
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->pdo->exec('CREATE TABLE ca_metadata_elements (element_id INTEGER PRIMARY KEY, element_code TEXT, datatype INTEGER)');
		$this->pdo->exec('CREATE TABLE ca_attributes (attribute_id INTEGER PRIMARY KEY, table_num INTEGER, row_id INTEGER)');
		$this->pdo->exec('CREATE TABLE ca_attribute_values (attribute_id INTEGER, element_id INTEGER, value_integer1 INTEGER, value_longtext1 TEXT)');
		$this->pdo->exec("INSERT INTO ca_metadata_elements VALUES (1, 'omeka_id', 11), (2, 'another_id', 11)");
	}
	function attribute($id, $value, $element = 1, $table = 57, $text = null) {
		$statement = $this->pdo->prepare('INSERT INTO ca_attributes (table_num, row_id) VALUES (?, ?)');
		$statement->execute([$table, $id]);
		$statement = $this->pdo->prepare('INSERT INTO ca_attribute_values VALUES (?, ?, ?, ?)');
		$statement->execute([$this->pdo->lastInsertId(), $element, $value, $text]);
	}
	function query($sql, $params) {
		$this->queries[] = ['sql'=>$sql, 'params'=>$params];
		if ($this->fail) { return false; }
		// Expand CollectiveAccess's array-bound IN (?) syntax, then execute real SQLite SQL.
		$index = 0; $bindings = [];
		$sql = preg_replace_callback('/\?/', static function () use (&$index, &$bindings, $params) {
			$values = is_array($params[$index]) ? $params[$index++] : [$params[$index++]];
			array_push($bindings, ...$values);
			return implode(',', array_fill(0, count($values), '?'));
		}, $sql);
		$statement = $this->pdo->prepare($sql); $statement->execute($bindings);
		return new OmekaSortRows($statement->fetchAll(PDO::FETCH_ASSOC));
	}
}
// Conditional declarations let --render use the existing media suite's native boundaries instead.
if (!class_exists('SearchResult', false)) {
	class WLPlugSearchEngineBrowseEngine {
		function __construct(public array $ids, $description, public int $tableNum) {}
	}
	class SearchResult {
		public int $initializations = 0;
		function __construct(private OmekaSortDb $db, private array $ids, private string $table = 'ca_objects', private int $number = 57) {}
		function getDb() { return $this->db; }
		function tableName() { return $this->table; }
		function tableNum() { return $this->number; }
		function getPrimaryKeyValues() { return $this->ids; }
		function init($engine, $tables, $options) {
			omekaSortCheck($options === ['db'=>$this->db] && $engine->tableNum === $this->number && $tables === [], 'Reinitialization must preserve the database and subject table.');
			$this->ids = $engine->ids; $this->initializations++;
		}
	}
}

$db = new OmekaSortDb();
foreach ([1=>2, 2=>10, 3=>100, 4=>null, 5=>null, 6=>0] as $id=>$value) { $db->attribute($id, $value, 1, 57, $id === 5 ? '' : null); }
$db->attribute(8, 1, 1, 20); // Same numeric ID belongs to another subject table.
$db->attribute(9, 1, 2); // Another metadata element does not populate Omeka ID.
$db->attribute(2, null); // A repeated blank can put this populated ID before smaller IDs natively.
foreach (['asc'=>[4, 5, 6, 2, 1, 3, 7, 8, 9], 'desc'=>[4, 5, 6, 1, 2, 3, 7, 8, 9]] as $direction=>$native) {
	$numbered = $direction === 'asc' ? [1, 2, 3] : [3, 2, 1];
	$expected = array_merge($numbered, [4, 5, 6, 7, 8, 9]);
	$result = new SearchResult($db, $native);
	omekaSortCheck(tadlOrderOmekaResult($result, 'ca_objects.omeka_id', $direction) === $result && $result->getPrimaryKeyValues() === $expected,
		'Positive IDs must sort numerically despite repeated blanks, followed by null, empty, zero and missing IDs.');
	omekaSortCheck($result->initializations === 1 && count($expected) === count($native)
		&& !array_diff($native, $expected) && count(array_unique($expected)) === count($expected), 'Partitioning must preserve the complete authorized membership without duplicates.');
	tadlOrderOmekaResult($result, 'ca_objects.omeka_id', $direction);
	omekaSortCheck($result->initializations === 1 && $result->getPrimaryKeyValues() === $expected, 'Applying ordering twice must be idempotent without unnecessary result reinitialization.');
}
omekaSortCheck(tadlOmekaIDOrder($db, [4, 1, 1, 2, 7], 57) === [1, 1, 2, 4, 7], 'A repeated input ID must retain its original multiplicity.');
foreach ([[4, 5, 6, 7], [1, 2, 3, 4]] as $ids) {
	$result = new SearchResult($db, $ids); tadlOrderOmekaResult($result, 'ca_objects.omeka_id');
	omekaSortCheck($result->getPrimaryKeyValues() === $ids && $result->initializations === 0, 'Already ordered and all-missing results must remain untouched.');
}
$before = count($db->queries);
foreach (['ca_objects.idno', '_natural', null, 'ca_objects.omeka_id;ca_objects.object_id'] as $field) {
	$result = new SearchResult($db, [4, 1]); tadlOrderOmekaResult($result, $field);
	omekaSortCheck($result->getPrimaryKeyValues() === [4, 1] && !$result->initializations, 'Other sorts must remain untouched.');
}
$result = new SearchResult($db, [4, 1], 'ca_collections', 20); tadlOrderOmekaResult($result, 'ca_objects.omeka_id');
omekaSortCheck(!$result->initializations && count($db->queries) === $before, 'Other sorts and subject tables must issue no metadata queries.');
foreach ([[], [4]] as $ids) { omekaSortCheck(tadlOmekaIDOrder($db, $ids, 57) === $ids, 'Zero or one result requires no ordering.'); }
omekaSortCheck(count($db->queries) === $before, 'Zero or one result must avoid metadata queries.');

$text = new OmekaSortDb(); $text->pdo->exec('UPDATE ca_metadata_elements SET datatype = 1 WHERE element_id = 1');
foreach ([1=>'0002', 2=>'10', 3=>'100', 4=>null, 5=>'', 6=>'0', 7=>'invalid', 8=>' 20 ', 9=>'000', 10=>'2',
	11=>'9223372036854775808', 12=>'9223372036854775807'] as $id=>$value) { $text->attribute($id, null, 1, 57, $value); }
$textInput = [4, 5, 6, 7, 9, 3, 1, 10, 2, 8, 11, 12];
omekaSortCheck(tadlOmekaIDOrder($text, $textInput, 57) === [1, 10, 2, 8, 3, 12, 11, 4, 5, 6, 7, 9],
	'Text IDs must sort without integer overflow, retain equal-key order and put nonpositive or invalid values last.');
omekaSortCheck(tadlOmekaIDOrder($text, $textInput, 57, 'desc') === [11, 12, 3, 8, 2, 1, 10, 4, 5, 6, 7, 9],
	'Descending text IDs must reverse numeric order while retaining equal-key and missing-ID order.');
$repeated = new OmekaSortDb();
$repeated->attribute(1, 10); $repeated->attribute(1, 100); $repeated->attribute(2, 20); $repeated->attribute(3, 50);
omekaSortCheck(tadlOmekaIDOrder($repeated, [2, 3, 1], 57) === [1, 2, 3]
	&& tadlOmekaIDOrder($repeated, [2, 3, 1], 57, 'desc') === [1, 3, 2],
	'Repeated positive values must use the minimum ascending and maximum descending.');

$large = new OmekaSortDb(); $populated = $missing = [];
foreach (range(1000, 2000) as $id) {
	if ($id % 2 === 0) { $populated[] = $id; $large->attribute($id, $id); }
	else { $missing[] = $id; }
}
$input = array_merge($missing, $populated);
omekaSortCheck(tadlOmekaIDOrder($large, $input, 57) === array_merge($populated, $missing) && count($large->queries) === 3,
	'Large results must retain every ID across bounded presence-query batches.');
$queried = [];
foreach ($large->queries as $query) {
	omekaSortCheck(count($query['params'][1]) <= 500 && $query['params'][0] === 57 && $query['params'][2] === 'omeka_id'
		&& !str_contains($query['sql'], '1000') && !str_contains($query['sql'], 'omeka_id'), 'Subject, IDs and element code must be bound parameters.');
	array_push($queried, ...$query['params'][1]);
}
omekaSortCheck($queried === $input, 'Presence queries must stay within the actual result cohort.');
$db->fail = true; $result = new SearchResult($db, [4, 1]);
try { tadlOrderOmekaResult($result, 'ca_objects.omeka_id'); throw new LogicException('Expected query failure.'); }
catch (RuntimeException $error) { omekaSortCheck($error->getMessage() === 'Unable to read Omeka IDs for sorting.', 'Metadata query failure must be explicit.'); }
omekaSortCheck($result->getPrimaryKeyValues() === [4, 1] && !$result->initializations, 'A failed presence read must not partially alter the result.');

$process = proc_open([PHP_BINARY, __FILE__, '--render'], [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes);
if (!is_resource($process)) { throw new RuntimeException('Unable to run shared result rendering checks.'); }
fclose($pipes[0]); $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
omekaSortCheck(proc_close($process) === 0, 'Shared rendering checks failed: '.$errors);
echo $output;
echo "Omeka sort passed: {$GLOBALS['omekaSortAssertions']} assertions (SQLite, synthetic native result boundary).\n";
