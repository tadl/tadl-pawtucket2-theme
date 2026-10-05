<?php
/** Native account features are visible only in authenticated sessions. */
function tadlUserMenu($request) {
	if (!$request->isLoggedIn()) { return ''; }
	$links = '';
	$attributes = ['role' => 'menuitem'];
	if (caDisplayLightbox($request)) {
		$links .= '<li role="none">'.caNavLink($request, _t('My lightboxes'), '', '', 'Lightbox', 'Index', [], $attributes).'</li>';
	}
	$links .= '<li role="none">'.caNavLink($request, _t('My profile'), '', '', 'LoginReg', 'profileForm', [], $attributes).'</li>';
	$links .= '<li role="none">'.caNavLink($request, _t('Log out'), '', '', 'LoginReg', 'Logout', [], $attributes).'</li>';
	$fallback = htmlspecialchars(caNavUrl($request, '', 'LoginReg', 'profileForm'), ENT_QUOTES, 'UTF-8');
	return '<li class="dropdown tadl-account-menu"><a href="'.$fallback.'" class="dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'
		.htmlspecialchars(_t('My account'), ENT_QUOTES, 'UTF-8').' <span class="caret" aria-hidden="true"></span></a><ul class="dropdown-menu" role="menu">'.$links.'</ul></li>';
}

/** Open the native form; it owns authentication, set access, saving and CSRF. */
function tadlAddToLightboxLink($request, $objectID) {
	if (!caDisplayLightbox($request) || (int)$objectID < 1) { return ''; }
	$url = caNavUrl($request, '', 'Lightbox', 'addItemForm', ['object_id' => (int)$objectID]);
	$callback = 'caMediaPanel.showPanel('.json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT).'); return false;';
	return '<a class="btn btn-default btn-sm tadl-add-lightbox" href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'" onclick="'.htmlspecialchars($callback, ENT_QUOTES, 'UTF-8').'">'
		.'<i class="fa fa-folder-plus" aria-hidden="true"></i> '.htmlspecialchars(_t('Add to lightbox'), ENT_QUOTES, 'UTF-8').'</a>';
}
