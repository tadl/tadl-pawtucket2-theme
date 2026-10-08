<?php

/** Text follows the same record, bundle and Pawtucket ACL boundaries as its PDF. */
function tadlDocumentTextReadable($request, $record, array $access, $bundle = null) {
	return $record && $record->getPrimaryKey() && !$record->get('deleted')
		&& in_array((int)$record->get('access'), $access, true)
		&& $record->isReadable($request, $bundle)
		&& (!caACLIsEnabled($record, ['forPawtucket' => true])
			|| $record->checkACLAccessForUser($request->user) >= __CA_ACL_READONLY_ACCESS__);
}

function tadlDocumentTextNormalize($value) {
	$text = trim(str_replace(["\r\n", "\r", "\f"], ["\n", "\n", "\n\n"], (string)$value));
	return preg_match('/[^\s\p{Z}\x{200B}\x{FEFF}]/u', $text) === 1 ? $text : '';
}

/** Imported Scripto/rich text becomes plain text without joining paragraphs. */
function tadlDocumentTextFromMetadata($value) {
	$text = preg_replace('~<(?:br\b[^>]*|/(?:li|tr|dt|dd)\s*)>~i', "\n", (string)$value);
	$text = preg_replace('~</(?:p|div|h[1-6]|blockquote|section|article|pre|ul|ol)\s*>~i', "\n\n", $text);
	$text = preg_replace('~</(?:td|th)\s*>~i', ' ', $text);
	// Decode after stripping so encoded literal brackets remain text, never markup.
	$text = tadlDocumentTextNormalize(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
	return preg_replace(['/\h+\n/u', '/\n{3,}/'], ["\n", "\n\n"], $text);
}

/** PDF text precedes object metadata; hidden paired PDFs never qualify an object. */
function tadlObjectDocumentText($request, $object) {
	if (!$object || !method_exists($object, 'getRepresentations')) { return []; }
	$access = array_map('intval', (array)caGetUserAccessValues($request));
	if (!$access || !tadlDocumentTextReadable($request, $object, $access, 'ca_object_representations')) { return []; }
	$rows = (array)$object->getRepresentations([], null, ['simple' => true, 'checkAccess' => $access]);
	usort($rows, static function ($a, $b) {
		return [(int)($a['rank'] ?? 0), (int)!($a['is_primary'] ?? false), (int)($a['representation_id'] ?? 0)]
			<=> [(int)($b['rank'] ?? 0), (int)!($b['is_primary'] ?? false), (int)($b['representation_id'] ?? 0)];
	});
	$pdfs = [];
	foreach ($rows as $row) {
		if (strtolower((string)($row['mimetype'] ?? '')) !== 'application/pdf') { continue; }
		$id = (int)($row['representation_id'] ?? 0);
		if ($id <= 0 || isset($pdfs[$id])) { continue; }
		// Each retained PDF needs its own loaded state; the native cache shares one model.
		$representation = Datamodel::getInstance('ca_object_representations', false);
		if (!$representation || !$representation->load($id)
			|| !tadlDocumentTextReadable($request, $representation, $access, 'media')
			|| strtolower((string)$representation->get('mimetype')) !== 'application/pdf') { continue; }
		$pdfs[$id] = ['record' => $representation, 'label' => $row['label'] ?? ''];
	}
	if (!$pdfs) { return []; }

	$entries = [];
	foreach ($pdfs as $pdf) {
		$representation = $pdf['record'];
		if (!$representation->isReadable($request, 'media_content')) { continue; }
		$text = tadlDocumentTextNormalize($representation->get('media_content'));
		if ($text === '') { continue; }
		$label = $representation->isReadable($request, 'preferred_labels')
			? tadlDocumentTextNormalize($pdf['label'] ?: $representation->get('ca_object_representations.preferred_labels.name')) : '';
		$entries[] = ['source' => 'pdf', 'label' => $label, 'text' => $text];
	}
	if ($entries) { return $entries; }

	foreach (['transcription', 'pdf_text'] as $bundle) {
		if (!$object->hasElement($bundle) || !$object->isReadable($request, $bundle)) { continue; }
		// Structured values avoid element display templates, defaults and flattening
		// across records/locales. Disable reference substitution before HTML escaping.
		$values = $object->get('ca_objects.'.$bundle, [
			'returnWithStructure' => true, 'checkAccess' => $access, 'dontReturnDefault' => true,
			'convertLineBreaks' => false, 'highlighting' => false, 'doRefSubstitution' => false
		]);
		$parts = [];
		foreach ((array)($values[$object->getPrimaryKey()] ?? []) as $value) {
			$text = tadlDocumentTextFromMetadata($value[$bundle] ?? '');
			if ($text !== '') { $parts[] = $text; }
		}
		if ($parts) { return [['source' => 'imported', 'label' => '', 'text' => join("\n\n", $parts)]]; }
	}

	return [];
}

function tadlObjectDocumentTextHTML($request, $object) {
	$entries = tadlObjectDocumentText($request, $object);
	if (!$entries) { return ''; }
	$escape = static function ($value) { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
	$html = '<details class="tadl-document-text"><summary>'.$escape(_t('Document text')).'</summary><div class="tadl-document-text-body">';
	$html .= '<p class="tadl-document-text-source">'.$escape($entries[0]['source'] === 'imported'
		? _t('Transcribed text') : _t('Text extracted from the PDF')).'</p>';
	foreach ($entries as $index => $entry) {
		if (count($entries) > 1) {
			$html .= '<h3>'.$escape($entry['label'] ?: _t('PDF').' '.($index + 1)).'</h3>';
		}
		$html .= '<div class="tadl-document-text-content">'.$escape($entry['text']).'</div>';
	}
	return $html.'</div></details>';
}
