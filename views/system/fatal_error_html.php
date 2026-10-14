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
$homeURL = defined('__CA_URL_ROOT__') ? rtrim(__CA_URL_ROOT__, '/').'/' : '/';
$escape = static fn($value) => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$title = $status === 404 ? 'Page not found' : 'Page unavailable';
$message = $status === 404 ? 'Please check the address or return to the Local History Collection.' : 'We could not load this page. Please try again later.';
?><!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= $escape($title) ?> | TADL Local History Collection</title>
	<link rel="stylesheet" href="<?= $escape($cssURL) ?>">
</head>
<body>
	<main class="tadl-error">
		<p>TADL Local History Collection</p>
		<h1><?= $escape($title) ?></h1>
		<p><?= $escape($message) ?></p>
		<p><a href="<?= $escape($homeURL) ?>">Return to the Local History Collection</a></p>
	</main>
</body>
</html>
