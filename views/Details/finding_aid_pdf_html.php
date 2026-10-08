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
p { margin: 0 0 8pt; }
.note { color: #555; font-size: 8pt; }
.field { margin-bottom: 8pt; }
.label { font-weight: bold; }
.collection { margin-bottom: 7pt; padding-left: 9pt; border-left: 1pt solid #ddd; word-wrap: break-word; page-break-inside: avoid; }
.collection-name { font-weight: bold; }
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
<div class="collection" style="margin-left: <?= (int)$node['depth'] * 14; ?>pt;">
<span class="collection-name"><?= $escape($node['title']); ?></span><?php if ($node['identifier'] !== '') { ?> <span class="note">[<?= $escape($node['identifier']); ?>]</span><?php } ?>
<br><span class="note"><?= (int)$node['count']; ?> directly linked <?= $node['count'] === 1 ? 'item' : 'items'; ?></span>
</div>
<?php } } ?>
<h2>Collection contents</h2>
<p><?= (int)$aid['object_count']; ?> <?= $aid['object_count'] === 1 ? 'item' : 'items'; ?>. Includes records with and without media in this collection and its readable subcollections.</p>
<?php if (count($aid['collections']) > 1) { ?><p class="note">Objects linked to multiple collections count once in this total. Directly linked counts above can overlap.</p><?php } ?>
<?php if (!$aid['object_count']) { ?><p>No accessible object records are currently linked to this collection.</p><?php } ?>
</body></html>
