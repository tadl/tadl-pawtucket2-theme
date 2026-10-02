<div class="close"><a href="#" aria-label="<?= htmlspecialchars(_t('Close viewer help'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-times" aria-hidden="true"></i></a></div>
<div class="content">
	<h2><?= _t('How to use the image viewer'); ?></h2>
	<p class="tileviewerHelpText"><?= _t('Click and drag to move around the image. Use the mouse wheel or trackpad, the + and − buttons, or the slider at the top to zoom. More detail loads as you zoom in.'); ?></p>
	<p class="tileviewerHelpText"><?= _t('The controls on the left let you show an overview, rotate the image, fit the whole image to the window, and open this help. When available, select the download icon to choose an image format. Click elsewhere or press Escape to close the download menu.'); ?></p>
	<h3><?= _t('Keyboard shortcuts'); ?></h3>
	<ul class="tileviewerHelpList">
		<li><?= _t('%1 or %2 to zoom in', '<code>+</code>', '<code>]</code>'); ?></li>
		<li><?= _t('%1 or %2 to zoom out', '<code>-</code>', '<code>[</code>'); ?></li>
		<li><?= _t('Arrow keys to move around the image'); ?></li>
		<li><?= _t('%1 to fit and center the image', '<code>h</code>'); ?></li>
		<li><?= _t('%1 to show or hide the overview', '<code>n</code>'); ?></li>
		<li><?= _t('%1 to show or hide viewer controls', '<code>c</code>'); ?></li>
	</ul>
</div>
