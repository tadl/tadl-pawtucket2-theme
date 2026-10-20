<?php
require_once(__DIR__.'/display_preferences.php');
/** Start a browse with its configured default, even with an older saved sort. */
function tadlBrowseEntryParameters($browseType, $browseInfo = null, $request = null) {
	if ($browseType === 'subjects') { return ['clear' => 1]; }
	$browseInfo = $browseInfo ?? caGetInfoForBrowseType($browseType);
	$sort = array_key_first((array)($browseInfo['sortBy'] ?? []));
	if ($sort === null) { return []; }
	$direction = strtolower((string)($browseInfo['sortDirection'][$sort] ?? 'asc'));
	$params = ['sort' => $sort, 'direction' => $direction === 'desc' ? 'desc' : 'asc'];
	// Start families in Tiles unless the visitor has chosen a display preference.
	if ($browseType === 'families' && isset($browseInfo['views'][$browseInfo['defaultView'] ?? ''])) {
		$params['view'] = $browseInfo['defaultView'];
	}
	if ($request && ($view = tadlDisplayPreferences($request)['view'] ?? null) && isset($browseInfo['views'][$view])) { $params['view'] = $view; }
	return $params;
}
