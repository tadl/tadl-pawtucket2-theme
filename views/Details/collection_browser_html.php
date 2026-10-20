<?php
$config = caGetCollectionsConfig();
$this->setVar('collections_config', $config);
$this->setVar('access_values', caGetUserAccessValues($this->request));
$list = new ca_lists();
foreach (['exclude', 'non_linkable'] as $kind) {
	$codes = $config->get($kind.'_collection_types');
	$this->setVar($kind.'_collection_type_ids', $codes ? $list->getItemIDsFromList('collection_types', $codes, ['dontIncludeSubItems'=>true]) : []);
}
$icons = [];
foreach ((array)$config->get('collection_type_icons') as $code => $icon) { $icons[$list->getItemId('collection_types', $code)] = $icon; }
$this->setVar('collection_type_icons', $icons);
print $this->render('Collections/collection_hierarchy_html.php');
