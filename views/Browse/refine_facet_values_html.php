<?php
/** Available values shared by flat facets and the hierarchy fallback. */
$vn_facet_size = sizeof($va_facet_info['content']);
$vn_c = 0;
$escape_facet = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$vs_more_id = $vs_facet_name.'_more';
$vs_facet_heading = tadlResultFacetHeading($va_facet_info);
foreach($va_facet_info['content'] as $va_item) {
	$vs_content_count = (isset($va_item['content_count']) && ($va_item['content_count'] > 0)) ? ' ('.(int)$va_item['content_count'].')' : '';
	print "<div>".caNavLink($this->request, htmlspecialchars($va_item['label'], ENT_QUOTES, 'UTF-8').$vs_content_count, '', '*', '*','*', array('key' => $vs_key, 'facet' => $vs_facet_name, 'id' => $va_item['id'], 'view' => $vs_view))."</div>";
	$vn_c++;

	if (($vn_c == $vn_facet_display_length_initial) && ($vn_facet_size > $vn_facet_display_length_initial) && ($vn_facet_size <= $vn_facet_display_length_maximum)) {
		print '<div id="'.$escape_facet($vs_more_id).'" class="tadl-facet-extra-values">';
	} else {
		if(($vn_c == $vn_facet_display_length_initial) && ($vn_facet_size > $vn_facet_display_length_maximum))  {
			break;
		}
	}
}
if (($vn_facet_size > $vn_facet_display_length_initial) && ($vn_facet_size <= $vn_facet_display_length_maximum)) {
	print "</div>\n";
	$vs_link_open_text = _t('View %1 more', $vn_facet_size - $vn_facet_display_length_initial);
	$vs_link_close_text = _t('View less');
	$vs_open_accessible = _t('%1: %2', $vs_facet_heading, $vs_link_open_text);
	$vs_close_accessible = _t('%1: %2', $vs_facet_heading, $vs_link_close_text);
	print '<div class="tadl-facet-actions" data-tadl-inline-facet-actions><button type="button" class="btn btn-default tadl-facet-toggle" data-tadl-facet-toggle';
	print ' id="'.$escape_facet($vs_facet_name.'_more_link').'" aria-controls="'.$escape_facet($vs_more_id).'" aria-expanded="false"';
	print ' aria-label="'.$escape_facet($vs_open_accessible).'" data-label-more="'.$escape_facet($vs_link_open_text).'" data-label-less="'.$escape_facet($vs_link_close_text).'"';
	print ' data-label-more-accessible="'.$escape_facet($vs_open_accessible).'" data-label-less-accessible="'.$escape_facet($vs_close_accessible).'">';
	print '<i class="fa fa-angle-down" aria-hidden="true"></i><span>'.$escape_facet($vs_link_open_text).'</span></button></div>';
} elseif (($vn_facet_size > $vn_facet_display_length_initial) && ($vn_facet_size > $vn_facet_display_length_maximum)) {
	$vs_all_label = _t('View all %1', $vn_facet_size);
	$vs_all_url = caNavUrl($this->request, '*', '*', '*', ['getFacet'=>1, 'facet'=>$vs_facet_name, 'view'=>$vs_view, 'key'=>$vs_key]);
	print '<div class="tadl-facet-actions"><a class="btn btn-default tadl-facet-toggle" data-tadl-facet-choices aria-controls="bMorePanel"';
	print ' href="'.$escape_facet($vs_all_url).'" aria-label="'.$escape_facet(_t('%1: %2', $vs_facet_heading, $vs_all_label)).'">';
	print '<i class="fa fa-list-ul" aria-hidden="true"></i><span>'.$escape_facet($vs_all_label).'</span></a></div>';
}
