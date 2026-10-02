<?php
$aid = $this->getVar('finding_aid');
$escape = static function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title><?= $escape($aid['title']); ?> - Finding aid</title>
<style>
@page { margin: 42pt 42pt 54pt; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; line-height: 1.45; color: #111; }
.brand { font-size: 9pt; color: #444; margin: 0 0 20pt; }
.kicker { font-size: 10pt; text-transform: uppercase; letter-spacing: 1pt; margin: 0 0 5pt; }
h1 { font-size: 21pt; line-height: 1.25; margin: 0 0 8pt; }
h2 { font-size: 13pt; margin: 22pt 0 9pt; border-bottom: 1pt solid #bbb; padding-bottom: 5pt; }
h3 { font-size: 10pt; margin: 12pt 0 3pt; }
p { margin: 0 0 8pt; }
.note { color: #555; font-size: 8pt; }
.field { margin-bottom: 8pt; }
.label { font-weight: bold; }
.entry { padding-bottom: 10pt; border-bottom: 0.5pt solid #ddd; page-break-inside: avoid; word-wrap: break-word; }
.entry h3 { page-break-after: avoid; }
.entry .field { margin-bottom: 3pt; }
.collection { margin-bottom: 6pt; }
</style></head><body>
<p class="brand">Traverse Area District Library | Local History Collection</p>
<p class="kicker">Finding aid</p>
<h1><?= $escape($aid['title']); ?></h1>
<?php if ($aid['identifier'] !== '') { ?><p><span class="label">Collection identifier:</span> <?= $escape($aid['identifier']); ?></p><?php } ?>
<p class="note">Generated <?= $escape($aid['generated']); ?> from current catalog records.</p>
<?php if ($aid['fields']) { ?>
<h2>About this collection</h2>
<?php foreach ($aid['fields'] as $field) { ?>
<div class="field"><span class="label"><?= $escape($field['label']); ?>:</span><br><?= nl2br($escape($field['value'])); ?></div>
<?php } } ?>
<?php if (count($aid['collections']) > 1) { ?>
<h2>Collection organization</h2>
<?php foreach ($aid['collections'] as $node) { ?>
<div class="collection"><?= $escape($node['path']); ?> <span class="note">(<?= (int)$node['count']; ?> directly linked items)</span></div>
<?php } } ?>
<h2>Object inventory</h2>
<p><?= count($aid['objects']); ?> <?= count($aid['objects']) === 1 ? 'item' : 'items'; ?>. Includes records with and without media in this collection and its readable subcollections.</p>
<?php if (count($aid['collections']) > 1) { ?><p class="note">Objects linked to multiple collections appear once in this inventory.</p><?php } ?>
<?php if (!$aid['objects']) { ?><p>No accessible object records are currently linked to this collection.</p><?php } ?>
<?php foreach ($aid['objects'] as $index => $object) { ?>
<div class="entry">
<h3><?= $index + 1; ?>. <?= $escape($object['title']); ?></h3>
<?php foreach ($object['fields'] as $field) { ?>
<div class="field"><span class="label"><?= $escape($field['label']); ?>:</span> <?= nl2br($escape($field['value'])); ?></div>
<?php } ?>
<?php if (count($aid['collections']) > 1) { ?><div class="field"><span class="label">Collection / series:</span> <?= $escape(implode('; ', $object['collections'])); ?></div><?php } ?>
</div>
<?php } ?>
</body></html>
