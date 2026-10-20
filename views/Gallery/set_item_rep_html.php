<?php
	$pn_previous_item_id = $this->getVar("previous_item_id");
	$pn_next_item_id = $this->getVar("next_item_id");
	$pn_previous_row_id = $this->getVar("previous_row_id");
	$pn_next_row_id = $this->getVar("next_row_id");
	$pn_previous_rep_id = $this->getVar("previous_representation_id");
	$pn_next_rep_id = $this->getVar("next_representation_id");
	
	$pn_set_id = $this->getVar("set_id");
	$pn_row_id = $this->getVar("row_id");
	$ps_table = $this->getVar("table");
	$pn_rep_id = $this->getVar("representation_id");
	print "<div id='galleryDetailImageWrapper'><div class='tadl-gallery-media-stage'>";
	if($pn_previous_item_id > 0){
		print "<button type='button' class='galleryDetailPrevious' aria-label='"._t('Previous gallery item')."' onclick='caGalleryNav(\"previous\"); return false;'><i class='fa fa-chevron-left' aria-hidden='true'></i></button>";
	}else{
		print "<button type='button' class='galleryDetailPrevious inactive' aria-label='"._t('Previous gallery item')."' disabled><i class='fa fa-chevron-left' aria-hidden='true'></i></button>";
	}
	if($pn_next_item_id > 0){
		print "<button type='button' class='galleryDetailNext' aria-label='"._t('Next gallery item')."' onclick='caGalleryNav(\"next\"); return false;'><i class='fa fa-chevron-right' aria-hidden='true'></i></button>";
	}else{
		print "<button type='button' class='galleryDetailNext inactive' aria-label='"._t('Next gallery item')."' disabled><i class='fa fa-chevron-right' aria-hidden='true'></i></button>";
	}
	$vs_rep_toolbar = $this->getVar('repToolBar');
	require_once(__DIR__.'/../../helpers/image_downloads.php');
	if ($ps_table === 'ca_objects' && $pn_rep_id > 0) {
		$t_download_object = Datamodel::getInstance('ca_objects', true);
		if ($t_download_object && $t_download_object->load((int)$pn_row_id)) {
			$vs_rep_toolbar = tadlImageToolbar($this->request, $t_download_object, (int)$pn_rep_id, $vs_rep_toolbar);
		}
	}
	print caDetailLink($this->request, $this->getVar("rep"), '', $ps_table, $pn_row_id)."</div>";
	if ($vs_rep_toolbar) {
		print '<div class="tadl-gallery-media-actions" role="group" aria-label="'.htmlspecialchars(_t('Media actions'), ENT_QUOTES, 'UTF-8').'">'.$vs_rep_toolbar.'</div>';
	}
	print '</div>';
?>


<?php
    $gallery_navigation = [];
    foreach (['previous', 'next'] as $direction) {
        $item_id = $this->getVar($direction.'_item_id');
        if (!$item_id) { continue; }
        $gallery_navigation[$direction] = [
            'representation' => caNavUrl($this->request, '', 'Gallery', 'getSetItemRep', ['item_id' => $item_id, 'set_id' => $pn_set_id]),
            'information' => caNavUrl($this->request, '', 'Gallery', 'getSetItemInfo', ['item_id' => $item_id, 'set_id' => $pn_set_id]),
            'overlay' => caNavUrl($this->request, '', 'Detail', 'GetMediaOverlay', [
                'context' => 'gallery', 'id' => $this->getVar($direction.'_row_id'),
                'representation_id' => $this->getVar($direction.'_representation_id'), 'set_id' => $pn_set_id, 'overlay' => 1
            ]),
            'thumbnail' => 'galleryIcon'.(int)$item_id
        ];
    }
?>
<script>
    function caGalleryNav(mode) {
        var navigation = <?= json_encode($gallery_navigation, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        var target = navigation[mode];
        if (!target) { return; }
        var selector = mode === 'previous' ? '.galleryDetailPrevious' : '.galleryDetailNext';
        var restoreFocus = jQuery(document.activeElement).is(selector);
        tadlSelectGalleryItem(target, restoreFocus ? selector : null);
    }
</script>
