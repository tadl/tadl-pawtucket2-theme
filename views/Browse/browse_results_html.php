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
	
	$vb_is_search		= in_array($this->request->getController(), ['Search', 'CollectionContents', 'FamilyContents', 'PlaceContents'], true);
	$vb_subject_browse = !$vb_is_search && ($this->getVar('browse_type') === 'subjects');

	$va_options			= $this->getVar('options');
	$vs_extended_info_template = caGetOption('extendedInformationTemplate', $va_options, null);
	$vb_fragment			= (bool)$this->getVar('tadl_embedded_results') || (bool)$this->request->isAjax();
	$va_browse_info = $this->getVar("browseInfo");
	if (
		!$this->request->getParameter('view', pString, ['forcePurify' => true])
		&& ($vs_default_view = caGetOption('defaultView', $va_browse_info, null))
		&& isset($va_views[$vs_default_view])
	) {
		$vs_current_view = $vs_default_view;
	}
	$vn_result_size 	= (int)$qr_res->numHits();
	if ($this->getVar('tadl_embedded_results') === 'authority' && !$vn_result_size) {
		$message = $vs_media_preference === 'only' ? _t('No related items with digital media are currently available.') : _t('No related items are currently available.');
		print '<p class="tadl-authority-empty">'.htmlspecialchars($message, ENT_QUOTES, 'UTF-8').'</p>';
		return;
	}
	$o_config = $this->getVar("config");
	$vs_result_col_class = $o_config->get('result_col_class');
	$vs_refine_col_class = $o_config->get('refine_col_class');
	$va_browse_type_info = $o_config->get($va_browse_info["table"]);
	$va_all_facets = (array)($va_browse_type_info['facets'] ?? []);
	if ($o_browse = $this->getVar('browse')) { $va_all_facets = $o_browse->getInfoForFacets(); }
	require_once(__DIR__.'/tadl_result_helpers.php');
	require_once(__DIR__.'/tadl_result_context_helpers.php');
	$preferred_view = tadlDisplayView($this->request, $vs_current_view);
	if (in_array($preferred_view, ['images', 'list'], true) && isset($va_views[$preferred_view])) { $vs_current_view = $preferred_view; }
	$this->setVar('view', $vs_current_view);
	$this->setVar('tadl_collapsible_filters', true);
	// Family/place results have a fixed relationship scope, with sort/view/paging controls.
	$vs_refine_html = !$vb_fragment && !in_array($this->request->getController(), ['FamilyContents', 'PlaceContents'], true)
		? $this->render('Browse/browse_refine_subview_html.php') : '';
	$vb_has_filters = trim($vs_refine_html) !== '';
	$vs_filter_state = tadlDisplayPreferences($this->request)['filters'] ?? 'auto';
	$va_result_context = null;
	$vs_facet_description = null;
	if (!$vb_fragment) {
		$va_criteria = tadlResultDisplayCriteria($this->request, $va_criteria, $va_all_facets);
		$va_result_context = tadlResultContext($vs_table, $va_criteria);
	}
	$vb_collection_controls = $vb_fragment && $vb_is_search && $vs_table === 'ca_objects'
		&& $this->request->getParameter('tadl_collection_controls', pInteger) === 1;
	ob_start();
	include __DIR__.'/result_options_html.php';
	$vs_tadl_result_options = ob_get_clean();
	if ($vb_fragment && $vb_is_search && !$vb_collection_controls) {
		// This summary is outside the cached cards and reflects the media-filtered result.
		print '<p class="tadl-related-results-summary" data-tadl-result-count="'.(int)$vn_result_size.'">'.htmlspecialchars(tadlResultItemCount($vn_result_size), ENT_QUOTES, 'UTF-8').'</p>';
	}
	$vn_tadl_page_size = tadlBrowseResultPageSize($vs_current_view, $this->request, $vn_result_size);
	if ($vn_tadl_page_size) {
		$vn_start = min(max(0, $vn_start), max(0, ((int)ceil($vn_result_size / $vn_tadl_page_size) - 1) * $vn_tadl_page_size));
		$this->setVar('start', $vn_start);
		$this->setVar('tadl_page_size', $vn_tadl_page_size);
	}
	$per_page_picker = $vn_tadl_page_size ? tadlBrowsePerPagePicker($this->request, $vn_result_size, $vn_tadl_page_size, $vs_browse_key, $vs_current_view, $vs_current_sort, $vs_sort_dir, $vn_is_advanced) : '';
	tadlMediaResultContext($this, $qr_res, $vs_find_type, $vb_is_search ? $this->getVar('browse_type') : null);
	if ($this->request->getParameter('source', pString) === 'multisearch') {
		tadlMediaResultContext($this, $qr_res, 'multisearch', $this->getVar('browse_type'));
	}
	$vs_tadl_result_view_controls = tadlBrowseResultViewControls($this->request, $va_views, $vs_current_view, $vs_browse_key, $vs_current_sort, $vs_sort_dir, $vn_hits_per_block_param, $vn_is_advanced ? true : false);
	if ($vb_collection_controls) {
		print '<div class="tadl-collection-results-toolbar">';
		print '<div class="tadl-collection-results-intro">';
		print '<div class="tadl-collection-results-heading"><h2>'._t('Collection items').'</h2>';
		print '<p class="tadl-related-results-summary" data-tadl-result-count="'.(int)$vn_result_size.'">'.htmlspecialchars(tadlResultItemCount($vn_result_size), ENT_QUOTES, 'UTF-8').'</p></div>';
		print $per_page_picker.'</div>';
		print '<div class="tadl-collection-results-options">'.$vs_tadl_result_options.$vs_tadl_result_view_controls.'</div>';
		if ($vn_tadl_page_size) {
			print '<div class="tadl-results-top-pager">'.tadlBrowseResultPager($this->request, $vn_result_size, $vn_start, $vn_tadl_page_size, $vs_browse_key, $vs_current_view, $vs_current_sort, $vs_sort_dir, $vn_is_advanced ? true : false, 'top').'</div>';
		}
		print '</div>';
	}

	
if (!$vb_fragment) {	// full page
?>
<?php if ($vb_subject_browse) { ?>
<p><?php print caNavLink($this->request, _t('Browse all subjects'), '', '', 'Browse', 'subjects', ['clear' => 1]); ?></p>
<?php } ?>
<div style="clear:both;" class="row<?= $vb_subject_browse ? ' tadl-subject-browse' : ''; ?><?= $vb_has_filters ? ' tadl-filter-layout' : ''; ?>"<?php if ($vb_has_filters) { print ' data-tadl-filter-layout data-tadl-filter-state="'.$vs_filter_state.'"'; } ?>>
	<div class='<?php print !$vb_has_filters ? "col-sm-12" : (($vs_result_col_class ?: "col-sm-8 col-md-8 col-lg-8").' tadl-results-content'); ?>'>
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
			<?php print $vs_tadl_result_options.$per_page_picker; ?>
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
		if (count($va_criteria) > ($vb_is_search ? 1 : 0)) {
			print caNavLink($this->request, _t('Start over'), 'tadl-filter-reset', '*', '*', '*', [
				'view' => $vs_current_view, 'key' => $vs_browse_key, 'clear' => 1, '_advanced' => $vn_is_advanced ? 1 : 0
			]);
		}
?>		
		</div>
			</div>
			<div class="tadl-results-tools">
<?php
		if ($vs_tadl_result_view_controls || $vb_has_filters) {
			print '<div class="tadl-results-display-controls">'.$vs_tadl_result_view_controls;
			if ($vb_has_filters) {
				$vs_show_filters_label = htmlspecialchars(_t('Show filters'), ENT_QUOTES, 'UTF-8');
				print '<button type="button" id="bRefineButton" class="btn btn-default tadl-results-filter" aria-controls="bRefine" aria-expanded="false" aria-label="'.$vs_show_filters_label.'" title="'.$vs_show_filters_label.'" hidden><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 4h18l-7 8v7l-4 2v-9z"/></svg> '._t('Filters').'</button>';
			}
			print '</div>';
		}
		if ($vn_tadl_page_size) {
			print "<div class='tadl-results-top-pager'>";
			print tadlBrowseResultPager($this->request, $vn_result_size, $vn_start, $vn_tadl_page_size, $vs_browse_key, $vs_current_view, $vs_current_sort, $vs_sort_dir, $vn_is_advanced ? true : false, 'top');
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
} // full page

// Render current permissions and preference on every request; never persist private HTML.
print $this->render('Browse/browse_results_'.$vs_current_view.'_html.php');

if (!$vb_fragment) {	// full page
?>
			</div><!-- end browseResultsContainer -->
		</div><!-- end row -->
	</div><!-- end col-8 -->
	<?php if ($vb_has_filters): ?>
	<div class="tadl-filter-sidebar <?php print ($vs_refine_col_class) ? $vs_refine_col_class : "col-sm-4 col-md-3 col-md-offset-1 col-lg-3 col-lg-offset-1"; ?>">
<?php
		print $vs_refine_html;
?>			
	</div><!-- end col-2 -->
	<?php endif; ?>
	
	
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
} // full page
?>
