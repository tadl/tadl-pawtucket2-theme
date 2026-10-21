<?php
require_once(__DIR__.'/../../helpers/place_contents.php');
require_once(__DIR__.'/../../helpers/collection_contents.php');

/** Keep native SearchController rendering while replacing indexed place and collection contents. */
class tadlPlaceContentsPlugin extends BaseApplicationPlugin {
	protected $description = 'Resolve descendant place/collection object results without a compound text search.';

	public function checkStatus() {
		return ['description' => $this->description, 'available' => true, 'errors' => [], 'warnings' => []];
	}

	public function hookReplaceSearch($params) {
		$request = $this->getRequest();
		if (!$request || !in_array($request->getController(), ['PlaceContents', 'CollectionContents'], true)
			|| strtolower($request->getAction()) !== 'objects'
			|| ($params['search'] ?? '') !== 'objects'
			|| ($params['browseInfo']['table'] ?? '') !== 'ca_objects') { return $params; }
		$params['result'] = $request->getController() === 'PlaceContents'
			? tadlPlaceContentsResult($request, $params['browseInfo']) : tadlCollectionContentsResult($request, $params['browseInfo']);
		if (!$params['result']) { throw new RuntimeException('Unable to load place results.'); }
		return $params;
	}
}
