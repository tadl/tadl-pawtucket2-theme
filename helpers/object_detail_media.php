<?php
/** Select the opening detail slide without changing the object's primary media. */
function tadlObjectDetailInitialMediaIndex($request, $subject, $slides) {
	if ($request->getController() !== 'Detail' || !$subject || $subject->tableName() !== 'ca_objects'
		|| !is_array($slides) || sizeof($slides) < 2) {
		return 0;
	}

	$has_requested_id = $request->parameterExists('representation_id') !== null;
	$requested_id = (int)$request->getParameter('representation_id', pInteger);
	if ($has_requested_id && $requested_id < 1) { return 0; }
	$rendered_ids = [];
	$video_indices = [];
	foreach ($slides as $index => $slide) {
		// Native slide IDs can differ from the representation_ids view variable
		// when relationship ranks collide. Use each rendered wrapper's own ID.
		if (!preg_match('/^\s*<[^>]+\bdata-representation_id=[\'\"](\d+)[\'\"]/', $slide, $match)) { continue; }
		$rendered_ids[$index] = (int)$match[1];
		// Missing playback media can render as a still or a player with no URL.
		if (!preg_match('/<video\b/i', $slide)) { continue; }
		$document = new DOMDocument();
		$document->loadHTML($slide, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
		foreach ($document->getElementsByTagName('video') as $video) {
			$has_source = strlen(trim($video->getAttribute('src'))) > 0;
			foreach ($video->getElementsByTagName('source') as $source) {
				if (strlen(trim($source->getAttribute('src')))) { $has_source = true; break; }
			}
			if ($has_source) { $video_indices[] = $index; break; }
		}
	}
	if (!$requested_id && !sizeof($video_indices)) { return 0; }

	$representations = (array)$subject->getRepresentations([], null, [
		'simple' => true,
		'checkAccess' => caGetUserAccessValues($request)
	]);
	if ($requested_id) {
		$index = array_search($requested_id, $rendered_ids, true);
		return isset($representations[$requested_id]) && $index !== false ? $index : 0;
	}

	foreach ($video_indices as $index) {
		$representation = $representations[$rendered_ids[$index]] ?? null;
		if ($representation && preg_match('!^video/!i', (string)($representation['mimetype'] ?? ''))) {
			return $index;
		}
	}
	return 0;
}
