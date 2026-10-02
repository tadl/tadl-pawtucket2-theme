<?php
/* ----------------------------------------------------------------------
 * views/Browse/browse_results_html.php : 
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2014 Whirl-i-Gig
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

	// The subject landing page lists vocabulary choices, never unfiltered objects.
	if ($this->request->getController() === 'Browse' && $this->getVar('browse_type') === 'subjects') {
		$subject_criteria = array_filter((array)$this->getVar('criteria'), static function ($criterion) {
			return ($criterion['facet_name'] ?? '') === 'term_facet';
		});
		if (!$subject_criteria) {
			print $this->render('Browse/subjects_index_html.php');
			return;
		}
	}
	$qr_res 			= $this->getVar('result');				// browse results (subclass of SearchResult)
	tadlFilterMediaResult($this->request, $qr_res);
	$vs_media_preference = tadlMediaPreference($this->request);
	$vs_find_type = ($this->getVar('find_type') ?: 'browse').($this->getVar('is_advanced') ? '_advanced' : '');
	$va_facets 			= $this->getVar('facets');				// array of available browse facets
	$va_criteria 		= (array)$this->getVar('criteria');		// array of browse criteria
	$vs_browse_key 		= $this->getVar('key');					// cache key for current browse
	$va_access_values 	= $this->getVar('access_values');		// list of access values for this user
	$vn_hits_per_block 	= (int)$this->getVar('hits_per_block');	// number of hits to display per block
	$vn_start		 	= (int)$this->getVar('start');			// offset to seek to before outputting results
	$vn_is_advanced		= (int)$this->getVar('is_advanced');
	$vb_showLetterBar	= (int)$this->getVar('showLetterBar');	
	$va_letter_bar		= $this->getVar('letterBar');	
	$vs_letter			= $this->getVar('letter');
	$vn_row_id 			= $this->request->getParameter('row_id', pInteger);
	
	$va_views			= $this->getVar('views');
	$vs_current_view	= $this->getVar('view');
	$va_view_icons		= $this->getVar('viewIcons');
	
	$vs_current_sort	= $this->getVar('sort');
	$vs_sort_dir		= $this->getVar('sort_direction');
	$vn_hits_per_block_param = $vn_hits_per_block ? $vn_hits_per_block : null;
	
	$vs_table 			= $this->getVar('table');
	$t_instance			= $this->getVar('t_instance');
	
	$vb_is_search		= ($this->request->getController() == 'Search');
	$vb_subject_browse = !$vb_is_search && ($this->getVar('browse_type') === 'subjects');

	$va_options			= $this->getVar('options');
	$vs_extended_info_template = caGetOption('extendedInformationTemplate', $va_options, null);
	$vb_ajax			= (bool)$this->request->isAjax();
	$va_browse_info = $this->getVar("browseInfo");
	if (
		!$this->request->getParameter('view', pString, ['forcePurify' => true])
		&& ($vs_default_view = caGetOption('defaultView', $va_browse_info, null))
		&& isset($va_views[$vs_default_view])
	) {
		$vs_current_view = $vs_default_view;
	}
	$vn_result_size 	= (int)$qr_res->numHits();
	$vs_sort_control_type = caGetOption('sortControlType', $va_browse_info, 'dropdown');
	$o_config = $this->getVar("config");
	$vs_result_col_class = $o_config->get('result_col_class');
	$vs_refine_col_class = $o_config->get('refine_col_class');
	$va_export_formats = $this->getVar('export_formats');
	$va_browse_type_info = $o_config->get($va_browse_info["table"]);
	$va_all_facets = (array)($va_browse_type_info['facets'] ?? []);
	if ($o_browse = $this->getVar('browse')) { $va_all_facets = $o_browse->getInfoForFacets(); }
	require_once(__DIR__.'/tadl_result_helpers.php');
	require_once(__DIR__.'/tadl_result_context_helpers.php');
	$va_result_context = null;
	$vs_facet_description = null;
	$va_sorts = [];
	if (!$vb_ajax) {
		$va_criteria = tadlResultDisplayCriteria($this->request, $va_criteria, $va_all_facets);
		$va_result_context = tadlResultContext($vs_table, $va_criteria);
	}
	if ($vb_ajax && $vb_is_search) {
		// This summary is outside the cached cards and reflects the media-filtered result.
		print '<p class="tadl-related-results-summary" data-tadl-result-count="'.(int)$vn_result_size.'">'.htmlspecialchars(tadlResultItemCount($vn_result_size), ENT_QUOTES, 'UTF-8').'</p>';
	}
	$vn_tadl_page_size = tadlBrowseResultPageSize($vs_current_view);
	if ($vn_tadl_page_size) {
		$vn_start = min(max(0, $vn_start), max(0, ((int)ceil($vn_result_size / $vn_tadl_page_size) - 1) * $vn_tadl_page_size));
		$this->setVar('start', $vn_start);
	}
	tadlMediaResultContext($this, $qr_res, $vs_find_type, $this->request->getController() === 'Search' ? $this->getVar('browse_type') : null);
	if ($this->request->getParameter('source', pString) === 'multisearch') {
		tadlMediaResultContext($this, $qr_res, 'multisearch', $this->getVar('browse_type'));
	}
	$vs_tadl_result_view_controls = tadlBrowseResultViewControls($this->request, $va_views, $vs_current_view, $vs_browse_key, $vs_current_sort, $vs_sort_dir, $vn_hits_per_block_param, $vn_is_advanced ? true : false);
	if ($vb_ajax && $vb_is_search && $vs_table === 'ca_objects'
		&& $this->request->getParameter('tadl_collection_controls', pInteger) === 1) {
		// Collection details request a compact header alongside the AJAX result cards.
		print '<div class="tadl-results-tools tadl-collection-results-tools">'.$vs_tadl_result_view_controls;
		if ($vn_tadl_page_size) {
			print '<div class="tadl-results-top-pager">'.tadlBrowseResultPager($this->request, $vn_result_size, $vn_start, $vn_tadl_page_size, $vs_browse_key, $vs_current_view, $vs_current_sort, $vs_sort_dir, $vn_is_advanced ? true : false).'</div>';
		}
		print '</div>';
	}
	
if (!$vb_ajax) {	// !ajax
?>
<?php if ($vb_subject_browse) { ?>
<p><?php print caNavLink($this->request, _t('Browse all subjects'), '', '', 'Browse', 'subjects', ['clear' => 1]); ?></p>
<?php } ?>
<div style="clear:both;" class="row<?= $vb_subject_browse ? ' tadl-subject-browse' : ''; ?>">
	<div class='<?php print ($vs_result_col_class) ? $vs_result_col_class : "col-sm-8 col-md-8 col-lg-8"; ?>'>
<?php 
			if($vs_sort_control_type == 'list'){
				if(is_array($va_sorts = $this->getVar('sortBy')) && sizeof($va_sorts)) {
					print "<div id='bSortByList'><ul><li><strong>"._t("Sort by:")."</strong></li>\n";
					$i = 0;
					foreach($va_sorts as $vs_sort => $vs_sort_flds) {
						$i++;
						if ($vs_current_sort === $vs_sort) {
							print "<li class='selectedSort'>{$vs_sort}</li>\n";
						} else {
							print "<li>".caNavLink($this->request, $vs_sort, '', '*', '*', '*', array('view' => $vs_current_view, 'key' => $vs_browse_key, 'sort' => $vs_sort, '_advanced' => $vn_is_advanced ? 1 : 0))."</li>\n";
						}
						if($i < sizeof($va_sorts)){
							print "<li class='divide'>&nbsp;</li>";
						}
					}
					print "<li>".caNavLink($this->request, '<span class="glyphicon glyphicon-sort-by-attributes'.(($vs_sort_dir == 'asc') ? '' : '-alt').'" aria-hidden="true"></span><span class="sr-only">'._t("Change sort direction").'</span>', '', '*', '*', '*', array('view' => $vs_current_view, 'key' => $vs_browse_key, 'direction' => (($vs_sort_dir == 'asc') ? _t("desc") : _t("asc")), '_advanced' => $vn_is_advanced ? 1 : 0))."</li>";
					print "</ul></div>\n";
				}
			}
?>
		<div class="tadl-results-header">
			<div class="tadl-results-title-block">
		<H1>
<?php
			$vs_result_label = ($vn_result_size === 1)
				? ($va_browse_info['labelSingular'] ?? $t_instance->getProperty('NAME_SINGULAR'))
				: ($va_browse_info['labelPlural'] ?? $t_instance->getProperty('NAME_PLURAL'));
			$vs_result_heading = $va_result_context['title'] ?? _t('%1 %2', $vn_result_size, $vs_result_label);
			print "<span class='tadl-results-title-text'>".htmlspecialchars($vs_result_heading, ENT_QUOTES, 'UTF-8')."</span>";
?>
		</H1>
		<?php if ($va_result_context) { print '<p class="tadl-results-context-count">'.htmlspecialchars(tadlResultItemCount($vn_result_size), ENT_QUOTES, 'UTF-8').'</p>'; } ?>
		<div class="tadl-results-title-actions">
			<div class="btn-group">
				<a href="#" class="tadl-results-action tadl-results-options" data-toggle="dropdown" aria-label="<?php print _t('Result options'); ?>" aria-haspopup="true" aria-expanded="false"><i class="fa fa-cog bGear" aria-hidden="true"></i><span class="tadl-results-action-label"><?php print _t('Options'); ?></span></a>
				<ul class="dropdown-menu" role="menu">
<?php
					if($vs_sort_control_type == 'dropdown'){
						if(is_array($va_sorts = $this->getVar('sortBy')) && sizeof($va_sorts)) {
							print "<li class='dropdown-header' role='menuitem'>"._t("Sort by:")."</li>\n";
							foreach($va_sorts as $vs_sort => $vs_sort_flds) {
								if ($vs_current_sort === $vs_sort) {
									print "<li role='menuitem'><a href='#'><em>{$vs_sort}</em></a></li>\n";
								} else {
									print "<li role='menuitem'>".caNavLink($this->request, $vs_sort, '', '*', '*', '*', array('view' => $vs_current_view, 'key' => $vs_browse_key, 'sort' => $vs_sort, '_advanced' => $vn_is_advanced ? 1 : 0))."</li>\n";
								}
							}
							print "<li class='divider' role='menuitem'></li>\n";
							print "<li class='dropdown-header' role='menuitem'>"._t("Sort order:")."</li>\n";
							print "<li role='menuitem'>".caNavLink($this->request, (($vs_sort_dir == 'asc') ? '<em>' : '')._t("Ascending").(($vs_sort_dir == 'asc') ? '</em>' : ''), '', '*', '*', '*', array('view' => $vs_current_view, 'key' => $vs_browse_key, 'direction' => 'asc', '_advanced' => $vn_is_advanced ? 1 : 0))."</li>";
							print "<li role='menuitem'>".caNavLink($this->request, (($vs_sort_dir == 'desc') ? '<em>' : '')._t("Descending").(($vs_sort_dir == 'desc') ? '</em>' : ''), '', '*', '*', '*', array('view' => $vs_current_view, 'key' => $vs_browse_key, 'direction' => 'desc', '_advanced' => $vn_is_advanced ? 1 : 0))."</li>";
						}
						
						if ((sizeof($va_criteria) > ($vb_is_search ? 1 : 0)) && is_array($va_sorts) && sizeof($va_sorts)) {
?>
						<li class="divider" role='menuitem'></li>
<?php
						}
					}
					if (sizeof($va_criteria) > ($vb_is_search ? 1 : 0)) {
						print "<li role='menuitem'>".caNavLink($this->request, _t("Start Over"), '', '*', '*', '*', array('view' => $vs_current_view, 'key' => $vs_browse_key, 'clear' => 1, '_advanced' => $vn_is_advanced ? 1 : 0))."</li>";
					}
					if(($vs_media_preference === 'all') && is_array($va_export_formats) && sizeof($va_export_formats)){
						// Native exports bypass theme filtering; offer them in All items mode.
						print "<li class='divider' role='menuitem'></li>\n";
						print "<li class='dropdown-header' role='menuitem'>"._t("Download results as:")."</li>\n";
						foreach($va_export_formats as $va_export_format){
							print "<li class='".$va_export_format["code"]."' role='menuitem'>".caNavLink($this->request, $va_export_format["name"], "", "*", "*", "*", array("view" => "pdf", "download" => true, "export_format" => $va_export_format["code"], "key" => $vs_browse_key))."</li>";
						}
					}
?>
				</ul>
			</div><!-- end btn-group -->
<?php
			if(is_array($va_facets) && sizeof($va_facets)){
?>
			<a href='#' id='bRefineButton' class='tadl-results-action tadl-results-filter' aria-controls='bRefine' aria-expanded='<?= $vb_subject_browse ? 'true' : 'false'; ?>' aria-label='<?php print _t("Toggle filters"); ?>' onclick='var expanded = jQuery("#bRefine").is(":visible"); jQuery("#bRefine").toggle(); jQuery(this).attr("aria-expanded", expanded ? "false" : "true"); return false;'><i class="fa fa-filter" aria-hidden="true"></i><span class="tadl-results-action-label"><?php print _t('Filters'); ?></span></a>
<?php
			}
?>
		</div>
		<div class='bCriteria tadl-active-filters'>
<?php
		if (sizeof($va_criteria) > 0) {
			foreach($va_criteria as $va_criterion) {
				print '<div class="tadl-filter-criterion"><strong>'.htmlspecialchars($va_criterion['facet'], ENT_QUOTES, 'UTF-8').':</strong>';
				if ($va_criterion['facet_name'] != '_search') {
					$vs_remove_filter_link = '<span class="sr-only">'.htmlspecialchars(_t('Remove filter'), ENT_QUOTES, 'UTF-8').': </span><span class="tadl-filter-chip-label">'.htmlspecialchars($va_criterion['value'], ENT_QUOTES, 'UTF-8').'</span><span class="tadl-filter-chip-remove" aria-hidden="true">&times;</span>';
					print caNavLink($this->request, $vs_remove_filter_link, 'browseRemoveFacet btn btn-default btn-sm tadl-filter-chip', '*', '*', '*', array('removeCriterion' => $va_criterion['facet_name'], 'removeID' => urlencode($va_criterion['id']), 'view' => $vs_current_view, 'key' => $vs_browse_key));
				}else{
					print '<span class="tadl-filter-context">'.(isset($va_criterion['tadl_authority']) || tadlResultRelatedSearchReference($va_criterion['id']) ? htmlspecialchars($va_criterion['value'], ENT_QUOTES, 'UTF-8') : $va_criterion['value']).'</span>';
					$vs_search = $va_criterion['value'];
					}
				print '</div>';
				$va_current_facet = $va_all_facets[$va_criterion['facet_name']] ?? [];
				if((sizeof($va_criteria) == 1) && !$vb_is_search && ($va_current_facet['show_description_when_first_facet'] ?? false) && ($va_current_facet['type'] ?? '') == 'authority' && ($va_criterion['tadl_authority'] ?? null)){
					$t_authority_table = new $va_current_facet["table"];
					$t_authority_table->load($va_criterion['id']);
					$vs_facet_description = $t_authority_table->get($va_current_facet["show_description_when_first_facet"]);
				}
			}
		}
?>		
		</div>
			</div>
			<div class="tadl-results-tools">
<?php
		print $vs_tadl_result_view_controls;
		if ($vn_tadl_page_size) {
			print "<div class='tadl-results-top-pager'>";
			print tadlBrowseResultPager($this->request, $vn_result_size, $vn_start, $vn_tadl_page_size, $vs_browse_key, $vs_current_view, $vs_current_sort, $vs_sort_dir, $vn_is_advanced ? true : false);
			print "</div>";
		}
?>
			</div>
		</div>
<?php
		if($vs_facet_description){
			print "<div class='bFacetDescription'>".$vs_facet_description."</div>";
		}

		if($vb_showLetterBar){
			print "<div id='bLetterBar'>";
			foreach(array_keys($va_letter_bar) as $vs_l){
				if(trim($vs_l)){
					print caNavLink($this->request, $vs_l, ($vs_letter == $vs_l) ? 'selectedLetter' : '', '*', '*', '*', array('key' => $vs_browse_key, 'l' => $vs_l))." ";
				}
			}
			print " | ".caNavLink($this->request, _t("All"), (!$vs_letter) ? 'selectedLetter' : '', '*', '*', '*', array('key' => $vs_browse_key, 'l' => 'all'));
			print "</div>";
		}
?>
		<div class="row">
			<div id="browseResultsContainer">
<?php
} // !ajax

# --- check if this result page has been cached
# --- key is MD5 of browse key, sort, sort direction, view, page/start, items per page, row_id
$vs_cache_key = md5('tadl_results_v5'.$vs_browse_key.$vs_current_sort.$vs_sort_dir.$vs_current_view.$vn_start.$vn_hits_per_block.$vn_row_id.$vs_letter.$vs_media_preference.serialize($va_access_values).serialize($qr_res->getPrimaryKeyValues()));
if(($o_config->get("cache_timeout") > 0) && ExternalCache::contains($vs_cache_key,'browse_results')){
	print ExternalCache::fetch($vs_cache_key, 'browse_results');
}else{
	$vs_result_page = $this->render("Browse/browse_results_{$vs_current_view}_html.php");
	ExternalCache::save($vs_cache_key, $vs_result_page, 'browse_results', $o_config->get("cache_timeout"));
	print $vs_result_page;
}		

if (!$vb_ajax) {	// !ajax
?>
			</div><!-- end browseResultsContainer -->
		</div><!-- end row -->
	</div><!-- end col-8 -->
	<div class="<?php print ($vs_refine_col_class) ? $vs_refine_col_class : "col-sm-4 col-md-3 col-md-offset-1 col-lg-3 col-lg-offset-1"; ?>">
<?php
		print $this->render("Browse/browse_refine_subview_html.php");
?>			
	</div><!-- end col-2 -->
	
	
</div><!-- end row -->

<script type="text/javascript">
	jQuery(document).ready(function() {
<?php
		if($vn_row_id){
?>
			window.setTimeout(function() {
				$("window,body,html").scrollTop( $("#row<?php print $vn_row_id; ?>").offset().top);
			}, 0);
<?php
		}
?>
	});

</script>
<?php
		print $this->render('Browse/browse_panel_subview_html.php');
} //!ajax
?>
