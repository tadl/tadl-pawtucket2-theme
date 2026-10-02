<?php
$authority_groups = tadlAuthorityRelatedGroups($this->request, $t_item, $authority_table);
$authority_fields = trim($authority_fields);
$has_sidebar = (bool)($authority_fields || $authority_groups);
$loading_html = '<p class="tadl-authority-loading">'.caBusyIndicatorIcon($this->request).' '.tadlAuthorityEscape(_t('Loading related items...')).'</p>';
$empty_html = '<p class="tadl-authority-empty">'.tadlAuthorityEscape(tadlMediaPreference($this->request) === 'only' ? _t('No related items with digital media are currently available.') : _t('No related items are currently available.')).'</p>';
$error_html = '<p class="tadl-authority-error">'.tadlAuthorityEscape(_t('Related items could not be loaded. Use “View all related items” to open the results page.')).'</p>';
?>
<article class="tadl-authority-detail">
	<nav class="tadl-authority-nav" aria-label="<?= tadlAuthorityEscape(_t('Record navigation')); ?>">
		{{{resultsLink}}}{{{previousLink}}}{{{nextLink}}}
		<?= caNavLink($this->request, tadlAuthorityEscape($authority['browse_label']), '', '', 'Browse', $authority['browse']); ?>
	</nav>
	<header class="tadl-authority-header">
		<div class="tadl-authority-kind"><?= tadlAuthorityEscape($authority['kind']); ?></div>
		<h1><?= tadlAuthorityEscape($authority['name']); ?></h1>
		<?php if ($authority['identifier'] || ($authority['type'] && strcasecmp($authority['type'], $authority['kind']) !== 0)): ?>
		<div class="tadl-authority-meta">
			<?php if ($authority['type'] && strcasecmp($authority['type'], $authority['kind']) !== 0): ?><span><?= tadlAuthorityEscape($authority['type']); ?></span><?php endif; ?>
			<?php if ($authority['identifier']): ?><span><?= tadlAuthorityEscape(_t('Identifier')); ?>: <?= tadlAuthorityEscape($authority['identifier']); ?></span><?php endif; ?>
		</div>
		<?php endif; ?>
	</header>
	<div class="tadl-authority-layout<?= $has_sidebar ? '' : ' tadl-authority-layout--items-only'; ?>">
		<section class="tadl-authority-items" aria-labelledby="tadl-authority-items-title">
			<div class="tadl-authority-section-heading">
				<h2 id="tadl-authority-items-title"><?= tadlAuthorityEscape(_t('Related items')); ?></h2>
				<a class="tadl-authority-all-items" href="<?= tadlAuthorityEscape($authority['items_url']); ?>"><?= tadlAuthorityEscape(_t('View all related items')); ?></a>
			</div>
			<div id="browseResultsContainer" class="row" aria-busy="true" aria-live="polite"><?= $loading_html; ?></div>
			<noscript><p><?= tadlAuthorityEscape(_t('Open “View all related items” to explore this record’s items.')); ?></p></noscript>
		</section>
		<?php if ($has_sidebar): ?>
		<aside class="tadl-authority-sidebar tadl-detail-metadata" aria-label="<?= tadlAuthorityEscape(_t('Record information')); ?>">
			<?php if ($authority_fields): ?>
			<section class="tadl-authority-about" aria-label="<?= tadlAuthorityEscape($authority['about']); ?>">
				<?= $authority_fields; ?>
			</section>
			<?php endif; ?>
			<?php foreach ($authority_groups as $group): ?>
			<section class="tadl-authority-related">
				<h2 class="tadl-metadata-heading"><?= tadlAuthorityEscape($group['heading']); ?></h2>
				<ul class="tadl-authority-relationships"><?= join('', array_slice($group['links'], 0, 5)); ?></ul>
				<?php if (count($group['links']) > 5): ?>
				<details class="tadl-authority-more">
					<summary><?= tadlAuthorityEscape(str_replace('%1', (string)(count($group['links']) - 5), $group['more'])); ?></summary>
					<ul class="tadl-authority-relationships"><?= join('', array_slice($group['links'], 5)); ?></ul>
				</details>
				<?php endif; ?>
			</section>
			<?php endforeach; ?>
		</aside>
		<?php endif; ?>
	</div>
	<?php if ($this->getVar('commentsEnabled') || $this->getVar('shareEnabled')): ?>
	<div id="detailTools">
		<?php if ($this->getVar('commentsEnabled')): ?>
		<details><summary><?= tadlAuthorityEscape(_t('Comments')); ?> (<?= count((array)$this->getVar('comments')); ?>)</summary><?= $this->getVar('itemComments'); ?></details>
		<?php endif; ?>
		<?php if ($this->getVar('shareEnabled')): ?><div class="detailTool"><?= $this->getVar('shareLink'); ?></div><?php endif; ?>
	</div>
	<?php endif; ?>
</article>
<script>
jQuery(document).ready(function () {
	var container = jQuery('#browseResultsContainer');
	container.load(<?= json_encode($authority['items_url'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, function (response, status) {
		container.attr('aria-busy', 'false');
		if (status === 'error') {
			container.html(<?= json_encode($error_html, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
			return;
		}
		if (container.find('[data-tadl-result-count="0"]').length) {
			container.html(<?= json_encode($empty_html, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
		}
	});
});
</script>
