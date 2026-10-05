<?php
/** Actual collection thumbnail SQL, in-memory SQLite and synthetic native tags/models. */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$assertions = 0;
function checkThumbnail($condition, $message) {
	global $assertions; $assertions++;
	if (!$condition) { throw new RuntimeException($message); }
}
function _t($text) { return $text; }
function caGetOption($key, $options, $default = null) { return $options[$key] ?? $default; }
function caMakeRelationshipTypeIDList($table, $types) { return $types; }
function caMakeTypeIDList($table, $types) { return $types; }
class ThumbnailDb {
	static public PDO $pdo;
	static public array $queries = [];
	function query($sql, $params) {
		$bindings = []; $index = 0;
		$sql = preg_replace_callback('/\?/', function () use (&$index, &$bindings, $params) {
			$values = (array)$params[$index++];
			array_push($bindings, ...$values);
			return implode(',', array_fill(0, count($values), '?'));
		}, $sql);
		$query = self::$pdo->prepare($sql); $query->execute($bindings);
		$rows = $query->fetchAll(PDO::FETCH_ASSOC);
		self::$queries[] = ['params' => $params, 'rows' => count($rows)];
		return new ThumbnailRows($rows);
	}
}
class ThumbnailRows {
	private int $position = -1;
	function __construct(private array $rows) {}
	function nextRow() { return ++$this->position < count($this->rows); }
	function get($name) { return $this->rows[$this->position][$name] ?? null; }
	function getMediaTag($field, $version, $options) {
		return '<img src="'.htmlspecialchars($this->get($field), ENT_QUOTES, 'UTF-8').'" alt="'.htmlspecialchars($options['alt'], ENT_QUOTES, 'UTF-8').'" data-version="'.$version.'">';
	}
}
class ca_collections {
	static public array $direct = [];
	function getDb() { return new ThumbnailDb(); }
	function getPrimaryMediaForIDs($ids, $versions, $options) {
		return array_intersect_key(self::$direct, array_fill_keys($ids, true));
	}
}
ThumbnailDb::$pdo = new PDO('sqlite::memory:');
ThumbnailDb::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach ([
	'ca_collections' => 'collection_id INTEGER PRIMARY KEY, hier_collection_id INTEGER, hier_left INTEGER, hier_right INTEGER, access INTEGER, deleted INTEGER',
	'ca_collection_labels' => 'collection_id INTEGER, name TEXT, is_preferred INTEGER',
	'ca_objects' => 'object_id INTEGER PRIMARY KEY, idno_sort TEXT, type_id INTEGER, access INTEGER, deleted INTEGER',
	'ca_objects_x_collections' => 'collection_id INTEGER, object_id INTEGER, type_id INTEGER',
	'ca_objects_x_object_representations' => 'relation_id INTEGER PRIMARY KEY, object_id INTEGER, representation_id INTEGER, rank INTEGER, is_primary INTEGER',
	'ca_object_representations' => 'representation_id INTEGER PRIMARY KEY, media TEXT, access INTEGER, deleted INTEGER'
] as $table => $columns) { ThumbnailDb::$pdo->exec('CREATE TABLE '.$table.' ('.$columns.')'); }
function thumbnailInsert($table, $values) {
	$query = ThumbnailDb::$pdo->prepare('INSERT INTO '.$table.' VALUES ('.implode(',', array_fill(0, count($values), '?')).')');
	$query->execute($values);
}
function thumbnailObject($id, $collection, $name, $access = 1, $deleted = 0, $repAccess = 1, $repDeleted = 0, $primary = 1, $type = 1, $relationType = 1) {
	thumbnailInsert('ca_objects', [$id,$name,$type,$access,$deleted]);
	thumbnailInsert('ca_objects_x_collections', [$collection,$id,$relationType]);
	thumbnailInsert('ca_objects_x_object_representations', [$id,$id,$id,0,$primary]);
	thumbnailInsert('ca_object_representations', [$id,'/synthetic/image-'.$id.'.jpg',$repAccess,$repDeleted]);
}
foreach ([
	[1,1,1,10,1,0], [2,1,2,3,1,0], [3,1,4,5,0,0],
	[4,4,1,4,0,0], [5,4,2,3,1,0], [6,6,0,0,1,0],
	[10,10,1,10,1,0], [11,10,2,3,1,0], [12,12,1,2,1,1]
] as $row) { thumbnailInsert('ca_collections',$row); }
thumbnailInsert('ca_collection_labels',[1,'Synthetic <Harbor> & archive',1]);
thumbnailObject(20,1,'B'); thumbnailObject(21,1,'A',1,0,1,0,1,2,2);
thumbnailObject(30,2,'A'); thumbnailObject(31,3,'A'); thumbnailObject(32,5,'A');
thumbnailObject(40,10,'0-private',0); thumbnailObject(41,10,'0-deleted',1,1);
thumbnailObject(42,10,'0-private-rep',1,0,0); thumbnailObject(43,10,'0-deleted-rep',1,0,1,1);
thumbnailObject(44,10,'0-secondary',1,0,1,0,0); thumbnailObject(45,11,'A');
thumbnailObject(50,6,'A'); thumbnailObject(51,12,'A');
require dirname(__DIR__).'/views/Browse/collection_thumbnail_helpers.php';
$options = ['checkAccess' => [1], 'version' => 'small'];
$images = tadlGetDescendantCollectionImages([1,10,4,6,12],$options);
checkThumbnail(count($images) === 3, 'Private/deleted roots returned images or valid roots disappeared.');
checkThumbnail(str_contains($images[1], 'image-21.jpg'), 'Name order did not pick the first direct object.');
checkThumbnail(str_contains($images[10], 'image-45.jpg'), 'Private/deleted/secondary images or an unrelated hierarchy leaked into selection.');
checkThumbnail(str_contains($images[6], 'image-50.jpg'), 'Uninitialized bounds prevented a direct image.');
checkThumbnail(str_contains($images[1], 'Synthetic &lt;Harbor&gt; &amp; archive'), 'Native alt-tag escaping was lost.');
$direct = tadlGetDescendantCollectionImages([1],$options+['directOnly'=>true,'relationshipTypes'=>[1],'objectTypes'=>[1]]);
checkThumbnail(str_contains($direct[1], 'image-20.jpg'), 'Direct object/relationship selectors were ignored.');
$queries = count(ThumbnailDb::$queries);
checkThumbnail(tadlGetDescendantCollectionImages([],$options) === [] && count(ThumbnailDb::$queries) === $queries, 'Empty inputs executed an unscoped query.');
ca_collections::$direct[1] = ['tags'=>['small'=>'<img src="/synthetic/collection-primary.jpg" alt="Synthetic primary">']];
$images = tadlGetCollectionImages([1,10],$options);
checkThumbnail(str_contains($images[1], 'collection-primary.jpg'), 'Native collection primary media precedence was lost.');
checkThumbnail(str_contains($images[10], 'image-45.jpg'), 'Missing direct media did not fall back to accessible descendants.');
$fallbackQueries = array_slice(ThumbnailDb::$queries, $queries);
checkThumbnail(count($fallbackQueries) === 2, 'Direct/descendant fallbacks did not stay bounded.');
foreach ($fallbackQueries as $query) { checkThumbnail($query['params'][count($query['params']) - 2] === [10], 'Resolved collection was unnecessarily included in fallbacks.'); }
// Thousands of attached images must still produce just one media row/tag.
foreach (range(1000,1999) as $id) { thumbnailObject($id,2,'Synthetic '.str_pad((string)$id,4,'0',STR_PAD_LEFT)); }
$images = tadlGetDescendantCollectionImages([2],$options);
checkThumbnail(str_contains($images[2],'image-30.jpg'), 'A large collection changed the selected image.');
checkThumbnail(end(ThumbnailDb::$queries)['rows'] === 1, 'Thumbnail query returned every object instead of one row per card.');

// Render the actual Tiles/List routes with populated and media-less collections.
function caGetDisplayImagesForAuthorityItems($table, $ids, $options) { throw new RuntimeException('Collection browse called the unbounded native authority fallback.'); }
function caDisplayLightbox($request) { return false; }
function caGetUserAccessValues($request) { return [1]; }
function caGetIconsConfig() { return new ThumbnailConfig(); }
function caDetailLink($request, $text, $class, $table, $id) { return '<a href="/Detail/collections/'.$id.'">'.$text.'</a>'; }
class ca_list_items {}
class ExternalCache {
	static function save($key, $value, $group, $timeout) {}
}
class ThumbnailConfig {
	function get($name) { return $name === 'cache_timeout' ? 0 : null; }
	function getAssoc($name) { return []; }
}
class ThumbnailRequest {
	function isAjax() { return false; }
}
class ThumbnailResult {
	private int $position = -1;
	function __construct(private array $ids) {}
	function numHits() { return count($this->ids); }
	function seek($position) { $this->position = $position - 1; }
	function nextHit() { return ++$this->position < count($this->ids); }
	function get($name) { return str_contains($name, 'preferred_labels') ? 'Synthetic collection' : $this->ids[$this->position]; }
	function getWithTemplate($template) { return ''; }
}
class ThumbnailView {
	public ThumbnailRequest $request;
	function __construct(private string $view, private array $ids) { $this->request = new ThumbnailRequest(); }
	function getVar($name) {
		return match ($name) {
			'result' => $this->result,
			'table' => 'ca_collections', 'primaryKey' => 'collection_id', 'view' => $this->view,
			'access_values' => [1], 'options', 'facets', 'criteria', 'views' => [],
			'start', 'row_id' => 0, 'config' => new ThumbnailConfig(), default => null
		};
	}
	private ThumbnailResult $result;
	function render() {
		$this->result = new ThumbnailResult($this->ids);
		ob_start();
		try { include dirname(__DIR__).'/views/Browse/browse_results_'.$this->view.'_html.php'; return ob_get_clean(); }
		catch (Throwable $error) { ob_end_clean(); throw $error; }
	}
}
foreach (['images','list'] as $view) {
	$html = (new ThumbnailView($view,[1,10]))->render();
	checkThumbnail(str_contains($html,'collection-primary.jpg') && str_contains($html,'image-45.jpg'), $view.': collection thumbnails did not use the bounded helper.');
	$html = (new ThumbnailView($view,[4]))->render();
	checkThumbnail(str_contains($html,'Placeholder') && !str_contains($html,'image-32.jpg'), $view.': missing media did not render a safe placeholder.');
}
echo 'Collection thumbnails passed: '.$assertions.' assertions (actual SQL, bounded media rows, stable order, access/deletion, primary precedence and fallbacks).'.PHP_EOL;
