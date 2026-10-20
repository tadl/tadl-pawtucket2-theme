<?php
require_once(__DIR__.'/record_access.php');

/** Original formats only: generated thumbnails and download conversions are not representations. */
function tadlRepresentationFormatLabel($mimetype, $filename = '') {
	$mime = strtolower(trim(explode(';', (string)$mimetype, 2)[0]));
	$known = [
		'application/pdf' => 'PDF', 'application/x-pdf' => 'PDF',
		'image/jpeg' => 'JPEG', 'image/pjpeg' => 'JPEG', 'image/jpg' => 'JPEG',
		'image/tiff' => 'TIFF', 'image/x-tiff' => 'TIFF',
		'image/bmp' => 'BMP', 'image/x-bmp' => 'BMP', 'image/x-ms-bmp' => 'BMP',
		'image/svg+xml' => 'SVG', 'image/jp2' => 'JPEG 2000', 'image/jpeg2000' => 'JPEG 2000',
		'audio/mpeg' => 'MP3', 'audio/x-wav' => 'WAV', 'audio/wave' => 'WAV',
		'audio/x-flac' => 'FLAC', 'audio/mp4' => 'M4A',
		'video/quicktime' => 'MOV', 'video/x-msvideo' => 'AVI', 'video/x-matroska' => 'MKV',
		'application/msword' => 'DOC', 'application/rtf' => 'RTF',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'DOCX',
		'application/vnd.ms-excel' => 'XLS',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'XLSX',
		'application/vnd.ms-powerpoint' => 'PPT',
		'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'PPTX',
		'application/zip' => 'ZIP', 'application/x-zip-compressed' => 'ZIP',
		'text/plain' => 'TXT', 'text/csv' => 'CSV', 'text/html' => 'HTML'
	];
	if (isset($known[$mime])) { return $known[$mime]; }
	if (preg_match('!\A(?:image|audio|video)/(?:x-)?([a-z0-9]{1,10})\z!', $mime, $match)) {
		return strtoupper($match[1]);
	}
	// Older imports can lack the scalar MIME field. Never expose a filename or path.
	$extension = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
	if (!preg_match('/\A[a-z0-9]{1,10}\z/', $extension)) { return ''; }
	return ['tif' => 'TIFF', 'tiff' => 'TIFF', 'jpg' => 'JPEG', 'jpe' => 'JPEG', 'jp2' => 'JPEG 2000'][$extension] ?? strtoupper($extension);
}

/** Page-sized scalar inventory with the same native object/media visibility as thumbnails. */
function tadlObjectRepresentationFormats($request, array $objectIDs, array $access) {
	$ids = array_values(array_unique(array_map('intval', array_filter($objectIDs,
		static fn($id) => (is_int($id) || (is_string($id) && ctype_digit($id))) && (int)$id > 0))));
	if (!$request || !$ids || !$access) { return []; }
	foreach (['ca_objects' => ['ca_object_representations'], 'ca_object_representations' => ['media', 'mimetype']] as $table => $bundles) {
		foreach ($bundles as $bundle) {
			if (caGetBundleAccessLevel($table, $bundle) < __CA_BUNDLE_ACCESS_READONLY__) { return []; }
		}
	}
	$filename = caGetBundleAccessLevel('ca_object_representations', 'original_filename') >= __CA_BUNDLE_ACCESS_READONLY__
		? 'r.original_filename' : "'' AS original_filename";
	$db = Datamodel::getInstanceByTableName('ca_objects', false)->getDb();
	$formats = [];
	foreach (array_chunk($ids, 500) as $chunk) {
		$result = $db->query("SELECT DISTINCT oxr.object_id, r.representation_id, r.mimetype, {$filename}
			FROM ca_objects_x_object_representations oxr
			INNER JOIN ca_object_representations r ON r.representation_id = oxr.representation_id
			WHERE oxr.object_id IN (?) AND r.deleted = 0 AND r.access IN (?)
				AND r.media IS NOT NULL AND r.media <> ''", [$chunk, $access]);
		if (!$result) { throw new RuntimeException('Unable to read representation formats.'); }
		$rows = [];
		while ($result->nextRow()) {
			$rows[] = ['object_id' => (int)$result->get('object_id'), 'representation_id' => (int)$result->get('representation_id'),
				'mimetype' => $result->get('mimetype'), 'original_filename' => $result->get('original_filename')];
		}
		foreach (tadlReadableMediaRows($request, $rows, $access) as $row) {
			if ($label = tadlRepresentationFormatLabel($row['mimetype'], $row['original_filename'])) {
				$formats[$row['object_id']][$label] = $label;
			}
		}
	}
	foreach ($formats as &$labels) { $labels = array_values($labels); sort($labels, SORT_NATURAL); }
	unset($labels);
	return $formats;
}

function tadlRepresentationFormatTags(array $formats, $id = null) {
	if (!$formats) { return ''; }
	$html = '<ul'.($id !== null ? ' id="'.htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8').'"' : '').' class="tadl-representation-formats" role="list" aria-label="'.htmlspecialchars(_t('File formats'), ENT_QUOTES, 'UTF-8').'">';
	foreach ($formats as $format) {
		$html .= '<li class="tadl-representation-format">'.htmlspecialchars($format, ENT_QUOTES, 'UTF-8').'</li>';
	}
	return $html.'</ul>';
}
