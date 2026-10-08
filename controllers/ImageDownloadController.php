<?php
require_once(__CA_LIB_DIR__.'/pawtucket/BasePawtucketController.php');
require_once(__CA_LIB_DIR__.'/Media.php');
require_once(__CA_LIB_DIR__.'/Logging/Downloadlog.php');
require_once(__DIR__.'/../helpers/image_downloads.php');
require_once(__DIR__.'/../helpers/image_download_cache.php');

class ImageDownloadController extends BasePawtucketController {
	public function Download() {
		if ($this->request->getRequestMethod() !== 'GET') {
			$this->response->addHeader('Allow', 'GET');
			$this->response->setHTTPResponseCode(405, 'Method Not Allowed');
			return;
		}
		$objectID = (int)$this->request->getParameter('object_id', pInteger);
		$representationID = (int)$this->request->getParameter('representation_id', pInteger);
		$format = $this->request->getParameter('format', pString);
		if ($objectID < 1 || $representationID < 1 || !in_array($format, ['jpg', 'tiff', 'pdf'], true)) {
			$this->response->setHTTPResponseCode(400, 'Bad Request'); return;
		}
		$object = Datamodel::getInstance('ca_objects', true);
		if (!$object || !$object->load($objectID) || !($source = tadlImageDownloadSource($this->request, $object, $representationID))) {
			$this->response->setHTTPResponseCode(404, 'Not Found'); return;
		}
		set_time_limit(120);
		// Native ImageMagick subprocesses inherit this wall-time resource limit.
		$previousLimit = getenv('MAGICK_TIME_LIMIT');
		putenv('MAGICK_TIME_LIMIT='.(($previousLimit !== false && (int)$previousLimit > 0) ? min(120, (int)$previousLimit) : 120));
		$imagickLimit = null;
		try {
			if (class_exists('Imagick', false) && defined('Imagick::RESOURCETYPE_TIME')) {
				$imagickLimit = Imagick::getResourceLimit(Imagick::RESOURCETYPE_TIME);
				Imagick::setResourceLimit(Imagick::RESOURCETYPE_TIME, $imagickLimit > 0 ? min(120, $imagickLimit) : 120);
			}
			$download = $this->request->config->get('tadl_cache_image_downloads')
				? tadlCachedImageDownload($source, $format, $this->request->config->get('tadl_image_download_cache_directory'))
				: tadlPrepareImageDownload($source, $format);
			if ($download && !$download['temporary'] && $download['path'] !== $source['path']) { tadlPruneImageDownloads(dirname($download['path'])); }
		}
		catch (Throwable $error) { $download = null; }
		finally {
			putenv($previousLimit === false ? 'MAGICK_TIME_LIMIT' : 'MAGICK_TIME_LIMIT='.$previousLimit);
			if ($imagickLimit !== null) { Imagick::setResourceLimit(Imagick::RESOURCETYPE_TIME, $imagickLimit); }
		}
		if (!$download) {
			$this->response->setHTTPResponseCode(503, 'Service Unavailable');
			$this->response->addContent(_t('This image could not be downloaded in the requested format. Please try again later.'));
			return;
		}
		$name = pathinfo((string)$source['representation']->get('original_filename'), PATHINFO_FILENAME);
		$name = trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', $name), '_');
		$name = substr($name ?: 'image-'.$representationID, 0, 150).'.'.$download['extension'];
		(new Downloadlog())->log([
			'user_id' => $this->request->getUserID() ?: null, 'ip_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
			'table_num' => $object->tableNum(), 'row_id' => $objectID,
			'representation_id' => $representationID, 'download_source' => 'pawtucket'
		]);
		$this->view->setVar('image_download', $download);
		$this->view->setVar('image_download_name', $name);
		$this->render('Details/image_download_binary.php', true);
	}
}
