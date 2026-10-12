<?php
/** Start a browse with its configured default, even with an older saved sort. */
function tadlBrowseEntryParameters($browseType, $browseInfo = null) {
	if ($browseType === 'subjects') { return ['clear' => 1]; }
	$browseInfo = $browseInfo ?? caGetInfoForBrowseType($browseType);
	$sort = array_key_first((array)($browseInfo['sortBy'] ?? []));
	if ($sort === null) { return []; }
	$direction = strtolower((string)($browseInfo['sortDirection'][$sort] ?? 'asc'));
	$params = ['sort' => $sort, 'direction' => $direction === 'desc' ? 'desc' : 'asc'];
	// Family entry links use the default even after a saved List view.
	if ($browseType === 'families' && isset($browseInfo['views'][$browseInfo['defaultView'] ?? ''])) {
		$params['view'] = $browseInfo['defaultView'];
	}
	return $params;
}
