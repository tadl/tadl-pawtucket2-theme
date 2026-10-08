<?php
/** Real focal helper + JS geometry, synthetic media/model/cache boundaries only. */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$temp = sys_get_temp_dir().'/tadl-focus-'.bin2hex(random_bytes(8));
mkdir($temp.'/tmp/tadl-thumbnail-faces', 0700, true);
mkdir($temp.'/durable', 0700);
define('__CA_APP_DIR__', $temp);
class Configuration {
	static function load($path) { return new self(); }
	function get($field) { return __CA_APP_DIR__.'/durable'; }
}
register_shutdown_function(function () use ($temp) {
	foreach (glob($temp.'/durable/*') as $file) { unlink($file); }
	foreach (glob($temp.'/tmp/tadl-thumbnail-faces/*') as $file) { unlink($file); }
	rmdir($temp.'/durable'); rmdir($temp.'/tmp/tadl-thumbnail-faces'); rmdir($temp.'/tmp'); rmdir($temp);
});
$assertions = 0;
function checkFocus($condition, $message) { global $assertions; $assertions++; if (!$condition) { throw new RuntimeException($message); } }
require dirname(__DIR__).'/helpers/thumbnail_focus.php';
class Datamodel {
	static public array $records = [];
	static public int $loads = 0;
	static function getInstanceByTableName($table, $useCache) {
		checkFocus($table === 'ca_object_representations', 'Wrong native model');
		static $cached;
		return $useCache ? ($cached ??= new FocusRepresentation()) : new FocusRepresentation();
	}
}
class FocusRepresentation {
	private array $record = [];
	function load($id) { Datamodel::$loads++; $this->record = Datamodel::$records[$id] ?? []; return (bool)$this->record; }
	function get($field) { return $this->record[$field] ?? 0; }
	function getMediaUrl($field, $version) { return $this->record['url'][$version] ?? ''; }
	function getMediaInfo($field) { return $this->record['info']; }
}
$info = ['original' => ['WIDTH' => 800, 'HEIGHT' => 1000, 'MD5' => 'synthetic-original'],
	'medium' => ['WIDTH' => 320, 'HEIGHT' => 400, 'FILENAME' => 'ca_object_representations_media_42_medium.jpg', 'MAGIC' => '123', 'MD5' => 'synthetic-medium']];
$directory = tadlThumbnailFaceCacheDirectory();
$key = tadlThumbnailFaceCacheKey($info, 'medium');
checkFocus(tadlThumbnailFocusData($info, 'medium', $directory) === null, 'Empty cache must keep default cover');
file_put_contents($directory.'/'.$key.'.json', json_encode(['schema' => 1, 'faces' => [[0.2, 0.1, 0.2, 0.25], [0.6, 0.12, 0.15, 0.2]]]));
checkFocus(count(tadlThumbnailFocusData($info, 'medium', $directory)['faces']) === 2, 'Group face boxes lost');
$legacy = tadlThumbnailLegacyFaceCacheDirectory();
rename($directory.'/'.$key.'.json', $legacy.'/'.$key.'.json');
checkFocus(count(tadlThumbnailFocusData($info, 'medium', $directory)['faces']) === 2, 'Legacy suggestions disappeared before migration');
copy($legacy.'/'.$key.'.json', $directory.'/'.$key.'.json');
unlink($legacy.'/'.$key.'.json');
checkFocus(count(tadlThumbnailFocusData($info, 'medium', $directory)['faces']) === 2, 'Durable suggestions depend on temporary cache');
file_put_contents($directory.'/'.$key.'.json', json_encode(['schema'=>1,'faces'=>[]]));
file_put_contents($legacy.'/'.$key.'.json', json_encode(['schema'=>1,'faces'=>[[0.2,0.1,0.2,0.2]]]));
checkFocus(tadlThumbnailFocusData($info, 'medium', $directory) === null, 'Valid durable no-face result was overridden by legacy cache');
unlink($legacy.'/'.$key.'.json');
file_put_contents($directory.'/'.$key.'.json', json_encode(['schema' => 1, 'faces' => [[0.2, 0.1, 0.2, 0.25], [0.6, 0.12, 0.15, 0.2]]]));
foreach ([['x' => 0.42, 'y' => 0.2], ['x' => 0.5, 'y' => 0.5], ['x' => 0, 'y' => 1]] as $point) {
	$manualInfo = $info + ['_CENTER' => $point];
	checkFocus(tadlThumbnailFocusData($manualInfo, 'medium', $directory) === ['point' => tadlThumbnailValidPoint($point), 'source' => 'manual'], 'Manual point must override cached faces, including center/edges');
}
$cropped = $info; $cropped['_CENTER'] = ['x' => 0.4, 'y' => 0.2]; $cropped['medium']['HEIGHT'] = 320;
checkFocus(tadlThumbnailFocusData($cropped, 'medium', $directory) === null, 'Original coordinates must not be applied to a cropped derivative');
$changed = $info; $changed['original']['MD5'] = 'replacement';
checkFocus(tadlThumbnailFaceCacheKey($changed, 'medium') !== $key && tadlThumbnailFocusData($changed, 'medium', $directory) === null, 'Replacement media must invalidate face suggestions');
foreach ([['x' => -1, 'y' => 0.2], ['x' => '0%;color:red', 'y' => 0.2], ['x' => NAN, 'y' => 0.2], ['x' => 0.3], null] as $point) {
	checkFocus(tadlThumbnailValidPoint($point) === null, 'Unsafe point accepted');
}
foreach ([[[0, 0, 2, 0.2]], [[0.9, 0, 0.2, 0.2]], [['bad', 0, 0.1, 0.1]], [[0, 0, 0, 0.1]], null] as $faces) {
	checkFocus(tadlThumbnailValidFaces($faces) === [], 'Unsafe face boxes accepted');
}
file_put_contents($directory.'/'.$key.'.json', '{invalid');
checkFocus(tadlThumbnailFocusData($info, 'medium', $directory) === null, 'Corrupt cache must not break rendering');
$url = '/media/images/123_ca_object_representations_media_42_medium.jpg';
Datamodel::$records[42] = ['info' => $info + ['_CENTER' => ['x' => 0.4, 'y' => 0.2]], 'url' => ['medium' => $url]];
$html = '<a href="/Detail/objects/7"><img src="'.$url.'" alt="Synthetic &amp; portrait" loading="lazy" style="border:0;object-position:50% 50%"></a>';
$out = tadlFocusThumbnail($html);
checkFocus(str_contains($out, 'data-tadl-focus=') && str_contains($out, 'object-position:40% 20%'), 'Manual metadata did not reach native tag');
checkFocus(str_contains($out, '<a href="/Detail/objects/7">') && str_contains($out, 'loading="lazy"') && str_contains($out, 'Synthetic &amp; portrait'), 'Native link/alt/lazy attributes changed');
tadlFocusThumbnail($html);
checkFocus(Datamodel::$loads === 1, 'Same representation loaded more than once in a render');
Datamodel::$records[43] = ['info' => $info + ['_CENTER' => ['x' => 0.8, 'y' => 0.6]], 'url' => ['medium' => str_replace('_42_', '_43_', $url)]];
checkFocus(str_contains(tadlFocusThumbnail(str_replace('_42_', '_43_', $html)), 'object-position:80% 60%'), 'Second focal record must retain its own center');
checkFocus(str_contains(tadlFocusThumbnail($html), 'object-position:40% 20%'), 'A -> B -> A must not overwrite the first cached focal record');
$stale = str_replace('123_', '999_', $html);
checkFocus(tadlFocusThumbnail($stale) === $stale, 'Metadata attached to a stale/different derivative');
foreach (['<div class="placeholder">Image</div>', '<img src="https://example.org/unrelated.jpg">', '', null] as $html) {
	checkFocus(tadlFocusThumbnail($html) === $html, 'Unrelated image/placeholder changed');
}

$js = <<<'JS'
const assert = require('node:assert/strict');
const {cropPosition} = require(process.argv[1]);
let count = 0;
function check(condition) { assert.ok(condition); count++; }
const point = {point:{x:0.45,y:0.22}};
const crop = cropPosition(332,400,468,210,point);
const visible = 210/(400*(468/332));
check(crop[0]===50);
check(cropPosition(332,400,382,210,point)[0]===50); // no division by rounding noise on the fitted axis
check(Math.abs((1-visible)*crop[1]/100+visible/2-0.22)<1e-10); // true focal centering, not percentage guess
check(crop[1]<15); // head rather than torso
check(cropPosition(332,400,240,210,point)[1]!==crop[1]); // responsive recalculation
check(cropPosition(100,100,200,200,point).every(v=>v===50));
check(cropPosition(400,200,100,200,{point:{x:1,y:0}})[0]===100);
check(cropPosition(400,200,100,200,{point:{x:0,y:1}})[0]===0);
check(cropPosition(0,400,468,210,point)===null); // image not loaded
check(cropPosition(332,400,0,210,point)===null); // hidden view
check(cropPosition(332,400,468,210,{point:{x:NaN,y:0.2}})===null);
check(cropPosition(332,400,468,210,null)===null);
const faces = [[0.1,0.08,0.2,0.12],[0.65,0.09,0.2,0.12]];
const group = cropPosition(332,400,468,210,{faces});
const start = group[1]/100*(1-visible);
check(start<=0.08 && start+visible>=0.21); // retain both faces when they fit
check(cropPosition(332,400,468,210,{...point,faces})[1]===crop[1]); // manual wins
check(cropPosition(332,400,468,210,{faces:[[0,0,1,1]]}).every(Number.isFinite)); // impossible group stays cover
process.stdout.write(String(count));
JS;
$process = proc_open(['node', '-e', $js, dirname(__DIR__).'/assets/pawtucket/js/thumbnail-focus.js'],
	[0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
fclose($pipes[0]); $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
checkFocus(proc_close($process) === 0, 'Crop geometry failed: '.$stderr);
echo 'Thumbnail focus passed: '.($assertions + (int)$stdout).' assertions (native tags, manual priority, cache invalidation, cover geometry).'.PHP_EOL;
