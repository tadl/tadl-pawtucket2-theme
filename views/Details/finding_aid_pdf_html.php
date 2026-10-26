<?php
require_once(__DIR__.'/../../helpers/finding_aid.php');
$aid = $this->getVar('finding_aid');
$fields = $aid['fields'];
$level = $fields['level']['value'] ?? '';
unset($fields['level']);
$escape = static function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title><?= $escape($aid['title']); ?> - Finding aid</title>
<style>
@page { margin: 54pt 54pt 54pt; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5pt; line-height: 1.4; color: #111; }
.brand { font-size: 9pt; color: #444; margin: 0 0 20pt; }
.kicker { font-size: 10pt; text-transform: uppercase; letter-spacing: 1pt; margin: 0 0 5pt; }
h1 { font-size: 21pt; line-height: 1.25; margin: 0 0 8pt; }
h2 { font-size: 13pt; margin: 22pt 0 9pt; border-bottom: 1pt solid #bbb; padding-bottom: 5pt; page-break-after: avoid; page-break-inside: avoid; }
h3 { font-size: 11pt; line-height: 1.35; margin: 0 0 6pt; }
p { margin: 0 0 8pt; }
.note { color: #555; font-size: 8pt; }
.field { margin-bottom: 9pt; word-wrap: break-word; orphans: 3; widows: 3; }
.label { font-weight: bold; }
.collection { margin-bottom: 10pt; padding-left: 9pt; border-left: 1pt solid #ddd; word-wrap: break-word; }
.collection-heading { page-break-inside: avoid; }
.collection-heading.with-fields { page-break-after: avoid; }
.collection-name { font-weight: bold; }
.collection-level { font-weight: normal; }
</style></head><body>
<p class="brand">Traverse Area District Library | Local History Collection</p>
<p class="kicker">Finding aid</p>
<h1><?= $escape($aid['title']); ?><?php if ($level !== '') { ?> <span class="collection-level">(<?= $escape($level); ?>)</span><?php } ?></h1>
<?php if ($aid['identifier'] !== '') { ?><p><span class="label">Collection identifier:</span> <?= $escape($aid['identifier']); ?></p><?php } ?>
<p class="note">Generated <?= $escape($aid['generated']); ?> from recorded archival descriptions. This is the document generation date, not a description revision date.</p>
<?php if ($fields) { ?>
<h2>About this collection</h2>
<?php foreach ($fields as $field) { ?>
<div class="field"><span class="label"><?= $escape($field['label']); ?>:</span><br><?= tadlFindingAidFieldHTML($field); ?></div>
<?php } } ?>
<?php if (count($aid['collections']) > 1) { ?>
<h2>Arrangement and contents</h2>
<?php foreach ($aid['collections'] as $node) { ?>
<?php
if ($node['parent_id'] === null) { continue; }
$node_fields = $node['fields'];
$node_level = $node_fields['level']['value'] ?? '';
unset($node_fields['level']);
?>
<div class="collection" style="margin-left: <?= min(8, max(0, (int)$node['depth'] - 1)) * 12; ?>pt;">
<div class="collection-heading<?= $node_fields ? ' with-fields' : ''; ?>">
<h3 class="collection-name"><?= $escape($node['title']); ?><?php if ($node_level !== '') { ?> <span class="collection-level">(<?= $escape($node_level); ?>)</span><?php } ?></h3><?php if ($node['identifier'] !== '') { ?><p class="note"><?= $escape($node['identifier']); ?></p><?php } ?>
</div>
<?php foreach ($node_fields as $field) { ?>
<div class="field"><span class="label"><?= $escape($field['label']); ?>:</span><br><?= tadlFindingAidFieldHTML($field); ?></div>
<?php } ?>
</div>
<?php } } ?>
</body></html>
