<?php
/** Exercise actual CLI launcher with a synthetic bootstrap and JSON-lines detector. */
$temp = sys_get_temp_dir().'/tadl-detector-'.bin2hex(random_bytes(8));
mkdir($temp.'/app/controllers', 0700, true); mkdir($temp.'/models', 0700);
$files = [];
function detectorFixture($path, $contents) { global $files; file_put_contents($path, $contents); $files[] = $path; }
register_shutdown_function(function () use ($temp, &$files) {
	foreach ($files as $file) { if (is_file($file)) { unlink($file); } }
	foreach (glob($temp.'/app/tmp/tadl-thumbnail-faces/*') ?: [] as $file) { unlink($file); }
	foreach (['/app/tmp/tadl-thumbnail-faces','/app/tmp','/app/controllers','/models','/app',''] as $path) { if (is_dir($temp.$path)) { rmdir($temp.$path); } }
});
detectorFixture($temp.'/app/controllers/FrontController.php', '<?php');
detectorFixture($temp.'/models/ca_collections.php', '<?php');
detectorFixture($temp.'/image.jpg', 'synthetic derivative for protocol test');
detectorFixture($temp.'/model.onnx', 'synthetic model for protocol test');
detectorFixture($temp.'/setup.php', <<<'PHP'
<?php
define('__CA_APP_DIR__', __DIR__.'/app'); define('__CA_MODELS_DIR__', __DIR__.'/models');
class ca_collections {
 function __construct(private $id) {}
 function getPrimaryKey() { return $this->id === 12 ? 12 : 0; }
 function get($f) { return $f === 'access' ? 1 : 0; }
 function getDb() { return new DetectorDB(); }
}
class DetectorDB {
 function query($sql, $params) {
  if ($params !== [12] || !str_contains($sql,'oxr.is_primary=1') || !str_contains($sql,'r.deleted=0 AND r.access=1')
   || !str_contains($sql,'o.deleted=0 AND o.access=1') || !str_contains($sql,'c.deleted=0 AND c.access=1')) { throw new RuntimeException('Selection boundaries lost'); }
  return new DetectorRows();
 }
}
class DetectorRows {
 private $i=0;
 function nextRow() { return ++$this->i <= 2; }
 function getMediaInfo($field) {
  return ['_CENTER'=>$this->i===1 ? ['x'=>0.4,'y'=>0.2] : [],
   'original'=>['MD5'=>'synthetic'], 'medium'=>['MAGIC'=>'123','FILENAME'=>'synthetic-medium.jpg','MD5'=>'synthetic-medium','WIDTH'=>320,'HEIGHT'=>400,'MIMETYPE'=>'image/jpeg'],
   'small'=>['MAGIC'=>'123','FILENAME'=>'synthetic-small.jpg','MD5'=>'synthetic-small','WIDTH'=>200,'HEIGHT'=>250,'MIMETYPE'=>'image/jpeg']];
 }
 function getMediaPath($field,$version) { return __DIR__.'/image.jpg'; }
}
PHP);
// Acts as the explicitly selected executable; tests transport/cache behavior without OpenCV.
detectorFixture($temp.'/detector', <<<'JS'
#!/usr/bin/env node
const readline = require('node:readline');
readline.createInterface({input:process.stdin}).on('line',line=>{
 const job=JSON.parse(line);
 process.stdout.write(JSON.stringify({key:job.key,faces:[[0.2,0.1,0.2,0.2]]})+'\n');
});
JS);
chmod($temp.'/detector', 0700);
$assertions = 0;
function checkDetector($condition, $message) { global $assertions; $assertions++; if (!$condition) { throw new RuntimeException($message); } }
function runDetector($arguments) {
	$process = proc_open(array_merge([PHP_BINARY, dirname(__DIR__).'/support/detect-thumbnail-faces.php'], $arguments),
		[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
	fclose($pipes[0]); $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
	fclose($pipes[1]); fclose($pipes[2]);
	return [proc_close($process), json_decode($output,true), $error];
}
$base=['--pawtucket-root='.$temp,'--collection-id=12'];
[$status,$plan,$error]=runDetector($base);
checkDetector($status===0 && $plan['manual']===1 && $plan['pending']===2 && $plan['written']===0, 'Inspection failed: '.$error);
checkDetector(!is_dir($temp.'/app/tmp'), 'Inspection wrote a cache directory');
[$status]=runDetector(array_merge($base,['--apply']));
checkDetector($status===1 && !is_dir($temp.'/app/tmp'), 'Missing runtime caused partial writes');
$apply=array_merge($base,['--apply','--python='.$temp.'/detector','--model='.$temp.'/model.onnx','--limit=1']);
[$status,$plan,$error]=runDetector($apply);
checkDetector($status===0 && $plan['written']===1 && $plan['pending']===1, 'Bounded apply failed: '.$error);
$cache=glob($temp.'/app/tmp/tadl-thumbnail-faces/*.json');
checkDetector(count($cache)===1 && (fileperms($cache[0]) & 0777)===0640, 'Private atomic cache write failed');
checkDetector(json_decode(file_get_contents($cache[0]),true)===['schema'=>1,'faces'=>[[0.2,0.1,0.2,0.2]]], 'Cache format changed');
[$status,$plan,$error]=runDetector($apply);
checkDetector($status===0 && $plan['written']===1 && $plan['cached']===1, 'Second batch did not advance beyond cached derivative: '.$error);
[$status,$plan]=runDetector($apply);
checkDetector($status===0 && $plan['written']===0 && $plan['pending']===0 && $plan['cached']===2, 'Repeat apply was not a no-op');
foreach (['0','12 OR 1=1','-1','999'] as $id) {
	[$status]=runDetector(['--pawtucket-root='.$temp,'--collection-id='.$id]);
	checkDetector($status===1, 'Invalid/missing collection accepted');
}
echo 'Thumbnail detector passed: '.$assertions.' assertions (actual launcher, synthetic native selection, bounded batches, cache writes and no-op repeats).'.PHP_EOL;
