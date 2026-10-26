<?php
require_once(__CA_LIB_DIR__.'/pawtucket/BasePawtucketController.php');
require_once(__DIR__.'/../helpers/finding_aid.php');
require_once(__DIR__.'/../helpers/export_diagnostics.php');
require_once(__DIR__.'/../helpers/crawler_policy.php');

// "FindingAid" is reserved by Pawtucket's bundled plugin during URL parsing.
class CollectionFindingAidController extends BasePawtucketController {
	public function Download() {
		$this->renderFindingAid(false);
	}

	public function View() {
		$this->renderFindingAid(true);
	}

    /** Equivalent semantic HTML remains usable without a PDF viewer or PDF tagging support. */
    public function Text() {
        if (!$collection = $this->readableCollection()) { return; }
        $this->response->addHeader('Cache-Control', 'private, no-store');
        $this->response->addHeader('X-Robots-Tag', 'noindex, nofollow');
        $aid = tadlFindingAidData($this->request, $collection, Configuration::load(__DIR__.'/../conf/finding_aid.conf'));
        MetaTagManager::setWindowTitle(_t('Finding aid').': '.$aid['title']);
        $this->view->setVar('finding_aid', $aid);
        $this->view->setVar('collection_url', caNavUrl($this->request, '', 'Detail', 'collections/'.(int)$collection->getPrimaryKey()));
        $this->view->setVar('download_url', caNavUrl($this->request, '', 'CollectionFindingAid', 'Download', ['collection_id' => (int)$collection->getPrimaryKey()]));
        $this->render('Details/finding_aid_html.php', false);
    }

	public function Viewer() {
		// Native caMediaPanel.showPanel() loads read-only overlay markup by POST.
		if (!$collection = $this->readableCollection(['GET', 'POST'])) { return; }
		$this->response->addHeader('Cache-Control', 'private, no-store');
		$this->response->addHeader('X-Robots-Tag', 'noindex, nofollow');
		$id = (int)$collection->getPrimaryKey();
		$pdf_url = caNavUrl($this->request, '', 'CollectionFindingAid', 'View', ['collection_id' => $id]);
		$text_url = caNavUrl($this->request, '', 'CollectionFindingAid', 'Text', ['collection_id' => $id]);
		$download_url = caNavUrl($this->request, '', 'CollectionFindingAid', 'Download', ['collection_id' => $id]);
		$title = tadlFindingAidValue($this->request, $collection, ['ca_collections.preferred_labels.name']) ?: _t('Finding aid');
		$identifier = tadlFindingAidValue($this->request, $collection, ['ca_collections.idno']);
		try {
			$viewer = new View($this->request, $this->request->getViewsDirectoryPath().'/mediaViewers');
			foreach (['viewer' => 'pdfjs', 'finding_aid_viewer' => true, 'context' => 'collections', 'search' => '', 'scroll_mode' => 0,
				'display_media_url' => $pdf_url, 'original_media_url' => $pdf_url, 'media_url' => $pdf_url] as $key => $value) {
				$viewer->setVar($key, $value);
			}
			$heading = htmlspecialchars(_t('Finding aid').': '.$title, ENT_QUOTES, 'UTF-8');
			$identifier_text = $identifier !== '' ? htmlspecialchars(' ('.$identifier.')', ENT_QUOTES, 'UTF-8') : '';
			$viewer->setVar('controls', '<div class="objectInfo" title="'.$heading.$identifier_text.'"><span class="tadl-viewer-object-title">'.$heading.'</span>'
				.($identifier_text !== '' ? '<span class="tadl-viewer-object-identifier">'.$identifier_text.'</span>' : '').'</div>'
				.'<div class="download"><a href="'.htmlspecialchars($text_url, ENT_QUOTES, 'UTF-8').'" rel="nofollow">'.htmlspecialchars(_t('Read as text'), ENT_QUOTES, 'UTF-8').'</a> <a href="'.htmlspecialchars($download_url, ENT_QUOTES, 'UTF-8').'" rel="nofollow">'.htmlspecialchars(_t('Download PDF'), ENT_QUOTES, 'UTF-8').'</a></div>');
			$this->response->addContent($viewer->render('viewerWrapper.php'));
		} catch (Throwable $error) {
			tadlLogExportFailure('finding_aid', ['collection_id' => $id, 'format' => 'viewer'], $error);
			$this->response->setHTTPResponseCode(503, 'Service Unavailable');
			$this->response->addContent(_t('The finding aid viewer could not be loaded. Please use Download instead.'));
		}
	}

	private function readableCollection($methods = ['GET']) {
		if (!in_array($this->request->getRequestMethod(), $methods, true)) {
			$this->response->addHeader('Allow', implode(', ', $methods));
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
		return $collection;
	}

	private function renderFindingAid($inline) {
		if (!$collection = $this->readableCollection()) { return; }
		$config = Configuration::load(__DIR__.'/../conf/finding_aid.conf');
		$id = (int)$collection->getPrimaryKey();
		$this->response->addHeader('Cache-Control', 'private, no-store');
		$time_limit = (int)ini_get('max_execution_time');
		if ($time_limit > 0) { set_time_limit(max($time_limit, 180)); }
		$failure = null;
		try {
			$data = tadlFindingAidData($this->request, $collection, $config);
			$this->view->setVar('finding_aid', $data);
			$renderer_file = class_exists('Dompdf\Dompdf') ? (new ReflectionClass('Dompdf\Dompdf'))->getFileName() : null;
            $renderer_key = hash_file('sha256', __DIR__.'/../views/Details/finding_aid_pdf_html.php').hash_file('sha256', __DIR__.'/../helpers/finding_aid.php')
                .($renderer_file && is_file($renderer_file) ? hash_file('sha256', $renderer_file) : 'unavailable');
            $timeout = $config->get('pdf_cache_timeout');
            $bytes = tadlFindingAidCachedPDF($data, $renderer_key, function () {
                return tadlFindingAidPDF($this->view->render('Details/finding_aid_pdf_html.php'));
            }, $timeout === null ? 86400 : max(0, (int)$timeout));
		} catch (Throwable $error) { $bytes = null; $failure = $error; }
		if (!$bytes) {
			tadlLogExportFailure('finding_aid', ['collection_id' => $id, 'format' => 'pdf'], $failure);
			$this->response->setHTTPResponseCode(503, 'Service Unavailable');
			$this->response->addContent(_t('The finding aid could not be generated. Please try again later.')); return;
		}
		$name = trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $data['identifier'] ?: $data['title']), '-');
		$this->view->setVar('finding_aid_name', substr($name ?: 'collection-'.$id, 0, 120).'-finding-aid.pdf');
		$this->view->setVar('finding_aid_bytes', $bytes);
		$this->view->setVar('finding_aid_inline', $inline);
		$this->render('Details/finding_aid_binary.php', true);
	}
}
