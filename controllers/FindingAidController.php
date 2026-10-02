<?php
require_once(__CA_LIB_DIR__.'/pawtucket/BasePawtucketController.php');
require_once(__DIR__.'/../helpers/finding_aid.php');

class FindingAidController extends BasePawtucketController {
	public function Download() {
		if ($this->request->getRequestMethod() !== 'GET') {
			$this->response->addHeader('Allow', 'GET');
			$this->response->setHTTPResponseCode(405, 'Method Not Allowed'); return;
		}
		if ($this->request->config->get('pawtucket_requires_login') && !$this->request->isLoggedIn()) {
			$this->response->setHTTPResponseCode(403, 'Forbidden'); return;
		}
		$config = Configuration::load(__DIR__.'/../conf/finding_aid.conf');
		if (!$config->get('enabled')) { $this->response->setHTTPResponseCode(404, 'Not Found'); return; }
		$id = (int)$this->request->getParameter('collection_id', pInteger);
		if ($id < 1) { $this->response->setHTTPResponseCode(400, 'Bad Request'); return; }
		$collection = Datamodel::getInstance('ca_collections', true);
		if (!$collection || !$collection->load($id) || !tadlFindingAidReadable($this->request, $collection)) {
			$this->response->setHTTPResponseCode(404, 'Not Found'); return;
		}
		$this->response->addHeader('Cache-Control', 'private, no-store');
		$time_limit = (int)ini_get('max_execution_time');
		if ($time_limit > 0) { set_time_limit(max($time_limit, 180)); }
		try {
			$data = tadlFindingAidData($this->request, $collection, $config);
			$this->view->setVar('finding_aid', $data);
			$bytes = tadlFindingAidPDF($this->view->render('Details/finding_aid_pdf_html.php'));
		} catch (Throwable $error) { $bytes = null; }
		if (!$bytes) {
			$this->response->setHTTPResponseCode(503, 'Service Unavailable');
			$this->response->addContent(_t('The finding aid could not be generated. Please try again later.')); return;
		}
		$name = trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $data['identifier'] ?: $data['title']), '-');
		$this->view->setVar('finding_aid_name', substr($name ?: 'collection-'.$id, 0, 120).'-finding-aid.pdf');
		$this->view->setVar('finding_aid_bytes', $bytes);
		$this->render('Details/finding_aid_binary.php', true);
	}
}
