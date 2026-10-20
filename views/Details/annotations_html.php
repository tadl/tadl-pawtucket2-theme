<?php
/* ----------------------------------------------------------------------
 * themes/default/views/Details/annotations_html.php :
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2015 Whirl-i-Gig
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
	$vs_player_name = 			$this->getVar('player_name');
	$va_annotations = 			$this->getVar('annotation_list');
	$va_annotation_times = 		$this->getVar('annotation_times');
	$vn_representation_id = 	$this->getVar('representation_id');

	if (sizeof($va_annotations) > 0) {
?>
<h2><?php print _t('Featured clips (%1)', sizeof($va_annotations)); ?></h2>
<div class='detailAnnotationList'>
	<ul class='detailAnnotation'>
<?php
		foreach($va_annotations as $vs_annotation) {
			print "<li class='detailAnnotation'><button type='button' class='btn btn-default tadl-featured-clip'>".htmlspecialchars(trim(strip_tags($vs_annotation)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."</button></li>\n";
		}
?>
	</ul>
<?php
	}
?>
</div>
<script type="text/javascript">
	jQuery(document).ready(function() {
		var detailAnnotationTimes = <?php print json_encode($va_annotation_times); ?>;
		jQuery('.tadl-featured-clip').on('click', function(e) {
			var i = jQuery('li.detailAnnotation').index(jQuery(this).closest('li'));

			caUI.mediaPlayerManager.seek(<?= json_encode((string)$vs_player_name, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, detailAnnotationTimes[i][0]);
		});

		caUI.mediaPlayerManager.onTimeUpdate(<?= json_encode((string)$vs_player_name, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, function() {
			var ct = caUI.mediaPlayerManager.currentTime(<?= json_encode((string)$vs_player_name, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);

			jQuery('li.detailAnnotation').removeClass('active');
			jQuery.each(detailAnnotationTimes, function(i, v) {
				if ((ct > v[0]) && (ct <= v[1])) {
					jQuery('li.detailAnnotation:eq(' + i + ')').addClass('active');
				}
			});
		});
	});
</script>
