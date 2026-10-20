<?php
require_once(__DIR__.'/../views/Details/detail_field_helpers.php');
require_once(__DIR__.'/finding_aid.php');

/** "Part of" names real readable ancestors only, never the selected collection. */
function tadlCollectionParentLinks($request, array $ancestry, $selected_id) {
	$links = [];
	foreach ($ancestry as $id => $ancestor) {
		if ((int)$id === (int)$selected_id) { continue; }
		$label = tadlFindingAidValue($request, $ancestor, ['ca_collections.preferred_labels.name']);
		if ($label === '') { continue; }
		$url = caNavUrl($request, '', 'Detail', 'collections/'.(int)$id);
		$links[] = '<a href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</a>';
	}
	return join(' &gt; ', $links);
}

/** Read only the selected archival unit's configured descriptive fields. */
function tadlCollectionMetadataField($request, $item, $label, $bundles, $repeat = false) {
	foreach ((array)$bundles as $bundle) {
		if (!preg_match('/\Aca_collections\.([a-zA-Z0-9_]+)(?:\.[a-zA-Z0-9_]+)*\z/', $bundle, $match)
			|| !$item->hasElement($match[1]) || !$item->isReadable($request, $match[1])) { continue; }
		$template = $repeat ? '<unit relativeTo="'.$bundle.'" delimiter="<br/>">^'.$bundle.'</unit>' : '^'.$bundle;
		if ($field = tadlDetailField($request, $item, $label, $template, ['dontReturnDefault' => true])) { return $field; }
	}
	return '';
}

/** Keep each external-link source with its URL, and escape both for their context. */
function tadlCollectionExternalLinks($request, $item) {
	if (!$item->hasElement('external_link') || !$item->isReadable($request, 'external_link')) { return ''; }
	$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$links = [];
	$attributes = $item->getAttributeDisplayValues('external_link', $item->getPrimaryKey(), [
		'checkAccess' => caGetUserAccessValues($request), 'convertCodesToDisplayText' => true
	]);
	foreach ((array)$attributes as $values) {
		foreach ((array)$values as $value) {
			$url = trim((string)($value['url_entry'] ?? ''));
			if ($url === '') { continue; }
			$source = trim((string)($value['url_source'] ?? ''));
			$link = $escape($url);
			if (filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
				$link = '<a href="'.$escape($url).'">'.$escape($url).'</a>';
			}
			$links[] = ($source !== '' ? $escape($source).': ' : '').$link;
		}
	}
	if (!$links) { return ''; }
	$label = _t('External links');
	return '<div class="unit"><label>'.$escape($label).'</label>'.tadlDetailExpandableValue(join('<br/>', $links), $label, true).'</div>';
}
