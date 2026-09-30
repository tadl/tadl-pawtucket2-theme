<?php
	$va_results = $this->getVar('results');
	$va_block_names = $this->getVar('blockNames');
	// Native multisearch captures counts before rendering the filtered result previews.
	$va_table_counts = array();
	foreach ($va_block_names as $vs_block) {
		$va_results[$vs_block]['count'] = count($va_results[$vs_block]['ids'] ?? array());
		$vs_table = $va_results[$vs_block]['table'];
		$va_table_counts[$vs_table] = ($va_table_counts[$vs_table] ?? 0) + $va_results[$vs_block]['count'];
	}
	$va_results['_info_']['totalCount'] = array_sum($va_table_counts);
	foreach ($va_table_counts as $vs_table => $vn_count) {
		$o_context = new ResultContext($this->request, $vs_table, 'multisearch');
		$o_context->setSearchHistory($vn_count);
		$o_context->setParameter('media', null);
		$o_context->saveContext();
	}
	$vn_result_count = (int)($va_results['_info_']['totalCount'] ?? 0);
	$vs_search_display = caUcFirstUTF8Safe((string)$this->getVar('searchForDisplay'));
	$escape = function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
?>
<div class="tadl-search-overview">
<?php if ($vn_result_count > 0) { ?>
	<h1><?php print $escape(_t('Search results for %1', $vs_search_display)); ?></h1>
	<nav class="tadl-search-categories" aria-label="<?php print $escape(_t('Result categories')); ?>">
<?php
	foreach ($va_block_names as $vs_block) {
		if (empty($va_results[$vs_block]['count'])) { continue; }
?>
		<a href="#<?php print $escape($vs_block); ?>"><?php print $escape($va_results[$vs_block]['displayName']); ?> <span><?php print (int)$va_results[$vs_block]['count']; ?></span></a>
<?php } ?>
	</nav>
<?php
	foreach ($va_block_names as $vs_block) {
		if (empty($va_results[$vs_block]['count'])) { continue; }
?>
	<section id="<?php print $escape($vs_block); ?>" class="tadl-search-section" aria-labelledby="<?php print $escape($vs_block); ?>-heading">
		<?php print $va_results[$vs_block]['html']; ?>
	</section>
<?php
	}
} else {
?>
	<h1><?php print $escape(_t('Your search for %1 returned no results', $vs_search_display)); ?></h1>
<?php } ?>
</div>
