<?php
/** Native facet content already respects catalogue access, label permissions and ACLs. */
$facet = ((array)$this->getVar('facets'))['term_facet'] ?? [];
$terms = [];
foreach ((array)($facet['content'] ?? []) as $item) {
	if (!is_array($item)) { continue; }
	$id = (int)($item['id'] ?? 0);
	$label = trim((string)($item['label'] ?? ''));
	if ($id > 0 && $label !== '') { $terms[$id] = $label; }
}
natcasesort($terms);
$escape = static function ($value) { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
?>
<section class="tadl-subject-index" aria-labelledby="tadl-subject-index-title">
	<h1 id="tadl-subject-index-title"><?php print _t('Browse Subjects'); ?></h1>
	<p><?php print _t('Choose a subject to see related objects.'); ?></p>
<?php if ($terms) { ?>
	<ul class="tadl-subject-index-list">
<?php foreach ($terms as $id => $label) {
	$url = caNavUrl($this->request, '', 'Browse', 'subjects', ['facet' => 'term_facet', 'id' => $id, 'clear' => 1]);
	print '<li><a href="'.$escape($url).'">'.$escape($label).'</a></li>';
} ?>
	</ul>
<?php } else { ?>
	<p><?php print _t('No subjects are available.'); ?></p>
<?php } ?>
</section>
