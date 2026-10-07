<?php
/** ---------------------------------------------------------------------
 * themes/default/views/mediaViewers/viewerWrapper.php :
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2016-2020 Whirl-i-Gig
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
 * @package CollectiveAccess
 * @subpackage Media
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 *
 * ----------------------------------------------------------------------
 */
require_once(__DIR__.'/../../helpers/image_downloads.php');
$download_controls = tadlNofollowMediaLinks($this->getVar('controls'));
$viewer_download = '';
$viewer_object_heading = '';
if ($this->getVar('viewer') === 'TileViewer' && in_array($this->getVar('context') ?: $this->request->getParameter('context', pString), ['objects', 'gallery'], true)
	&& preg_match('/^representation:(\d+)$/', (string)$this->getVar('identifier'), $download_match)) {
	$t_download_object = Datamodel::getInstance('ca_objects', true);
	$download_object_id = $this->request->getParameter('id', pInteger) ?: $this->request->getParameter('object_id', pInteger);
	if ($t_download_object && $t_download_object->load((int)$download_object_id)) {
		if ($t_download_object->isReadable($this->request)) {
			$object_title = trim((string)$t_download_object->get('ca_objects.preferred_labels.name')) ?: _t('Object');
			$object_identifier = trim((string)$t_download_object->get('idno'));
			$identifier_text = $object_identifier !== '' ? ' ('.$object_identifier.')' : '';
			$viewer_object_heading = '<div class="objectInfo" title="'.htmlspecialchars($object_title.$identifier_text, ENT_QUOTES, 'UTF-8').'">'
				.'<span class="tadl-viewer-object-title">'.htmlspecialchars($object_title, ENT_QUOTES, 'UTF-8').'</span>'
				.($identifier_text !== '' ? '<span class="tadl-viewer-object-identifier">'.htmlspecialchars($identifier_text, ENT_QUOTES, 'UTF-8').'</span>' : '').'</div>';
			$download_controls = preg_replace_callback('~<div class=[\'\"]objectInfo[\'\"]>.*?</div>~is', static function () use ($viewer_object_heading) { return $viewer_object_heading; }, (string)$download_controls);
		}
		$viewer_download = tadlImageDownloadLinks($this->request, $t_download_object, (int)$download_match[1], true);
		$download_controls = preg_replace('~<div class=[\'\"]download[\'\"]>.*?</div>~is', '', (string)$download_controls);
	}
}
?>
<div id="caMediaOverlayContent"><?php print $this->render($this->getVar('viewer').".php").$viewer_download; ?></div>
<?php if ($this->getVar('hideOverlayControls')) { ?>
<div class="caMediaOverlayControlsMinimal">
	<div class='close'><a href="#" onclick="caMediaPanel.hidePanel(); return false;" title="close"><i class="fa fa-times" aria-hidden="true"></i></a></div>
</div>
<?php } else { ?>
<div class="caMediaOverlayControls<?= $viewer_object_heading !== '' ? ' tadl-image-viewer-controls' : ''; ?>">
	<div class='close'><a href="#" onclick="caMediaPanel.hidePanel(); return false;" title="close"><i class="fa fa-times" aria-hidden="true"></i></a></div>
	<?php print $download_controls; ?>
</div>
<?php } ?>

<script>
	jQuery(document).ready(function () {
		if (typeof tadlPlaceViewerDownload === 'function') {
			tadlPlaceViewerDownload(document.getElementById('caMediaOverlayContent'));
		}
	});
	function caMediaOverlayNav(mode, id, representation_id) {
		jQuery("#caMediaPanelContentArea:visible").load("<?= caNavUrl($this->request, '', 'Detail', 'GetMediaOverlay', ['context' => $this->getVar('context'), 'overlay' => 1]); ?>/id/" + id + '/representation_id/' + representation_id);
		jQuery('#' + caMediaPanel.getPanelID()).data('reloadUrl', '<?= caNavUrl($this->request, '', '*', $this->getVar('context')); ?>/' + id);
	}
</script>
