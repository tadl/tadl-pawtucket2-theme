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
	require_once(__DIR__.'/../../helpers/user_features.php');
	require_once(__DIR__.'/../../helpers/collection_contents.php');
	require_once(__DIR__.'/../../helpers/collection_metadata.php');
	require_once(__DIR__.'/../../helpers/finding_aid.php');

	$t_item = $this->getVar("item");
	$va_access_values = (array)caGetUserAccessValues($this->request);
	$collection_mode = tadlCollectionContentsMode($this->request);
	$va_related_object_ids = $collection_mode === 'hierarchy'
		? (array)$t_item->get('ca_objects.object_id', array('returnAsArray' => true, 'checkAccess' => $va_access_values)) : [];
	$va_comments = $this->getVar("comments");
	$vn_comments_enabled = 	$this->getVar("commentsEnabled");
	$vn_share_enabled = 	$this->getVar("shareEnabled");
	$vn_pdf_enabled = 		$this->getVar("pdfEnabled");

	# --- get collections configuration
	$o_collections_config = caGetCollectionsConfig();
	$collection_has_hierarchy = !$o_collections_config->get('do_not_display_collection_browser')
		&& tadlCollectionHasHierarchy($this->request, $t_item);
	$vb_show_hierarchy_viewer = $collection_has_hierarchy && $collection_mode === 'hierarchy';

	$collection_view = tadlDisplayView($this->request);
	if (!in_array($collection_view, ['images', 'list'], true)) { $collection_view = 'images'; }
	$collection_sorts = (array)(caGetBrowseConfig()->getAssoc('browseTypes')['objects']['sortBy'] ?? []);
	$collection_sort = $this->request->getParameter('sort', pString, ['forcePurify' => true]);
	if (!isset($collection_sorts[$collection_sort ?? ''])) { $collection_sort = 'Identifier'; }
	$collection_direction = $this->request->getParameter('direction', pString);
	if (!in_array($collection_direction, ['asc', 'desc'], true)) { $collection_direction = 'asc'; }
	$collection_result_params = [
		'collection_id' => (int)$t_item->get('collection_id'), 'collection_view' => $collection_mode,
		'view' => $collection_view, 'sort' => $collection_sort, 'direction' => $collection_direction,
		's' => max(0, (int)$this->request->getParameter('s', pInteger)),
		'n' => tadlResultPageSize($this->request), 'per_page' => tadlResultPageChoice($this->request)
	];
	$collection_show_metadata = $collection_result_params['s'] === 0;
	$collection_fields = $collection_facts = '';
	if ($collection_show_metadata) {
		ob_start();
			print tadlCollectionMetadataField($this->request, $t_item, 'Description', ['ca_collections.description']);
			print tadlCollectionMetadataField($this->request, $t_item, 'Source of description', ['ca_collections.description_source']);
			print tadlCollectionMetadataField($this->request, $t_item, 'Scope and content', ['ca_collections.collection_scope_content']);
			print tadlCollectionMetadataField($this->request, $t_item, 'Provenance', ['ca_collections.provenance']);
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
			print tadlDetailField($this->request, $t_item, 'Creators', '<unit relativeTo="ca_entities" restrictToRelationshipTypes="creator" delimiter="<br/>"><l>^ca_entities.preferred_labels.displayname</l></unit>');
			print tadlCollectionMetadataField($this->request, $t_item, 'Dates', ['ca_collections.date.dates_value']);
			print tadlCollectionMetadataField($this->request, $t_item, 'Extent', [
				'ca_collections.extent_text',
				'ca_collections.extent'
			]);
			if ($t_item->hasElement('language') && $t_item->isReadable($this->request, 'language')) {
				print tadlDetailFirstAvailableField($this->request, $t_item, 'Language', [
					'<unit relativeTo="ca_collections.language" delimiter="<br/>">^ca_collections.language</unit>',
					'^ca_collections.language'
				], ['skipAccessCheck' => true, 'dontReturnDefault' => true]);
			}
			print tadlDetailFirstAvailableField($this->request, $t_item, 'Vocabulary terms', [
				'<unit relativeTo="ca_list_items" delimiter="<br/>"><l>^ca_list_items.preferred_labels.name_singular</l><ifdef code="relationship_typename"> (^relationship_typename)</ifdef></unit>',
				'<unit relativeTo="ca_list_items" delimiter="<br/>"><l>^ca_list_items.preferred_labels.name_plural</l><ifdef code="relationship_typename"> (^relationship_typename)</ifdef></unit>'
			], ['skipAccessCheck' => true]);
			print tadlCollectionMetadataField($this->request, $t_item, 'Library of Congress subject headings', ['ca_collections.lcsh_terms'], true);
			print tadlCollectionMetadataField($this->request, $t_item, 'Rights', ['ca_collections.rights.rightsText']);
			print tadlCollectionMetadataField($this->request, $t_item, 'Copyright statement', ['ca_collections.rights.copyrightStatement']);
			print tadlCollectionExternalLinks($this->request, $t_item);
			print tadlDetailField($this->request, $t_item, 'Related collections', '<unit relativeTo="ca_collections.related" delimiter="<br/>"><l>^ca_collections.preferred_labels.name</l><ifdef code="relationship_typename"> (^relationship_typename)</ifdef></unit>');
			print tadlDetailField($this->request, $t_item, 'Related people', '<unit relativeTo="ca_entities" delimiter="<br/>"><l>^ca_entities.preferred_labels.displayname</l><ifdef code="relationship_typename"> (^relationship_typename)</ifdef></unit>');
			print tadlDetailField($this->request, $t_item, 'Related events', '<unit relativeTo="ca_occurrences" delimiter="<br/>"><l>^ca_occurrences.preferred_labels.name</l><ifdef code="relationship_typename"> (^relationship_typename)</ifdef></unit>');
			print tadlDetailField($this->request, $t_item, 'Related places', '<unit relativeTo="ca_places" delimiter="<br/>"><l>^ca_places.preferred_labels.name</l><ifdef code="relationship_typename"> (^relationship_typename)</ifdef></unit>');
		$collection_facts = trim(ob_get_clean());
	}
?>
<div class="row tadl-collection-detail">
	<div class='col-xs-12'>
		<div class="container">
			<header class="tadl-record-header tadl-collection-record-header">
				<div class="tadl-collection-heading tadl-record-heading">
					<H1>{{{^ca_collections.preferred_labels.name}}}</H1>
					<H2>{{{^ca_collections.type_id}}}{{{<ifdef code="ca_collections.idno">, ^ca_collections.idno</ifdef>}}}</H2>
				</div>
				<div class="tadl-collection-header-actions">
					<?= tadlDetailNavigation($this, 'index', _t('Browse collections'), 'Collections', 'collections'); ?>
					<?php if ($providence_link = tadlProvidenceCollectionLink($this->request, $t_item->getPrimaryKey())): ?>
					<div class="tadl-collection-staff-actions"><?= $providence_link; ?></div>
					<?php endif; ?>
				</div>
			</header>
			<div class="tadl-collection-overview<?= $collection_show_metadata ? '' : ' tadl-collection-overview--compact'; ?>">
				<div class="tadl-collection-heading">
<?php
					if (!$collection_show_metadata) {
						$details_params = $collection_result_params;
						unset($details_params['collection_id']);
						$details_params['s'] = 0;
						$details_url = caNavUrl($this->request, '', 'Detail', 'collections/'.(int)$t_item->getPrimaryKey(), $details_params);
						print '<p class="tadl-collection-details-link"><a href="'.htmlspecialchars($details_url, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars(_t('About this collection'), ENT_QUOTES, 'UTF-8').'</a></p>';
					}
					$collection_ancestry = tadlCollectionContentsAncestry($this->request, $t_item);
					$parent_links = tadlCollectionParentLinks($this->request, $collection_ancestry, $t_item->getPrimaryKey());
					if ($parent_links !== '') { print '<div class="unit">'.htmlspecialchars(_t('Part of:'), ENT_QUOTES, 'UTF-8').' '.$parent_links.'</div>'; }
					if ($vn_pdf_enabled && $collection_ancestry) {
						$finding_aid_root = reset($collection_ancestry);
						$finding_aid_id = (int)$finding_aid_root->getPrimaryKey();
						$finding_aid_view_url = caNavUrl($this->request, '', 'CollectionFindingAid', 'View', ['collection_id' => $finding_aid_id]);
						$finding_aid_viewer_url = caNavUrl($this->request, '', 'CollectionFindingAid', 'Viewer', ['collection_id' => $finding_aid_id]);
						print '<div class="exportCollection"><span class="glyphicon glyphicon-file" aria-hidden="true"></span> '
							.'<a href="'.htmlspecialchars($finding_aid_view_url, ENT_QUOTES, 'UTF-8').'" data-finding-aid-viewer="'.htmlspecialchars($finding_aid_viewer_url, ENT_QUOTES, 'UTF-8').'" aria-haspopup="dialog" aria-controls="caMediaPanel" rel="nofollow" title="'.htmlspecialchars(_t('View finding aid in document viewer'), ENT_QUOTES, 'UTF-8').'" onclick="if (!event.ctrlKey &amp;&amp; !event.metaKey &amp;&amp; !event.shiftKey &amp;&amp; !event.altKey &amp;&amp; window.caMediaPanel &amp;&amp; typeof caMediaPanel.showPanel === &quot;function&quot;) { caMediaPanel.showPanel(this.getAttribute(&quot;data-finding-aid-viewer&quot;)); return false; }">'.htmlspecialchars(_t('View finding aid'), ENT_QUOTES, 'UTF-8').'<span class="sr-only"> '.htmlspecialchars(_t('(PDF)'), ENT_QUOTES, 'UTF-8').'</span></a><noscript><p><a href="'.htmlspecialchars(caNavUrl($this->request, '', 'CollectionFindingAid', 'Text', ['collection_id' => $finding_aid_id]), ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars(_t('Read finding aid as text'), ENT_QUOTES, 'UTF-8').'</a></p></noscript></div>';
					}
					if ($collection_has_hierarchy) {
						$switch_params = $collection_result_params;
						unset($switch_params['collection_id'], $switch_params['n']);
						$switch_params['s'] = 0;
						$switch_params['collection_view'] = $collection_mode === 'flat' ? 'hierarchy' : 'flat';
						$switch_url = caNavUrl($this->request, '', 'Detail', 'collections/'.(int)$t_item->get('collection_id'), $switch_params);
						$switch_label = $collection_mode === 'flat' ? _t('Collection hierarchy view') : _t('Flat collection view');
						print '<div class="tadl-collection-view-switch"><a data-tadl-collection-view="'.$switch_params['collection_view'].'" href="'.htmlspecialchars($switch_url, ENT_QUOTES, 'UTF-8').'"><span class="glyphicon glyphicon-eye-open" aria-hidden="true"></span> '.htmlspecialchars($switch_label, ENT_QUOTES, 'UTF-8').'</a></div>';
					}
?>
				</div>
				<?php if ($collection_fields || $collection_facts): ?>
				<div class="tadl-collection-metadata">
					<?php if ($collection_fields): ?><div class="tadl-collection-fields"><?php print $collection_fields; ?></div><?php endif; ?>
					<?php if ($collection_facts): ?><div class="tadl-collection-facts"><?php print $collection_facts; ?></div><?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
			<div class="row">
				<div class='col-sm-12'>
<?php
			if ($vb_show_hierarchy_viewer) {
?>
				<div id="collectionHierarchy" aria-busy="true"><?php print caBusyIndicatorIcon($this->request).' '.addslashes(_t('Loading...')); ?></div>
				<script>
					$(document).ready(function(){
						$('#collectionHierarchy').load(<?php print json_encode(caNavUrl($this->request, '', 'Collections', 'collectionHierarchy', array('collection_id' => $t_item->get('collection_id')))); ?>, function(response, status) {
							jQuery(this).attr('aria-busy', 'false');
							if (status === 'error') { jQuery(this).html(<?= json_encode('<p role="status">'.htmlspecialchars(_t('The hierarchy could not be loaded. Please try again.'), ENT_QUOTES, 'UTF-8').'</p>'); ?>); }
						});
					})
				</script>
<?php
			}
?>
				</div><!-- end col -->
			</div><!-- end row -->

<?php if ($collection_mode === 'flat' || sizeof($va_related_object_ids) >= 1) { ?>
			<div class="row">
				<div id="browseResultsContainer" aria-busy="true">
					<?php print caBusyIndicatorIcon($this->request).' '.addslashes(_t('Loading...')); ?>
				</div><!-- end browseResultsContainer -->
				<noscript><p><a href="<?= htmlspecialchars(caNavUrl($this->request, '', 'CollectionContents', 'Objects', $collection_result_params), ENT_QUOTES, 'UTF-8'); ?>"><?= _t('Open collection items'); ?></a></p></noscript>
			</div><!-- end row -->
			<script type="text/javascript">
				jQuery(document).ready(function() {
					jQuery("#browseResultsContainer").load(<?php print json_encode(caNavUrl($this->request, '', 'CollectionContents', 'Objects', $collection_result_params)); ?>, function(response, status) {
						jQuery(this).attr('aria-busy', 'false');
						if (status === 'error') {
							jQuery(this).html(<?= json_encode('<p role="status">'.htmlspecialchars(_t('Collection items could not be loaded.'), ENT_QUOTES, 'UTF-8').' <a href="'.htmlspecialchars(caNavUrl($this->request, '', 'CollectionContents', 'Objects', $collection_result_params), ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars(_t('Open collection items'), ENT_QUOTES, 'UTF-8').'</a></p>', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
							return;
						}
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
