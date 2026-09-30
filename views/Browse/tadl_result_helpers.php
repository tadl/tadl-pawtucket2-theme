<?php
if (!function_exists('tadlBrowseResultPager')) {
	function tadlBrowseResultPager($po_request, $pn_total, $pn_start, $pn_per_page, $ps_browse_key, $ps_view, $ps_sort, $ps_sort_dir, $pb_is_advanced) {
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

		$vs_output = "<nav class='tadl-results-pagination' aria-label='"._t('Results pagination')."'>";
		$vs_output .= "<span class='tadl-results-page-status'>"._t('Page %1 of %2', $vn_current_page, $vn_total_pages)."</span>";

		if ($vn_current_page > 1) {
			$vs_output .= caNavLink($po_request, _t('Previous'), 'btn btn-default tadl-results-page', '*', '*', '*', array_merge($va_base_params, array('s' => max(0, $pn_start - $pn_per_page))));
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
				$vs_output .= caNavLink($po_request, (string)$vn_page, 'btn btn-default tadl-results-page', '*', '*', '*', array_merge($va_base_params, array('s' => ($vn_page - 1) * $pn_per_page)));
			}
			$vn_previous_page = $vn_page;
		}

		if ($vn_current_page < $vn_total_pages) {
			$vs_output .= caNavLink($po_request, _t('Next'), 'btn btn-default tadl-results-page', '*', '*', '*', array_merge($va_base_params, array('s' => $pn_start + $pn_per_page)));
		} else {
			$vs_output .= "<span class='btn btn-default tadl-results-page disabled' aria-disabled='true'>"._t('Next')."</span>";
		}

		return $vs_output."</nav>";
	}
}

if (!function_exists('tadlBrowseResultPageSize')) {
	function tadlBrowseResultPageSize($ps_view) {
		switch($ps_view) {
			case 'images':
				return 9;
			case 'list':
				return 24;
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
				$vs_output .= caNavLink(
					$po_request,
					$vs_link_content,
					'btn btn-default tadl-result-view-toggle',
					'*',
					'*',
					'*',
					array(
						'view' => $vs_view,
						'key' => $ps_browse_key,
						'sort' => $ps_sort,
						'direction' => $ps_sort_dir,
						'n' => tadlBrowseResultPageSize($vs_view) ?: $pn_hits_per_block,
						'_advanced' => $pb_is_advanced ? 1 : 0,
						's' => 0
					)
				);
			}
		}

		return $vs_output."</div>";
	}
}
