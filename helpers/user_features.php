<?php
/** Native account features are visible only in authenticated sessions. */
function tadlUserMenu($request) {
	if (!$request->isLoggedIn()) { return ''; }
	$links = '';
	$attributes = ['role' => 'menuitem'];
	$links .= '<li role="none">'.caNavLink($request, _t('My profile'), '', '', 'LoginReg', 'profileForm', [], $attributes).'</li>';
	$links .= '<li role="none">'.caNavLink($request, _t('Log out'), '', '', 'LoginReg', 'Logout', [], $attributes).'</li>';
	$fallback = htmlspecialchars(caNavUrl($request, '', 'LoginReg', 'profileForm'), ENT_QUOTES, 'UTF-8');
	return '<li class="dropdown tadl-account-menu"><a href="'.$fallback.'" class="dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'
		.htmlspecialchars(_t('My account'), ENT_QUOTES, 'UTF-8').' <span class="caret" aria-hidden="true"></span></a><ul class="dropdown-menu" role="menu">'.$links.'</ul></li>';
}

function tadlProvidenceObjectLink($request, $objectID) {
	return tadlProvidenceRecordLink($request, 'ca_objects', $objectID);
}

function tadlProvidenceCollectionLink($request, $collectionID) {
	return tadlProvidenceRecordLink($request, 'ca_collections', $collectionID);
}

/** Full-access accounts are staff; public accounts must not get editor links. */
function tadlProvidenceRecordLink($request, $table, $recordID) {
	if (!$request->isLoggedIn() || !$request->user->isStandardUser()) { return ''; }
	$routes = [
		'ca_objects' => ['objects/ObjectEditor/Edit/Screen49/object_id/', 'object'],
		// Let Providence choose the default screen for this archival unit's type.
		'ca_collections' => ['collections/CollectionEditor/Edit/collection_id/', 'collection']
	];
	if (!is_string($table) || !isset($routes[$table]) || (!is_int($recordID) && !is_string($recordID))) { return ''; }
	$id = filter_var($recordID, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
	if ($id === false) { return ''; }
	$url = 'https://collections.tadl.org/index.php/editor/'.$routes[$table][0].$id;
	return '<a class="btn btn-default btn-sm tadl-providence-'.$routes[$table][1].'" href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener noreferrer">'
		.'<i class="fa fa-external-link" aria-hidden="true"></i> '.htmlspecialchars(_t('View in Providence'), ENT_QUOTES, 'UTF-8')
		.'<span class="sr-only"> '.htmlspecialchars(_t('(opens in a new tab)'), ENT_QUOTES, 'UTF-8').'</span></a>';
}
