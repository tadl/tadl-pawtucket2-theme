<?php
$aid = $this->getVar('finding_aid');
$summary_fields = $aid['fields']; $root_level = $summary_fields['level']['value'] ?? ''; unset($summary_fields['level']);
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$renderFields = static function ($fields) use ($escape) {
 unset($fields['level']);
 if (!$fields) { return; }
 print '<dl class="tadl-finding-aid-fields">';
 foreach ($fields as $field) { print '<dt>'.$escape($field['label']).'</dt><dd>'.nl2br($escape($field['value'])).'</dd>'; }
 print '</dl>';
};
$children = [];
foreach ($aid['collections'] as $id => $node) { if ($node['parent_id'] !== null) { $children[(int)$node['parent_id']][$id] = $node; } }
$renderChildren = static function ($parent) use (&$renderChildren, $children, $renderFields, $escape) {
 if (empty($children[$parent])) { return; }
 print '<ol class="tadl-finding-aid-tree">';
 foreach ($children[$parent] as $id => $node) {
  $level = $node['fields']['level']['value'] ?? '';
  print '<li><p><strong>'.$escape($node['title']).'</strong>'.($level !== '' ? ' ('.$escape($level).')' : '').'</p>';
  if ($node['identifier'] !== '') { print '<p>'.$escape($node['identifier']).'</p>'; }
  $renderFields($node['fields']); $renderChildren($id); print '</li>';
 }
 print '</ol>';
};
?>
<article class="tadl-finding-aid-text">
 <h1><?= $escape(_t('Finding aid').': '.$aid['title']); ?><?php if ($root_level !== '') { ?> <span class="collection-level">(<?= $escape($root_level); ?>)</span><?php } ?></h1>
 <?php if ($aid['identifier'] !== '') { ?><p><?= $escape(_t('Collection identifier').': '.$aid['identifier']); ?></p><?php } ?>
 <p><a href="<?= $escape($this->getVar('download_url')); ?>" rel="nofollow"><?= $escape(_t('Download PDF')); ?></a></p>
 <?php if ($summary_fields) { ?><h2><?= $escape(_t('About this collection')); ?></h2><?php $renderFields($summary_fields); } ?>
 <?php if ($children) { ?><h2><?= $escape(_t('Arrangement and contents')); ?></h2><?php $renderChildren((int)array_key_first($aid['collections'])); } ?>
 <p><?= $escape(_t('Generated').' '.$aid['generated']); ?></p>
</article>
