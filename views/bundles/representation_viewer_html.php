<?php
/* ----------------------------------------------------------------------
 * themes/default/views/bundles/representation_viewer_html.php : 
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2015-2022 Whirl-i-Gig
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
$representation_count 			= $this->getVar('representation_count');
$representation_ids				= $this->getVar('representation_ids');
$show_annotations_mode			= $this->getVar('display_annotations');
$context							= $this->getVar('context');

$t_subject						= $this->getVar('t_subject');
$subject_id						= $t_subject->getPrimaryKey();

$slide_list = tadlObjectDetailVideoPosters($this->request, $t_subject, $this->getVar('slide_list'));
require_once(__DIR__.'/../../helpers/image_downloads.php');
if (function_exists('caObjectsDisplayDownloadLink') && $t_subject && $t_subject->tableName() === 'ca_objects') {
	foreach ((array)$slide_list as $slide_index => $slide) {
		if (preg_match('/^\s*<[^>]+\bdata-representation_id=[\'\"](\d+)[\'\"]/', $slide, $match)) {
			$slide_list[$slide_index] = tadlImageViewerSlide($this->request, $t_subject, (int)$match[1], $slide);
		}
	}
}
$initial_index = tadlObjectDetailInitialMediaIndex($this->request, $t_subject, $slide_list);
$rendered_count = count((array)$slide_list);

// Keep thumbnail callbacks available when native IDs outnumber rendered slides.
if ($rendered_count > 1 || $representation_count > 1) {
?>
<div class="repViewerWrapper tadl-representation-viewer">
	<div<?= $rendered_count > 1 ? ' class="tadl-representation-stage"' : ''; ?>>
		<div id="repViewerItemDisplay"></div>
		<?php if ($rendered_count > 1): ?>
		<div id="detailRepNav">
			<button type="button" id="detailRepNavPrev" aria-controls="repViewerItemDisplay" aria-label="<?= htmlspecialchars(_t('Previous media'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-chevron-left" aria-hidden="true"></i></button>
			<button type="button" id="detailRepNavNext" aria-controls="repViewerItemDisplay" aria-label="<?= htmlspecialchars(_t('Next media'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-chevron-right" aria-hidden="true"></i></button>
		</div>
		<?php endif; ?>
	</div>
	<?php if ($rendered_count > 1): ?>
	<p id="detailRepCounter" class="tadl-representation-counter" role="status" aria-live="polite" aria-atomic="true"></p>
	<?php endif; ?>
</div><!-- end wrapper -->

<script type='text/javascript'>
	let index = <?= (int)$initial_index; ?>;
	let slide_list = <?= json_encode($slide_list, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
	const mediaCounterLabel = <?= json_encode(_t('Media %1 of %2', '{position}', '{count}'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
	jQuery(document).ready(function() {
		setByIndex(index);
		
		jQuery('#detailRepNavPrev').on('click', function(e) {
			previousItem();
			e.preventDefault();
		});
		jQuery('#detailRepNavNext').on('click', function(e) {
			nextItem();
			e.preventDefault();
		});
	});
	
	function nextItem() {
		if(index < (slide_list.length - 1)) {
			index = index + 1;
			setByIndex(index);
		}
		return false;
	};
	function previousItem() {
		if(index > 0) {
			index = index - 1;
			setByIndex(index);
		}
		return false;
	};
	function setByIndex(i) {
		if((i >= 0) && (i < slide_list.length)) {
			// Stop outgoing playback before the native slide scripts initialize the next player.
			jQuery('#repViewerItemDisplay video, #repViewerItemDisplay audio').each(function () { this.pause(); });
			jQuery('#repViewerItemDisplay').html(slide_list[i]);

			let repid = jQuery('#repViewerItemDisplay').children(":first").attr('data-representation_id');
			let thumbnails = jQuery('#detailRepresentationThumbnails .repThumb');
			thumbnails.removeClass('active').removeAttr('aria-current');
			thumbnails.filter('[data-representation_id="' + repid + '"]').addClass('active').attr('aria-current', 'true');

			index = i;
			jQuery('#detailRepNavPrev').prop('disabled', index === 0);
			jQuery('#detailRepNavNext').prop('disabled', index === slide_list.length - 1);
			jQuery('#detailRepCounter').text(mediaCounterLabel.replace('{position}', index + 1).replace('{count}', slide_list.length));
		}
		return false;
	};
	function setItem(i) {
		let repid = jQuery('#repThumb_' + i).attr('data-representation_id');

		for (let newindex = 0; newindex < slide_list.length; newindex++) {
			let slideId = slide_list[newindex].match(/^\s*<[^>]+\bdata-representation_id=['"](\d+)['"]/);
			if (slideId && slideId[1] === repid) {
				setByIndex(newindex);
				break;
			}
		}
		return false;
	};
</script>
<?php
	} elseif($rendered_count == 1) {
		// Just dump the slide list without controls when there is only one representation

		print $slide_list[0];
		if ($show_annotations_mode == 'div') {
?>	
<script type='text/javascript'>
	jQuery(document).ready(function() {
			if (jQuery('#detailAnnotations').length) { jQuery('#detailAnnotations').load('<?php print caNavUrl($this->request, '*', '*', 'GetTimebasedRepresentationAnnotationList', array('context' => $context, 'id' => $subject_id, 'representation_id' => $representation_ids[0])); ?>'); }
	});
</script>
<?php
		}
	} else {
		// Use placeholder graphic when no representations are available
?>
		{{{placeholder}}}
<?php
	}
