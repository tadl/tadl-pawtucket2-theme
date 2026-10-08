<?php
require_once(__DIR__.'/image_downloads.php');

/** Reuse private exports only after the controller has reauthorized the source. */
function tadlCachedImageDownload(array $source, $format, $directory = null, $ttl = 604800) {
	// Originals never need a converted cache entry.
	if ($format === 'tiff' || ($format === 'jpg' && $source['mime'] === 'image/jpeg')) {
		return tadlPrepareImageDownload($source, $format);
	}
	if (!in_array($format, ['jpg', 'pdf'], true)) { return null; }
	$directory = $directory ?: sys_get_temp_dir().'/tadl-image-downloads-'.(function_exists('posix_geteuid') ? posix_geteuid() : substr(hash('sha256', __DIR__), 0, 12));
	// Shared exports must never live under a publicly served directory. The
	// default is OS temp storage; a configured path must be outside the app root.
	$root = defined('__CA_BASE_DIR__') ? realpath(__CA_BASE_DIR__) : false;
	if (!is_string($directory) || !str_starts_with($directory, '/') || is_link($directory)
		|| ($root && (str_starts_with(rtrim($directory, '/').'/', $root.'/')))) { return null; }
	if (!is_dir($directory) && !@mkdir($directory, 0700, true)) { return null; }
	$resolved = realpath($directory);
	if (!$resolved || ($root && str_starts_with($resolved.'/', $root.'/'))
		|| (fileperms($resolved) & 0077) || !is_writable($resolved)
		|| (function_exists('posix_geteuid') && fileowner($resolved) !== posix_geteuid())) { return null; }
	$ttl = max(60, min(2592000, (int)$ttl));
	$stat = stat($source['path']);
	$key = hash('sha256', json_encode(['full-image-v1', realpath($source['path']), $stat['mtime'], $stat['ctime'], $stat['size'], $source['checksum'] ?? '', $format]));
	$path = $resolved.'/'.$key.'.'.$format;
	$lockPath = $resolved.'/'.$key.'.lock';
	if (is_link($path) || is_link($lockPath)) { return null; }
	$lock = fopen($lockPath, 'c');
	if (!$lock) { return null; }
	chmod($lockPath, 0600);
	$deadline = microtime(true) + 3;
	try {
		while (!flock($lock, LOCK_EX | LOCK_NB)) {
			if (microtime(true) >= $deadline) { return null; }
			usleep(50000);
		}
		clearstatcache(true, $path);
		if (is_file($path) && filemtime($path) >= time() - $ttl && tadlValidCachedImageDownload($path, $format, $source)) {
			touch($path);
			return ['path' => $path, 'mime' => $format === 'pdf' ? 'application/pdf' : 'image/jpeg', 'extension' => $format, 'temporary' => false];
		}
		$download = tadlPrepareImageDownload($source, $format);
		if (!$download || !tadlValidCachedImageDownload($download['path'], $format, $source)) { return null; }
		$staging = tempnam($resolved, 'export-');
		try {
			if (!$staging || !copy($download['path'], $staging) || !chmod($staging, 0600) || !rename($staging, $path)) { return null; }
		} finally { if ($staging && is_file($staging)) { unlink($staging); } }
		return ['path' => $path, 'mime' => $download['mime'], 'extension' => $format, 'temporary' => false];
	} finally { flock($lock, LOCK_UN); fclose($lock); }
}

function tadlValidCachedImageDownload($path, $format, array $source) {
	if (!is_file($path) || !is_readable($path) || is_link($path) || filesize($path) === 0) { return false; }
	$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
	if ($format === 'jpg') {
		$size = @getimagesize($path);
		return $mime === 'image/jpeg' && $size && $size[2] === IMAGETYPE_JPEG
			&& (empty($source['info']['WIDTH']) || (int)$source['info']['WIDTH'] === $size[0])
			&& (empty($source['info']['HEIGHT']) || (int)$source['info']['HEIGHT'] === $size[1]);
	}
	if ($mime !== 'application/pdf') { return false; }
	$stream = fopen($path, 'rb');
	if (!$stream) { return false; }
	try {
		$head = fread($stream, 5); fseek($stream, -min(32, filesize($path)), SEEK_END);
		return $head === '%PDF-' && str_ends_with(rtrim(stream_get_contents($stream)), '%%EOF');
	} finally { fclose($stream); }
}

/** Expire unused exports at most hourly; skip any file with an active download lock. */
function tadlPruneImageDownloads($directory, $ttl = 604800) {
	if (!is_string($directory) || !is_dir($directory) || is_link($directory)) { return; }
	$stamp = $directory.'/.pruned';
	if (is_link($stamp) || (is_file($stamp) && filemtime($stamp) > time() - 3600)) { return; }
	$guard = fopen($stamp, 'c');
	if (!$guard) { return; }
	try {
		if (!flock($guard, LOCK_EX | LOCK_NB)) { return; }
		chmod($stamp, 0600); touch($stamp);
		foreach (new DirectoryIterator($directory) as $file) {
			if (!$file->isFile() || $file->isLink() || !preg_match('/\A([a-f0-9]{64})\.(jpg|pdf)\z/', $file->getFilename(), $match)
				|| $file->getMTime() >= time() - max(60, (int)$ttl)) { continue; }
			$lockPath = $directory.'/'.$match[1].'.lock';
			if (is_link($lockPath)) { continue; }
			$lock = fopen($lockPath, 'c');
			if (!$lock) { continue; }
			try {
				if (flock($lock, LOCK_EX | LOCK_NB)) {
					clearstatcache(true, $file->getPathname());
					if (is_file($file->getPathname()) && filemtime($file->getPathname()) < time() - max(60, (int)$ttl)) { unlink($file->getPathname()); }
				}
			} finally { flock($lock, LOCK_UN); fclose($lock); }
		}
	} finally { flock($guard, LOCK_UN); fclose($guard); }
}
