<?php
/** Session-backed CSRF protection for public forms; never uses the SQL token store. */
function tadlPublicCSRFEntryValid($entry, string $sessionID, int $now): bool {
	return is_array($entry) && is_string($entry['token'] ?? null)
		&& preg_match('/\A[a-f0-9]{64}\z/D', $entry['token']) === 1
		&& is_string($entry['session'] ?? null)
		&& hash_equals(hash('sha256', $sessionID), $entry['session'])
		&& is_int($entry['expires'] ?? null) && $entry['expires'] > $now
		&& $entry['expires'] <= $now + 7200;
}

/** Reuse one cryptographic nonce per form purpose for at most two hours. */
function tadlGeneratePublicCSRFToken($request, string $purpose): string {
	$purposes = ['media_preference', 'contact'];
	if (!in_array($purpose, $purposes, true)) { throw new InvalidArgumentException('Unknown public form purpose.'); }
	$sessionID = $request->getSessionID();
	if (!is_string($sessionID) || $sessionID === '') { return ''; }
	$now = time();
	$stored = Session::getVar('tadl_public_csrf');
	$tokens = [];
	foreach ($purposes as $name) {
		$entry = is_array($stored) ? ($stored[$name] ?? null) : null;
		if (tadlPublicCSRFEntryValid($entry, $sessionID, $now)) { $tokens[$name] = $entry; }
	}
	if (!isset($tokens[$purpose])) {
		$tokens[$purpose] = ['token' => bin2hex(random_bytes(32)), 'session' => hash('sha256', $sessionID), 'expires' => $now + 7200];
	}
	if ($tokens !== $stored && Session::setVar('tadl_public_csrf', $tokens) === false) {
		throw new RuntimeException('Public form protection could not be saved.');
	}
	return $tokens[$purpose]['token'];
}

/** Reject missing, expired, cross-session and wrong-purpose tokens without mutation. */
function tadlValidatePublicCSRFToken($request, string $purpose, $token): bool {
	if (!in_array($purpose, ['media_preference', 'contact'], true)
		|| !is_string($token) || preg_match('/\A[a-f0-9]{64}\z/D', $token) !== 1) { return false; }
	$sessionID = $request->getSessionID();
	if (!is_string($sessionID) || $sessionID === '') { return false; }
	$tokens = Session::getVar('tadl_public_csrf');
	$entry = is_array($tokens) ? ($tokens[$purpose] ?? null) : null;
	return tadlPublicCSRFEntryValid($entry, $sessionID, time()) && hash_equals($entry['token'], $token);
}
