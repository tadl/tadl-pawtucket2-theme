<?php

/** A deliberately indistinguishable response for missing or non-public records. */
class TadlPublicNotFoundException extends RuntimeException {}

function tadlPublicErrorStatus($exception = null, $routingFailure = false, $status = 200) {
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
	header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
	header('X-Content-Type-Options: nosniff');
}
