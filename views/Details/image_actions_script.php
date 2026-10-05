<script>
// Move the current image/PDF's native actions without rebuilding its callbacks.
function tadlPlaceImageToolbar(reset = false) {
	const detail = document.querySelector('.tadl-object-detail');
	const target = document.getElementById('tadlObjectMediaActions');
	if (!detail || !target) { return; }
	const toolbar = Array.from(detail.querySelectorAll('.tadl-object-media .tadl-image-toolbar'))
		.find(node => !target.contains(node));
	// Initial ready callbacks can run twice; keep the already placed toolbar.
	if (!toolbar && !reset) { return; }
	target.replaceChildren();
	if (toolbar) { target.appendChild(toolbar); }
}
jQuery(document).ready(function () { tadlPlaceImageToolbar(); });
</script>
