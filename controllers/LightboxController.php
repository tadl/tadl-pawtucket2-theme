<?php
require_once(__DIR__.'/../helpers/public_errors.php');

// Retire every Lightbox action before native controller construction or set access.
// ResultContext may still include this file for static helpers on a Detail request.
if (strcasecmp((string)AppController::getInstance()->getRequest()->getController(), 'Lightbox') === 0) {
	throw new TadlPublicNotFoundException('Page not found');
}
require_once(__CA_APP_DIR__.'/controllers/LightboxController.php');
