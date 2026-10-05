<?php
/* ----------------------------------------------------------------------
 * views/Browse/browse_refine_subview_html.php : 
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2014-2015 Whirl-i-Gig
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
 *
 * This source code is free and modifiable under the terms of 
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * ----------------------------------------------------------------------
 */
 
	$va_facets 			= $this->getVar('facets');				// array of available browse facets
	$va_criteria 		= $this->getVar('criteria');			// array of browse criteria
	$vs_key 			= $this->getVar('key');					// cache key for current browse
	$va_access_values 	= $this->getVar('access_values');		// list of access values for this user
	$vs_view			= $this->getVar('view');
	$vs_browse_type		= $this->getVar('browse_type');
	$o_browse			= $this->getVar('browse');
	require_once(__DIR__.'/tadl_result_context_helpers.php');
	
	$vn_facet_display_length_initial = 7;
	$vn_facet_display_length_maximum = 60;
	// Resolve deferred facets and apply the display preference before emitting headings.
	$va_facets = is_array($va_facets) ? $va_facets : [];
	foreach ($va_facets as $vs_facet_name => &$va_facet_info) {
		$vb_hierarchy = caGetOption('deferred_load', $va_facet_info, false) || (($va_facet_info['group_mode'] ?? '') === 'hierarchical');
		$va_content = $vb_hierarchy && $o_browse
			? $o_browse->getFacet($vs_facet_name, ['checkAccess' => $va_access_values, 'request' => $this->request])
			: ($va_facet_info['content'] ?? []);
		$va_facet_info['content'] = tadlMediaFacetItems($this->request, $va_content, $va_facet_info);
		if (!is_array($va_facet_info['content']) || !$va_facet_info['content']) { unset($va_facets[$vs_facet_name]); }
	}
	unset($va_facet_info);
	
	if(is_array($va_facets) && sizeof($va_facets)){
		print "<div id='bMorePanel'><!-- long lists of facets are loaded here --></div>";
		print "<div id='bRefine'>";
		print "<a href='#' class='pull-right' id='bRefineClose' aria-label='"._t("Close filters")."' onclick='jQuery(\"#bRefine\").toggle(); jQuery(\"#bRefineButton\").attr(\"aria-expanded\", \"false\"); return false;'><span class='glyphicon glyphicon-remove-circle' aria-hidden='true'></span></a>";
		print "<H2>"._t("Filter by")."</H2>";
		foreach($va_facets as $vs_facet_name => $va_facet_info) {
			print "<h3>".htmlspecialchars(tadlResultFacetHeading($va_facet_info), ENT_QUOTES, 'UTF-8')."</h3>";
			if (caGetOption('deferred_load', $va_facet_info, false) || (($va_facet_info['group_mode'] ?? '') === 'hierarchical')) {
				$facet_dom_id = 'bHierarchyList_'.$vs_facet_name;
				$remote_dom_id = $facet_dom_id.'_remote';
				print '<div id="'.htmlspecialchars($facet_dom_id, ENT_QUOTES, 'UTF-8').'">';
				include __DIR__.'/refine_facet_values_html.php';
				print '</div>';
				print '<div id="'.htmlspecialchars($remote_dom_id, ENT_QUOTES, 'UTF-8').'" hidden></div>';
?>
				<script type="text/javascript">
					jQuery(document).ready(function() {
						var choices = jQuery(<?php print json_encode('#'.$facet_dom_id); ?>);
						var hierarchy = jQuery(<?php print json_encode('#'.$remote_dom_id); ?>);
						// A hierarchy root can yield only scripts, even when values exist.
						// Keep the usable links unless the response contains choices.
						hierarchy.load(<?php print json_encode(caNavUrl($this->request, '*', '*', 'getFacetHierarchyLevel', array('facet' => $vs_facet_name, 'browseType' => $vs_browse_type, 'key' => $vs_key, 'linkTo' => 'morePanel')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, function(response, status) {
							if ((status === 'success' || status === 'notmodified') && hierarchy.find('a').length) {
								choices.empty().append(hierarchy.contents());
							}
							hierarchy.remove();
						});
					});
				</script>
<?php
			} else {
				include __DIR__.'/refine_facet_values_html.php';
			}
		}
		print "</div><!-- end bRefine -->\n";
?>
	<script type="text/javascript">
		jQuery(document).ready(function() {
            if(jQuery('#browseResultsContainer').height() > jQuery(window).height()){
				var offset = jQuery('#bRefine').height(jQuery(window).height() - 30).offset();   // 0px top + (2 * 15px padding) = 30px
				var panelWidth = jQuery('#bRefine').width();
				jQuery(window).scroll(function () {
					var scrollTop = $(window).scrollTop();
					// check the visible top of the browser
					if (offset.top<scrollTop && ((offset.top + jQuery('#pageArea').height() - jQuery('#bRefine').height()) > scrollTop)) {
						jQuery('#bRefine').addClass('fixed');
						jQuery('#bRefine').width(panelWidth);
					} else {
						jQuery('#bRefine').removeClass('fixed');
					}
				});
            }
		});
	</script>
<?php	
	}
?>
