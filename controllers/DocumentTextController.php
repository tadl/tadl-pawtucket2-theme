<?php
require_once(__CA_LIB_DIR__.'/pawtucket/BasePawtucketController.php');
require_once(__DIR__.'/../helpers/document_text.php');

class DocumentTextController extends BasePawtucketController {
 public function View() {
  if ($this->request->getRequestMethod() !== 'GET') {
   $this->response->addHeader('Allow', 'GET'); $this->response->setHTTPResponseCode(405, 'Method Not Allowed'); return;
  }
  $this->response->addHeader('Cache-Control', 'private, no-store');
  $this->response->addHeader('X-Robots-Tag', 'noindex, nofollow');
  $this->response->addHeader('X-Content-Type-Options', 'nosniff');
  if ($this->request->config->get('pawtucket_requires_login') && !$this->request->isLoggedIn()) { $this->response->setHTTPResponseCode(403, 'Forbidden'); return; }
  $id = (int)$this->request->getParameter('object_id', pInteger);
  $object = Datamodel::getInstance('ca_objects', false);
  $access = array_map('intval', (array)caGetUserAccessValues($this->request));
  if ($id < 1 || !$object || !$object->load($id) || !tadlDocumentTextReadable($this->request, $object, $access, 'ca_object_representations')) {
   $this->response->setHTTPResponseCode(404, 'Not Found'); return;
  }
  $html = tadlObjectDocumentTextHTML($this->request, $object, false);
  if ($html === '') { $html = '<p>'.htmlspecialchars(_t('No document text is currently available.'), ENT_QUOTES, 'UTF-8').'</p>'; }
  if ($this->request->isAjax()) {
   $this->response->addHeader('Content-Type', 'text/html; charset=UTF-8'); $this->response->addContent($html); return;
  }
  $this->view->setVar('document_text', $html);
  $this->render('Details/document_text_html.php', false);
 }
}
