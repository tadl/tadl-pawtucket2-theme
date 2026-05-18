<?php
if (!function_exists('tadlDetailField')) {
	function tadlDetailField($request, $item, $label, $template) {
		if (!$item || !trim((string)$template)) { return ''; }

		$value = trim((string)$item->getWithTemplate($template, [
			'checkAccess' => caGetUserAccessValues($request),
			'convertCodesToDisplayText' => true
		]));
		if (!strlen(trim(strip_tags($value)))) { return ''; }

		return "<div class='unit'><label>".htmlspecialchars($label, ENT_QUOTES, 'UTF-8')."</label>{$value}</div>\n";
	}
}
