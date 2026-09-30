<?php
/* ----------------------------------------------------------------------
 * views/pageFormat/browseMenuFacets.php : 
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
 
	$vs_target = $this->getVar("target");
	$vs_name = $this->getVar("browse_name");
	$mediaMode = tadlMediaPreference($this->request);
	$vs_facet_list = '';
	$browseInfo = caGetInfoForBrowseType($vs_target);
	if (is_array($browseInfo)) {
		$browse = caGetBrowseInstance($browseInfo['table']);
		$browseConfig = caGetBrowseConfig();
		if (!empty($browseInfo['facetGroup'])) {
			$browse->setFacetGroup($browseConfig->get('menubarFacetGroup') ?: $browseInfo['facetGroup']);
		}
		if (!empty($browseInfo['restrictToTypes'])) {
			$browse->setTypeRestrictions($browseInfo['restrictToTypes']);
		}
		$browse->execute(array('checkAccess' => caGetUserAccessValues($this->request), 'showAllForNoCriteriaBrowse' => true));
		$menuFacets = $browse->getInfoForAvailableFacets();
		$defaultURL = null;
		foreach ($menuFacets as $facetName => $facetInfo) {
			$url = caNavUrl($this->request, '*', 'Browse', $vs_target, array(
				'media' => $mediaMode, 'facet' => $facetName, 'getFacet' => 1, 'key' => 'null', 'isNav' => 1
			), array('useQueryString' => true));
			$active = $defaultURL ? '' : ' class="active"';
			if (!$defaultURL) { $defaultURL = $url; }
			$script = 'jQuery(".browseMenuFacet").load('.json_encode($url).', function() { jQuery(this).parent().scrollTop(0); }); '
				.'jQuery(this).parent().siblings().removeClass("active"); jQuery(this).parent().addClass("active"); return false;';
			$vs_facet_list .= '<li'.$active.'><a href="#" onclick="'.htmlspecialchars($script, ENT_QUOTES, 'UTF-8').'">'
				.htmlspecialchars(caUcFirstUTF8Safe($facetInfo['label_plural']), ENT_QUOTES, 'UTF-8').'</a></li>';
		}
		if ($defaultURL) {
			$vs_facet_list .= '<script>jQuery(function() { jQuery(".browseMenuFacet").load('
				.json_encode($defaultURL, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT).'); });</script>';
		}
	}
	
	if($vs_facet_list) {
?>			
	<div class="filterMenuFacetList col-sm-2">
		<div class="filterbylabel"><?php print _t("Browse by"); ?></div>
		<ul>
			<?php print $vs_facet_list; ?>
		</ul>
	</div>
		
	<div class="filterMenuResults col-sm-10">
		<div class='browseMenuFacet row'> </div>	
	</div>	
	<div class="browsedivider">&nbsp;</div>

	<div class="container">
		<div class='browseMenuBrowseSearch'>
			<form><input type="text" size="20" id="browseMenuFacetSearchInput" class="browseMenuFacetSearchInput form-control" placeholder="<?php print addslashes(_t('Filter facet')); ?>"/></form>
		</div> <!--end browseMenuSearch-->
		<div class='browseMenuBrowseAll'>
<?php
		print caNavLink($this->request, _t('Browse all %1 &nbsp;<span class="glyphicon glyphicon-arrow-right"></span>', $vs_name), 'browseMenuBrowseAll btn btn-default btn-sm', '', 'Browse', $vs_target, array('media' => $mediaMode));
?>
		</div> <!--end browseMenuAll-->
	</div><!--end container-->	
<?php
	} else {
		print _t('No facets available');
	}
?>

<script type="text/javascript">
	jQuery(document).ready(function() {
		jQuery("#browseMenuFacetSearchInput").on("keyup", function(e) {
			var s = jQuery(this).val().toLowerCase();
			jQuery(".browseMenuFacet div.browseFacetItem").each(function(k, v) {
				var item = jQuery(v).find("a").text().toLowerCase();
				(item.indexOf(s) == -1) ? jQuery(v).hide() : jQuery(v).show();
			});
		}).on("focus click", function(e) { jQuery(this).val("").trigger("keyup"); });
	});
</script>
