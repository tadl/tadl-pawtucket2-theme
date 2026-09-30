<?php
if (!function_exists('tadlCollectionIndexPager')) {
	function tadlCollectionIndexPager($po_request, $pn_page, $pn_total_pages, $ps_view) {
		if ($pn_total_pages <= 1) { return ''; }

		$link = function($page, $label, $aria_label) use ($po_request, $ps_view) {
			$url = caNavUrl($po_request, '', 'Collections', 'Index', ['page' => $page, 'view' => $ps_view, 'media' => tadlMediaPreference($po_request)]);
			return '<a class="btn btn-default tadl-collections-page" href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'" aria-label="'.htmlspecialchars($aria_label, ENT_QUOTES, 'UTF-8').'">'.$label.'</a>';
		};
		$pages = array_values(array_unique(array_filter([1, $pn_page - 1, $pn_page, $pn_page + 1, $pn_total_pages], function($page) use ($pn_total_pages) {
			return $page >= 1 && $page <= $pn_total_pages;
		})));
		sort($pages);

		$output = '<nav class="tadl-collections-pagination" aria-label="'.htmlspecialchars(_t('Collections pages'), ENT_QUOTES, 'UTF-8').'">';
		$output .= '<span class="tadl-results-page-status">'._t('Page %1 of %2', $pn_page, $pn_total_pages).'</span>';
		$output .= $pn_page > 1
			? $link($pn_page - 1, '&lsaquo;', _t('Previous page'))
			: '<span class="btn btn-default tadl-collections-page disabled" role="link" aria-disabled="true" aria-label="'.htmlspecialchars(_t('Previous page'), ENT_QUOTES, 'UTF-8').'">&lsaquo;</span>';

		$last_page = 0;
		foreach ($pages as $page) {
			if ($last_page && $page > $last_page + 1) {
				$output .= '<span class="tadl-collections-page-gap" aria-hidden="true">&hellip;</span>';
			}
			$output .= $page === $pn_page
				? '<span class="btn btn-default tadl-collections-page active" aria-current="page">'.$page.'</span>'
				: $link($page, (string)$page, _t('Page %1', $page));
			$last_page = $page;
		}

		$output .= $pn_page < $pn_total_pages
			? $link($pn_page + 1, '&rsaquo;', _t('Next page'))
			: '<span class="btn btn-default tadl-collections-page disabled" role="link" aria-disabled="true" aria-label="'.htmlspecialchars(_t('Next page'), ENT_QUOTES, 'UTF-8').'">&rsaquo;</span>';

		return $output.'</nav>';
	}
}
