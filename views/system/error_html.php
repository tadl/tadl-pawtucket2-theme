<?php
// Native ErrorController supplies the 404; never echo its diagnostics or referrer.
if (class_exists('AppController', false) && AppController::instanceExists()) {
	$response = AppController::getInstance()->getResponse();
	$response->addHeader('Cache-Control', 'no-store');
	$response->addHeader('X-Robots-Tag', 'noindex, nofollow');
}
$home = htmlspecialchars(caNavUrl($this->request, '', 'Front', 'Index'), ENT_QUOTES, 'UTF-8');
?>
<div class="alert alert-danger text-center" role="alert">
	<h1><?= _t('Page not found') ?></h1>
	<p><?= _t('Please check the address or return to the Local History Collection.') ?></p>
	<p><a href="<?= $home ?>"><?= _t('Return to the Local History Collection') ?></a></p>
</div>
