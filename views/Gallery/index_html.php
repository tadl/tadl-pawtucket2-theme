<?php
	$va_sets = (array)$this->getVar('sets');
	$va_first_items_from_set = (array)$this->getVar('first_items_from_sets');
	$vs_section_name = htmlspecialchars((string)$this->getVar('section_name'), ENT_QUOTES, 'UTF-8');
?>
<div class="tadl-gallery-index">
	<h1><?= $vs_section_name; ?></h1>
<?php if ($va_sets):
	reset($va_sets);
	$vn_first_set_id = (int)key($va_sets);
	$va_other_sets = $va_sets;
	unset($va_other_sets[$vn_first_set_id]);
	$vs_featured_link = caNavLink($this->request, _t('View gallery'), 'btn btn-primary tadl-gallery-cta', '', 'Gallery', $vn_first_set_id);
	$vs_error_html = '<p class="tadl-gallery-message">'.htmlspecialchars(_t('The gallery preview could not be loaded.'), ENT_QUOTES, 'UTF-8').'</p>'.$vs_featured_link;
?>
	<div class="tadl-gallery-layout<?= $va_other_sets ? '' : ' tadl-gallery-layout--single'; ?>">
		<section class="tadl-gallery-featured" aria-label="<?= htmlspecialchars(_t('Featured gallery'), ENT_QUOTES, 'UTF-8'); ?>">
			<p class="tadl-gallery-eyebrow"><?= _t('Featured gallery'); ?></p>
			<div id="gallerySetInfo" aria-busy="true" aria-live="polite">
				<p class="tadl-gallery-message"><?= _t('Loading featured gallery…'); ?></p>
				<?= $vs_featured_link; ?>
			</div>
		</section>
		<?php if ($va_other_sets): ?>
		<aside class="tadl-gallery-more" aria-labelledby="tadl-more-galleries-title">
			<h2 id="tadl-more-galleries-title"><?= _t('More galleries'); ?></h2>
			<div class="tadl-gallery-list">
			<?php foreach ($va_other_sets as $vn_set_id => $va_set):
				$vn_set_id = (int)$vn_set_id;
				$va_set_items = (array)($va_first_items_from_set[$vn_set_id] ?? []);
				$va_first_item = reset($va_set_items);
				$vs_thumbnail = $va_first_item['representation_tag'] ?? '';
				$vs_name = htmlspecialchars((string)($va_set['name'] ?? ''), ENT_QUOTES, 'UTF-8');
				$vn_count = (int)($va_set['item_count'] ?? 0);
			?>
				<article class="galleryItem tadl-gallery-card<?= $vs_thumbnail ? '' : ' tadl-gallery-card--text-only'; ?>">
					<?php if ($vs_thumbnail): ?>
					<div class="tadl-gallery-card-media"><?= caNavLink($this->request, $vs_thumbnail, '', '', 'Gallery', $vn_set_id); ?></div>
					<?php endif; ?>
					<div class="tadl-gallery-card-info">
						<h3><?= caNavLink($this->request, $vs_name, '', '', 'Gallery', $vn_set_id); ?></h3>
						<p class="tadl-gallery-count"><?= _t($vn_count === 1 ? '%1 item' : '%1 items', $vn_count); ?></p>
						<?= caNavLink($this->request, _t('View gallery'), 'btn btn-primary tadl-gallery-cta', '', 'Gallery', $vn_set_id); ?>
					</div>
				</article>
			<?php endforeach; ?>
			</div>
		</aside>
		<?php endif; ?>
	</div>
	<script>
	jQuery(document).ready(function () {
		var preview = jQuery('#gallerySetInfo');
		preview.load(<?= json_encode(caNavUrl($this->request, '', 'Gallery', 'getSetInfo', ['set_id' => $vn_first_set_id]), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, function (response, status) {
			preview.attr('aria-busy', 'false');
			if (status === 'error') {
				preview.html(<?= json_encode($vs_error_html, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
			}
		});
	});
	</script>
<?php else: ?>
	<p class="tadl-gallery-message"><?= _t('No galleries are currently available.'); ?></p>
<?php endif; ?>
</div>
