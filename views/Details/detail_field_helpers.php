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
