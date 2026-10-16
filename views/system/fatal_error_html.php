<?php
require_once(__DIR__.'/../../helpers/public_errors.php');
$existingStatus = http_response_code() ?: 200;
if (class_exists('AppController', false) && AppController::instanceExists()) {
	$existingStatus = AppController::getInstance()->getResponse()->getHTTPResponseCode();
}
$status = tadlPublicErrorStatus($e ?? null, $g_errored ?? false, $existingStatus);
tadlPublicErrorHeaders($status);
$themeURL = defined('__CA_THEME_URL__') ? __CA_THEME_URL__ : '/themes/tadl';
$cssPath = dirname(__DIR__, 2).'/assets/pawtucket/css/error.css';
$cssURL = $themeURL.'/assets/pawtucket/css/error.css';
if (is_readable($cssPath)) { $cssURL .= '?v='.substr(hash_file('sha256', $cssPath), 0, 16); }
$escape = static fn($value) => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$title = $status === 404 ? 'Page not found' : 'Page unavailable';
$errorRequest = class_exists('AppController', false) && AppController::instanceExists() ? AppController::getInstance()->getRequest() : null;
?><!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= $escape($title) ?> | TADL Local History Collection</title>
	<link rel="stylesheet" href="<?= $escape($cssURL) ?>">
</head>
<body class="tadl-error-page">
	<main class="tadl-error-shell">
		<p>TADL Local History Collection</p>
		<?= tadlPublicErrorContent($errorRequest, $status) ?>
	</main>
</body>
</html>
