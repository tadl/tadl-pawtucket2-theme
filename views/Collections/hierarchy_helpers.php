<?php
require_once dirname(__DIR__, 2).'/helpers/collection_contents.php';

/** Preserve the visible title in the accessible name while describing media availability. */
function tadlCollectionHierarchyTitle($title, $has_media) {
	$title = htmlspecialchars((string)$title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	return $has_media ? '<strong class="tadl-collection-has-media">'.$title.'</strong><span class="sr-only"> — '.htmlspecialchars(_t('contains digitized media'), ENT_QUOTES, 'UTF-8').'</span>' : $title;
}

/** Sort only the supplied access/media-filtered siblings; never expand the hierarchy. */
function tadlCollectionHierarchyIDs(array $ids, $sort) {
	if (count($ids) < 2 || $sort !== 'ca_collections.preferred_labels.name') { return $ids; }
	$labels = [];
	$rows = caMakeSearchResult('ca_collections', $ids);
	while ($rows->nextHit()) {
		$name = (string)$rows->get('ca_collections.preferred_labels.name');
		$labels[(int)$rows->get('ca_collections.collection_id')] = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
	}
	$positions = array_flip($ids);
	usort($ids, function ($a, $b) use ($labels, $positions) {
		return strnatcmp($labels[(int)$a] ?? '', $labels[(int)$b] ?? '') ?: ($positions[$a] <=> $positions[$b]);
	});
	return $ids;
}
