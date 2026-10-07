<?php
/* ----------------------------------------------------------------------
 * themes/default/views/bundles/ca_collections_default_html.php :
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2013-2022 Whirl-i-Gig
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

	require_once(__DIR__.'/detail_field_helpers.php');

	$t_item = $this->getVar("item");
	$va_access_values = (array)caGetUserAccessValues($this->request);
	$va_related_object_ids = (array)$t_item->get('ca_objects.object_id', array('returnAsArray' => true, 'checkAccess' => $va_access_values));
	$vb_show_single_related_object = sizeof($va_related_object_ids) === 1;
	if (tadlMediaPreference($this->request) === 'only') {
		$vb_show_single_related_object = (sizeof($va_related_object_ids) === 1) && (bool)tadlMediaEligibleIDs('ca_objects', $va_related_object_ids, $va_access_values);
	}
	$va_comments = $this->getVar("comments");
	$vn_comments_enabled = 	$this->getVar("commentsEnabled");
	$vn_share_enabled = 	$this->getVar("shareEnabled");
	$vn_pdf_enabled = 		$this->getVar("pdfEnabled");

	# --- get collections configuration
	$o_collections_config = caGetCollectionsConfig();
	$vb_show_hierarchy_viewer = true;
	if($o_collections_config->get("do_not_display_collection_browser")){
		$vb_show_hierarchy_viewer = false;
	}

	$collection_view = $this->request->getParameter('view', pString, ['forcePurify' => true]);
	if (!in_array($collection_view, ['images', 'list'], true)) { $collection_view = 'images'; }
	$collection_sorts = (array)(caGetBrowseConfig()->getAssoc('browseTypes')['objects']['sortBy'] ?? []);
	$collection_sort = $this->request->getParameter('sort', pString, ['forcePurify' => true]);
	if (!isset($collection_sorts[$collection_sort ?? ''])) { $collection_sort = 'Identifier'; }
	$collection_direction = $this->request->getParameter('direction', pString);
	if (!in_array($collection_direction, ['asc', 'desc'], true)) { $collection_direction = 'asc'; }
	$collection_result_params = [
		'search' => 'collection_id:'.(int)$t_item->get('collection_id'),
		'tadl_collection_controls' => 1,
		'tadl_collection_id' => (int)$t_item->get('collection_id'),
		'view' => $collection_view, 'sort' => $collection_sort, 'direction' => $collection_direction,
		's' => max(0, (int)$this->request->getParameter('s', pInteger)),
		'n' => $collection_view === 'list' ? 24 : 9
	];
	ob_start();
		print tadlDetailField($this->request, $t_item, 'Description', '^ca_collections.description');
		print tadlDetailField($this->request, $t_item, 'Source of description', '^ca_collections.description_source');
		print tadlDetailField($this->request, $t_item, 'Creators', '<unit relativeTo="ca_entities" restrictToRelationshipTypes="creator" delimiter="<br/>"><l>^ca_entities.preferred_labels.displayname</l></unit>');
		print tadlDetailField($this->request, $t_item, 'Dates', '^ca_collections.date.dates_value');
		print tadlDetailFirstAvailableField($this->request, $t_item, 'Extent', [
			'^ca_collections.extent_text',
			'^ca_collections.extent'
		]);
		print tadlDetailField($this->request, $t_item, 'Scope and content', '^ca_collections.collection_scope_content');
		print tadlDetailFirstAvailableField($this->request, $t_item, 'Language', [
			'<unit relativeTo="ca_collections.language" delimiter="<br/>">^ca_collections.language</unit>',
			'^ca_collections.language'
		], ['skipAccessCheck' => true]);
		print tadlDetailFirstAvailableField($this->request, $t_item, 'Vocabulary terms', [
			'<unit relativeTo="ca_list_items" delimiter="<br/>"><l>^ca_list_items.preferred_labels.name_singular</l><ifdef code="relationship_typename"> (^relationship_typename)</ifdef></unit>',
			'<unit relativeTo="ca_list_items" delimiter="<br/>"><l>^ca_list_items.preferred_labels.name_plural</l><ifdef code="relationship_typename"> (^relationship_typename)</ifdef></unit>'
		], ['skipAccessCheck' => true]);
		print tadlDetailField($this->request, $t_item, 'Library of Congress subject headings', '<unit relativeTo="ca_collections.lcsh_terms" delimiter="<br/>">^ca_collections.lcsh_terms</unit>');
		print tadlDetailField($this->request, $t_item, 'Rights', '^ca_collections.rights.rightsText');
		print tadlDetailField($this->request, $t_item, 'Copyright statement', '^ca_collections.rights.copyrightStatement');
		if ($vb_show_single_related_object) {
?>
		{{{<ifcount code="ca_objects" min="1" max="1"><div class='unit'><unit relativeTo="ca_objects" delimiter=" "><l>^ca_object_representations.media.large</l><div class='caption'>Related Object: <l>^ca_objects.preferred_labels.name</l></div></unit></div></ifcount>}}}
<?php
		}
		# Comment and Share Tools
		if ($vn_comments_enabled | $vn_share_enabled) {
			print '<div id="detailTools">';
			if ($vn_comments_enabled) {
?>
				<div class="detailTool"><a href='#' onclick='jQuery("#detailComments").slideToggle(); return false;'><span class="glyphicon glyphicon-comment" aria-hidden="true"></span>Comments (<?php print sizeof($va_comments); ?>)</a></div><!-- end detailTool -->
				<div id='detailComments'><?php print $this->getVar("itemComments");?></div><!-- end itemComments -->
<?php
			}
			if ($vn_share_enabled) {
				print '<div class="detailTool"><span class="glyphicon glyphicon-share-alt" aria-hidden="true"></span>'.$this->getVar("shareLink").'</div><!-- end detailTool -->';
			}
			print '</div><!-- end detailTools -->';
		}
	$collection_fields = trim(ob_get_clean());
	ob_start();
		print tadlDetailField($this->request, $t_item, 'Related collections', '<unit relativeTo="ca_collections.related" delimiter="<br/>"><l>^ca_collections.preferred_labels.name</l><ifdef code="relationship_typename"> (^relationship_typename)</ifdef></unit>');
		print tadlDetailField($this->request, $t_item, 'Related people', '<unit relativeTo="ca_entities" delimiter="<br/>"><l>^ca_entities.preferred_labels.displayname</l><ifdef code="relationship_typename"> (^relationship_typename)</ifdef></unit>');
		print tadlDetailField($this->request, $t_item, 'Related events', '<unit relativeTo="ca_occurrences" delimiter="<br/>"><l>^ca_occurrences.preferred_labels.name</l><ifdef code="relationship_typename"> (^relationship_typename)</ifdef></unit>');
		print tadlDetailField($this->request, $t_item, 'Related places', '<unit relativeTo="ca_places" delimiter="<br/>"><l>^ca_places.preferred_labels.name</l><ifdef code="relationship_typename"> (^relationship_typename)</ifdef></unit>');
	$collection_relationships = trim(ob_get_clean());
?>
<div class="row tadl-collection-detail">
	<div class='col-xs-12'>
		<div class="container">
			<div class="tadl-collection-overview">
				<div class="tadl-collection-heading">
					<H1>{{{^ca_collections.preferred_labels.name}}}</H1>
					<H2>{{{^ca_collections.type_id}}}{{{<ifdef code="ca_collections.idno">, ^ca_collections.idno</ifdef>}}}</H2>
					{{{<ifdef code="ca_collections.parent_id"><div class="unit">Part of: <unit relativeTo="ca_collections.hierarchy" delimiter=" &gt; "><l>^ca_collections.preferred_labels.name</l></unit></div></ifdef>}}}
<?php
					if ($vn_pdf_enabled) {
						$finding_aid_url = caNavUrl($this->request, '', 'CollectionFindingAid', 'Download', ['collection_id' => (int)$t_item->get('collection_id')]);
						print "<div class='exportCollection'><span class='glyphicon glyphicon-file' aria-hidden='true'></span> <a href=\"".htmlspecialchars($finding_aid_url, ENT_QUOTES, 'UTF-8')."\" rel=\"nofollow\">".htmlspecialchars(_t('Download Finding Aid'), ENT_QUOTES, 'UTF-8')."</a></div>";
					}
?>
				</div>
				<?php if ($collection_fields || $collection_relationships): ?>
				<div class="tadl-collection-metadata">
					<?php if ($collection_fields): ?><div class="tadl-collection-fields"><?php print $collection_fields; ?></div><?php endif; ?>
					<?php if ($collection_relationships): ?><div class="tadl-collection-relationships"><?php print $collection_relationships; ?></div><?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
			<div class="row">
				<div class='col-sm-12'>
<?php
			if ($vb_show_hierarchy_viewer) {
?>
				<div id="collectionHierarchy"><?php print caBusyIndicatorIcon($this->request).' '.addslashes(_t('Loading...')); ?></div>
				<script>
					$(document).ready(function(){
						$('#collectionHierarchy').load(<?php print json_encode(caNavUrl($this->request, '', 'Collections', 'collectionHierarchy', array('collection_id' => $t_item->get('collection_id')))); ?>);
					})
				</script>
<?php
			}
?>
				</div><!-- end col -->
			</div><!-- end row -->

<?php if (sizeof($va_related_object_ids) >= 2) { ?>
			<div class="row">
				<div id="browseResultsContainer">
					<?php print caBusyIndicatorIcon($this->request).' '.addslashes(_t('Loading...')); ?>
				</div><!-- end browseResultsContainer -->
			</div><!-- end row -->
			<script type="text/javascript">
				jQuery(document).ready(function() {
					jQuery("#browseResultsContainer").load(<?php print json_encode(caNavUrl($this->request, '', 'Search', 'objects', $collection_result_params)); ?>, function() {
						jQuery('#browseResultsContainer').jscroll({
							autoTrigger: true,
							loadingHtml: <?php print json_encode(caBusyIndicatorIcon($this->request).' '._t('Loading...'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
							padding: 20,
							nextSelector: 'a.jscroll-next'
						});
					});


				});
			</script>
<?php } ?>
		</div><!-- end container -->
	</div><!-- end col -->
</div><!-- end row -->
