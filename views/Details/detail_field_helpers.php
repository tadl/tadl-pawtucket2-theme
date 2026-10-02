<?php
if (!function_exists('tadlDetailField')) {
	function tadlDetailField($request, $item, $label, $template, $options = []) {
		if (!$item || !trim((string)$template)) { return ''; }

		$template_options = ['convertCodesToDisplayText' => true];
		if (!($options['skipAccessCheck'] ?? false)) {
			$template_options['checkAccess'] = caGetUserAccessValues($request);
		}

		$value = trim((string)$item->getWithTemplate($template, $template_options));
		if (!strlen(trim(strip_tags($value)))) { return ''; }

		return "<div class='unit'><label>".htmlspecialchars($label, ENT_QUOTES, 'UTF-8')."</label>{$value}</div>\n";
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
	$rows = (array)$object->getRelatedItems('ca_list_items', ['checkAccess' => caGetUserAccessValues($request)]);
	if ($rows && caACLIsEnabled('ca_list_items', ['forPawtucket' => true])) {
		$target = Datamodel::getInstance('ca_list_items', true);
		$browse = caGetBrowseInstance('ca_list_items');
		$readable = array_fill_keys($target && $browse ? $browse->filterHitsByACL(array_column($rows, 'item_id'), $target->tableNum(), $request->getUserID()) : [], true);
		$rows = array_filter($rows, static function ($row) use ($readable) { return isset($readable[(int)($row['item_id'] ?? 0)]); });
	}
	$terms = [];
	foreach ($rows as $row) {
		$id = (int)($row['item_id'] ?? 0);
		$label = trim((string)($row['label'] ?? ''));
		if ($id > 0 && $label !== '') { $terms[$id] = $label; }
	}
	if (!$terms) { return ''; }
	natcasesort($terms);
	$links = [];
	foreach ($terms as $id => $label) {
		$url = caNavUrl($request, '', 'Browse', 'subjects', ['facet' => 'term_facet', 'id' => $id, 'clear' => 1]);
		$links[] = '<li><a href="'.htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">'.htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</a></li>';
	}
	return '<div class="unit"><label>'.htmlspecialchars(_t('Subjects'), ENT_QUOTES, 'UTF-8').'</label><ul class="tadl-subject-terms">'.join('', $links).'</ul></div>';
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

		return "<div class='unit'><label>".htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."</label>".join('<br/>', $terms)."</div>\n";
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
			if (!strlen(trim(strip_tags($caption)))) { continue; }

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

		return "<div class='unit'><label>".htmlspecialchars($label, ENT_QUOTES, 'UTF-8')."</label>".join('<br/>', $captions)."</div>\n";
	}
}
