<?php

/** Log bounded operational context, never exception messages or request payloads. */
function tadlLogExportFailure($operation, array $context, $exception = null) {
	$entry = ['event' => 'tadl_export_failed', 'operation' => $operation === 'image' ? 'image' : 'finding_aid'];
	foreach (['object_id', 'representation_id', 'collection_id'] as $field) {
		if (isset($context[$field])) { $entry[$field] = max(0, (int)$context[$field]); }
	}
	if (isset($context['format']) && in_array($context['format'], ['jpg', 'tiff', 'pdf'], true)) { $entry['format'] = $context['format']; }
	$entry['reason'] = $exception instanceof Throwable ? 'exception' : 'empty_or_invalid_output';
	if ($exception instanceof Throwable) {
		$class = get_class($exception);
		$entry['exception'] = preg_match('/\A[A-Za-z_][A-Za-z0-9_\\\\]*\z/', $class) ? $class : 'Throwable';
		$entry['file'] = basename($exception->getFile());
		$entry['line'] = $exception->getLine();
	}
	error_log(json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
}
