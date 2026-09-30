<?php

/** Submit the preference independently of search and navigation URLs. */
function tadlRenderMediaPreferenceToggle($request) {
	$mode = tadlMediaPreference($request);
	$options = array('only' => _t('Only items with media'), 'all' => _t('All items'));
	$html = '<form class="tadl-media-preference" method="post" action="'.htmlspecialchars(caNavUrl($request, '', 'MediaPreference', 'Set'), ENT_QUOTES, 'UTF-8').'" role="group" aria-label="'.htmlspecialchars(_t('Items shown'), ENT_QUOTES, 'UTF-8').'">';
	$html .= '<input type="hidden" name="csrfToken" value="'.htmlspecialchars(caGenerateCSRFToken($request), ENT_QUOTES, 'UTF-8').'">';
	$html .= '<input type="hidden" name="returnTo" value="'.htmlspecialchars(tadlMediaPreferenceUrl($request), ENT_QUOTES, 'UTF-8').'">';
	foreach ($options as $value => $label) {
		$selected = ($mode === $value);
		$html .= '<button type="submit" class="tadl-media-preference-option'.($selected ? ' is-selected' : '').'" name="tadlMediaPreference" value="'.$value.'" aria-pressed="'.($selected ? 'true' : 'false').'">';
		$html .= '<span class="tadl-media-preference-check" aria-hidden="true">&#10003;</span><span>'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</span></button>';
	}
	return $html.'</form>';
}
