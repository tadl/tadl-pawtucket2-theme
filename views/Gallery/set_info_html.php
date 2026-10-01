<?php
	$va_set_item = (array)$this->getVar('set_item');
	$vn_set_id = (int)$this->getVar('set_id');
	$vs_thumbnail = $va_set_item['representation_tag'] ?? '';
	$vs_label = htmlspecialchars((string)$this->getVar('label'), ENT_QUOTES, 'UTF-8');
	$vs_caption = htmlspecialchars((string)($va_set_item['set_item_label'] ?? ''), ENT_QUOTES, 'UTF-8');
	$vn_count = (int)$this->getVar('num_items');
?>
<div class="tadl-gallery-featured-content<?= $vs_thumbnail ? '' : ' tadl-gallery-featured-content--text-only'; ?>">
	<?php if ($vs_thumbnail): ?>
	<figure class="tadl-gallery-featured-media">
		<?= caNavLink($this->request, $vs_thumbnail, '', '', 'Gallery', $vn_set_id); ?>
		<?php if ($vs_caption): ?><figcaption><?= $vs_caption; ?></figcaption><?php endif; ?>
	</figure>
	<?php endif; ?>
	<div class="tadl-gallery-featured-text">
		<h2 class="tadl-gallery-featured-title"><?= caNavLink($this->request, $vs_label, '', '', 'Gallery', $vn_set_id); ?></h2>
		<p class="tadl-gallery-count"><?= _t($vn_count === 1 ? '%1 item' : '%1 items', $vn_count); ?></p>
		<div class="tadl-gallery-description"><?= $this->getVar('description'); ?></div>
		<?= caNavLink($this->request, _t('View gallery'), 'btn btn-primary tadl-gallery-cta', '', 'Gallery', $vn_set_id); ?>
	</div>
</div>
