<?php
require_once(__CA_APP_DIR__.'/controllers/SearchController.php');
require_once(__DIR__.'/../helpers/display_preferences.php');
require_once(__DIR__.'/../helpers/place_contents.php');

/** Rebuild the place scope on every page; delegate object search/access to Pawtucket. */
class PlaceContentsController extends SearchController {
	public function Objects() {
		if ($this->request->getRequestMethod() !== 'GET') {
			$this->response->addHeader('Allow', 'GET');
			$this->response->setHTTPResponseCode(405, 'Method Not Allowed'); return;
		}
		if ($this->request->config->get('pawtucket_requires_login') && !$this->request->isLoggedIn()) { return; }
		$id = (int)$this->request->getParameter('place_id', pInteger);
		if ($id < 1) { $this->response->setHTTPResponseCode(400, 'Bad Request'); return; }
		$place = Datamodel::getInstance('ca_places', false);
		$access = array_map('intval', (array)caGetUserAccessValues($this->request));
		if (!$place || !$place->load($id)
			|| !tadlReadableIDs($this->request, 'ca_places', [$id], $access, 'preferred_labels')) {
			$this->response->setHTTPResponseCode(404, 'Not Found'); return;
		}
		if (!(ApplicationPluginManager::checkPluginStatus('tadlPlaceContents')['available'] ?? false)) {
			throw new RuntimeException('The place contents search plugin is unavailable.');
		}
		$sorts = (array)(caGetBrowseConfig()->getAssoc('browseTypes')['objects']['sortBy'] ?? []);
		$sort = (string)$this->request->getParameter('sort', pString);
		$view = tadlDisplayView($this->request);
		$direction = (string)$this->request->getParameter('direction', pString);
		$params = [
			// The theme's hookReplaceSearch resolves descendants through SQL. Keep
			// only this short root reference for native headings/result navigation.
			'search' => 'place_id:'.$id,
			'view' => in_array($view, ['images', 'list', 'pdf', 'xlsx', 'pptx'], true) ? $view : 'images',
			'sort' => isset($sorts[$sort]) ? $sort : 'Identifier',
			'direction' => in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc',
			's' => max(0, (int)$this->request->getParameter('s', pInteger)),
			'n' => tadlResultPageSize($this->request), 'per_page' => tadlResultPageChoice($this->request),
			'_advanced' => 0, 'key' => '', 'facets' => '', 'search_refine' => '',
			'removeCriterion' => '', 'removeID' => '', 'facet' => '', 'id' => '',
			'source' => '', 'getFacet' => 0, 'label' => '', 'values' => ''
		];
		foreach ($params as $key => $value) { $this->request->setParameter($key, $value, 'GET'); }
		$this->response->addHeader('Cache-Control', 'private, no-store');
		$this->response->addHeader('X-Robots-Tag', 'noindex, follow');
		parent::__call('objects', [[]]);
	}
}
