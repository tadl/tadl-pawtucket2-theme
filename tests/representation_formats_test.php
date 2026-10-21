<?php
/** Actual scalar SQL and result views; synthetic native permissions and catalogue only. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
define('__CA_BUNDLE_ACCESS_READONLY__', 1);
define('pInteger', 'integer');
define('pString', 'string');
$checks = 0;
function formatCheck($condition, $message) { $GLOBALS['checks']++; if (!$condition) { throw new RuntimeException($message); } }
function _t($text, ...$values) { foreach ($values as $i => $value) { $text = str_replace('%'.($i + 1), (string)$value, $text); } return $text; }
function caGetBundleAccessLevel($table, $bundle) { return $GLOBALS['deniedBundle'] === $table.':'.$bundle ? 0 : 1; }
function caGetTypeRestrictionsForUser($table) { return $GLOBALS['restricted'] ? [1] : null; }
function caGetSourceRestrictionsForUser($table) { return $GLOBALS['restricted'] ? [1] : null; }
function caSourceAccessControlIsEnabled($model) { return $GLOBALS['restricted']; }
function caACLIsEnabled($model, $options) {
	formatCheck($options === ['forPawtucket' => true], 'Native front-end ACL context was lost.');
	return $GLOBALS['aclChecking'];
}
function caGetBrowseInstance($table) { return new FormatBrowse(); }
class FormatBrowse {
	function filterHitsByACL($ids, $tableNum, $userID) {
		formatCheck($userID === 7, 'ACLs must use the visitor identity.');
		return array_values(array_diff($ids, $GLOBALS['aclDenied'][$tableNum] ?? []));
	}
}
class FormatConfig {
	function get($key) { return $key === 'perform_type_access_checking' ? $GLOBALS['restricted'] : null; }
	function getAssoc($key) { return []; }
}
class FormatModel {
	function __construct(private string $table) {}
	function getDb() { return new FormatDb(); }
	function getAppConfig() { return new FormatConfig(); }
	function getTypeFieldName() { return 'type_id'; }
	function getSourceFieldName() { return 'source_id'; }
	function tableNum() { return $this->table === 'ca_objects' ? 57 : 56; }
}
class Datamodel { static function getInstanceByTableName($table, $cached) { return new FormatModel($table); } }
class FormatDb {
	static public PDO $pdo;
	static public array $queries = [];
	function query($sql, $params) {
		self::$queries[] = [$sql, $params];
		$values = []; $index = 0;
		$sql = preg_replace_callback('/\?/', static function () use ($params, &$index, &$values) {
			$chunk = (array)$params[$index++]; array_push($values, ...$chunk);
			return join(',', array_fill(0, count($chunk), '?'));
		}, $sql);
		$query = self::$pdo->prepare($sql); $query->execute($values);
		return new FormatRows($query->fetchAll(PDO::FETCH_ASSOC));
	}
}
class FormatRows {
	static public array $decoded = [];
	private int $position = -1;
	function __construct(private array $rows) {}
	function nextRow() { return ++$this->position < count($this->rows); }
	function get($field) { return $this->rows[$this->position][$field] ?? null; }
	function getMediaTag($field, $version, $options) {
		self::$decoded[] = $this->get('representation_id');
		return $this->get('media') ? '<img src="/portrait.svg" alt="">' : '';
	}
}
class FormatUser {
	function getTypeAccessLevel($table, $id) { return $id === 2 ? 0 : 1; }
	function getSourceAccessLevel($table, $id) { return $id === 2 ? 0 : 1; }
}
class FormatRequest {
	public object $user;
	function __construct(public bool $collection = true, public bool $ajax = true) { $this->user = new FormatUser(); }
	function getUserID() { return 7; }
	function getController() { return 'Search'; }
	function getParameter($key, $type) { return $this->collection ? ['tadl_collection_controls' => 1, 'tadl_collection_id' => 42][$key] ?? null : null; }
	function isAjax() { return $this->ajax; }
}
$GLOBALS['deniedBundle'] = ''; $GLOBALS['restricted'] = true; $GLOBALS['aclChecking'] = true;
$GLOBALS['aclDenied'] = [56 => [19], 57 => []];
FormatDb::$pdo = new PDO('sqlite::memory:');
FormatDb::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
FormatDb::$pdo->exec('CREATE TABLE ca_objects (object_id INTEGER PRIMARY KEY, access INTEGER, deleted INTEGER, type_id INTEGER, source_id INTEGER)');
FormatDb::$pdo->exec('CREATE TABLE ca_object_representations (representation_id INTEGER PRIMARY KEY, access INTEGER, deleted INTEGER, type_id INTEGER, source_id INTEGER, media TEXT, mimetype TEXT, original_filename TEXT)');
FormatDb::$pdo->exec('CREATE TABLE ca_objects_x_object_representations (object_id INTEGER, representation_id INTEGER, is_primary INTEGER DEFAULT 1)');
FormatDb::$pdo->exec('INSERT INTO ca_objects VALUES (1,1,0,1,1),(2,0,0,1,1),(3,1,0,1,1),(99,1,0,1,1)');
$insertRepresentation = FormatDb::$pdo->prepare('INSERT INTO ca_object_representations VALUES (?,?,?,?,?,?,?,?)');
$insertLink = FormatDb::$pdo->prepare('INSERT INTO ca_objects_x_object_representations (object_id,representation_id) VALUES (?,?)');
foreach ([
	[10,1,0,1,1,'descriptor','image/tiff','synthetic.jpg'],
	[11,1,0,1,1,'descriptor','image/jpeg','synthetic.jpg'],
	[12,1,0,1,1,'descriptor','image/pjpeg','synthetic-copy.jpg'],
	[13,1,0,1,1,'descriptor','application/pdf','synthetic.pdf'],
	[14,0,0,1,1,'descriptor','image/bmp','private.bmp'],
	[15,1,1,1,1,'descriptor','image/gif','deleted.gif'],
	[16,1,0,1,1,'','image/png','empty.png'],
	[17,1,0,2,1,'descriptor','image/bmp','denied-type.bmp'],
	[18,1,0,1,2,'descriptor','image/gif','denied-source.gif'],
	[19,1,0,1,1,'descriptor','application/zip','denied-acl.zip'],
	[20,1,0,1,1,'descriptor','','legacy.tif']
] as $row) { $insertRepresentation->execute($row); $insertLink->execute([1, $row[0]]); }
$insertLink->execute([2, 13]);
require dirname(__DIR__).'/helpers/representation_formats.php';
require dirname(__DIR__).'/views/Browse/tadl_result_helpers.php';
$request = new FormatRequest();
$GLOBALS['g_request'] = $request;
$formats = tadlObjectRepresentationFormats($request, [1,2,3,1,'1 OR 1=1',-1], [1]);
formatCheck($formats === [1 => ['JPEG','PDF','TIFF']], 'Original formats must deduplicate and exclude private/deleted/blank/restricted representations and private objects.');
$insertRepresentation->execute([21,0,0,1,1,'descriptor','application/pdf','paired.pdf']);
$insertRepresentation->execute([22,0,0,1,1,'descriptor','image/jpeg','paired.jpg']);
foreach ([10,21,22] as $id) { $insertLink->execute([99,$id]); }
formatCheck(tadlObjectRepresentationFormats($request, [99], [1]) === [99 => ['TIFF']], 'Private paired PDF/JPEG files must not appear beside a public TIFF.');
$GLOBALS['aclDenied'][57] = [1];
formatCheck(tadlObjectRepresentationFormats($request, [1], [1]) === [], 'Object ACLs must also hide formats.');
$GLOBALS['aclDenied'][57] = [];
FormatDb::$pdo->exec('UPDATE ca_objects SET type_id = 2 WHERE object_id = 1');
formatCheck(tadlObjectRepresentationFormats($request, [1], [1]) === [], 'Object type restrictions must hide formats.');
FormatDb::$pdo->exec('UPDATE ca_objects SET type_id = 1, source_id = 2 WHERE object_id = 1');
formatCheck(tadlObjectRepresentationFormats($request, [1], [1]) === [], 'Object source restrictions must hide formats.');
FormatDb::$pdo->exec('UPDATE ca_objects SET source_id = 1, deleted = 1 WHERE object_id = 1');
formatCheck(tadlObjectRepresentationFormats($request, [1], [1]) === [], 'Deleted objects must hide formats.');
FormatDb::$pdo->exec('UPDATE ca_objects SET deleted = 0 WHERE object_id = 1');
// Runtime per-type/source checks must work even without restriction lists: covered by the shared access suite.
foreach (['ca_objects:ca_object_representations','ca_object_representations:media','ca_object_representations:mimetype'] as $denied) {
	$GLOBALS['deniedBundle'] = $denied; $queries = count(FormatDb::$queries);
	formatCheck(tadlObjectRepresentationFormats($request, [1], [1]) === [] && count(FormatDb::$queries) === $queries, 'Denied bundle must fail closed before querying.');
}
$GLOBALS['deniedBundle'] = 'ca_object_representations:original_filename';
$queries = count(FormatDb::$queries);
formatCheck(tadlObjectRepresentationFormats($request, [1], [1]) === [1 => ['JPEG','PDF','TIFF']], 'MIME fields should work without a readable filename.');
formatCheck(!str_contains(FormatDb::$queries[$queries][0], 'r.original_filename'), 'Denied filenames must not be fetched.');
$GLOBALS['deniedBundle'] = '';
foreach ([['image/tiff','wrong.jpg','TIFF'],['IMAGE/JPEG; charset=binary','','JPEG'],['image/x-ms-bmp','','BMP'],['image/png','','PNG'],['audio/mpeg','','MP3'],['video/mp4','','MP4'],['application/octet-stream','legacy.tif','TIFF'],['','legacy.JPEG','JPEG'],['','unsafe.<script>','']] as [$mime,$file,$label]) {
	formatCheck(tadlRepresentationFormatLabel($mime, $file) === $label, 'Format normalization failed for '.$mime);
}
formatCheck(tadlRepresentationFormatTags([]) === '', 'Metadata-only objects should not get an empty tag row.');
formatCheck(str_contains(tadlRepresentationFormatTags(['<script>']), '&lt;script&gt;'), 'Format text must be HTML escaped.');
$queries = count(FormatDb::$queries);
formatCheck(tadlObjectRepresentationFormats(null,[1],[1]) === [] && tadlObjectRepresentationFormats($request,[1],[]) === []
	&& tadlObjectRepresentationFormats($request,['invalid'],[1]) === [] && count(FormatDb::$queries) === $queries, 'Invalid context/mask/IDs must fail closed.');
$insertObject = FormatDb::$pdo->prepare('INSERT INTO ca_objects VALUES (?,1,0,1,1)');
foreach (range(1000,1600) as $id) { $insertObject->execute([$id]); $insertLink->execute([$id,10]); }
$queries = count(FormatDb::$queries);
formatCheck(count(tadlObjectRepresentationFormats($request, range(1000,1600), [1])) === 601, 'Batched formats dropped objects.');
$inventory = array_values(array_filter(array_slice(FormatDb::$queries, $queries), static fn($q) => str_contains($q[0], 'SELECT DISTINCT oxr.object_id')));
formatCheck(count($inventory) === 2 && max(array_map(static fn($q) => count($q[1][0]), $inventory)) <= 500, 'Inventory must batch IDs, not query per object.');
foreach (FormatDb::$queries as [$sql]) { formatCheck(!preg_match('/SELECT[^;]*\br\.media\b/is', explode('FROM', $sql, 2)[0]), 'Do not select full media descriptors.'); }

// Render both actual templates, with readable search-result IDs and local fixture images.
$GLOBALS['restricted'] = $GLOBALS['aclChecking'] = false;
foreach (range(4,10) as $id) { $insertObject->execute([$id]); $insertLink->execute([$id,10]); }
foreach ([11,13,17] as $id) { $insertLink->execute([4,$id]); }
FormatDb::$pdo->exec('UPDATE ca_object_representations SET mimetype = "image/png" WHERE representation_id = 18');
$insertLink->execute([4,18]);
function caGetOption($key, $options, $default = null) { return $options[$key] ?? $default; }
function caGetUserAccessValues($request) { return [1]; }
function caGetIconsConfig() { return new FormatConfig(); }
function caDetailLink($request, $label, $class, $table, $id, $params = null, $attributes = []) {
	$extra = '';
	foreach ($attributes as $key => $value) { $extra .= ' '.$key.'="'.htmlspecialchars($value, ENT_QUOTES, 'UTF-8').'"'; }
	return '<a href="/Detail/objects/'.$id.'"'.$extra.'>'.$label.'</a>';
}
function caGetPlaceholder($code, $key) { return ''; }
function caDetailUrl($request, $table, $id) { return '/Detail/'.substr($table,3).'/'.$id; }
function caNavUrl($request, $module, $controller, $action, $params = [], $options = []) { return '/'.$controller.'/'.$action.'?'.http_build_query($params); }
function caNavLink($request, $label, $class, $module, $controller, $action, $params = [], $attributes = []) {
	return '<a class="'.htmlspecialchars($class, ENT_QUOTES, 'UTF-8').'" href="'.htmlspecialchars(caNavUrl($request,$module,$controller,$action,$params), ENT_QUOTES, 'UTF-8').'">'.$label.'</a>';
}
// Eligibility is covered by media_preferences_test.php; these are already-readable result IDs.
function tadlFilterMediaResult($request, $result) {}
class ca_objects {
	function __construct($id = 0) {}
	function getPrimaryKey() { return null; }
	function getPrimaryMediaForIDs($ids, $versions, $options) {
		$media = [];
		foreach ($ids as $id) {
			if ($id === 3) { continue; }
			$media[$id]['urls']['medium'] = $id === 4 ? '/landscape.svg' : ($id === 5 ? '/tall.svg' : '/portrait.svg');
			foreach ($versions as $version) { $media[$id]['tags'][$version] = '<img src="'.$media[$id]['urls']['medium'].'" alt="Synthetic photograph">'; }
		}
		return $media;
	}
}
class ca_list_items { function load($id) {} function get($key) { return ''; } }
require dirname(__DIR__).'/helpers/object_thumbnails.php';
$GLOBALS['restricted'] = $GLOBALS['aclChecking'] = true;
$before = count(FormatRows::$decoded);
$thumbnails = tadlObjectThumbnails($request,[1,2,'1 OR 1=1',-1], 'medium', [1]);
formatCheck(array_keys($thumbnails) === [1] && array_slice(FormatRows::$decoded,$before) === [10], 'Bulk thumbnails must decode only the chosen readable primary for readable objects.');
FormatDb::$pdo->exec('UPDATE ca_objects_x_object_representations SET is_primary = 0 WHERE object_id = 1 AND representation_id IN (10,11,12,13,16,20)');
$before = count(FormatRows::$decoded);
formatCheck(tadlObjectThumbnails($request,[1], 'medium', [1]) === [1=>''] && count(FormatRows::$decoded) === $before,
	'Private/deleted/type/source/ACL-denied primaries must not be decoded by the bulk path.');
FormatDb::$pdo->exec('UPDATE ca_objects_x_object_representations SET is_primary = 1 WHERE object_id = 1');
$GLOBALS['restricted'] = $GLOBALS['aclChecking'] = false;
$before = count(FormatRows::$decoded); $queries = count(FormatDb::$queries);
formatCheck(count(tadlObjectThumbnails($request,range(1000,1600), 'small', [1])) === 601 && count(FormatRows::$decoded)-$before === 2,
	'601 objects sharing a primary must decode it once in each bounded batch, not per object.');
$mediaQueries = array_filter(array_slice(FormatDb::$queries,$queries), static fn($q) => str_contains($q[0],'SELECT representation_id, media'));
formatCheck(count($mediaQueries) === 2, 'Bulk thumbnails must bound descriptor queries independently of the number of cards.');
$GLOBALS['deniedBundle'] = 'ca_object_representations:media'; $queries = count(FormatDb::$queries);
formatCheck(tadlObjectThumbnails($request,[1], 'small', [1]) === [] && count(FormatDb::$queries) === $queries, 'A denied media bundle must prevent every bulk thumbnail query.');
$GLOBALS['deniedBundle'] = '';
class FormatResult {
	public int $position = -1;
	function __construct(private array $ids = [1,3,4,5,6,7,8,9,10]) {}
	function numHits() { return count($this->ids); }
	function getPrimaryKeyValues() { return $this->ids; }
	function getPrimaryKey() { return $this->ids[$this->position]; }
	function seek($start) { $this->position = $start - 1; }
	function nextHit() { return ++$this->position < count($this->ids); }
	function get($key, $options = []) {
		$id = $this->ids[$this->position];
		if (str_contains($key, '.media.')) { return $id === 3 ? '' : '<img src="/portrait.svg" alt="Synthetic photograph">'; }
		if (str_contains($key, '.idno')) { return 'SYN.'.$id; }
		return str_contains($key, 'preferred_labels') ? 'Synthetic archival item '.$id : $id;
	}
	function getMediaTag($field, $version, $options) { return $this->get($field.'.'.$version); }
	function getWithTemplate($template) { return ''; }
}
class FormatView {
	function __construct(public FormatRequest $request, private array $values = []) {}
	function getVar($key) {
		if (array_key_exists($key, $this->values)) { return $this->values[$key]; }
		return match ($key) {
			'result' => new FormatResult(), 'table' => 'ca_objects', 'primaryKey' => 'object_id', 'access_values' => [1],
			'facets', 'criteria', 'options', 'views' => [], 'config' => new FormatConfig(), 'start', 'row_id' => 0, default => null
		};
	}
	function render($file) { ob_start(); try { include dirname(__DIR__).'/views/Browse/'.$file; return ob_get_clean(); } catch (Throwable $error) { ob_end_clean(); throw $error; } }
}
$result = new FormatResult(range(1,70)); $result->seek(10);
formatCheck(tadlBrowseResultPageIDs($result,9,9) === range(10,18) && $result->position === 9, 'Normal paging must read only current IDs without moving the cursor.');
formatCheck(tadlBrowseResultPageIDs($result,0,9,20) === range(19,27), 'Back restoration must jump directly to the target page.');
formatCheck(tadlBrowseResultPageIDs($result,24,24,60) === range(49,70), 'List Back restoration must respect its own page size.');
formatCheck(tadlBrowseResultPageIDs($result,9,9,999) === range(10,18), 'A missing Back target must remain within one page.');
$fixtures = [];
foreach (['tiles' => 'browse_results_images_html.php', 'list' => 'browse_results_list_html.php'] as $name => $file) {
	$queries = count(FormatDb::$queries);
	$html = (new FormatView(new FormatRequest()))->render($file);
	formatCheck(!str_contains($html, 'SYN.') && str_contains($html, 'tadl-representation-format'), $name.': collection items should have formats in place of identifiers.');
	$document = new DOMDocument(); @$document->loadHTML($html); $xpath = new DOMXPath($document);
	formatCheck($xpath->query('//*[@id="row1"]//ul[@aria-label="File formats"]/li')->length === 6, $name.': public secondary formats must be deduplicated.');
	formatCheck($xpath->query('//*[@id="row1"]//*[contains(@class,"tadl-result-with-formats")]/a[following-sibling::ul]')->length === 1, $name.': the title must precede its format list in reading order.');
	formatCheck($xpath->query('//*[@id="row3"]//ul')->length === 0 && $xpath->query('//*[@id="row3"]//small')->length === 0, $name.': metadata-only items should start with the title.');
	formatCheck($xpath->query('//*[@id="row3"]//*[contains(@class,"tadl-result-with-formats")]')->length === 0, $name.': metadata-only items must retain the full caption width.');
	$inventory = array_filter(array_slice(FormatDb::$queries, $queries), static fn($q) => str_contains($q[0], 'SELECT DISTINCT oxr.object_id'));
	formatCheck(count($inventory) === 1, $name.': one page needs one inventory query.');
	formatCheck(str_contains((new FormatView(new FormatRequest(false)))->render($file), 'tadl-representation-formats'), $name.': ordinary object search/browse must also show formats.');
	formatCheck(!str_contains((new FormatView(new FormatRequest(true,false)))->render($file), 'SYN.1'), $name.': full object Search pages must replace identifiers with formats too.');
	$fixtures[$name] = $html;
	foreach ([999999=>9,1555=>($name === 'tiles' ? 540 : 550)] as $target => $start) {
		$pageSize = $name === 'tiles' ? 18 : 25;
		$bounded = (new FormatView(new FormatRequest(), ['result'=>new FormatResult(range(1000,5999)), 'start'=>9, 'row_id'=>$target]))->render($file);
		$document = new DOMDocument(); @$document->loadHTML($bounded); $xpath = new DOMXPath($document);
		$cards = $xpath->query('//div[starts-with(@id,"row")]');
		formatCheck($cards->length === $pageSize && $cards->item(0)->getAttribute('id') === 'row'.(1000+$start),
			$name.': a distant or missing Back target must render one bounded page, not thousands of cards.');
	}
}
$queries = count(FormatDb::$queries);
$overview = (new FormatView(new FormatRequest(false), ['result' => new FormatResult(), 'itemsPerPage' => 6,
	'block' => 'objects', 'blockInfo' => ['table' => 'ca_objects', 'displayName' => 'Objects'], 'search' => 'synthetic']))
	->render('../Search/tadl_search_results_subview_html.php');
$document = new DOMDocument(); @$document->loadHTML($overview); $xpath = new DOMXPath($document);
formatCheck($xpath->query('//ul[@class="tadl-search-grid"]/li')->length === 6, 'Overview must retain a bounded six-item preview.');
formatCheck($xpath->query('//ul[@class="tadl-search-grid"]/li[1]//ul[@aria-label="File formats"]/li')->length === 6, 'Overview must show each readable original format.');
formatCheck($xpath->query('//ul[@class="tadl-search-grid"]/li[2]//ul')->length === 0, 'Overview metadata-only item must not get an empty badge list.');
$inventory = array_values(array_filter(array_slice(FormatDb::$queries, $queries), static fn($q) => str_contains($q[0], 'SELECT DISTINCT oxr.object_id')));
formatCheck(count($inventory) === 1 && $inventory[0][1][0] === [1,3,4,5,6,7], 'Overview inventory must query only the six displayed objects in one batch.');
$fixtures['overview'] = $overview;
$caption = caDetailLink($request, 'Synthetic lightbox item', '', 'ca_objects', 1);
$representation = '<a href="/Detail/objects/1"><div class="lbItemImg"><img src="/portrait.svg" alt="Synthetic photograph"></div></a>';
$lightbox = (new FormatView($request, ['write_access' => false, 'view' => 'images', 'item_id' => 99, 'set_id' => 42,
	'object_id' => 1, 'caption' => $caption, 'representation_id' => 0, 'representation' => $representation,
	'format_tags' => tadlRepresentationFormatTags(tadlObjectRepresentationFormats($request,[1],[1])[1])]))
	->render('../Lightbox/set_detail_item_html.php');
$lightbox = strtr($lightbox, ['{{{caption}}}' => $caption, '{{{representation}}}' => $representation,
	'{{{item_id}}}' => '99', '{{{commentCount}}}' => '0']);
$document = new DOMDocument(); @$document->loadHTML($lightbox); $xpath = new DOMXPath($document);
formatCheck($xpath->query('//div[contains(@class,"caption")]//li')->length === 6, 'Lightbox caption must include all readable formats.');
formatCheck($xpath->query('//a[@href="/Detail/objects/1"]')->length >= 2 && !str_contains($lightbox,'lbItemDeleteButton'), 'Read-only lightbox native links and controls must be preserved.');
$fixtures['lightbox'] = $lightbox;
if (($argv[1] ?? '') === '--fixtures') { echo json_encode($fixtures, JSON_THROW_ON_ERROR); }
else { echo "Representation formats passed: {$checks} assertions (batched original formats, permissions, paging, Tiles/List, overview and lightbox views).\n"; }

$described = tadlRepresentationFormatTags(['JPEG', 'TIFF'], 'synthetic-formats');
if (!str_contains($described, 'id="synthetic-formats"') || !str_contains($described, '>JPEG<') || !str_contains($described, '>TIFF<')) { throw new RuntimeException('Format descriptions must retain every visible format.'); }
$preview_source = file_get_contents(dirname(__DIR__).'/views/Search/tadl_search_results_subview_html.php');
if (!str_contains($preview_source, '<a class="tadl-search-card-title"') || !str_contains($preview_source, '<div class="tadl-search-card">')) { throw new RuntimeException('Object preview formats must sit outside title and image links.'); }
