<?php
/** Offline, collection-scoped suggestions. No database, media, or focal-point writes. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$options = getopt('', ['pawtucket-root:', 'collection-id:', 'python:', 'model:', 'limit:', 'apply', 'help']);
if (isset($options['help']) || empty($options['pawtucket-root']) || empty($options['collection-id'])) {
	fwrite(STDOUT, "Usage: php support/detect-thumbnail-faces.php --pawtucket-root=/path/to/pawtucket --collection-id=123 [--limit=100] [--apply --python=/private/venv/bin/python --model=/private/yunet.onnx]\nInspection is read-only; apply writes disposable face-box cache files only.\n");
	exit(isset($options['help']) ? 0 : 1);
}
$process = null; $pipes = [];
try {
	$root = realpath($options['pawtucket-root']);
	$id = filter_var($options['collection-id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
	$limit = filter_var($options['limit'] ?? 100, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
	if (!$root || !is_file($root.'/setup.php') || !is_file($root.'/app/controllers/FrontController.php') || !$id || !$limit) { throw new RuntimeException('Invalid Pawtucket root, collection ID or limit (1–1000).'); }
	chdir($root);
	define('__CA_APP_TYPE__', 'PAWTUCKET'); // Native index.php sets this before bootstrapping.
	require $root.'/setup.php';
	if (__CA_APP_TYPE__ !== 'PAWTUCKET') { throw new RuntimeException('A Pawtucket bootstrap is required.'); }
	require_once __CA_MODELS_DIR__.'/ca_collections.php';
	require_once dirname(__DIR__).'/helpers/thumbnail_focus.php';
	$collection = new ca_collections($id);
	if (!$collection->getPrimaryKey() || $collection->get('deleted') || (int)$collection->get('access') !== 1) {
		throw new RuntimeException('Select an existing public collection.');
	}
	$rows = $collection->getDb()->query("SELECT DISTINCT r.representation_id, r.media
		FROM ca_collections p JOIN ca_collections c ON c.collection_id=p.collection_id OR
		(c.hier_collection_id=p.hier_collection_id AND p.hier_collection_id>0 AND p.hier_left<p.hier_right
		AND c.hier_left>=p.hier_left AND c.hier_right<=p.hier_right)
		JOIN ca_objects_x_collections oc ON oc.collection_id=c.collection_id
		JOIN ca_objects o ON o.object_id=oc.object_id
		JOIN ca_objects_x_object_representations oxr ON oxr.object_id=o.object_id AND oxr.is_primary=1
		JOIN ca_object_representations r ON r.representation_id=oxr.representation_id
		WHERE p.collection_id=? AND c.deleted=0 AND c.access=1 AND o.deleted=0 AND o.access=1 AND r.deleted=0 AND r.access=1
		ORDER BY r.representation_id", [$id]);
	$directory = tadlThumbnailFaceCacheDirectory();
	$jobs = []; $counts = ['manual' => 0, 'cached' => 0, 'pending' => 0, 'written' => 0, 'failed' => 0];
	while ($rows->nextRow() && count($jobs) < $limit) {
		$info = $rows->getMediaInfo('media');
		if (!is_array($info)) { continue; }
		if (tadlThumbnailValidPoint($info['_CENTER'] ?? null)) { $counts['manual']++; continue; }
		foreach (['medium', 'small'] as $version) {
			if (count($jobs) >= $limit) { break; }
			$v = $info[$version] ?? [];
			if (!empty($v['QUEUED']) || !empty($v['USE_ICON']) || ($v['MIMETYPE'] ?? '') !== 'image/jpeg'
				|| empty($v['WIDTH']) || empty($v['HEIGHT']) || max($v['WIDTH'], $v['HEIGHT']) > 2000) { continue; }
			$key = tadlThumbnailFaceCacheKey($info, $version);
			$cache = $directory.'/'.$key.'.json';
			if (is_file($cache) && is_readable($cache) && filesize($cache) <= 65536) {
				$entry = json_decode(file_get_contents($cache), true);
				if (($entry['schema'] ?? null) === 1 && is_array($entry['faces'] ?? null)
					&& (!$entry['faces'] || tadlThumbnailValidFaces($entry['faces']))) { $counts['cached']++; continue; }
			}
			$path = $rows->getMediaPath('media', $version);
			if (!$path || !is_file($path) || !is_readable($path)) { continue; }
			$jobs[$key] = ['key' => $key, 'path' => $path];
		}
	}
	$counts['pending'] = count($jobs);
	if (isset($options['apply']) && $jobs) {
		// Preserve the virtualenv executable path: resolving its symlink loses the venv packages.
		$python = $options['python'] ?? ''; $model = realpath($options['model'] ?? '');
		if (!$python || $python[0] !== '/' || !is_executable($python) || !$model || !is_readable($model)) { throw new RuntimeException('Apply requires an absolute Python executable path and verified YuNet model.'); }
		if (!is_dir($directory) && !mkdir($directory, 0750, true)) { throw new RuntimeException('Cannot create face cache directory.'); }
		$process = proc_open([$python, __DIR__.'/thumbnail-faces/detect.py', '--model', $model],
			[0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => STDERR], $pipes);
		if (!is_resource($process)) { throw new RuntimeException('Cannot start detector.'); }
		stream_set_timeout($pipes[1], 30);
		foreach ($jobs as $job) {
			$line = json_encode($job, JSON_THROW_ON_ERROR).PHP_EOL;
			if (fwrite($pipes[0], $line) !== strlen($line)) { throw new RuntimeException('Cannot send detector job.'); }
			$response = fgets($pipes[1], 65537);
			if (!$response) { throw new RuntimeException('Detector stopped or timed out.'); }
			$data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
			if (($data['key'] ?? '') !== $job['key']) { throw new RuntimeException('Detector returned a different cache key.'); }
			if (isset($data['error'])) { $counts['failed']++; continue; }
			if (!is_array($data['faces'] ?? null) || ($data['faces'] && !tadlThumbnailValidFaces($data['faces']))) { throw new RuntimeException('Invalid detector boxes.'); }
			$json = json_encode(['schema' => 1, 'faces' => $data['faces']], JSON_THROW_ON_ERROR);
			$temp = tempnam($directory, '.faces-');
			try {
				if (!$temp || !chmod($temp, 0640) || file_put_contents($temp, $json) !== strlen($json)
					|| !rename($temp, $directory.'/'.$job['key'].'.json')) { throw new RuntimeException('Cannot save face cache.'); }
			} finally { if ($temp && is_file($temp)) { unlink($temp); } }
			$counts['written']++;
		}
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
}
exit($exitStatus);
