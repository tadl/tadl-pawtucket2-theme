<?php
/* ----------------------------------------------------------------------
 * themes/default/views/bundles/ca_objects_default_html.php : 
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

	$t_object = 			$this->getVar("item");
	$va_comments = 			$this->getVar("comments");
	$va_tags = 				$this->getVar("tags_array");
	$vn_comments_enabled = 	$this->getVar("commentsEnabled");
	$vn_share_enabled = 	$this->getVar("shareEnabled");
	$vn_pdf_enabled = 		$this->getVar("pdfEnabled");
	$vn_id =				$t_object->get('ca_objects.object_id');
?>
<div class="row">
	<div class='col-xs-12 navTop'><!--- only shown at small screen size -->
		{{{previousLink}}}{{{resultsLink}}}{{{nextLink}}}
	</div><!-- end detailTop -->
	<div class='navLeftRight col-xs-1 col-sm-1 col-md-1 col-lg-1'>
		<div class="detailNavBgLeft">
			{{{previousLink}}}{{{resultsLink}}}
		</div><!-- end detailNavBgLeft -->
	</div><!-- end col -->
	<div class='col-xs-12 col-sm-10 col-md-10 col-lg-10'>
		<div class="container"><div class="row">
			<div class='col-sm-6 col-md-6 col-lg-5 col-lg-offset-1'>
				{{{representationViewer}}}
				
				
				<div id="detailAnnotations"></div>
				
				<?= caObjectRepresentationThumbnails($this->request, $this->getVar("representation_id"), $t_object, array("returnAs" => "bsCols", "linkTo" => "basic", "bsColClasses" => "smallpadding col-sm-3 col-md-3 col-xs-4", "primaryOnly" => $this->getVar('representationViewerPrimaryOnly') ? 1 : 0)); ?>
				
<?php
				# Comment and Share Tools
				if ($vn_comments_enabled | $vn_share_enabled | $vn_pdf_enabled) {
						
					print '<div id="detailTools">';
					if ($vn_comments_enabled) {
?>				
						<div class="detailTool"><a href='#' onclick='jQuery("#detailComments").slideToggle(); return false;'><span class="glyphicon glyphicon-comment" aria-label="<?php print _t("Comments and tags"); ?>"></span><?= _t('Comments and Tags'); ?> (<?php print sizeof($va_comments) + sizeof($va_tags); ?>)</a></div><!-- end detailTool -->
						<div id='detailComments'><?php print $this->getVar("itemComments");?></div><!-- end itemComments -->
<?php				
					}
					if ($vn_share_enabled) {
						print '<div class="detailTool"><span class="glyphicon glyphicon-share-alt" aria-label="'._t("Share").'"></span>'.$this->getVar("shareLink").'</div><!-- end detailTool -->';
					}
					if ($vn_pdf_enabled) {
						print "<div class='detailTool'><span class='glyphicon glyphicon-file' aria-label='"._t("Download")."'></span>".caDetailLink($this->request, "Download as PDF", "faDownload", "ca_objects",  $vn_id, array('view' => 'pdf', 'export_format' => '_pdf_ca_objects_summary'))."</div>";
					}
					print '</div><!-- end detailTools -->';
				}				

?>

			</div><!-- end col -->
			
			<div class='col-sm-6 col-md-6 col-lg-5'>
				<H1>{{{<unit relativeTo="ca_collections" delimiter="<br/>"><l>^ca_collections.preferred_labels.name</l></unit><ifcount min="1" code="ca_collections"> ➔ </ifcount>}}}{{{ca_objects.preferred_labels.name}}}</H1>
				<H2>{{{<unit>^ca_objects.type_id</unit>}}}</H2>
				<HR>
				
				
				{{{<ifdef code="ca_objects.idno"><div class="unit"><label>Identifier</label>^ca_objects.idno</div></ifdef>}}}
				{{{<ifdef code="ca_objects.containerID"><div class="unit"><label>Box/series</label>^ca_objects.containerID</div></ifdef>}}}				
<?php
				print tadlDetailField($this->request, $t_object, 'Alternate title', '<unit relativeTo="ca_objects.nonpreferred_labels" delimiter="<br/>">^ca_objects.nonpreferred_labels.name</unit>');
				print tadlDetailField($this->request, $t_object, 'Date', '^ca_objects.date.dates_value');
				print tadlDetailField($this->request, $t_object, 'Creators', '<unit relativeTo="ca_entities" restrictToRelationshipTypes="creator" delimiter="<br/>"><l>^ca_entities.preferred_labels.displayname</l></unit>');
				print tadlDetailField($this->request, $t_object, 'Publisher', '<unit relativeTo="ca_entities" restrictToRelationshipTypes="publisher" delimiter="<br/>"><l>^ca_entities.preferred_labels.displayname</l></unit>');
				print tadlDetailField($this->request, $t_object, 'Description', '<span class="trimText">^ca_objects.description</span>');
				print tadlDetailField($this->request, $t_object, 'Source of description', '^ca_objects.description_source');
				print tadlDetailFirstAvailableField($this->request, $t_object, 'Languages', [
					'<unit relativeTo="ca_objects.language" delimiter="<br/>">^ca_objects.language</unit>',
					'^ca_objects.language'
				], ['skipAccessCheck' => true]);
				print tadlDetailField($this->request, $t_object, 'Dimensions', '<unit relativeTo="ca_objects.dimensions" delimiter="<br/>"><ifdef code="ca_objects.dimensions.dimensions_height">^ca_objects.dimensions.dimensions_height H</ifdef><ifdef code="ca_objects.dimensions.dimensions_width"> x ^ca_objects.dimensions.dimensions_width W</ifdef><ifdef code="ca_objects.dimensions.dimensions_depth"> x ^ca_objects.dimensions.dimensions_depth D</ifdef><ifdef code="ca_objects.dimensions.dimensions_diameter"> x ^ca_objects.dimensions.dimensions_diameter diameter</ifdef><ifdef code="ca_objects.dimensions.dimensions_weight">; ^ca_objects.dimensions.dimensions_weight</ifdef><ifdef code="ca_objects.dimensions.dimensions_type"> (^ca_objects.dimensions.dimensions_type)</ifdef></unit>');
				print tadlDetailField($this->request, $t_object, 'Inscriptions/marks', '^ca_objects.inscriptions_marks');
				print tadlDetailFirstAvailableField($this->request, $t_object, 'Materials and techniques', [
					'<unit relativeTo="ca_objects.materials_techniques" delimiter="<br/>">^ca_objects.materials_techniques</unit>',
					'<unit relativeTo="ca_objects.materials_and_techniques" delimiter="<br/>">^ca_objects.materials_and_techniques</unit>',
					'<unit relativeTo="ca_objects.list_materials" delimiter="<br/>">^ca_objects.list_materials</unit>',
					'<unit relativeTo="ca_objects.materials" delimiter="<br/>">^ca_objects.materials</unit>',
					'<unit relativeTo="ca_objects.techniques" delimiter="<br/>">^ca_objects.techniques</unit>',
					'<unit relativeTo="ca_objects.technique" delimiter="<br/>">^ca_objects.technique</unit>',
					'^ca_objects.text_format',
					'^ca_objects.image_format',
					'^ca_objects.formatNotes'
				], ['skipAccessCheck' => true]);
				print tadlDetailField($this->request, $t_object, 'Art and Architecture terms', '<unit relativeTo="ca_objects.art_architecture_authority" delimiter="<br/>">^ca_objects.art_architecture_authority</unit>');
				print tadlDetailField($this->request, $t_object, 'Thesaurus terms', '<unit relativeTo="ca_objects.lctgm" delimiter="<br/>">^ca_objects.lctgm</unit>');
				print tadlDetailField($this->request, $t_object, 'Library of Congress subject headings', '<unit relativeTo="ca_objects.lcsh_terms" delimiter="<br/>">^ca_objects.lcsh_terms</unit>');
				print tadlDetailField($this->request, $t_object, 'Rights', '^ca_objects.rights.rightsText');
				print tadlDetailField($this->request, $t_object, 'Copyright statement', '^ca_objects.rights.copyrightStatement');
				print tadlObjectRepresentationCaptions($this->request, $t_object);
				print tadlDetailField($this->request, $t_object, 'External links', '<unit relativeTo="ca_objects.external_link" delimiter="<br/>"><ifdef code="ca_objects.external_link.url_source">^ca_objects.external_link.url_source: </ifdef><a href="^ca_objects.external_link.url_entry">^ca_objects.external_link.url_entry</a></unit>');
?>
			
				<hr></hr>
					<div class="row">
						<div class="col-sm-6">		
<?php
							print tadlDetailField($this->request, $t_object, 'Related people/organizations', '<unit relativeTo="ca_entities" delimiter="<br/>" excludeRelationshipTypes="creator,publisher"><l>^ca_entities.preferred_labels</l> (^relationship_typename)</unit>');
?>

							{{{<ifcount code="ca_objects.related" min="1"><div class="unit">
								<ifcount code="ca_objects.related" min="1" max="1"><label>Related object</label></ifcount>
								<ifcount code="ca_objects.related" min="2"><label>Related objects</label></ifcount>
								<unit relativeTo="ca_objects.related" delimiter="<br/>"><l>^ca_objects.related.preferred_labels.name</l> (^relationship_typename)</unit>
							</div></ifcount>}}}
							
							{{{<ifcount code="ca_occurrences" min="1"><div class="unit">
								<ifcount code="ca_occurrences" min="1" max="1"><label>Related occurrence</label></ifcount>
								<ifcount code="ca_occurrences" min="2"><label>Related occurrences</label></ifcount>
								<unit relativeTo="ca_occurrences" delimiter="<br/>"><l>^ca_occurrences.preferred_labels</l> (^relationship_typename)</unit>
							</div></ifcount>}}}
							
							{{{<ifcount code="ca_places" min="1"><div class="unit">
								<ifcount code="ca_places" min="1" max="1"><label>Related place</label></ifcount>
								<ifcount code="ca_places" min="2"><label>Related places</label></ifcount>
								<unit relativeTo="ca_places" delimiter="<br/>"><l>^ca_places.preferred_labels</l> (^relationship_typename)</unit>
							</div></ifcount>}}}
							
							{{{<ifcount code="ca_list_items" min="1"><div class="unit">
								<ifcount code="ca_list_items" min="1" max="1"><label>Related Term</label></ifcount>
								<ifcount code="ca_list_items" min="2"><label>Related Terms</label></ifcount>
								<unit relativeTo="ca_list_items" delimiter="<br/>"><l>^ca_list_items.preferred_labels.name_plural</l> (^relationship_typename)</unit>
							</div></ifcount>}}}
							
						</div><!-- end col -->				
						<div class="col-sm-6 colBorderLeft">
							{{{map}}}
						</div>
					</div><!-- end row -->
						
			</div><!-- end col -->
		</div><!-- end row --></div><!-- end container -->
	</div><!-- end col -->
	<div class='navLeftRight col-xs-1 col-sm-1 col-md-1 col-lg-1'>
		<div class="detailNavBgRight">
			{{{nextLink}}}
		</div><!-- end detailNavBgLeft -->
	</div><!-- end col -->
</div><!-- end row -->

<script type='text/javascript'>
	jQuery(document).ready(function() {
		$('.trimText').readmore({
		  speed: 75,
		  maxHeight: 120
		});
	});
</script>
