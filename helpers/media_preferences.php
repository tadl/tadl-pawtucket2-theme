<?php

/** Read the browser's site-wide preference without putting it into navigation URLs. */
function tadlMediaPreference($request) {
	$cookies = $request->getParameters(array('COOKIE'));
	$mode = $cookies['tadlMediaPreference'] ?? null;
	if (in_array($mode, array('only', 'all'), true)) {
		Session::setVar('tadlMediaPreference', $mode);
		return $mode;
	}
	return Session::getVar('tadlMediaPreference') === 'all' ? 'all' : 'only';
}

/** Match the supported authority browse routes, including their AJAX requests. */
function tadlMediaAuthorityBrowseTable($request) {
	if ($request->getController() !== 'Browse') { return null; }
	$browseType = $request->getParameter('browseType', pString) ?: $request->getAction();
	return [
		'people' => 'ca_entities', 'organizations' => 'ca_entities',
		'places' => 'ca_places', 'occurrences' => 'ca_occurrences'
	][$browseType] ?? null;
}

/** The preference is not access control; only the two display modes are accepted. */
function tadlSetMediaPreference($request, $mode) {
	if (!in_array($mode, array('only', 'all'), true)) { return false; }
	$options = tadlMediaPreferenceCookieOptions($request);
	if (!setcookie('tadlMediaPreference', $mode, $options)) { return false; }
	Session::setVar('tadlMediaPreference', $mode);
	$request->setParameter('tadlMediaPreference', $mode, 'COOKIE');
	return true;
}

function tadlMediaPreferenceCookieOptions($request) {
	return array(
		'expires' => time() + 31536000,
		'path' => rtrim($request->getBaseUrlPath(), '/').'/',
		'secure' => defined('__CA_SITE_PROTOCOL__')
			? (__CA_SITE_PROTOCOL__ === 'https')
			: (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
		'httponly' => true,
		'samesite' => 'Lax'
	);
}

/** Keep the active search/filters/display, but start at the first page when switching. */
function tadlMediaPreferenceUrl($request) {
	$params = array_intersect_key($request->getParameters(array('PATH', 'GET')), array_flip(array(
		'search', 'key', 'facets', 'facet', 'id', 'removeCriterion', 'removeID', 'clear',
		'view', 'sort', 'direction', 'n', '_advanced', 'source', 'label',
		'collection_id', 'collection_view', 'object_id', 'entity_id', 'place_id', 'occurrence_id', 'set_id'
	)));
	$params = array_filter($params, function ($value) { return is_scalar($value); });
	return caNavUrl($request, '*', '*', '*', $params, array('useQueryString' => true));
}

/** Save the displayed (filtered) IDs for detail-page Previous/Next navigation. */
function tadlMediaResultContext($view, $result, $findType, $block = null) {
	if (!$result || (!in_array($result->tableName(), array('ca_objects', 'ca_collections'), true)
		&& $result->tableName() !== tadlMediaAuthorityBrowseTable($view->request))) { return; }
	$context = new ResultContext($view->request, $result->tableName(), $findType, $block);
	$limit = (int)$view->request->config->get('maximum_find_result_list_values');
	if ($limit < 10) { $limit = 1000; }
	$result->seek(max(0, (int)$view->getVar('start') - (int)floor($limit / 2)));
	$ids = $result->getPrimaryKeyValues($limit);
	$context->setResultList($ids);
	$context->setSearchHistory($result->numHits());
	$context->setParameter('media', null);
	$context->saveContext();
	$result->seek(0);
}
