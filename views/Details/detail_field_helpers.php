<?php
require_once(dirname(__DIR__, 2).'/helpers/browse_navigation.php');
require_once(__DIR__.'/detail_preview_helpers.php');
require_once(dirname(__DIR__, 2).'/helpers/rich_text.php');

/** Label the existing native link without changing its destination or contents. */
function tadlDetailNavigationLink($html, $label) {
	if ($html === '') { return ''; }
	$document = new DOMDocument('1.0', 'UTF-8');
	$document->loadHTML('<meta charset="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	$link = $document->getElementsByTagName('a')->item(0);
	if (!$link) { return $html; }
	$link->setAttribute('title', $label);
	$link->setAttribute('aria-label', $label);
	return $document->saveHTML($link);
}

/** Return only to the result set containing this record, never an unrelated last search. */
function tadlDetailResultsReturnLink($view, $recordType) {
	if (!$view->getVar('resultsURL')) { return ''; }
	$table = [
		'objects' => 'ca_objects', 'collections' => 'ca_collections',
		'people' => 'ca_entities', 'organizations' => 'ca_entities', 'families' => 'ca_entities',
		'places' => 'ca_places', 'occurrences' => 'ca_occurrences'
	][$recordType] ?? null;
	if (!$table) { return ''; }
	$item = $view->getVar('item');
	$context = ResultContext::getResultContextForLastFind($view->request, $table);
	if (!$item || !$context || !in_array($item->getPrimaryKey(), (array)$context->getResultList())) { return ''; }
	// Read stored metadata only; request parameters must not override the return destination.
	$return = $context->getParameter('tadl_result_return', true);
	$url = is_array($return) ? ($return['url'] ?? null) : $view->getVar('resultsURL');
	if (!is_string($url) || !str_starts_with($url, '/') || str_starts_with($url, '//') || preg_match('/[\\\\\x00-\x20\x7f]/', $url)) { return ''; }
	$kind = is_array($return) ? ($return['kind'] ?? '') : $context->findType();
	$label = [
		'collection' => _t('Back to collection'),
		'collections' => _t('Back to collections'),
		'people' => _t('Back to people'),
		'organizations' => _t('Back to organizations'),
		'families' => _t('Back to families'),
		'places' => _t('Back to places'),
		'occurrences' => _t('Back to events'),
		'gallery' => _t('Back to gallery'),
		'lightbox' => _t('Back to lightbox')
	][$kind] ?? _t('Back to results');
	$escape = static fn($text) => htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	return '<a class="tadl-record-return" href="'.$escape($url).'" title="'.$escape($label).'" aria-label="'.$escape($label).'">'
		.'<span class="small">'.$escape($label).'</span></a>';
}

/** Reuse native result-context links, with an optional browse destination. */
function tadlDetailNavigation($view, $browseAction = null, $browseLabel = '', $browseController = 'Browse', $recordType = 'objects') {
	$escape = static fn($text) => htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$labels = [
		'objects' => [_t('Go to previous object'), _t('Go to next object')],
		'collections' => [_t('Go to previous collection'), _t('Go to next collection')],
		'people' => [_t('Go to previous person'), _t('Go to next person')],
		'organizations' => [_t('Go to previous organization'), _t('Go to next organization')],
		'families' => [_t('Go to previous family'), _t('Go to next family')],
		'places' => [_t('Go to previous place'), _t('Go to next place')],
		'occurrences' => [_t('Go to previous event'), _t('Go to next event')]
	][$recordType] ?? [_t('Go to previous record'), _t('Go to next record')];
	$returnLink = tadlDetailResultsReturnLink($view, $recordType);
	$links = $returnLink
		.tadlDetailNavigationLink((string)$view->getVar('previousLink'), $labels[0])
		.tadlDetailNavigationLink((string)$view->getVar('nextLink'), $labels[1]);
	if ($browseAction !== null && $returnLink === '') {
		$browseParams = $browseController === 'Browse' ? tadlBrowseEntryParameters($browseAction, null, $view->request) : [];
		$browseLink = caNavLink($view->request, $escape($browseLabel), '', '', $browseController, $browseAction, $browseParams);
		$links = $recordType === 'collections' ? $browseLink.$links : $links.$browseLink;
	}
	if ($links === '') { return ''; }
	return '<nav class="tadl-record-nav" aria-label="'.$escape(_t('Record navigation')).'">'
		.$links
		.'</nav>';
}

/** Empty rich-text markup and encoded whitespace do not make a display field. */
if (!function_exists('tadlDetailHasContent')) {
	function tadlDetailHasContent($value) {
		$text = html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		return preg_match('/[^\s\p{Z}\x{200B}\x{FEFF}]/u', $text) === 1;
	}
}

/** Keep full native values intact and preserve links/formatting in rich previews. */
if (!function_exists('tadlDetailExpandableValue')) {
	function tadlDetailExpandableValue($value, $label, $entries = false) {
		$value = tadlPresentationHTML($value);
		$plain = preg_replace('~<(?:br\b[^>]*|/(?:p|div|li|h[1-6]|tr|dt|dd))>~i', ' ', (string)$value);
		$plain = html_entity_decode(strip_tags($plain), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$plain = trim(preg_replace('/[\s\p{Z}\x{200B}\x{FEFF}]+/u', ' ', $plain));
		if (mb_strlen($plain) <= 600) { return $value; }

		$preview = mb_substr($plain, 0, 320);
		if (($space = mb_strrpos($preview, ' ')) !== false) { $preview = mb_substr($preview, 0, $space); }
		$escape = static function ($text) { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
		$previewHTML = $escape($preview).'…'; $previewClass = 'tadl-long-field-preview';
		if (preg_match('/<[a-z][^>]*>/i', (string)$value) && ($rich = tadlDetailRichPreview($value, $entries))) {
			if ($rich['complete']) { return $value; }
			$previewHTML = $rich['html']; $previewClass .= ' tadl-long-field-preview--rich';
		}
		return '<div class="tadl-long-field"><div class="'.$previewClass.'">'.$previewHTML.'</div>'
			.'<details class="tadl-long-field-details"><summary><span class="tadl-read-more">'.$escape(_t('Read more')).'</span>'
			.'<span class="tadl-read-less">'.$escape(_t('Read less')).'</span><span class="sr-only">: '.$escape($label).'</span></summary>'
			.'<div class="tadl-long-field-full">'.$value.'</div></details></div>';
	}
}

if (!function_exists('tadlDetailField')) {
	function tadlDetailField($request, $item, $label, $template, $options = []) {
		if (!$item || !trim((string)$template)) { return ''; }

		$template_options = ['convertCodesToDisplayText' => true];
		if (isset($options['dontReturnDefault'])) { $template_options['dontReturnDefault'] = (bool)$options['dontReturnDefault']; }
		if (!($options['skipAccessCheck'] ?? false)) {
			$template_options['checkAccess'] = caGetUserAccessValues($request);
		}

		$value = trim(tadlPresentationHTML($item->getWithTemplate($template, $template_options)));
		if (!tadlDetailHasContent($value)) { return ''; }
		// Repeated linked native units are entries, rather than paragraphs to cut mid-role.
		$entries = preg_match('/<unit\b/i', $template) && preg_match('/<(?:l|a)\b/i', $template);
		$value = tadlDetailExpandableValue($value, $label, $entries);

		return "<div class='unit'><h3>".htmlspecialchars($label, ENT_QUOTES, 'UTF-8')."</h3>{$value}</div>\n";
	}
}

if (!function_exists('tadlDetailFirstAvailableField')) {
	function tadlDetailFirstAvailableField($request, $item, $label, $templates, $options = []) {
		if (!is_array($templates)) { $templates = [$templates]; }

		foreach ($templates as $template) {
			$field = tadlDetailField($request, $item, $label, $template, $options);
			if ($field) { return $field; }
		}

		return '';
	}
}

/** Catalog subjects are related vocabulary records, rather than LC attributes. */
function tadlObjectSubjects($request, $object) {
	if (!$object || !$object->getPrimaryKey()
		|| caGetBundleAccessLevel('ca_objects', 'ca_list_items') < __CA_BUNDLE_ACCESS_READONLY__
		|| caGetBundleAccessLevel('ca_list_items', 'preferred_labels') < __CA_BUNDLE_ACCESS_READONLY__) { return ''; }
	$rows = (array)$object->getRelatedItems('ca_list_items', ['checkAccess' => caGetUserAccessValues($request), 'returnLabelsAsArray' => true]);
	if ($rows && caACLIsEnabled('ca_list_items', ['forPawtucket' => true])) {
		$target = Datamodel::getInstance('ca_list_items', true);
		$browse = caGetBrowseInstance('ca_list_items');
		$readable = array_fill_keys($target && $browse ? $browse->filterHitsByACL(array_column($rows, 'item_id'), $target->tableNum(), $request->getUserID()) : [], true);
		$rows = array_filter($rows, static function ($row) use ($readable) { return isset($readable[(int)($row['item_id'] ?? 0)]); });
	}
	$terms = [];
	foreach ($rows as $row) {
		$id = (int)($row['item_id'] ?? 0);
		// Native "label" selects the user's locale; the array retains both label forms.
		$label = $row['label'] ?? '';
		if (is_array($label)) {
			$plural = trim((string)($label['name_plural'] ?? ''));
			$label = $plural !== '' ? $plural : ($label['name_singular'] ?? '');
		}
		$label = trim((string)$label);
		if ($id > 0 && $label !== '') { $terms[$id] = $label; }
	}
	if (!$terms) { return ''; }
	natcasesort($terms);
	$links = [];
	foreach ($terms as $id => $label) {
		$url = caNavUrl($request, '', 'Browse', 'subjects', ['facet' => 'term_facet', 'id' => $id, 'clear' => 1]);
		$links[] = '<li><a href="'.htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">'.htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</a></li>';
	}
	return '<div class="unit"><h3>'.htmlspecialchars(_t('Subjects'), ENT_QUOTES, 'UTF-8').'</h3><ul class="tadl-subject-terms">'.join('', $links).'</ul></div>';
}

if (!function_exists('tadlObjectThesaurusTerms')) {
	function tadlObjectThesaurusTerms($request, $object, $label = 'Thesaurus terms') {
		if (!$object || caGetBundleAccessLevel('ca_objects', 'lctgm') < __CA_BUNDLE_ACCESS_READONLY__) { return ''; }
		if (!($object_id = $object->getPrimaryKey())) { return ''; }

		$options = [
			'returnWithStructure' => true, 'checkAccess' => caGetUserAccessValues($request),
			'highlighting' => false, 'convertLineBreaks' => false, 'dontReturnDefault' => true
		];
		$labels = $object->get('ca_objects.lctgm', array_merge($options, ['text' => true]))[$object_id] ?? [];
		$ids = $object->get('ca_objects.lctgm', array_merge($options, ['n' => true]))[$object_id] ?? [];
		if (!is_array($labels)) { return ''; }

		$terms = [];
		foreach ($labels as $attribute_id => $values) {
			$text = trim((string)($values['lctgm'] ?? ''));
			if ($text === '') { continue; }

			$text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			$id = trim((string)($ids[$attribute_id]['lctgm'] ?? ''));
			// Native LC values can have a malformed stored URI; use their term ID
			// and a fixed TGM destination rather than trusting that URI as a link.
			if (preg_match('/^tgm[0-9]+$/D', $id)) {
				$text = '<a href="https://id.loc.gov/vocabulary/graphicMaterials/'.$id.'">'.$text.'</a>';
			}
			$terms[] = $text;
		}
		if (!$terms) { return ''; }

		return "<div class='unit'><h3>".htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."</h3>".join('<br/>', $terms)."</div>\n";
	}
}

if (!function_exists('tadlObjectRepresentationCaptions')) {
	function tadlObjectRepresentationCaptions($request, $object, $label = 'Media caption') {
		if (!$object || !method_exists($object, 'getRepresentations')) { return ''; }
		if (!class_exists('ca_object_representations') && defined('__CA_MODELS_DIR__')) {
			require_once(__CA_MODELS_DIR__.'/ca_object_representations.php');
		}

		$captions = [];
		$representations = $object->getRepresentations(['thumbnail'], null, ['checkAccess' => caGetUserAccessValues($request)]);
		if (!is_array($representations) || !sizeof($representations)) { return ''; }

		foreach ($representations as $representation_id => $representation) {
			$rep = new ca_object_representations($representation_id);
			if (!$rep->getPrimaryKey()) { continue; }

			$caption = trim((string)$rep->getWithTemplate('^ca_object_representations.media_caption', [
				'convertCodesToDisplayText' => true
			]));
			if (!tadlDetailHasContent($caption)) { continue; }

			if (sizeof($representations) > 1) {
				$rep_label = trim((string)$rep->getWithTemplate('^ca_object_representations.preferred_labels.name', [
					'convertCodesToDisplayText' => true
				]));
				if (strlen(trim(strip_tags($rep_label))) && !preg_match('/^BLANK/i', $rep_label)) {
					$caption = '<strong>'.htmlspecialchars($rep_label, ENT_QUOTES, 'UTF-8').':</strong> '.$caption;
				}
			}
			$captions[] = $caption;
		}

		if (!sizeof($captions)) { return ''; }

		return "<div class='unit'><h3>".htmlspecialchars($label, ENT_QUOTES, 'UTF-8')."</h3>".tadlDetailExpandableValue(join('<br/>', $captions), $label)."</div>\n";
	}
}
