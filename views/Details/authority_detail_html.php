<?php
$authority_groups = tadlAuthorityRelatedGroups($this->request, $t_item, $authority_table);
$authority_fields = trim($authority_fields);
$has_sidebar = (bool)($authority_fields || $authority_groups);
$this->setVar('tadl_related_scope', ['table'=>$authority_table, 'id'=>(int)$t_item->get($authority['key']), 'family'=>$authority['browse'] === 'families']);
?>
<article class="tadl-authority-detail">
	<header class="tadl-authority-header tadl-record-header">
		<div class="tadl-record-heading">
		<div class="tadl-authority-kind"><?= tadlAuthorityEscape($authority['kind']); ?></div>
		<h1><?= tadlAuthorityEscape($authority['name']); ?></h1>
		<?php if ($authority['identifier'] || ($authority['type'] && strcasecmp($authority['type'], $authority['kind']) !== 0)): ?>
		<div class="tadl-authority-meta">
			<?php if ($authority['type'] && strcasecmp($authority['type'], $authority['kind']) !== 0): ?><span><?= tadlAuthorityEscape($authority['type']); ?></span><?php endif; ?>
			<?php if ($authority['identifier']): ?><span><?= tadlAuthorityEscape(_t('Identifier')); ?>: <?= tadlAuthorityEscape($authority['identifier']); ?></span><?php endif; ?>
		</div>
		<?php endif; ?>
		</div>
		<?= tadlDetailNavigation($this, $authority['browse'], $authority['browse_label'], 'Browse', $authority['browse'] ?? 'records'); ?>
	</header>
	<div class="tadl-authority-layout<?= $has_sidebar ? '' : ' tadl-authority-layout--items-only'; ?>">
		<section class="tadl-authority-items" aria-labelledby="tadl-authority-items-title">
			<div class="tadl-authority-section-heading">
				<h2 id="tadl-authority-items-title"><?= tadlAuthorityEscape(_t('Related items')); ?></h2>
				<a class="tadl-authority-all-items" href="<?= tadlAuthorityEscape($authority['items_url']); ?>"><?= tadlAuthorityEscape(_t('View all related items')); ?></a>
			</div>
			<?php if ($authority['items_note'] ?? ''): ?><p><?= tadlAuthorityEscape($authority['items_note']); ?></p><?php endif; ?>
			<div id="browseResultsContainer" class="row"><?= $this->render('Details/related_items_html.php'); ?></div>
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
