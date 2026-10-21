<?php
$sorts = (array)$this->getVar('sortBy');
if (!$sorts) { return; }
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$direction = $vs_sort_dir === 'desc' ? 'desc' : 'asc';
$directions = ['asc' => _t('Ascending'), 'desc' => _t('Descending')];
$current = _t('Current order: %1, %2', $vs_current_sort, $directions[$direction]);
?>
<details class="tadl-results-sort">
	<summary class="tadl-results-action" title="<?= $escape($current); ?>">
		<i class="fa fa-sort" aria-hidden="true"></i><span><?= $escape(_t('Sort by')); ?></span>
		<span class="sr-only">. <?= $escape($current); ?></span><i class="fa fa-angle-down" aria-hidden="true"></i>
	</summary>
	<ul class="tadl-sort-menu" aria-label="<?= $escape(_t('Sort options')); ?>">
<?php foreach ($sorts as $sort => $fields): ?>
		<li class="tadl-sort-row<?= $sort === $vs_current_sort ? ' is-current' : ''; ?>">
			<span class="tadl-sort-field"><?= $escape($sort); ?></span>
<?php
	foreach ($directions as $value => $label) {
		$selected = $sort === $vs_current_sort && $direction === $value;
		$name = _t('Sort by %1, %2', $sort, $label);
		$content = '<span aria-hidden="true">'.($value === 'asc' ? '↑' : '↓').'</span>';
		if ($selected) { $content .= '<i class="fa fa-check" aria-hidden="true"></i>'; }
		$attributes = ['aria-label' => $name, 'title' => $name];
		if ($selected) { $attributes['aria-current'] = 'true'; }
		print tadlBrowseResultLink($this->request, $content, 'tadl-sort-choice', [
			'view' => $vs_current_view, 'key' => $vs_browse_key, 'sort' => $sort,
			'direction' => $value, '_advanced' => $vn_is_advanced ? 1 : 0, 's' => 0
		], $attributes);
	}
?>
		</li>
<?php endforeach; ?>
	</ul>
</details>
