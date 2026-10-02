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

function tadlImageDownloadLinks($request, $object, $representationID) {
	$source = tadlImageDownloadSource($request, $object, $representationID);
	if (!$source) { return ''; }
	$formats = [];
	if (in_array($source['mime'], ['image/tiff', 'image/x-tiff'], true)) {
		$formats['tiff'] = _t('TIFF (to print)');
	}
	$formats['jpg'] = _t('JPG (to share)');
	$html = '<details class="tadl-image-downloads"><summary class="btn btn-default btn-sm"><i class="fa fa-download" aria-hidden="true"></i> '.htmlspecialchars(_t('Download'), ENT_QUOTES, 'UTF-8').' <span class="caret" aria-hidden="true"></span></summary><ul>';
	foreach ($formats as $format => $label) {
		$url = caNavUrl($request, '', 'ImageDownload', 'Download', [
			'object_id' => (int)$object->getPrimaryKey(), 'representation_id' => (int)$representationID, 'format' => $format
		]);
		$html .= '<li><a href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</a></li>';
	}
	return $html.'</ul></details>';
}

/** Keep native zoom/compare actions and replace the image toolbar's set/download links. */
function tadlImageToolbar($request, $object, $representationID, $toolbar) {
	$rows = (array)$object->getRepresentations([], null, ['simple' => true, 'checkAccess' => caGetUserAccessValues($request)]);
	if (!preg_match('!^image/!i', (string)($rows[$representationID]['mimetype'] ?? ''))) { return $toolbar; }
	$toolbar = preg_replace('~<a\b(?=[^>]*\bclass=[\'\"][^\'\"]*\b(?:setsButton|dlButton)\b)[^>]*>.*?</a>~is', '', $toolbar);
	$toolbar = preg_replace_callback('~(<a\b(?=[^>]*\bclass=[\'\"][^\'\"]*\bzoomButton\b)[^>]*>)(.*?)(</a>)~is', static function ($match) {
		return $match[1].$match[2].'<span>'.htmlspecialchars(_t('Open media view'), ENT_QUOTES, 'UTF-8').'</span>'.$match[3];
	}, $toolbar);
	$menu = tadlImageDownloadLinks($request, $object, $representationID);
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

/** Convert the original with the installed native media plugin, without scaling. */
function tadlPrepareImageDownload($source, $format) {
	$mime = (new finfo(FILEINFO_MIME_TYPE))->file($source['path']);
	if ($format === 'tiff') {
		return in_array($mime, ['image/tiff', 'image/x-tiff'], true)
			? ['path' => $source['path'], 'mime' => 'image/tiff', 'extension' => 'tiff', 'temporary' => false] : null;
	}
	if ($format !== 'jpg' || !is_string($mime) || !str_starts_with($mime, 'image/')) { return null; }
	if ($mime === 'image/jpeg') {
		return ['path' => $source['path'], 'mime' => 'image/jpeg', 'extension' => 'jpg', 'temporary' => false];
	}
	$directory = sys_get_temp_dir().'/tadl-image-download-'.bin2hex(random_bytes(16));
	if (!mkdir($directory, 0700)) { return null; }
	$cleanup = static function () use ($directory) {
		foreach (glob($directory.'/*') ?: [] as $file) { if (is_file($file)) { unlink($file); } }
		if (is_dir($directory)) { rmdir($directory); }
	};
	register_shutdown_function($cleanup);
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
