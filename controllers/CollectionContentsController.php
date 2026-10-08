<?php
require_once(__CA_APP_DIR__.'/controllers/SearchController.php');
require_once(__DIR__.'/../helpers/collection_contents.php');

/** Reuse native search/ACLs/exports; callers supply a collection, never a query. */
class CollectionContentsController extends SearchController {
	public function Objects() {
		if ($this->request->getRequestMethod() !== 'GET') {
			$this->response->addHeader('Allow', 'GET');
			$this->response->setHTTPResponseCode(405, 'Method Not Allowed'); return;
		}
		if ($this->request->config->get('pawtucket_requires_login') && !$this->request->isLoggedIn()) { return; }
		$id = (int)$this->request->getParameter('collection_id', pInteger);
		if ($id < 1) { $this->response->setHTTPResponseCode(400, 'Bad Request'); return; }
		$collection = Datamodel::getInstance('ca_collections', true);
		$access = array_map('intval', (array)caGetUserAccessValues($this->request));
		if (!$access || !$collection || !$collection->load($id)
			|| !tadlCollectionContentsReadable($this->request, $collection, $access)) {
			$this->response->setHTTPResponseCode(404, 'Not Found'); return;
		}
		$mode = tadlCollectionContentsMode($this->request);
		$sorts = (array)(caGetBrowseConfig()->getAssoc('browseTypes')['objects']['sortBy'] ?? []);
		$sort = $this->request->getParameter('sort', pString);
		$view = $this->request->getParameter('view', pString);
		$direction = $this->request->getParameter('direction', pString);
		$params = [
			'search' => tadlCollectionContentsSearch(tadlCollectionContentsIDs($this->request, $collection, $mode === 'flat')),
			'collection_view' => $mode, 'tadl_collection_controls' => 1, 'tadl_collection_id' => $id,
			'view' => in_array($view, ['images', 'list', 'pdf', 'xlsx', 'pptx'], true) ? $view : 'images',
			'sort' => isset($sorts[$sort]) ? $sort : 'Identifier',
			'direction' => in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc',
			's' => max(0, (int)$this->request->getParameter('s', pInteger)),
			'n' => $view === 'list' ? 24 : 9,
			'_advanced' => 0, 'key' => '', 'facets' => '', 'search_refine' => '',
			'removeCriterion' => '', 'removeID' => '', 'facet' => '', 'id' => '',
			'source' => '', 'getFacet' => 0, 'label' => '', 'values' => ''
		];
		// GET has precedence over PATH/POST; empty strings also override stored keys.
		foreach ($params as $key => $value) { $this->request->setParameter($key, $value, 'GET'); }
		$this->response->addHeader('Cache-Control', 'private, no-store');
		$this->response->addHeader('X-Robots-Tag', 'noindex, follow');
		parent::__call('objects', [[]]);
	}
}
