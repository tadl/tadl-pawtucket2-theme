<?php
require_once(__DIR__.'/../../helpers/place_contents.php');

/** Keep native SearchController rendering/exports while replacing only place contents. */
class tadlPlaceContentsPlugin extends BaseApplicationPlugin {
	protected $description = 'Resolve descendant-place object results without a compound text search.';

	public function checkStatus() {
		return ['description' => $this->description, 'available' => true, 'errors' => [], 'warnings' => []];
	}

	public function hookReplaceSearch($params) {
		$request = $this->getRequest();
		if (!$request || $request->getController() !== 'PlaceContents'
			|| strtolower($request->getAction()) !== 'objects'
			|| ($params['search'] ?? '') !== 'objects'
			|| ($params['browseInfo']['table'] ?? '') !== 'ca_objects') { return $params; }
		$params['result'] = tadlPlaceContentsResult($request, $params['browseInfo']);
		if (!$params['result']) { throw new RuntimeException('Unable to load place results.'); }
		return $params;
	}
}
