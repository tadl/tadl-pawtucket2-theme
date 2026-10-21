<?php

/** Submit the preference independently of search and navigation URLs. */
function tadlRenderMediaPreferenceToggle($request) {
	$mode = tadlMediaPreference($request);
	$options = array('only' => _t('Only items with media'), 'all' => _t('All items'));
	$html = '<form class="tadl-media-preference" method="post" action="'.htmlspecialchars(caNavUrl($request, '', 'MediaPreference', 'Set'), ENT_QUOTES, 'UTF-8').'" aria-label="'.htmlspecialchars(_t('Items shown'), ENT_QUOTES, 'UTF-8').'">';
	$html .= '<input type="hidden" name="csrfToken" value="'.htmlspecialchars(caGenerateCSRFToken($request), ENT_QUOTES, 'UTF-8').'">';
	$html .= '<input type="hidden" name="returnTo" value="'.htmlspecialchars(tadlMediaPreferenceUrl($request), ENT_QUOTES, 'UTF-8').'">';
	$html .= '<details class="tadl-media-preference-menu"><summary class="btn btn-default" aria-label="'.htmlspecialchars(_t('Items shown: %1', $options[$mode]), ENT_QUOTES, 'UTF-8').'">';
	$html .= htmlspecialchars($options[$mode], ENT_QUOTES, 'UTF-8').'<span class="fa fa-angle-down" aria-hidden="true"></span></summary><div class="tadl-media-preference-choices">';
	foreach ($options as $value => $label) {
		if ($mode === $value) { continue; }
		$html .= '<button type="submit" class="tadl-media-preference-option" name="tadlMediaPreference" value="'.$value.'">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</button>';
	}
	return $html.'</div></details></form>';
}
