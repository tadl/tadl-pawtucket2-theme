<script>
// Move the current image's native actions without rebuilding its callbacks.
function tadlPlaceImageToolbar(reset = false) {
	const detail = document.querySelector('.tadl-object-detail');
	const target = document.getElementById('tadlObjectMediaActions');
	if (!detail || !target) { return; }
	const toolbar = detail.querySelector('.tadl-object-media .tadl-image-toolbar');
	// Initial ready callbacks can run twice; keep the already placed toolbar.
	if (!toolbar && !reset) { return; }
	target.replaceChildren();
	if (toolbar) { target.appendChild(toolbar); }
}
jQuery(document).ready(function () { tadlPlaceImageToolbar(); });
</script>
