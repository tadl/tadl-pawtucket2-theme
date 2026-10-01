<?php
	$pa_set_items = (array)$this->getVar('set_items');
	$pn_set_id = (int)$this->getVar('set_id');
	$ps_description = $this->getVar('description');
	$pn_set_item_id = (int)$this->getVar('set_item_id');
	$vn_first_item_id = 0;
	$vn_item_position = 0;
	$va_thumbnails = [];
	foreach ($pa_set_items as $pa_set_item) {
		$vn_item_id = (int)($pa_set_item['item_id'] ?? 0);
		if (!$vn_item_id) { continue; }
		$vn_item_position++;
		if (!$vn_first_item_id) { $vn_first_item_id = $vn_item_id; }
		$t_set_item = new ca_set_items($vn_item_id);
		$vs_icon = !empty($pa_set_item['representation_url_iconlarge']) ? 'iconlarge' : 'icon';
		$vs_rep = $t_set_item->get('ca_set_items.set_item_media', ['version' => 'iconlarge']) ?: ($pa_set_item['representation_tag_'.$vs_icon] ?? '');
		if ($vs_rep) { $va_thumbnails[$vn_item_id] = ['media' => $vs_rep, 'position' => $vn_item_position]; }
	}
	$vn_initial_item_id = $pn_set_item_id ?: $vn_first_item_id;
?>
<div class="tadl-gallery-detail">
	<h1><?= htmlspecialchars((string)$this->getVar('section_name').': '.(string)$this->getVar('label'), ENT_QUOTES, 'UTF-8'); ?></h1>
	<div class="tadl-gallery-detail-columns tadl-gallery-detail-main">
		<div id="galleryDetailImageArea"><?php if (!$vn_initial_item_id) { print '<p>'._t('No gallery items are currently available.').'</p>'; } ?></div>
		<div id="galleryDetailObjectInfo"></div>
	</div>
	<div class="tadl-gallery-detail-columns tadl-gallery-detail-bottom">
		<nav id="galleryDetailImageGrid" aria-label="<?= htmlspecialchars(_t('Gallery items'), ENT_QUOTES, 'UTF-8'); ?>">
			<div class="tadl-gallery-thumbnails">
			<?php $vn_i = 0; foreach ($va_thumbnails as $vn_item_id => $va_thumbnail): $vn_i++; ?>
				<div class="tadl-gallery-thumbnail<?= $vn_i > 12 ? ' galleryIconHidden' : ''; ?>">
					<a class="tadl-gallery-thumbnail-link" id="galleryIcon<?= $vn_item_id; ?>"
						href="<?= htmlspecialchars(caNavUrl($this->request, '', 'Gallery', $pn_set_id, ['set_item_id' => $vn_item_id]), ENT_QUOTES, 'UTF-8'); ?>"
						data-rep-url="<?= htmlspecialchars(caNavUrl($this->request, '', 'Gallery', 'getSetItemRep', ['item_id' => $vn_item_id, 'set_id' => $pn_set_id]), ENT_QUOTES, 'UTF-8'); ?>"
						data-info-url="<?= htmlspecialchars(caNavUrl($this->request, '', 'Gallery', 'getSetItemInfo', ['item_id' => $vn_item_id, 'set_id' => $pn_set_id]), ENT_QUOTES, 'UTF-8'); ?>"
						aria-label="<?= htmlspecialchars(_t('View gallery item %1', $va_thumbnail['position']), ENT_QUOTES, 'UTF-8'); ?>"><?= $va_thumbnail['media']; ?></a>
				</div>
			<?php endforeach; ?>
			</div>
			<?php if (count($va_thumbnails) > 12): ?>
			<button type="button" class="btn btn-default" id="moreLink"><?= _t('%1 more items', count($va_thumbnails) - 12); ?></button>
			<?php endif; ?>
		</nav>
		<?php if ($ps_description): ?>
		<section class="tadl-gallery-description" aria-labelledby="tadl-gallery-description-title">
			<h2 id="tadl-gallery-description-title"><?= _t('About this gallery'); ?></h2>
			<?= $ps_description; ?>
		</section>
		<?php endif; ?>
	</div>
</div>
<script>
	jQuery(document).ready(function () {
		jQuery('#galleryDetailImageGrid').on('click', '.tadl-gallery-thumbnail-link', function (event) {
			if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) { return; }
			event.preventDefault();
			jQuery('#galleryDetailImageArea').load(jQuery(this).attr('data-rep-url'));
			jQuery('#galleryDetailObjectInfo').load(jQuery(this).attr('data-info-url'));
			galleryHighlightThumbnail(this.id);
		});
		jQuery('#moreLink').on('click', function () {
			jQuery('#galleryDetailImageGrid .galleryIconHidden').removeClass('galleryIconHidden');
			jQuery(this).hide();
		});
		<?php if ($vn_initial_item_id): ?>
		jQuery('#galleryDetailImageArea').load(<?= json_encode(caNavUrl($this->request, '', 'Gallery', 'getSetItemRep', ['item_id' => $vn_initial_item_id, 'set_id' => $pn_set_id]), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
		jQuery('#galleryDetailObjectInfo').load(<?= json_encode(caNavUrl($this->request, '', 'Gallery', 'getSetItemInfo', ['item_id' => $vn_initial_item_id, 'set_id' => $pn_set_id]), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
		galleryHighlightThumbnail(<?= json_encode('galleryIcon'.$vn_initial_item_id); ?>);
		<?php endif; ?>
	});
	function galleryHighlightThumbnail(id) {
		jQuery('#galleryDetailImageGrid a').removeClass('galleryIconActive').removeAttr('aria-current');
		jQuery('#' + id).addClass('galleryIconActive').attr('aria-current', 'true');
	}
</script>
