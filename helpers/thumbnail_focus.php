<?php
/** Focal metadata only: never select media or replace native access-filtered tags. */
function tadlThumbnailFaceCacheDirectory() {
	return defined('__CA_APP_DIR__') ? __CA_APP_DIR__.'/tmp/tadl-thumbnail-faces' : null;
}

function tadlThumbnailFaceCacheKey(array $info, $version) {
	$v = $info[$version] ?? [];
	return hash('sha256', json_encode(['yunet-2023mar-v1', $v['MAGIC'] ?? '', $v['FILENAME'] ?? '',
		$v['MD5'] ?? '', $v['WIDTH'] ?? 0, $v['HEIGHT'] ?? 0, $info['original']['MD5'] ?? '']));
}

function tadlThumbnailValidPoint($point) {
	if (!is_array($point)) { return null; }
	foreach (['x', 'y'] as $axis) {
		if (!isset($point[$axis]) || !is_numeric($point[$axis]) || !is_finite((float)$point[$axis])
			|| $point[$axis] < 0 || $point[$axis] > 1) { return null; }
	}
	return ['x' => (float)$point['x'], 'y' => (float)$point['y']];
}

function tadlThumbnailValidFaces($faces) {
	if (!is_array($faces) || array_values($faces) !== $faces || count($faces) > 100) { return []; }
	$out = [];
	foreach ($faces as $face) {
		if (!is_array($face) || array_values($face) !== $face || count($face) !== 4) { return []; }
		foreach ($face as $value) {
			if (!is_numeric($value) || !is_finite((float)$value) || $value < 0 || $value > 1) { return []; }
		}
		[$x, $y, $w, $h] = array_map('floatval', array_values($face));
		if ($w <= 0 || $h <= 0 || $x + $w > 1.000001 || $y + $h > 1.000001) { return []; }
		$out[] = [$x, $y, $w, $h];
	}
	return $out;
}

function tadlThumbnailFocusData(array $info, $version, $cacheDirectory = null) {
	$manual = tadlThumbnailValidPoint($info['_CENTER'] ?? null);
	// Native center coordinates refer to the original, not an already cropped derivative.
	$v = $info[$version] ?? [];
	$o = $info['original'] ?? [];
	if ($manual && !empty($v['WIDTH']) && !empty($v['HEIGHT']) && !empty($o['WIDTH']) && !empty($o['HEIGHT'])
		&& abs(($v['WIDTH'] / $v['HEIGHT']) / ($o['WIDTH'] / $o['HEIGHT']) - 1) < 0.02) {
		return ['point' => $manual, 'source' => 'manual'];
	}
	// Even if the derivative was cropped, do not substitute an automatic suggestion for a staff choice.
	if ($manual || !$cacheDirectory) { return null; }
	$path = $cacheDirectory.'/'.tadlThumbnailFaceCacheKey($info, $version).'.json';
	if (!is_file($path) || !is_readable($path) || filesize($path) > 65536) { return null; }
	$data = json_decode(file_get_contents($path), true);
	$faces = ($data['schema'] ?? null) === 1 ? tadlThumbnailValidFaces($data['faces'] ?? null) : [];
	return $faces ? ['faces' => $faces, 'source' => 'faces'] : null;
}

/** Only inspect the exact representation/version already present in a native image tag. */
function tadlFocusThumbnail($html) {
	if (!is_string($html) || !str_contains($html, '<img')) { return $html; }
	static $representations = [];
	return preg_replace_callback('~<img\b(?:[^>\'\"]+|\'[^\']*\'|\"[^\"]*\")*>~i', function ($match) use (&$representations) {
		$doc = new DOMDocument();
		$doc->loadHTML('<meta charset="UTF-8">'.$match[0], LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
		$img = $doc->getElementsByTagName('img')->item(0);
		$path = parse_url($img->getAttribute('src'), PHP_URL_PATH);
		if (!is_string($path) || !preg_match('~_ca_object_representations_media_(\d+)_([a-z0-9]+)\.[a-z0-9]+$~i', $path, $parts)) { return $match[0]; }
		$id = (int)$parts[1]; $version = $parts[2];
		if (!array_key_exists($id, $representations)) {
			$rep = Datamodel::getInstanceByTableName('ca_object_representations', true);
			$representations[$id] = $rep && $rep->load($id) && !$rep->get('deleted') ? $rep : null;
		}
		$rep = $representations[$id];
		if (!$rep || parse_url((string)$rep->getMediaUrl('media', $version), PHP_URL_PATH) !== $path) { return $match[0]; }
		$info = $rep->getMediaInfo('media');
		if (!is_array($info) || !($focus = tadlThumbnailFocusData($info, $version, tadlThumbnailFaceCacheDirectory()))) { return $match[0]; }
		$img->setAttribute('data-tadl-focus', json_encode($focus, JSON_THROW_ON_ERROR));
		if (isset($focus['point'])) {
			// Progressive fallback; JS centers this point precisely for the final box dimensions.
			$style = preg_replace('~(?:^|;)\s*object-position\s*:[^;]*~i', '', $img->getAttribute('style'));
			$img->setAttribute('style', rtrim($style, ';').';object-position:'.($focus['point']['x'] * 100).'% '.($focus['point']['y'] * 100).'%;');
		}
		return $doc->saveHTML($img);
	}, $html);
}
