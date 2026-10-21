<?php

/** A deliberately indistinguishable response for missing or non-public records. */
class TadlPublicNotFoundException extends RuntimeException {}
class TadlResultExportDisabledException extends RuntimeException {}

function tadlPublicErrorStatus($exception = null, $routingFailure = false, $status = 200) {
	if ($exception instanceof TadlResultExportDisabledException) { return 410; }
	if ($exception instanceof TadlPublicNotFoundException) { return 404; }
	if ($status >= 400 && $status <= 599) { return $status; }
	return $routingFailure ? 404 : 500;
}

/** This view also runs before normal response headers have been emitted. */
function tadlPublicErrorHeaders($status) {
	http_response_code($status);
	header('Content-Type: text/html; charset=UTF-8');
	header('Cache-Control: no-store');
	header('X-Robots-Tag: noindex, nofollow');
	header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
	header('X-Content-Type-Options: nosniff');
}

/** Shared recovery content; never reflects the failed URL or exception details. */
function tadlPublicErrorContent($request = null, $status = 404) {
	$translate = static fn($text) => function_exists('_t') ? _t($text) : $text;
	$escape = static fn($text) => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$url = static function ($controller, $action) use ($request) {
		if ($request && function_exists('caNavUrl')) { return caNavUrl($request, '', $controller, $action); }
		$base = defined('__CA_URL_ROOT__') ? rtrim(__CA_URL_ROOT__, '/') : '';
		return $base.'/index.php/'.$controller.'/'.$action;
	};
	ob_start();
	include(__DIR__.'/../views/system/error_content_html.php');
	return ob_get_clean();
}

/** PageFormat calls the header after dispatch, including native plain-text 404s. */
function tadlPrepareNotFoundPage($request) {
	if (!class_exists('AppController', false) || !AppController::instanceExists()) { return false; }
	$response = AppController::getInstance()->getResponse();
	if ($response->getHTTPResponseCode() !== 404 || $response->isRedirect()
		|| $request->isAjax() || $request->isDownload()
		|| !preg_match('!^text/html(?:;|$)!i', $response->getContentType())) { return false; }
	// Replace only the body; keep native notifications, header/footer and HTTP status.
	$response->setContent(tadlPublicErrorContent($request));
	$response->addHeader('Cache-Control', 'no-store');
	$response->addHeader('X-Robots-Tag', 'noindex, nofollow');
	AssetLoadManager::register('pawtucket', 'errorcss');
	return true;
}
