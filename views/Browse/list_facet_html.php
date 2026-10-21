<?php
/* ----------------------------------------------------------------------
 * views/Browse/list_facet_html.php : 
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2014-2022 Whirl-i-Gig
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
 
	# -----------------------------------------------------------
	# --- facet view for group_mode = alphabetical, none
	# -----------------------------------------------------------

	$va_facet_content = 	$this->getVar('facet_content');
	$vs_facet_name = 		$this->getVar('facet_name');
	$vs_view = 				$this->getVar('view');
	$vs_key = 				$this->getVar('key');
	$va_facet_info = 		$this->getVar("facet_info");
	$va_facet_content = tadlMediaFacetItems($this->request, $va_facet_content, $va_facet_info);
	$vb_is_nav = 			(bool)$this->getVar('isNav');
	$vn_start =				$this->getVar('start');
	$vn_items_per_page =	$this->getVar('limit');
	$vn_facet_size =		$this->getVar('facet_size');
	
	$vn_c = 0;
	

	if($vb_is_nav){
		if ($vn_start == 0) {
?>
			<div id="panel_<?php print $vs_facet_name; ?>" class="row">
<?php
		}
		foreach($va_facet_content as $vn_id => $va_item) {
		    $vs_content_count = (isset($va_item['content_count']) && ($va_item['content_count'] > 0)) ? " (".$va_item['content_count'].")" : "";
			print "<div class='browseFacetItem col-sm-4 col-md-3'>".caNavLink($this->request, $va_item['label'].$vs_content_count, '', '*', '*', '*', array('facet' => $vs_facet_name, 'id' => $va_item['id'], 'view' => $vs_view, 'key' => $vs_key))."</div>";
			$vn_c++;
			
			if ($vn_c >= $vn_items_per_page) { break; }
		}
		
		if ((int)$vn_facet_size >= ((int)$vn_start + (int)$vn_items_per_page)) {
			print caNavLink($this->request, caBusyIndicatorIcon($this->request).' '._t('Loading'), 'caNextPage', '*', '*', '*', array('facet' => $vs_facet_name, 'getFacet' => 1, 'key' => $vs_key, 'isNav' => $vb_is_nav ? 1 : 0, 's' => (int)$vn_start + (int)$vn_items_per_page));
		}
		if ($vn_start == 0) {
?>
			</div>
			<script type="text/javascript">
				jQuery(document).ready(function() {
					jQuery("#panel_<?php print $vs_facet_name; ?>").jscroll({
						loadingHtml: "<div class='browseFacetItem col-sm-4 col-md-3'><a href='#'><?php print addslashes(caBusyIndicatorIcon($this->request).' '._t('Loading...')); ?></a></div>",
						padding: 1000,
						nextSelector: '.caNextPage',
						debug: true
					});
				});	
			</script>
<?php
		}
		
	} else {
		$escape_facet = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		$vb_alphabetical = ($va_facet_info['group_mode'] ?? '') === 'alphabetical';
		$va_groups = [];
		foreach ($va_facet_content as $va_item) {
			$vs_first_letter = '';
			if ($vb_alphabetical) {
				$vs_sort_field = $va_facet_info['order_by_label_fields'][0] ?? '';
				foreach ([$va_item[$vs_sort_field] ?? '', $va_item['label_sort_'] ?? '', $va_item['label']] as $vs_sort_label) {
					$vs_first_letter = mb_strtoupper(mb_substr(trim((string)$vs_sort_label), 0, 1));
					if ($vs_first_letter !== '') { break; }
				}
			}
			$va_groups[$vs_first_letter][] = $va_item;
		}
?>
		<div class="tadl-facet-browser">
			<div class="tadl-facet-browser-header">
				<h2 id="bScrollListLabel"><?php print $escape_facet($va_facet_info['label_plural']); ?><span class="bFilterCount"> <?php print $escape_facet(_t('(%1 total)', count($va_facet_content))); ?></span></h2>
				<button type="button" class="tadl-dialog-close" id="bMorePanelClose" data-tadl-facet-close aria-label="<?php print $escape_facet(_t('Close filter choices')); ?>"><span class="glyphicon glyphicon-remove-circle" aria-hidden="true"></span></button>
			</div>
<?php
		if ($vb_alphabetical && $va_groups) {
			print '<nav id="bLetterBar" aria-label="'.$escape_facet(_t('Jump to a letter')).'">';
			$vn_group = 0;
			foreach ($va_groups as $vs_letter => $va_items) {
				// Numeric IDs also work for punctuation, non-Latin letters and CSS metacharacters.
				print '<a href="#tadl-facet-group-'.$vn_group++.'" data-tadl-facet-jump>'.$escape_facet($vs_letter).'</a>';
			}
			print '</nav>';
		}
		print '<div id="bScrollList" tabindex="0" role="region" aria-labelledby="bScrollListLabel">';
		$vn_group = 0;
		foreach ($va_groups as $vs_letter => $va_items) {
			if ($vb_alphabetical) {
				print '<h3 id="tadl-facet-group-'.$vn_group++.'" tabindex="-1">'.$escape_facet($vs_letter).'</h3>';
			}
			foreach ($va_items as $va_item) {
				$vs_content_count = !empty($va_item['content_count']) && $va_item['content_count'] > 0 ? ' ('.(int)$va_item['content_count'].')' : '';
				print '<div>'.caNavLink($this->request, $escape_facet($va_item['label']).$vs_content_count, '', '*', '*', '*', ['facet'=>$vs_facet_name, 'id'=>$va_item['id'], 'view'=>$vs_view, 'key'=>$vs_key])."</div>\n";
			}
		}
		print '</div></div>';
	}
?>
