<?php
$sorts = (array)$this->getVar('sortBy');
if (!$sorts) { return; }
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$direction = $vs_sort_dir === 'desc' ? 'desc' : 'asc';
$directions = ['asc' => _t('Ascending'), 'desc' => _t('Descending')];
$info = (array)$this->getVar('browseInfo');
$defaults = (array)($info['sortDirection'] ?? []);
$current = _t('Current order: %1, %2', $vs_current_sort, $directions[$direction]);
?>
<details class="tadl-results-sort">
	<summary class="btn btn-default tadl-sort-toggle" title="<?= $escape($current); ?>">
		<span><?= $escape(_t('Sort by %1', $vs_current_sort)); ?></span>
		<span class="sr-only">. <?= $escape($directions[$direction]); ?></span><i class="fa fa-angle-down" aria-hidden="true"></i>
	</summary>
	<ul class="tadl-sort-menu" aria-label="<?= $escape(_t('Sort options')); ?>">
<?php foreach ($sorts as $sort => $fields): ?>
		<li class="tadl-sort-row">
<?php
	$selected = $sort === $vs_current_sort;
	$default = $defaults[$sort] ?? 'asc';
	$value = $selected ? ($direction === 'asc' ? 'desc' : 'asc')
		: (is_string($default) && strtolower($default) === 'desc' ? 'desc' : 'asc');
	$name = $selected
		? _t('Sort by %1. Currently %2; activate for %3.', $sort, $directions[$direction], $directions[$value])
		: _t('Sort by %1, %2', $sort, $directions[$value]);
	$content = '<span class="tadl-sort-field">'.$escape($sort).'</span>';
	if ($selected) {
		$content .= '<span class="tadl-sort-direction" aria-hidden="true">'.($direction === 'asc' ? '↓' : '↑').'</span>';
	}
	$attributes = ['aria-label' => $name, 'title' => $name];
	if ($selected) { $attributes['aria-current'] = 'true'; }
	print tadlBrowseResultLink($this->request, $content, 'tadl-sort-choice', [
		'view' => $vs_current_view, 'key' => $vs_browse_key, 'sort' => $sort,
		'direction' => $value, '_advanced' => $vn_is_advanced ? 1 : 0, 's' => 0
	], $attributes);
?>
		</li>
<?php endforeach; ?>
	</ul>
</details>
