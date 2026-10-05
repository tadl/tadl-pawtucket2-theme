<?php
/** Actual maintenance CLI against a synthetic SQL catalogue and JSON-lines detector. */
error_reporting(E_ALL);
$temp = sys_get_temp_dir().'/tadl-detector-'.bin2hex(random_bytes(8));
mkdir($temp.'/app/controllers', 0700, true); mkdir($temp.'/models', 0700);
$files = [];
function detectorFixture($path, $contents) { global $files; file_put_contents($path, $contents); $files[] = $path; }
register_shutdown_function(function () use ($temp, &$files) {
	foreach (['/durable','/app/tmp/tadl-thumbnail-faces'] as $directory) {
		foreach (glob($temp.$directory.'/{*,.*}', GLOB_BRACE) ?: [] as $file) { if (is_file($file)) { unlink($file); } }
	}
	foreach ($files as $file) { if (is_file($file)) { unlink($file); } }
	foreach (['/durable','/app/tmp/tadl-thumbnail-faces','/app/tmp','/app/controllers','/models','/app',''] as $path) { if (is_dir($temp.$path)) { rmdir($temp.$path); } }
});
detectorFixture($temp.'/app/controllers/FrontController.php', '<?php');
detectorFixture($temp.'/models/ca_collections.php', '<?php');
detectorFixture($temp.'/models/ca_object_representations.php', '<?php');
detectorFixture($temp.'/image.jpg', 'synthetic derivative for protocol test');
detectorFixture($temp.'/model.onnx', 'synthetic model for protocol test');
detectorFixture($temp.'/catalogue.sqlite', '');
$db = new PDO('sqlite:'.$temp.'/catalogue.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach ([
 'CREATE TABLE ca_collections (collection_id INTEGER PRIMARY KEY, deleted INTEGER, access INTEGER, hier_collection_id INTEGER, hier_left INTEGER, hier_right INTEGER)',
 'CREATE TABLE ca_objects (object_id INTEGER PRIMARY KEY, deleted INTEGER, access INTEGER)',
 'CREATE TABLE ca_object_representations (representation_id INTEGER PRIMARY KEY, deleted INTEGER, access INTEGER, media TEXT)',
 'CREATE TABLE ca_objects_x_collections (object_id INTEGER, collection_id INTEGER)',
 'CREATE TABLE ca_objects_x_object_representations (object_id INTEGER, representation_id INTEGER, is_primary INTEGER)',
 'INSERT INTO ca_collections VALUES (12,0,1,12,1,10),(13,0,1,12,2,3),(14,0,0,12,4,5),(15,0,1,15,1,2),(16,1,1,12,6,7)'
] as $sql) { $db->exec($sql); }
function detectorMedia($id, $mime='image/tiff') {
 return ['_CENTER'=>[], 'original'=>['MD5'=>'synthetic-'.$id,'MIMETYPE'=>$mime],
  'medium'=>['MAGIC'=>'123','FILENAME'=>'synthetic-'.$id.'-medium.jpg','MD5'=>'synthetic-'.$id.'-medium','WIDTH'=>320,'HEIGHT'=>400,'MIMETYPE'=>'image/jpeg'],
  'small'=>['MAGIC'=>'123','FILENAME'=>'synthetic-'.$id.'-small.jpg','MD5'=>'synthetic-'.$id.'-small','WIDTH'=>200,'HEIGHT'=>250,'MIMETYPE'=>'image/jpeg']];
}
function detectorRecord($id, $collection=null, $primary=1, $objectAccess=1, $objectDeleted=0, $repAccess=1, $repDeleted=0, $info=null, $attached=true) {
 global $db;
 $db->prepare('INSERT INTO ca_objects VALUES (?,?,?)')->execute([$id,$objectDeleted,$objectAccess]);
 $db->prepare('INSERT INTO ca_object_representations VALUES (?,?,?,?)')->execute([$id,$repDeleted,$repAccess,json_encode($info ?? detectorMedia($id))]);
 if ($attached) { $db->prepare('INSERT INTO ca_objects_x_object_representations VALUES (?,?,?)')->execute([$id,$id,$primary]); }
 if ($collection) { $db->prepare('INSERT INTO ca_objects_x_collections VALUES (?,?)')->execute([$id,$collection]); }
}
$manual=detectorMedia(1); $manual['_CENTER']=['x'=>0.4,'y'=>0.2];
detectorRecord(1,12,info:$manual); detectorRecord(2,12);
detectorFixture($temp.'/setup.php', <<<'BOOT'
<?php
define('__CA_APP_DIR__', __DIR__.'/app'); define('__CA_MODELS_DIR__', __DIR__.'/models');
class Configuration {
 static function load($path) { return new self(); }
 function get($field) { return __DIR__.'/durable'; }
}
class ca_collections {
 private $row;
 function __construct($id) { $stmt=(new DetectorDB())->db->prepare('SELECT * FROM ca_collections WHERE collection_id=?'); $stmt->execute([$id]); $this->row=$stmt->fetch(PDO::FETCH_ASSOC); }
 function getPrimaryKey() { return $this->row['collection_id'] ?? 0; }
 function get($field) { return $this->row[$field] ?? null; }
}
class ca_object_representations { function getDb() { return new DetectorDB(); } }
class DetectorDB {
 public $db;
 function __construct() { $this->db=new PDO('sqlite:'.__DIR__.'/catalogue.sqlite'); $this->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); }
 function query($sql,$params) { $stmt=$this->db->prepare($sql); $stmt->execute($params); return new DetectorRows($stmt); }
}
class DetectorRows {
 private $row;
 function __construct(private $stmt) {}
 function nextRow() { $this->row=$this->stmt->fetch(PDO::FETCH_ASSOC); return (bool)$this->row; }
 function get($field) { return $this->row[$field]; }
 function getMediaInfo($field) { return json_decode($this->row[$field],true); }
 function getMediaPath($field,$version) { return __DIR__.'/image.jpg'; }
}
BOOT);
// Explicit executable verifies transport/cache behavior without installing OpenCV for these tests.
detectorFixture($temp.'/detector', <<<'JS'
#!/usr/bin/env node
const fs = require('node:fs');
const path = require('node:path');
const readline = require('node:readline');
readline.createInterface({input:process.stdin}).on('line',line=>{
 const job=JSON.parse(line), root=path.dirname(process.argv[1]);
 const response={key:job.key};
 if(fs.existsSync(path.join(root,'fail'))) response.error='Synthetic failure';
 else response.faces=fs.existsSync(path.join(root,'no-faces'))?[]:[[0.2,0.1,0.2,0.2]];
 process.stdout.write(JSON.stringify(response)+'\n');
});
JS);
chmod($temp.'/detector', 0700);
$assertions = 0;
function checkDetector($condition, $message) { global $assertions; $assertions++; if (!$condition) { throw new RuntimeException($message); } }
function runDetector($arguments) {
 $process = proc_open(array_merge([PHP_BINARY, dirname(__DIR__).'/support/detect-thumbnail-faces.php'], $arguments), [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
 fclose($pipes[0]); $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
 return [proc_close($process), json_decode($output,true), $error];
}
$base=['--pawtucket-root='.$temp,'--collection-id=12'];
[$status,$plan,$error]=runDetector($base);
checkDetector($status===0 && $plan['manual']===1 && $plan['pending']===2 && $plan['written']===0 && $plan['complete'], 'Inspection failed: '.$error);
checkDetector(!is_dir($temp.'/durable'), 'Inspection wrote a cache directory');
[$status]=runDetector(array_merge($base,['--apply','--python='.$temp.'/missing','--model='.$temp.'/model.onnx']));
checkDetector($status===1 && !is_dir($temp.'/durable'), 'Missing runtime caused partial writes');
$runtime=['--apply','--python='.$temp.'/detector','--model='.$temp.'/model.onnx'];
$apply=array_merge($base,$runtime,['--limit=1']);
[$status,$plan,$error]=runDetector($apply);
checkDetector($status===0 && $plan['written']===1 && $plan['pending']===1 && !$plan['complete'], 'Bounded apply failed: '.$error);
$cache=glob($temp.'/durable/*.json');
checkDetector(count($cache)===1 && (fileperms($cache[0]) & 0777)===0640, 'Private atomic cache write failed');
checkDetector(json_decode(file_get_contents($cache[0]),true)===['schema'=>1,'faces'=>[[0.2,0.1,0.2,0.2]]], 'Cache format changed');
[$status,$plan,$error]=runDetector($apply);
checkDetector($status===0 && $plan['written']===1 && $plan['cached']===1, 'Second batch did not advance: '.$error);
[$status,$plan]=runDetector($apply);
checkDetector($status===0 && $plan['written']===0 && $plan['pending']===0 && $plan['cached']===2 && $plan['complete'], 'Repeat apply was not a no-op');
foreach (['0','12 OR 1=1','-1','999','14','16'] as $id) {
 [$status]=runDetector(['--pawtucket-root='.$temp,'--collection-id='.$id]);
 checkDetector($status===1, 'Invalid/missing/private/deleted collection accepted');
}
foreach ([['--all','--collection-id=12'],['--all','--limit=0'],['--all','--limit=1001'],[]] as $args) {
 [$status]=runDetector(array_merge(['--pawtucket-root='.$temp],$args));
 checkDetector($status===1, 'Invalid/missing scope or limit accepted');
}
// SQL fixtures exercise public/deleted owners and media, descendants, sharing and nonprimary/uncollected media.
detectorRecord(3,12,primary:0); detectorRecord(4); detectorRecord(5,13);
detectorRecord(6,12,objectAccess:0); detectorRecord(7,12,objectDeleted:1);
detectorRecord(8,12,repAccess:0); detectorRecord(9,12,repDeleted:1);
detectorRecord(10,15); detectorRecord(11,14); detectorRecord(12,16);
foreach ([13=>'video/mp4',14=>'audio/mpeg',15=>'application/pdf'] as $id=>$mime) { detectorRecord($id,12,info:detectorMedia($id,$mime)); }
detectorRecord(16,12,attached:false);
$db->exec('INSERT INTO ca_objects_x_object_representations VALUES (4,2,0)');
$skipped=detectorMedia(17); $skipped['medium']['QUEUED']=1; $skipped['small']['USE_ICON']=1;
detectorRecord(17,12,info:$skipped);
$oversize=detectorMedia(18); $oversize['medium']['WIDTH']=2001; $oversize['small']['MIMETYPE']='image/png';
detectorRecord(18,12,info:$oversize);
[$status,$plan,$error]=runDetector($base);
checkDetector($status===0 && $plan['pending']===2 && $plan['cached']===2, 'Collection boundaries changed: '.$error);
$all=['--pawtucket-root='.$temp,'--all'];
[$status,$plan,$error]=runDetector($all);
checkDetector($status===0 && $plan['pending']===12 && $plan['cached']===2 && $plan['manual']===1, 'Global public image selection failed: '.$error);
// Two legacy caches (including no faces) migrate with no inference, without removing originals.
require dirname(__DIR__).'/helpers/thumbnail_focus.php';
mkdir($temp.'/app/tmp/tadl-thumbnail-faces',0700,true);
$legacy=$temp.'/app/tmp/tadl-thumbnail-faces';
foreach (['medium'=>[[0.2,0.1,0.2,0.2]],'small'=>[]] as $version=>$faces) {
 file_put_contents($legacy.'/'.tadlThumbnailFaceCacheKey(detectorMedia(3),$version).'.json',json_encode(['schema'=>1,'faces'=>$faces]));
}
[$status,$plan]=runDetector($all);
checkDetector($status===0 && $plan['cached']===4 && $plan['pending']===10 && $plan['migrated']===0, 'Legacy read-only inspection failed');
checkDetector(count(glob($temp.'/durable/*.json'))===2, 'Inspection migrated caches');
detectorFixture($temp.'/no-faces','');
[$status,$plan,$error]=runDetector(array_merge($all,$runtime));
checkDetector($status===0 && $plan['written']===10 && $plan['migrated']===2 && $plan['complete'], 'Global apply/migration failed: '.$error);
checkDetector(count(glob($legacy.'/*.json'))===2 && count(glob($temp.'/durable/*.json'))===14, 'Migration removed sources or duplicated shared images');
foreach (glob($legacy.'/*.json') as $file) { unlink($file); }
rmdir($legacy); rmdir($temp.'/app/tmp');
[$status,$plan]=runDetector(array_merge($all,$runtime));
checkDetector($status===0 && $plan['cached']===14 && $plan['written']===0 && $plan['migrated']===0, 'Suggestions did not survive app/tmp purge (including no-face caches)');
// More than one database page must complete automatically with bounded memory.
for ($id=100;$id<360;$id++) {
 $media=detectorMedia($id); $media['small']['USE_ICON']=1;
 detectorRecord($id,15,info:$media);
}
[$status,$plan,$error]=runDetector(array_merge($all,$runtime));
checkDetector($status===0 && $plan['written']===260 && $plan['cached']===14 && $plan['complete'], 'Global scan failed across keyset pages: '.$error);
checkDetector(str_contains($error,'Progress:'), 'Long run did not report progress');
// A lock conflict refuses new writes; failures remain retryable and never become no-face results.
detectorRecord(400,15);
$lock=fopen($temp.'/durable/.maintenance.lock','c'); flock($lock,LOCK_EX);
[$status,$plan,$error]=runDetector(array_merge($all,$runtime));
checkDetector($status===1 && str_contains($error,'cache lock'), 'Concurrent writer was allowed');
fclose($lock);
detectorFixture($temp.'/fail','');
[$status,$plan]=runDetector(array_merge($all,$runtime));
checkDetector($status===1 && $plan['failed']===2 && $plan['written']===0 && $plan['complete'], 'Detector failures were hidden');
unlink($temp.'/fail');
[$status,$plan]=runDetector(array_merge($all,$runtime));
checkDetector($status===0 && $plan['written']===2, 'Failed detections were cached rather than retried');
echo 'Thumbnail detector passed: '.$assertions.' assertions (SQL selection, global keyset scan, migration/purge survival, limits, locking and retries).'.PHP_EOL;
