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
			<div class="tadl-hero-track" data-tadl-track>
<?php
	foreach ($va_slides as $vn_slide_index => $va_slide) {
?>
				<div class="tadl-hero-slide<?= ($vn_slide_index === 0) ? ' is-visible' : ''; ?>" data-tadl-slide="<?= $vn_slide_index; ?>">
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
				</div>
<?php
	}
?>
			</div>
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
				var $track = $root.find('[data-tadl-track]');
				var $slides = $root.find('[data-tadl-slide]');
				var current = 0;
				var perView = 3;
				var startX = null;
				var startY = null;
				var didSwipe = false;

				function getPerView() {
					var width = window.innerWidth || document.documentElement.clientWidth || 1200;
					if (width <= 767) { return 1; }
					if (width <= 1100) { return 2; }
					return 3;
				}

				function getGap() {
					var rawGap = window.getComputedStyle($track[0]).columnGap || window.getComputedStyle($track[0]).gap || '0';
					return parseFloat(rawGap) || 0;
				}

				function maxStart() {
					return Math.max(0, $slides.length - perView);
				}

				function updateControls() {
					var disabled = $slides.length <= perView;
					$root.find('[data-tadl-prev], [data-tadl-next]').prop('disabled', disabled).attr('aria-hidden', disabled ? 'true' : 'false');
				}

				function setVisibleSlides() {
					$slides.removeClass('is-visible').attr('aria-hidden', 'true').find('a, button').attr('tabindex', '-1');
					$slides.slice(current, current + perView).addClass('is-visible').attr('aria-hidden', 'false').find('a, button').removeAttr('tabindex');
				}

				function applyPosition() {
					var slideWidth = $slides.length ? $slides.eq(0)[0].getBoundingClientRect().width : 0;
					var offset = current * (slideWidth + getGap());
					$track.css('transform', 'translate3d(' + (-offset) + 'px, 0, 0)');
					setVisibleSlides();
					updateControls();
				}

				function showPage(index) {
					if (!$slides.length) { return; }
					var max = maxStart();
					if (index > max) {
						current = 0;
					} else if (index < 0) {
						current = max;
					} else {
						current = index;
					}
					applyPosition();
				}

				function refreshLayout() {
					perView = getPerView();
					$root.css('--tadl-hero-per-view', perView);
					current = Math.min(current, maxStart());
					applyPosition();
				}

				$root.find('[data-tadl-prev]').on('click', function() {
					showPage(current - perView);
				});

				$root.find('[data-tadl-next]').on('click', function() {
					showPage(current + perView);
				});

				$root.on('touchstart pointerdown', function(event) {
					var point = event.originalEvent.touches ? event.originalEvent.touches[0] : event.originalEvent;
					startX = point.clientX;
					startY = point.clientY;
				});

				$root.on('touchend pointerup pointercancel', function(event) {
					if (startX === null) { return; }
					var changed = event.originalEvent.changedTouches ? event.originalEvent.changedTouches[0] : event.originalEvent;
					var deltaX = changed.clientX - startX;
					var deltaY = changed.clientY - startY;
					startX = null;
					startY = null;
					if (Math.abs(deltaX) < 45 || Math.abs(deltaX) < Math.abs(deltaY)) { return; }
					didSwipe = true;
					showPage(current + ((deltaX < 0) ? perView : -perView));
					window.setTimeout(function() { didSwipe = false; }, 250);
				});

				$root.on('click', 'a', function(event) {
					if (!didSwipe) { return; }
					event.preventDefault();
					event.stopImmediatePropagation();
				});

				$(window).on('resize orientationchange', refreshLayout);
				refreshLayout();
			});
		});
	</script>
<?php
}
?>
