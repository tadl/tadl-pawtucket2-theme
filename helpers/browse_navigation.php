<?php
/** Start a browse with its configured default, even with an older saved sort. */
function tadlBrowseEntryParameters($browseType, $browseInfo = null) {
	if ($browseType === 'subjects') { return ['clear' => 1]; }
	$browseInfo = $browseInfo ?? caGetInfoForBrowseType($browseType);
	$sort = array_key_first((array)($browseInfo['sortBy'] ?? []));
	if ($sort === null) { return []; }
	$direction = strtolower((string)($browseInfo['sortDirection'][$sort] ?? 'asc'));
	return ['sort' => $sort, 'direction' => $direction === 'desc' ? 'desc' : 'asc'];
}
