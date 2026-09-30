<?php

/** Render the shared media preference as ordinary navigation links. */
function tadlRenderMediaPreferenceToggle($request) {
	$mode = tadlMediaPreference($request);
	$options = array('only' => _t('Only items with media'), 'all' => _t('All items'));
	$html = '<div class="tadl-media-preference" role="group" aria-label="'.htmlspecialchars(_t('Items shown'), ENT_QUOTES, 'UTF-8').'">';
	foreach ($options as $value => $label) {
		$selected = ($mode === $value);
		$html .= '<a class="tadl-media-preference-option'.($selected ? ' is-selected' : '').'" href="'.htmlspecialchars(tadlMediaPreferenceUrl($request, $value), ENT_QUOTES, 'UTF-8').'"'.($selected ? ' aria-current="true"' : '').'>';
		$html .= '<span class="tadl-media-preference-check" aria-hidden="true">&#10003;</span><span>'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</span></a>';
	}
	return $html.'</div>';
}
