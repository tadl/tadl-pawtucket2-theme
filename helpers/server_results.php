<?php
require_once __DIR__.'/collection_contents.php';
require_once __DIR__.'/place_contents.php';
require_once __DIR__.'/family_contents.php';

/** Separate result controls from the containing detail request and its scope. */
function tadlServerResultsRequest($request, $controller, array $params) {
	$child = clone $request;
	$child->setController($controller);
	$child->setAction('objects');
	$child->setActionExtra('');
	foreach (['search', 'key', 'facets', 'facet', 'id', 'removeCriterion', 'removeID', 'clear', 'source', 'label', 'values', 'search_refine', 'row_id', 'getFacet', 'dontSetFind', '_advanced', 'collection_id', 'entity_id', 'place_id', 'occurrence_id', 'tadl_collection_id', 'tadl_collection_controls'] as $key) {
		$child->setParameter($key, '', 'GET');
	}
	foreach ($params as $key => $value) { $child->setParameter($key, $value, 'GET'); }
	$info = caGetInfoForBrowseType('objects');
	$sorts = (array)($info['sortBy'] ?? []);
	$sort = (string)$child->getParameter('sort', pString);
	$display = tadlDisplayView($child);
	$child->setParameter('view', in_array($display, ['images','list'], true) ? $display : 'images', 'GET');
	$child->setParameter('sort', isset($sorts[$sort]) ? $sort : (isset($sorts['Identifier']) ? 'Identifier' : array_key_first($sorts)), 'GET');
	$child->setParameter('direction', $child->getParameter('direction', pString) === 'desc' ? 'desc' : 'asc', 'GET');
	return $child;
}

/** Render the existing access-aware result templates without an HTTP subrequest. */
function tadlServerObjectResults($outer, $request, $result, $kind, array $info, $expression) {
	// These permission/preference-dependent cards now belong to the enclosing response.
	if (class_exists('AppController', false) && AppController::instanceExists()) {
		AppController::getInstance()->getResponse()->addHeader('Cache-Control', 'private, no-store');
	}
	$view = new View($request, $outer->getViewPaths());
	$display = tadlDisplayView($request);
	if (!in_array($display, ['images', 'list'], true)) { $display = 'images'; }
	$sorts = (array)($info['sortBy'] ?? []);
	$sort = (string)$request->getParameter('sort', pString);
	if (!isset($sorts[$sort])) { $sort = isset($sorts['Identifier']) ? 'Identifier' : array_key_first($sorts); }
	$values = [
		'result'=>$result, 'table'=>'ca_objects', 'primaryKey'=>'object_id', 't_instance'=>Datamodel::getInstance('ca_objects', false),
		'access_values'=>caGetUserAccessValues($request), 'config'=>caGetBrowseConfig(), 'browseInfo'=>$info,
		'browse_type'=>'objects', 'find_type'=>'search', 'is_advanced'=>false, 'options'=>$info['options'] ?? [],
		'criteria'=>[], 'facets'=>[], 'views'=>array_intersect_key((array)$info['views'], array_flip(['images','list'])),
		'view'=>$display, 'viewIcons'=>[], 'sort'=>$sort, 'sortBy'=>$sorts,
		'sort_direction'=>$request->getParameter('direction', pString) === 'desc' ? 'desc' : 'asc',
		'start'=>max(0, (int)$request->getParameter('s', pInteger)), 'hits_per_block'=>tadlResultPageSize($request, null, $display),
		'key'=>'', 'tadl_embedded_results'=>$kind, 'tadl_search_expression'=>$expression
	];
	foreach ($values as $key => $value) { $view->setVar($key, $value); }
	return $view->render('Browse/browse_results_html.php');
}

function tadlServerCollectionResults($view) {
	$params = $view->getVar('collection_result_params');
	$id = (int)$params['collection_id'];
	$request = tadlServerResultsRequest($view->request, 'CollectionContents', $params + [
		'tadl_collection_controls'=>1, 'tadl_collection_id'=>$id, 'search'=>'collection_id:'.$id
	]);
	$info = caGetInfoForBrowseType('objects');
	return tadlServerObjectResults($view, $request, tadlCollectionContentsResult($request, $info), 'collection', $info, 'collection_id:'.$id);
}

function tadlServerRelatedResults($view) {
	$scope = $view->getVar('tadl_related_scope');
	$id = (int)$scope['id'];
	$key = ['ca_entities'=>'entity_id', 'ca_places'=>'place_id', 'ca_occurrences'=>'occurrence_id'][$scope['table']];
	$controller = $scope['table'] === 'ca_places' ? 'PlaceContents' : ($scope['family'] ? 'FamilyContents' : 'Search');
	$expression = $key.':'.$id;
	$request = tadlServerResultsRequest($view->request, $controller, [$key=>$id, 'search'=>$expression, 's'=>0]);
	$info = caGetInfoForBrowseType('objects');
	if ($scope['table'] === 'ca_places') {
		$result = tadlPlaceContentsResult($request, $info);
	} else {
		if ($scope['family']) { $expression = tadlFamilyContentsSearch(tadlFamilyEntityScopes($request, [$id])[$id] ?? []); }
		$request->setParameter('search', $expression, 'GET');
		$result = tadlScopedObjectContentsResult($request, $info, null, 'tadl_related:'.$scope['table'].':'.$id, $expression);
	}
	return tadlServerObjectResults($view, $request, $result, 'authority', $info, $expression);
}
