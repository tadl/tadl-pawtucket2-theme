<?php
function tadlRenderedVideoHasSource($slide) {
	if (!preg_match('/<video\b/i', $slide)) { return false; }
	$document = new DOMDocument();
	$document->loadHTML($slide, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	foreach ($document->getElementsByTagName('video') as $video) {
		if (strlen(trim($video->getAttribute('src')))) { return true; }
		foreach ($video->getElementsByTagName('source') as $source) {
			if (strlen(trim($source->getAttribute('src')))) { return true; }
		}
	}
	return false;
}

/** Use the primary still as the native player's cover before Plyr initializes. */
function tadlObjectDetailVideoPosters($request, $subject, $slides) {
	if ($request->getController() !== 'Detail' || !$subject || $subject->tableName() !== 'ca_objects'
		|| !is_array($slides) || !method_exists($subject, 'getPrimaryRepresentation')) {
		return $slides;
	}

	$video_slides = [];
	foreach ($slides as $index => $slide) {
		if (tadlRenderedVideoHasSource($slide)
			&& preg_match('/^\s*<[^>]+\bdata-representation_id=[\'\"](\d+)[\'\"]/', $slide, $match)) {
			$video_slides[$index] = (int)$match[1];
		}
	}
	if (!sizeof($video_slides)) { return $slides; }

	$options = ['simple' => true, 'checkAccess' => caGetUserAccessValues($request)];
	$representations = (array)$subject->getRepresentations([], null, $options);
	foreach ($video_slides as $index => $representation_id) {
		if (!preg_match('!^video/!i', (string)($representations[$representation_id]['mimetype'] ?? ''))) {
			unset($video_slides[$index]);
		}
	}
	if (!sizeof($video_slides)) { return $slides; }

	// Unlike getPrimaryRepresentationInstance(), this lookup also enforces
	// representation ACLs and bundle access before loading the image itself.
	$primary = $subject->getPrimaryRepresentation([], null, $options);
	if (!is_array($primary) || empty($primary['representation_id'])
		|| !preg_match('!^image/!i', (string)($primary['mimetype'] ?? ''))) {
		return $slides;
	}
	$image = Datamodel::getInstance('ca_object_representations', true);
	if (!$image || !$image->load((int)$primary['representation_id']) || !$image->getPrimaryKey()) { return $slides; }

	$poster_url = null;
	foreach (['large', 'mediumlarge', 'medium'] as $version) {
		if (!$image->hasMediaVersion('media', $version)) { continue; }
		$info = $image->getMediaInfo('media', $version);
		if (!is_array($info) || !empty($info['USE_ICON']) || !empty($info['QUEUED'])
			|| !preg_match('!^image/!i', (string)($info['MIMETYPE'] ?? ''))
			|| (empty($info['FILENAME']) && empty($info['EXTERNAL_URL']))) { continue; }
		if ($poster_url = $image->getMediaUrl('media', $version)) { break; }
	}
	if (!$poster_url) { return $slides; }
	$poster_attribute = htmlspecialchars($poster_url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	foreach ($video_slides as $index => $representation_id) {
		// Patch only the opening video tag: serializing the whole slide changes
		// wrapper quotes used by thumbnail navigation and can alter player scripts.
		// Consume other tags, comments and script/style blocks as whole tokens so
		// video-like text inside their attributes or contents remains untouched.
		$slides[$index] = preg_replace_callback('~<!--.*?-->|<(script|style)\b(?:[^>\'\"]+|\'[^\']*\'|\"[^\"]*\")*>.*?</\1\s*>|<[a-z][a-z0-9:-]*(?:[^>\'\"]+|\'[^\']*\'|\"[^\"]*\")*>~is', function ($match) use ($poster_attribute) {
			if (!preg_match('~^<video(?=[\s/>])~i', $match[0])) { return $match[0]; }
			$tag = preg_replace_callback('~\s+([^\s=/>]+)(?:\s*=\s*(?:\"[^\"]*\"|\'[^\']*\'|[^\s>]+))?~', function ($attribute) {
				return in_array(strtolower($attribute[1]), ['poster', 'data-poster'], true) ? '' : $attribute[0];
			}, $match[0]);
			return preg_replace_callback('~\s*(/?)>$~', function ($end) use ($poster_attribute) {
				return ' poster="'.$poster_attribute.'" data-poster="'.$poster_attribute.'"'.$end[1].'>';
			}, $tag);
		}, $slides[$index]);
	}
	return $slides;
}

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
		if (tadlRenderedVideoHasSource($slide)) { $video_indices[] = $index; }
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
