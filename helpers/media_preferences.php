<?php

/** The URL wins over the session so bookmarked pages and Back restore their mode. */
function tadlMediaPreference($request) {
	$params = $request->getParameters(array('PATH', 'GET'));
	$mode = $params['media'] ?? null;
	if (in_array($mode, array('only', 'all'), true)) {
		Session::setVar('tadlMediaPreference', $mode);
		return $mode;
	}
	return Session::getVar('tadlMediaPreference') === 'all' ? 'all' : 'only';
}

/** Keep the active search/filters/display, but start at the first page when switching. */
function tadlMediaPreferenceUrl($request, $mode) {
	$params = array_intersect_key($request->getParameters(array('PATH', 'GET')), array_flip(array(
		'search', 'key', 'facets', 'facet', 'id', 'removeCriterion', 'removeID', 'clear',
		'view', 'sort', 'direction', 'n', '_advanced', 'source', 'label',
		'collection_id', 'object_id', 'entity_id', 'place_id', 'occurrence_id', 'set_id'
	)));
	$params = array_filter($params, function ($value) { return is_scalar($value); });
	$params['media'] = $mode === 'only' ? 'only' : 'all';
	return caNavUrl($request, '*', '*', '*', $params, array('useQueryString' => true));
}

/** Save the displayed (filtered) IDs for detail-page Previous/Next navigation. */
function tadlMediaResultContext($view, $result, $findType, $block = null) {
	if (!$result || !in_array($result->tableName(), array('ca_objects', 'ca_collections'), true)) { return; }
	$context = new ResultContext($view->request, $result->tableName(), $findType, $block);
	$limit = (int)$view->request->config->get('maximum_find_result_list_values');
	if ($limit < 10) { $limit = 1000; }
	$result->seek(max(0, (int)$view->getVar('start') - (int)floor($limit / 2)));
	$ids = $result->getPrimaryKeyValues($limit);
	$context->setResultList($ids);
	$context->setSearchHistory($result->numHits());
	$context->setParameter('media', tadlMediaPreference($view->request));
	$context->saveContext();
	$result->seek(0);
}
