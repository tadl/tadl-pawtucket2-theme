<?php
/** ---------------------------------------------------------------------
 * themes/tadl/Front/featured_set_slideshow_html.php
 * ----------------------------------------------------------------------
 */
$va_access_values = $this->getVar("access_values");
$va_item_ids = $this->getVar('featured_set_item_ids');
$va_slides = [];

if (is_array($va_item_ids) && sizeof($va_item_ids)) {
	$t_object = new ca_objects();
	$va_item_media = $t_object->getPrimaryMediaForIDs($va_item_ids, ["mediumlarge"], ['checkAccess' => $va_access_values]);
	$va_item_labels = $t_object->getPreferredDisplayLabelsForIDs($va_item_ids);

	if (is_array($va_item_media) && sizeof($va_item_media)) {
		foreach ($va_item_ids as $vn_object_id) {
			$va_media = $va_item_media[$vn_object_id] ?? null;
			if (!is_array($va_media)) { continue; }
			$vs_media_tag = null;
			foreach (["mediumlarge", "large", "medium", "small"] as $vs_candidate) {
				if (isset($va_media["tags"][$vs_candidate]) && trim((string)$va_media["tags"][$vs_candidate])) {
					$vs_media_tag = $vs_candidate;
					break;
				}
			}
			if (!$vs_media_tag) { continue; }
			$va_slides[] = [
				'media' => caDetailLink($this->request, $va_media["tags"][$vs_media_tag], '', 'ca_objects', $vn_object_id),
				'caption' => $va_item_labels[$vn_object_id] ?? ''
			];
		}
	}
}

if (sizeof($va_slides)) {
?>
	<div class="tadl-hero-slider" data-tadl-slider>
		<div class="tadl-hero-slides">
<?php
	foreach ($va_slides as $vn_index => $va_slide) {
?>
			<div class="tadl-hero-slide<?= ($vn_index === 0) ? ' is-active' : ''; ?>" data-tadl-slide="<?= $vn_index; ?>">
				<div class="tadl-hero-image"><?= $va_slide['media']; ?></div>
<?php
		if ($va_slide['caption']) {
?>
				<div class="tadl-hero-caption"><?= $va_slide['caption']; ?></div>
<?php
		}
?>
			</div>
<?php
	}
?>
		</div>
<?php
	if (sizeof($va_slides) > 1) {
?>
		<button class="tadl-hero-control tadl-hero-control-prev" type="button" data-tadl-prev aria-label="<?= _t("Previous"); ?>">
			<i class="fa fa-angle-left" aria-hidden="true"></i>
		</button>
		<button class="tadl-hero-control tadl-hero-control-next" type="button" data-tadl-next aria-label="<?= _t("Next"); ?>">
			<i class="fa fa-angle-right" aria-hidden="true"></i>
		</button>
<?php
	}
?>
	</div>
	<script type="text/javascript">
		jQuery(function($) {
			var $slider = $('[data-tadl-slider]');
			if (!$slider.length) { return; }

			$slider.each(function() {
				var $root = $(this);
				var $slides = $root.find('[data-tadl-slide]');
				var current = 0;

				function showSlide(index) {
					if (!$slides.length) { return; }
					current = (index + $slides.length) % $slides.length;
					$slides.removeClass('is-active').eq(current).addClass('is-active');
				}

				$root.find('[data-tadl-prev]').on('click', function() {
					showSlide(current - 1);
				});

				$root.find('[data-tadl-next]').on('click', function() {
					showSlide(current + 1);
				});

				showSlide(0);
			});
		});
	</script>
<?php
}
?>
