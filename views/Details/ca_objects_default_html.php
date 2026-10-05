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
	require_once(__DIR__.'/../../helpers/user_features.php');

	$t_object = 			$this->getVar("item");
	$va_comments = 			$this->getVar("comments");
	$va_tags = 				$this->getVar("tags_array");
	$vn_comments_enabled = 	$this->getVar("commentsEnabled");
	$vn_share_enabled = 	$this->getVar("shareEnabled");
?>
<div class="row tadl-object-detail">
	<div class='col-xs-12 tadl-object-main'>
		<div class="container tadl-object-content"><div class="row">
			<div class='col-sm-6 col-md-6 col-lg-6 tadl-object-media'>
				{{{representationViewer}}}
				<div id="tadlObjectMediaActions" class="tadl-object-media-actions" role="group" aria-label="<?= htmlspecialchars(_t('Image actions'), ENT_QUOTES, 'UTF-8'); ?>"></div>
				<?php if ($lightbox_link = tadlAddToLightboxLink($this->request, $t_object->getPrimaryKey())): ?>
				<div class="tadl-object-lightbox-action"><?= $lightbox_link; ?></div>
				<?php endif; ?>
				
				<div id="detailAnnotations"></div>
				
				<?php
				$va_representation_thumbnails = caObjectRepresentationThumbnails($this->request, $this->getVar('representation_id'), $t_object, [
					'returnAs' => 'array', 'linkTo' => 'basic',
					'primaryOnly' => $this->getVar('representationViewerPrimaryOnly') ? 1 : 0
				]);
				if ($va_representation_thumbnails): ?>
				<div id="detailRepresentationThumbnails" class="tadl-object-thumbnails" role="group" aria-label="<?= htmlspecialchars(_t('Select media'), ENT_QUOTES, 'UTF-8'); ?>">
					<?php foreach ($va_representation_thumbnails as $vn_representation_id => $vs_thumbnail_link): ?>
					<div id="detailRepresentationThumbnail<?= (int)$vn_representation_id; ?>"><?= $vs_thumbnail_link; ?></div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				
<?php
				# Comment and Share Tools
				if ($vn_comments_enabled || $vn_share_enabled) {
						
					print '<div id="detailTools">';
					if ($vn_comments_enabled) {
?>				
						<div class="detailTool"><a href='#' onclick='jQuery("#detailComments").slideToggle(); return false;'><span class="glyphicon glyphicon-comment" aria-hidden="true"></span><?= _t('Comments and Tags'); ?> (<?php print sizeof($va_comments) + sizeof($va_tags); ?>)</a></div><!-- end detailTool -->
						<div id='detailComments'><?php print $this->getVar("itemComments");?></div><!-- end itemComments -->
<?php				
					}
					if ($vn_share_enabled) {
						print '<div class="detailTool"><span class="glyphicon glyphicon-share-alt" aria-hidden="true"></span>'.$this->getVar("shareLink").'</div><!-- end detailTool -->';
					}
					print '</div><!-- end detailTools -->';
				}				

?>

			</div><!-- end col -->
			
			<div class='col-sm-6 col-md-6 col-lg-6 tadl-object-info'>
				<H1>{{{<unit relativeTo="ca_collections" delimiter="<br/>"><l>^ca_collections.preferred_labels.name</l></unit><ifcount min="1" code="ca_collections"><br/>➔ </ifcount>}}}{{{ca_objects.preferred_labels.name}}}</H1>
				<H2>{{{<unit>^ca_objects.type_id</unit>}}}</H2>
				<HR>
				
				
<?php
				print tadlDetailField($this->request, $t_object, 'Identifier', '^ca_objects.idno');
				print tadlDetailField($this->request, $t_object, 'Box/series', '^ca_objects.containerID');
				print tadlDetailField($this->request, $t_object, 'Alternate title', '<unit relativeTo="ca_objects.nonpreferred_labels" delimiter="<br/>">^ca_objects.nonpreferred_labels.name</unit>');
				print tadlDetailField($this->request, $t_object, 'Date', '^ca_objects.date.dates_value');
				print tadlDetailField($this->request, $t_object, 'Creators', '<unit relativeTo="ca_entities" restrictToRelationshipTypes="creator" delimiter="<br/>"><l>^ca_entities.preferred_labels.displayname</l></unit>');
				print tadlDetailField($this->request, $t_object, 'Publisher', '<unit relativeTo="ca_entities" restrictToRelationshipTypes="publisher" delimiter="<br/>"><l>^ca_entities.preferred_labels.displayname</l></unit>');
				print tadlDetailField($this->request, $t_object, 'Description', '<span class="trimText">^ca_objects.description</span>');
				print tadlObjectSubjects($this->request, $t_object);
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
				print tadlObjectThesaurusTerms($this->request, $t_object);
				print tadlDetailField($this->request, $t_object, 'Library of Congress subject headings', '<unit relativeTo="ca_objects.lcsh_terms" delimiter="<br/>">^ca_objects.lcsh_terms</unit>');
				print tadlDetailField($this->request, $t_object, 'Rights', '^ca_objects.rights.rightsText');
				print tadlDetailField($this->request, $t_object, 'Copyright statement', '^ca_objects.rights.copyrightStatement');
				print tadlObjectRepresentationCaptions($this->request, $t_object);
				print tadlDetailField($this->request, $t_object, 'External links', '<unit relativeTo="ca_objects.external_link" delimiter="<br/>"><ifdef code="ca_objects.external_link.url_source">^ca_objects.external_link.url_source: </ifdef><a href="^ca_objects.external_link.url_entry">^ca_objects.external_link.url_entry</a></unit>');
?>
			
				<hr></hr>
					<div class="row">
						<div class="col-sm-12">
<?php
							print tadlDetailField($this->request, $t_object, 'Related people/organizations', '<unit relativeTo="ca_entities" delimiter="<br/>" excludeRelationshipTypes="creator,publisher"><l>^ca_entities.preferred_labels</l> (^relationship_typename)</unit>');
?>

<?php
							print tadlDetailField($this->request, $t_object, 'Related objects', '<unit relativeTo="ca_objects.related" delimiter="<br/>"><l>^ca_objects.preferred_labels.name<ifdef code="ca_objects.idno"> — ^ca_objects.idno%htmlEncode=1</ifdef></l> (^relationship_typename)</unit>');
							print tadlDetailField($this->request, $t_object, 'Related occurrences', '<unit relativeTo="ca_occurrences" delimiter="<br/>"><l>^ca_occurrences.preferred_labels.name</l> (^relationship_typename)</unit>');
							print tadlDetailField($this->request, $t_object, 'Related places', '<unit relativeTo="ca_places" delimiter="<br/>"><l>^ca_places.preferred_labels.name</l> (^relationship_typename)</unit>');
?>

						</div><!-- end col -->				
						<div class="col-sm-12">
							{{{map}}}
						</div>
					</div><!-- end row -->
						
			</div><!-- end col -->
		</div><!-- end row --></div><!-- end container -->
	</div><!-- end col -->
</div><!-- end row -->

<?php require __DIR__.'/image_actions_script.php'; ?>
<script type='text/javascript'>
	jQuery(document).ready(function() {
		$('.trimText').readmore({
		  speed: 75,
		  maxHeight: 120
		});
	});
</script>
