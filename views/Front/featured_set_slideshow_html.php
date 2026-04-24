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
	$va_slide_pages = array_chunk($va_slides, 3);
?>
	<div class="tadl-hero-slider" data-tadl-slider>
		<div class="tadl-hero-slides">
<?php
	foreach ($va_slide_pages as $vn_page_index => $va_slide_page) {
?>
			<div class="tadl-hero-page tadl-hero-page-count-<?= sizeof($va_slide_page); ?><?= ($vn_page_index === 0) ? ' is-active' : ''; ?>" data-tadl-page="<?= $vn_page_index; ?>">
<?php
		foreach ($va_slide_page as $va_slide) {
?>
				<div class="tadl-hero-card">
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
	}
?>
		</div>
<?php
	if (sizeof($va_slide_pages) > 1) {
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
				var $pages = $root.find('[data-tadl-page]');
				var current = 0;

				function showPage(index) {
					if (!$pages.length) { return; }
					current = (index + $pages.length) % $pages.length;
					$pages.removeClass('is-active').attr('aria-hidden', 'true');
					$pages.eq(current).addClass('is-active').attr('aria-hidden', 'false');
				}

				$root.find('[data-tadl-prev]').on('click', function() {
					showPage(current - 1);
				});

				$root.find('[data-tadl-next]').on('click', function() {
					showPage(current + 1);
				});

				showPage(0);
			});
		});
	</script>
<?php
}
?>
