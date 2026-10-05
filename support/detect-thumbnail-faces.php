<?php
/** Offline suggestions. No database, media, or staff focal-point writes. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$options = getopt('', ['pawtucket-root:', 'collection-id:', 'all', 'python:', 'model:', 'limit:', 'apply', 'help']);
if (isset($options['help']) || (!isset($options['all']) && !isset($options['collection-id']))) {
	fwrite(STDOUT, "Usage: php support/detect-thumbnail-faces.php (--all | --collection-id=123) [--pawtucket-root=/path/to/pawtucket] [--limit=100] [--apply] [--python=/opt/tadl-thumbnail-faces/venv/bin/python --model=/opt/tadl-thumbnail-faces/yunet.onnx]\nWithout --apply, inspect only. --all scans every public attached image in one invocation unless --limit is supplied. Collection mode defaults to 100 uncached thumbnails.\n");
	exit(isset($options['help']) ? 0 : 1);
}

/** Serialize maintenance writers; web requests never acquire this lock. */
function tadlDetectorCacheLock($directory, &$lock) {
	if ($lock) { return; }
	if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
		throw new RuntimeException('Cannot create durable face cache directory; provision its ownership first.');
	}
	$lock = fopen($directory.'/.maintenance.lock', 'c');
	if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { throw new RuntimeException('Another detection run holds the cache lock, or the cache is not writable.'); }
}

function tadlDetectorWriteCache($directory, $key, array $data) {
	$json = json_encode($data, JSON_THROW_ON_ERROR);
	$temp = tempnam($directory, '.faces-');
	try {
		if (!$temp || !chmod($temp, 0640) || file_put_contents($temp, $json) !== strlen($json)
			|| !rename($temp, $directory.'/'.$key.'.json')) { throw new RuntimeException('Cannot save face cache.'); }
	} finally { if ($temp && is_file($temp)) { unlink($temp); } }
}

$process = null; $pipes = []; $lock = null;
try {
	$all = isset($options['all']);
	if ($all && isset($options['collection-id'])) { throw new RuntimeException('Choose --all or --collection-id, not both.'); }
	$root = realpath($options['pawtucket-root'] ?? dirname(__DIR__, 3));
	$id = $all ? null : filter_var($options['collection-id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
	$limit = isset($options['limit']) ? filter_var($options['limit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]) : ($all ? null : 100);
	if (!$root || !is_file($root.'/setup.php') || !is_file($root.'/app/controllers/FrontController.php') || (!$all && !$id) || $limit === false) {
		throw new RuntimeException('Invalid Pawtucket root, collection ID or limit (1–1000).');
	}
	chdir($root);
	define('__CA_APP_TYPE__', 'PAWTUCKET'); // Native index.php sets this before bootstrapping.
	require $root.'/setup.php';
	if (__CA_APP_TYPE__ !== 'PAWTUCKET') { throw new RuntimeException('A Pawtucket bootstrap is required.'); }
	require_once __CA_MODELS_DIR__.'/ca_object_representations.php';
	require_once dirname(__DIR__).'/helpers/thumbnail_focus.php';
	$db = (new ca_object_representations())->getDb();
	$scope = ''; $scopeParams = [];
	if (!$all) {
		require_once __CA_MODELS_DIR__.'/ca_collections.php';
		$collection = new ca_collections($id);
		if (!$collection->getPrimaryKey() || $collection->get('deleted') || (int)$collection->get('access') !== 1) {
			throw new RuntimeException('Select an existing public collection.');
		}
		// Preserve collection mode's primary-only selection and public descendants.
		$scope = "AND oxr.is_primary=1 AND EXISTS (
			SELECT 1 FROM ca_objects_x_collections oc JOIN ca_collections c ON c.collection_id=oc.collection_id
			JOIN ca_collections p ON p.collection_id=?
			WHERE oc.object_id=o.object_id AND c.deleted=0 AND c.access=1 AND (c.collection_id=p.collection_id OR
			(c.hier_collection_id=p.hier_collection_id AND p.hier_collection_id>0 AND p.hier_left<p.hier_right
			AND c.hier_left>=p.hier_left AND c.hier_right<=p.hier_right)))";
		$scopeParams = [$id];
	}
	$directory = tadlThumbnailFaceCacheDirectory();
	if (!$directory) { throw new RuntimeException('Configure an absolute durable face_cache_directory in thumbnail_focus.conf.'); }
	$legacy = tadlThumbnailLegacyFaceCacheDirectory();
	$apply = isset($options['apply']);
	$counts = ['manual' => 0, 'cached' => 0, 'pending' => 0, 'written' => 0, 'failed' => 0, 'migrated' => 0, 'complete' => false];
	$cursor = 0; $stopped = false; $lastProgress = 0;
	while (!$stopped) {
		// Keyset pages bound metadata in memory; EXISTS deduplicates shared representations.
		$rows = $db->query("SELECT r.representation_id, r.media FROM ca_object_representations r
			WHERE r.representation_id>? AND r.deleted=0 AND r.access=1 AND EXISTS (
			SELECT 1 FROM ca_objects_x_object_representations oxr JOIN ca_objects o ON o.object_id=oxr.object_id
			WHERE oxr.representation_id=r.representation_id AND o.deleted=0 AND o.access=1 $scope)
			ORDER BY r.representation_id LIMIT 250", array_merge([$cursor], $scopeParams));
		$pageRows = 0;
		while ($rows->nextRow()) {
			$cursor = (int)$rows->get('representation_id'); $pageRows++;
			$info = $rows->getMediaInfo('media');
			if (!is_array($info)) { continue; }
			if (tadlThumbnailValidPoint($info['_CENTER'] ?? null)) { $counts['manual']++; continue; }
			// Do not scan video frames, audio/PDF previews or icons as image representations.
			if (!str_starts_with($info['original']['MIMETYPE'] ?? '', 'image/')) { continue; }
			foreach (['medium', 'small'] as $version) {
				$v = $info[$version] ?? [];
				if (!empty($v['QUEUED']) || !empty($v['USE_ICON']) || ($v['MIMETYPE'] ?? '') !== 'image/jpeg'
					|| ($v['WIDTH'] ?? 0) <= 0 || ($v['HEIGHT'] ?? 0) <= 0 || max($v['WIDTH'], $v['HEIGHT']) > 2000) { continue; }
				$key = tadlThumbnailFaceCacheKey($info, $version);
				$entry = tadlThumbnailReadFaceCache($directory, $key);
				if ($entry !== null) { $counts['cached']++; continue; }
				$entry = $legacy !== $directory ? tadlThumbnailReadFaceCache($legacy, $key) : null;
				if ($entry !== null) {
					$counts['cached']++;
					if ($apply) {
						tadlDetectorCacheLock($directory, $lock);
						tadlDetectorWriteCache($directory, $key, $entry); $counts['migrated']++;
					}
				} else {
					$path = $rows->getMediaPath('media', $version);
					if (!$path || !is_file($path) || !is_readable($path)) { continue; }
					$counts['pending']++;
					if ($apply) {
						if (!$process) {
							// Preserve the venv executable path; realpath would lose its packages.
							$python = $options['python'] ?? '/opt/tadl-thumbnail-faces/venv/bin/python';
							$model = realpath($options['model'] ?? '/opt/tadl-thumbnail-faces/yunet.onnx');
							if (!$python || $python[0] !== '/' || !is_executable($python) || !$model || !is_readable($model)) { throw new RuntimeException('Apply requires an absolute Python executable path and verified YuNet model.'); }
							tadlDetectorCacheLock($directory, $lock);
							$process = proc_open([$python, __DIR__.'/thumbnail-faces/detect.py', '--model', $model], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => STDERR], $pipes);
							if (!is_resource($process)) { throw new RuntimeException('Cannot start detector.'); }
							stream_set_timeout($pipes[1], 30);
						}
						$line = json_encode(['key' => $key, 'path' => $path], JSON_THROW_ON_ERROR).PHP_EOL;
						if (fwrite($pipes[0], $line) !== strlen($line)) { throw new RuntimeException('Cannot send detector job.'); }
						$response = fgets($pipes[1], 65537);
						if (!$response) { throw new RuntimeException('Detector stopped or timed out.'); }
						$data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
						if (($data['key'] ?? '') !== $key) { throw new RuntimeException('Detector returned a different cache key.'); }
						if (isset($data['error'])) { $counts['failed']++; }
						else {
							if (!is_array($data['faces'] ?? null) || ($data['faces'] && !tadlThumbnailValidFaces($data['faces']))) { throw new RuntimeException('Invalid detector boxes.'); }
							tadlDetectorWriteCache($directory, $key, ['schema' => 1, 'faces' => $data['faces']]); $counts['written']++;
						}
					}
				}
				$processed = $counts['written'] + $counts['failed'] + $counts['migrated'];
				if ($apply && $processed > $lastProgress && $processed % 100 === 0) {
					fwrite(STDERR, 'Progress: '.json_encode($counts, JSON_THROW_ON_ERROR).PHP_EOL); $lastProgress = $processed;
				}
				if ($limit !== null && $counts['pending'] >= $limit) { $stopped = true; break; }
			}
			if ($stopped) { break; }
		}
		if (!$pageRows) { $counts['complete'] = true; break; }
	}
	if ($process) {
		fclose($pipes[0]); fclose($pipes[1]); $pipes = [];
		$status = proc_close($process); $process = null;
		if ($status !== 0) { throw new RuntimeException('Detector exited unsuccessfully.'); }
	}
	fwrite(STDOUT, json_encode($counts, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL);
	$exitStatus = $counts['failed'] ? 1 : 0;
} catch (Throwable $error) {
	fwrite(STDERR, 'Thumbnail detection failed: '.$error->getMessage().PHP_EOL);
	$exitStatus = 1;
} finally {
	foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
	if (is_resource($process)) { proc_terminate($process); proc_close($process); }
	if (is_resource($lock)) { fclose($lock); }
}
exit($exitStatus);
