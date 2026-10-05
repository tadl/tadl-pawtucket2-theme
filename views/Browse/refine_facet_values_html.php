<?php
/** Available values shared by flat facets and the hierarchy fallback. */
$vn_facet_size = sizeof($va_facet_info['content']);
$vn_c = 0;
foreach($va_facet_info['content'] as $va_item) {
	$vs_content_count = (isset($va_item['content_count']) && ($va_item['content_count'] > 0)) ? " (".$va_item['content_count'].")" : "";
	print "<div>".caNavLink($this->request, htmlspecialchars($va_item['label'], ENT_QUOTES, 'UTF-8').$vs_content_count, '', '*', '*','*', array('key' => $vs_key, 'facet' => $vs_facet_name, 'id' => $va_item['id'], 'view' => $vs_view))."</div>";
	$vn_c++;

	if (($vn_c == $vn_facet_display_length_initial) && ($vn_facet_size > $vn_facet_display_length_initial) && ($vn_facet_size <= $vn_facet_display_length_maximum)) {
		print "<span id='{$vs_facet_name}_more' style='display: none;'>";
	} else {
		if(($vn_c == $vn_facet_display_length_initial) && ($vn_facet_size > $vn_facet_display_length_maximum))  {
			break;
		}
	}
}
if (($vn_facet_size > $vn_facet_display_length_initial) && ($vn_facet_size <= $vn_facet_display_length_maximum)) {
	print "</span>\n";

	$vs_link_open_text = _t("and %1 more", $vn_facet_size - $vn_facet_display_length_initial);
	$vs_link_close_text = _t("close", $vn_facet_size - $vn_facet_display_length_initial);
	print "<div><a href='#' class='more' id='{$vs_facet_name}_more_link' onclick='jQuery(\"#{$vs_facet_name}_more\").slideToggle(250, function() { jQuery(this).is(\":visible\") ? jQuery(\"#{$vs_facet_name}_more_link\").text(\"".addslashes($vs_link_close_text)."\") : jQuery(\"#{$vs_facet_name}_more_link\").text(\"".addslashes($vs_link_open_text)."\")}); return false;'><em>{$vs_link_open_text}</em></a></div>";
} elseif (($vn_facet_size > $vn_facet_display_length_initial) && ($vn_facet_size > $vn_facet_display_length_maximum)) {
	print "<div><a href='#' class='more' onclick='jQuery(\"#bMorePanel\").load(\"".caNavUrl($this->request, '*', '*', '*', array('getFacet' => 1, 'facet' => $vs_facet_name, 'view' => $vs_view, 'key' => $vs_key))."\", function(){jQuery(\"#bMorePanel\").show(); jQuery(\"#bMorePanel\").mouseleave(function(){jQuery(\"#bMorePanel\").hide();});}); return false;'><em>"._t("and %1 more", $vn_facet_size - $vn_facet_display_length_initial)."</em></a></div>";
}
