<?php
// Native ErrorController supplies the 404; never echo its diagnostics or referrer.
if (class_exists('AppController', false) && AppController::instanceExists()) {
	$response = AppController::getInstance()->getResponse();
	$response->addHeader('Cache-Control', 'no-store');
	$response->addHeader('X-Robots-Tag', 'noindex, nofollow');
}
require_once(__DIR__.'/../../helpers/public_errors.php');
?>
<?= tadlPublicErrorContent($this->request) ?>
