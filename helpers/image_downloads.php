<?php
/** Resolve an attached image through native record, bundle, ACL and download checks. */
function tadlImageDownloadSource($request, $object, $representationID) {
	if ($request->config->get('pawtucket_requires_login') && !$request->isLoggedIn()) { return null; }
	if (!$object || $object->tableName() !== 'ca_objects' || !$object->getPrimaryKey()
		|| $object->get('deleted') || !$object->isReadable($request)) { return null; }
	$access = caGetUserAccessValues($request);
	if (!caACLIsEnabled($object, ['forPawtucket' => true]) && $access
		&& !in_array($object->get('access'), $access)) { return null; }
	$rows = (array)$object->getRepresentations([], null, ['simple' => true, 'checkAccess' => $access]);
	$row = $rows[$representationID] ?? null;
	if (!$row || !preg_match('!^image/!i', (string)($row['mimetype'] ?? ''))) { return null; }
	$representation = Datamodel::getInstance('ca_object_representations', true);
	if (!$representation || !$representation->load($representationID) || $representation->get('deleted')
		|| !$representation->isReadable($request)
		|| !caObjectsDisplayDownloadLink($request, $object->getPrimaryKey(), $representation)) { return null; }
	$mime = $representation->getMediaInfo('media', 'INPUT', 'MIMETYPE') ?: $row['mimetype'];
	// Full-resolution exports require permission to download the original version.
	if (!in_array('original', (array)caGetAvailableDownloadVersions($request, $mime), true)) { return null; }
	$info = $representation->getMediaInfo('media', 'original');
	$path = $representation->getMediaPath('media', 'original');
	if (!is_array($info) || !empty($info['QUEUED']) || !empty($info['USE_ICON'])
		|| !preg_match('!^image/!i', (string)($info['MIMETYPE'] ?? ''))
		|| !is_string($path) || !is_file($path) || !is_readable($path)) { return null; }
	return ['representation' => $representation, 'path' => $path, 'mime' => strtolower($info['MIMETYPE'])];
}

function tadlImageDownloadLinks($request, $object, $representationID, $inViewer = false) {
	$source = tadlImageDownloadSource($request, $object, $representationID);
	if (!$source) { return ''; }
	$formats = [];
	if (in_array($source['mime'], ['image/tiff', 'image/x-tiff'], true)) {
		$formats['tiff'] = _t('TIFF (to print)');
	}
	$formats['jpg'] = _t('JPG (to share)');
	$formats['pdf'] = _t('PDF');
	$label = htmlspecialchars(_t('Download'), ENT_QUOTES, 'UTF-8');
	$html = $inViewer
		? '<details class="tadl-image-downloads tadl-viewer-downloads"><summary aria-label="'.$label.'" title="'.$label.'"><i class="fa fa-download" aria-hidden="true"></i></summary><ul>'
		: '<details class="tadl-image-downloads"><summary class="btn btn-default btn-sm"><i class="fa fa-download" aria-hidden="true"></i> '.$label.' <span class="caret" aria-hidden="true"></span></summary><ul>';
	foreach ($formats as $format => $label) {
		$url = caNavUrl($request, '', 'ImageDownload', 'Download', [
			'object_id' => (int)$object->getPrimaryKey(), 'representation_id' => (int)$representationID, 'format' => $format
		]);
		$html .= '<li><a href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</a></li>';
	}
	return $html.'</ul></details>';
}

/** Normalize image/PDF actions while preserving native callbacks and PDF downloads. */
function tadlImageToolbar($request, $object, $representationID, $toolbar) {
	// Object-level Lightbox controls live below the viewer for logged-in users.
	// Remove native representation links, including anonymous video login prompts.
	$toolbar = preg_replace('~<a\b(?=[^>]*\bclass=[\'\"][^\'\"]*\bsetsButton\b)[^>]*>.*?</a>~is', '', $toolbar);
	$rows = (array)$object->getRepresentations([], null, ['simple' => true, 'checkAccess' => caGetUserAccessValues($request)]);
	$mime = strtolower((string)($rows[$representationID]['mimetype'] ?? ''));
	$isImage = (bool)preg_match('!^image/!', $mime);
	if (!$isImage && $mime !== 'application/pdf') { return $toolbar; }
	if ($isImage) {
		$toolbar = preg_replace('~<a\b(?=[^>]*\bclass=[\'\"][^\'\"]*\bdlButton\b)[^>]*>.*?</a>~is', '', $toolbar);
	}
	// PDFs already have a native, permission-checked original download link.
	// Keep the complete document; do not send it through single-image conversion.
	if (!$isImage) {
		$toolbar = preg_replace_callback('~(<a\b(?=[^>]*\bclass=[\'\"][^\'\"]*\bdlButton\b)[^>]*>)(.*?)(</a>)~is', static function ($match) {
			return $match[1].$match[2].'<span>'.htmlspecialchars(_t('Download PDF'), ENT_QUOTES, 'UTF-8').'</span>'.$match[3];
		}, $toolbar);
	}
	$toolbar = preg_replace_callback('~(<a\b(?=[^>]*\bclass=[\'\"][^\'\"]*\bzoomButton\b)[^>]*>)(.*?)(</a>)~is', static function ($match) {
		$label = htmlspecialchars(_t('Media viewer'), ENT_QUOTES, 'UTF-8');
		$link = preg_replace_callback('~\b(aria-label|title)\s*=\s*([\'\"])(.*?)\2~is', static function ($attribute) use ($label) {
			return $attribute[1].'="'.$label.'"';
		}, $match[1]);
		return $link.$match[2].'<span>'.$label.'</span>'.$match[3];
	}, $toolbar);
	$menu = $isImage ? tadlImageDownloadLinks($request, $object, $representationID) : '';
	if (preg_match('~<div\b[^>]*\bclass=[\'\"]detailMediaToolbar[\'\"][^>]*>~i', $toolbar)) {
		$toolbar = preg_replace('~\bclass=[\'\"]detailMediaToolbar[\'\"]~i', 'class="detailMediaToolbar tadl-image-toolbar"', $toolbar, 1);
		return str_replace('</div><!-- end detailMediaToolbar -->', $menu.'</div><!-- end detailMediaToolbar -->', $toolbar);
	}
	return $toolbar.($menu ? '<div class="detailMediaToolbar tadl-image-toolbar">'.$menu.'</div>' : '');
}

function tadlImageViewerSlide($request, $object, $representationID, $slide) {
	$matched = false;
	$slide = preg_replace_callback('~<div\b[^>]*\bclass=[\'\"]detailMediaToolbar[\'\"][^>]*>.*?</div><!-- end detailMediaToolbar -->~is', function ($match) use ($request, $object, $representationID, &$matched) {
		$matched = true;
		return tadlImageToolbar($request, $object, $representationID, $match[0]);
	}, $slide);
	return $matched ? $slide : $slide.tadlImageToolbar($request, $object, $representationID, '');
}

/** Private request-local workspace for conversions and renderer scratch files. */
function tadlImageDownloadTemporaryDirectory() {
	$directory = sys_get_temp_dir().'/tadl-image-download-'.bin2hex(random_bytes(16));
	if (!mkdir($directory, 0700)) { return null; }
	$cleanup = static function () use ($directory) {
		foreach (glob($directory.'/*') ?: [] as $file) { if (is_file($file)) { unlink($file); } }
		if (is_dir($directory)) { rmdir($directory); }
	};
	register_shutdown_function($cleanup);
	return [$directory, $cleanup];
}

/** Single-page image PDF using the Dompdf dependency supplied by Pawtucket. */
function tadlPrepareImagePDF($source) {
	if (!class_exists('Dompdf\\Dompdf') || !class_exists('Dompdf\\Options')) { return null; }
	$jpeg = tadlPrepareImageDownload($source, 'jpg');
	if (!$jpeg || !($dimensions = @getimagesize($jpeg['path']))) { return null; }
	$workspace = tadlImageDownloadTemporaryDirectory();
	if (!$workspace) { return null; }
	[$directory, $cleanup] = $workspace;
	try {
		$landscape = $dimensions[0] > $dimensions[1];
		$pageWidth = $landscape ? 792 : 612;
		$pageHeight = $landscape ? 612 : 792;
		$scale = min(($pageWidth - 72) / $dimensions[0], ($pageHeight - 72) / $dimensions[1]);
		$width = $dimensions[0] * $scale;
		$height = $dimensions[1] * $scale;
		// CSS changes the printed size; the JPEG's full pixel dimensions are retained.
		$options = new \Dompdf\Options(['tempDir' => $directory, 'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false]);
		$pdf = new \Dompdf\Dompdf($options);
		$pdf->setPaper('letter', $landscape ? 'landscape' : 'portrait');
		$data = base64_encode(file_get_contents($jpeg['path']));
		$pdf->loadHtml('<!doctype html><html><head><style>@page{margin:36pt}body{margin:0;text-align:center;font-size:0}img{display:block;margin:0 auto}</style></head><body><img alt="" src="data:image/jpeg;base64,'.$data.'" style="width:'.sprintf('%.4F', $width).'pt;height:'.sprintf('%.4F', $height).'pt"></body></html>');
		$pdf->render();
		$path = $directory.'/image.pdf';
		$bytes = $pdf->output();
		if (!is_string($bytes) || !str_starts_with($bytes, '%PDF-') || !str_ends_with(rtrim($bytes), '%%EOF')
			|| file_put_contents($path, $bytes) !== strlen($bytes)
			|| (new finfo(FILEINFO_MIME_TYPE))->file($path) !== 'application/pdf') { $cleanup(); return null; }
		return ['path' => $path, 'mime' => 'application/pdf', 'extension' => 'pdf', 'temporary' => true];
	} catch (Throwable $error) { $cleanup(); throw $error; }
}

/** Convert the original with the installed native media plugin, without scaling. */
function tadlPrepareImageDownload($source, $format) {
	if ($format === 'pdf') { return tadlPrepareImagePDF($source); }
	$mime = (new finfo(FILEINFO_MIME_TYPE))->file($source['path']);
	if ($format === 'tiff') {
		return in_array($mime, ['image/tiff', 'image/x-tiff'], true)
			? ['path' => $source['path'], 'mime' => 'image/tiff', 'extension' => 'tiff', 'temporary' => false] : null;
	}
	if ($format !== 'jpg' || !is_string($mime) || !str_starts_with($mime, 'image/')) { return null; }
	if ($mime === 'image/jpeg') {
		return ['path' => $source['path'], 'mime' => 'image/jpeg', 'extension' => 'jpg', 'temporary' => false];
	}
	$workspace = tadlImageDownloadTemporaryDirectory();
	if (!$workspace) { return null; }
	[$directory, $cleanup] = $workspace;
	$media = new Media();
	try {
		if (!$media->read($source['path'])) { $cleanup(); return null; }
		$width = (int)$media->get('width');
		$height = (int)$media->get('height');
		$media->set('quality', 90);
		$media->set('background', '#ffffff');
		$path = $media->write($directory.'/image', 'image/jpeg');
		$dimensions = is_string($path) && is_file($path) ? @getimagesize($path) : false;
		if (!$dimensions || $dimensions[2] !== IMAGETYPE_JPEG || $dimensions[0] !== $width || $dimensions[1] !== $height) {
			$cleanup(); return null;
		}
		return ['path' => $path, 'mime' => 'image/jpeg', 'extension' => 'jpg', 'temporary' => true];
	} finally {
		$media->cleanup();
	}
}
