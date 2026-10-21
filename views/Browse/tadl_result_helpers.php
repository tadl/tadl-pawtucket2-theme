<?php
require_once(__DIR__.'/../../helpers/display_preferences.php');
/** Collection contents use ordinary detail URLs; other results retain native routes. */
function tadlBrowseResultCollectionID($request) {
	if (!method_exists($request, 'getParameter') || !method_exists($request, 'getController')
		|| !in_array($request->getController(), ['Search', 'CollectionContents'], true)
		|| $request->getParameter('tadl_collection_controls', pInteger) !== 1) { return 0; }
	return max(0, (int)$request->getParameter('tadl_collection_id', pInteger));
}

/** Jump directly to the target page; a missing/stale Back target must not render the entire set. */
function tadlBrowseResultStart($result, $start, $pageSize, $rowID = 0) {
	if ($rowID) {
		$position = array_search($rowID, array_values($result->getPrimaryKeyValues()));
		if ($position !== false) { $start = (int)floor($position / $pageSize) * $pageSize; }
	}
	return min(max(0, (int)$start), max(0, ((int)ceil($result->numHits() / $pageSize) - 1) * $pageSize));
}

/** Only the displayed page needs format inventory or thumbnail queries. */
function tadlBrowseResultPageIDs($result, $start, $pageSize, $rowID = 0) {
	$start = tadlBrowseResultStart($result, $start, $pageSize, $rowID);
	return array_slice(array_values($result->getPrimaryKeyValues()), $start, $pageSize);
}

function tadlBrowseResultRoute($request, $params) {
	$params += ['per_page' => tadlResultPageChoice($request, $params['view'] ?? null)];
	if (in_array($request->getController(), ['FamilyContents', 'PlaceContents'], true)) {
		$params = array_intersect_key($params, array_flip(['view', 'sort', 'direction', 's', 'n', 'per_page']));
		$key = $request->getController() === 'FamilyContents' ? 'entity_id' : 'place_id';
		$params[$key] = (int)$request->getParameter($key, pInteger);
		return ['', $request->getController(), 'Objects', $params];
	}
	if ($id = tadlBrowseResultCollectionID($request)) {
		$params = array_intersect_key($params, array_flip(['view', 'sort', 'direction', 's', 'n', 'per_page']));
		$params['collection_view'] = $request->getParameter('collection_view', pString) === 'hierarchy' ? 'hierarchy' : 'flat';
		return ['', 'Detail', 'collections/'.$id, $params];
	}
	// Embedded authority previews have no native browse cache key. Carry their
	// fixed query into ordinary links rather than depending on session history.
	if ($request->getController() === 'Search' && empty($params['key']) && ($search = $request->getParameter('search', pString))) {
		$params['search'] = $search;
		$params['_advanced'] = 0;
	}
	return ['*', '*', '*', $params];
}

function tadlBrowseResultLink($request, $label, $class, $params, array $attributes = []) {
	[$module, $controller, $action, $params] = tadlBrowseResultRoute($request, $params);
	if (in_array($params['view'] ?? '', ['images', 'list'], true)) { $attributes['data-tadl-view'] = $params['view']; }
	return caNavLink($request, $label, $class, $module, $controller, $action, $params, $attributes);
}

function tadlBrowsePerPagePicker($request, $total, $size, $key, $view, $sort, $direction, $advanced) {
	[$module, $controller, $action, $params] = tadlBrowseResultRoute($request, [
		'key' => $key, 'view' => $view, 'sort' => $sort, 'direction' => $direction, '_advanced' => $advanced ? 1 : 0, 's' => 0
	]);
	return tadlPerPagePicker($request, $total, $size, [$module, $controller, $action], $params);
}

if (!function_exists('tadlBrowseResultPager')) {
	function tadlBrowseResultPager($po_request, $pn_total, $pn_start, $pn_per_page, $ps_browse_key, $ps_view, $ps_sort, $ps_sort_dir, $pb_is_advanced, $position = 'bottom') {
		$pn_total = (int)$pn_total;
		$pn_start = max(0, (int)$pn_start);
		$pn_per_page = max(1, (int)$pn_per_page);
		if ($pn_total <= $pn_per_page) { return ''; }

		$vn_current_page = (int)floor($pn_start / $pn_per_page) + 1;
		$vn_total_pages = (int)ceil($pn_total / $pn_per_page);
		$va_base_params = array(
			'key' => $ps_browse_key,
			'view' => $ps_view,
			'sort' => $ps_sort,
			'direction' => $ps_sort_dir,
			'n' => $pn_per_page,
			'_advanced' => $pb_is_advanced ? 1 : 0
		);

		$va_pages = array(1, $vn_current_page - 1, $vn_current_page, $vn_current_page + 1, $vn_total_pages);
		$va_pages = array_values(array_unique(array_filter($va_pages, function($pn_page) use ($vn_total_pages) {
			return ($pn_page >= 1) && ($pn_page <= $vn_total_pages);
		})));
		sort($va_pages);

		$vs_output = "<nav class='tadl-results-pagination' aria-label='".($position === 'top' ? _t('Results pagination above results') : _t('Results pagination below results'))."'>";
		$vs_output .= "<span class='tadl-results-page-status'>"._t('Page %1 of %2', $vn_current_page, $vn_total_pages)."</span>";

		if ($vn_current_page > 1) {
			$vs_output .= tadlBrowseResultLink($po_request, _t('Previous'), 'btn btn-default tadl-results-page', array_merge($va_base_params, array('s' => max(0, $pn_start - $pn_per_page))));
		} else {
			$vs_output .= "<span class='btn btn-default tadl-results-page disabled' aria-disabled='true'>"._t('Previous')."</span>";
		}

		$vn_previous_page = null;
		foreach($va_pages as $vn_page) {
			if ($vn_previous_page && ($vn_page > ($vn_previous_page + 1))) {
				$vs_output .= "<span class='tadl-results-page-gap' aria-hidden='true'>...</span>";
			}
			if ($vn_page == $vn_current_page) {
				$vs_output .= "<span class='btn btn-default tadl-results-page active' aria-current='page'>".$vn_page."</span>";
			} else {
				$vs_output .= tadlBrowseResultLink($po_request, (string)$vn_page, 'btn btn-default tadl-results-page', array_merge($va_base_params, array('s' => ($vn_page - 1) * $pn_per_page)));
			}
			$vn_previous_page = $vn_page;
		}

		if ($vn_current_page < $vn_total_pages) {
			$vs_output .= tadlBrowseResultLink($po_request, _t('Next'), 'btn btn-default tadl-results-page', array_merge($va_base_params, array('s' => $pn_start + $pn_per_page)));
		} else {
			$vs_output .= "<span class='btn btn-default tadl-results-page disabled' aria-disabled='true'>"._t('Next')."</span>";
		}

		return $vs_output."</nav>";
	}
}

if (!function_exists('tadlBrowseResultPageSize')) {
	function tadlBrowseResultPageSize($ps_view, $request = null, $total = null) {
		switch($ps_view) {
			case 'images':
			case 'list':
				return $request ? tadlResultPageSize($request, $total, $ps_view) : ($ps_view === 'list' ? 25 : 18);
			default:
				return null;
		}
	}
}

if (!function_exists('tadlBrowseResultViewControls')) {
	function tadlBrowseResultViewControls($po_request, $pa_views, $ps_current_view, $ps_browse_key, $ps_sort, $ps_sort_dir, $pn_hits_per_block, $pb_is_advanced) {
		if (!is_array($pa_views) || (sizeof($pa_views) <= 1)) { return ''; }

		$va_view_labels = array(
			'images' => _t('Tiles'),
			'list' => _t('List')
		);
		$va_view_icons = array(
			'images' => 'glyphicon-th',
			'list' => 'glyphicon-list'
		);

		$vs_output = "<div class='tadl-results-view-buttons' aria-label='"._t('Result display options')."'>";
		foreach($va_view_labels as $vs_view => $vs_view_label) {
			if (!isset($pa_views[$vs_view])) { continue; }

			$vs_icon = $va_view_icons[$vs_view];
			$vs_link_content = '<span class="glyphicon '.$vs_icon.'" aria-hidden="true"></span> '.$vs_view_label;
			if ($ps_current_view === $vs_view) {
				$vs_output .= '<span class="btn btn-default tadl-result-view-toggle active" aria-current="true" aria-disabled="true">'.$vs_link_content.'</span>';
			} else {
				$vs_output .= tadlBrowseResultLink(
					$po_request,
					$vs_link_content,
					'btn btn-default tadl-result-view-toggle',
					array(
						'view' => $vs_view,
						'key' => $ps_browse_key,
						'sort' => $ps_sort,
						'direction' => $ps_sort_dir,
						'n' => (int)tadlPreferredPageChoice($po_request, $vs_view),
						'per_page' => tadlPreferredPageChoice($po_request, $vs_view),
						'_advanced' => $pb_is_advanced ? 1 : 0,
						's' => 0
					)
				);
			}
		}

		return $vs_output."</div>";
	}
}
