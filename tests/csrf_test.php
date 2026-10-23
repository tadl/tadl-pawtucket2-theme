<?php
/** Real public CSRF helper, synthetic isolated sessions and traps for SQL token use. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$assertions = 0;
function csrfCheck($condition, $message) {
	$GLOBALS['assertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}
class Session {
	static public array $sessions = [];
	static public string $id = 'synthetic-session-a';
	static public int $writes = 0;
	static public bool $failWrites = false;
	static function getVar($key) { return self::$sessions[self::$id][$key] ?? null; }
	static function setVar($key, $value) {
		self::$writes++;
		if (self::$failWrites) { return false; }
		self::$sessions[self::$id][$key] = $value;
		return true;
	}
}
class PersistentCache {
	static function fetch(...$args) { throw new RuntimeException('Public tokens must not read SQL persistence.'); }
	static function save(...$args) { throw new RuntimeException('Public tokens must not write SQL persistence.'); }
}
class CSRFRequest { function __construct(public $id) {} function getSessionID() { return $this->id; } }
require dirname(__DIR__).'/helpers/csrf.php';
$request = new CSRFRequest(Session::$id);
$media = tadlGeneratePublicCSRFToken($request, 'media_preference');
$contact = tadlGeneratePublicCSRFToken($request, 'contact');
csrfCheck(preg_match('/\A[a-f0-9]{64}\z/', $media) === 1 && $media !== $contact, 'Each public purpose must receive a distinct 256-bit nonce.');
foreach (['media_preference' => $media, 'contact' => $contact] as $purpose => $token) {
	csrfCheck(tadlValidatePublicCSRFToken($request, $purpose, $token), 'A valid token must work for its session and purpose.');
	$before = Session::$writes;
	csrfCheck(tadlGeneratePublicCSRFToken($request, $purpose) === $token && Session::$writes === $before, 'Repeated header/form renders must reuse the existing nonce without another save.');
	csrfCheck(tadlValidatePublicCSRFToken($request, $purpose, $token) && Session::$writes === $before, 'Validation must not write, consume tokens or break another open form.');
}
csrfCheck(!tadlValidatePublicCSRFToken($request, 'contact', $media) && !tadlValidatePublicCSRFToken($request, 'media_preference', $contact), 'Tokens must not be interchangeable between forms.');
foreach ([null, '', '0', true, [], ['token' => $media], str_repeat('a', 63), str_repeat('g', 64), strtoupper($media), str_repeat('a', 65)] as $invalid) {
	csrfCheck(!tadlValidatePublicCSRFToken($request, 'media_preference', $invalid), 'Malformed tokens must fail closed.');
}
csrfCheck(!tadlValidatePublicCSRFToken($request, 'unknown', $media), 'Unknown purposes must not validate.');
$rejected = false;
try { tadlGeneratePublicCSRFToken($request, 'unknown'); } catch (InvalidArgumentException $error) { $rejected = true; }
csrfCheck($rejected, 'Callers must not grow the session store with arbitrary purpose names.');

$sessionA = Session::$sessions[Session::$id];
Session::$id = 'synthetic-session-b';
$other = new CSRFRequest(Session::$id);
csrfCheck(!tadlValidatePublicCSRFToken($other, 'media_preference', $media), 'A different session must not accept another visitor\'s token.');
Session::$sessions[Session::$id] = $sessionA;
csrfCheck(!tadlValidatePublicCSRFToken($other, 'contact', $contact), 'Session-ID binding must reject a copied or stale session payload.');
$otherMedia = tadlGeneratePublicCSRFToken($other, 'media_preference');
csrfCheck($otherMedia !== $media && tadlValidatePublicCSRFToken($other, 'media_preference', $otherMedia), 'A session change must generate a new nonce.');
Session::$id = 'synthetic-session-a';
csrfCheck(tadlValidatePublicCSRFToken($request, 'media_preference', $media), 'A different visitor must not mutate the original session.');

$store =& Session::$sessions[Session::$id]['tadl_public_csrf'];
$store['media_preference']['expires'] = time() - 1;
csrfCheck(!tadlValidatePublicCSRFToken($request, 'media_preference', $media), 'Expired tokens must fail closed.');
$renewed = tadlGeneratePublicCSRFToken($request, 'media_preference');
csrfCheck($renewed !== $media && tadlValidatePublicCSRFToken($request, 'media_preference', $renewed), 'Reopening a form must replace an expired nonce.');
csrfCheck(tadlValidatePublicCSRFToken($request, 'contact', $contact), 'Renewing one purpose must preserve another valid form.');
$validEntry = $store['media_preference'];
foreach ([null, 'corrupt', ['token' => $renewed], array_replace($validEntry, ['session' => 'invalid']), array_replace($validEntry, ['token' => []]), array_replace($validEntry, ['expires' => (string)(time() + 60)]), array_replace($validEntry, ['expires' => time() + 7500])] as $invalid) {
	$store['media_preference'] = $invalid;
	csrfCheck(!tadlValidatePublicCSRFToken($request, 'media_preference', $renewed), 'Malformed persisted entries and excessive lifetimes must fail closed.');
}
$store['media_preference'] = $validEntry;
$store['unexpected'] = str_repeat('x', 5000);
tadlGeneratePublicCSRFToken($request, 'media_preference');
csrfCheck(count($store) === 2 && !isset($store['unexpected']), 'The store must remain bounded to the two public form purposes.');
foreach ([null, '', false, []] as $id) {
	$missing = new CSRFRequest($id); $before = Session::$writes;
	csrfCheck(tadlGeneratePublicCSRFToken($missing, 'contact') === '' && !tadlValidatePublicCSRFToken($missing, 'contact', $contact) && Session::$writes === $before, 'No established session must mean no valid public token.');
}
Session::$failWrites = true; Session::$sessions[Session::$id]['tadl_public_csrf'] = [];
$rejected = false;
try { tadlGeneratePublicCSRFToken($request, 'contact'); } catch (RuntimeException $error) { $rejected = true; }
csrfCheck($rejected, 'A failed session save must not return a token that cannot be validated.');
echo "Public CSRF passed: {$assertions} assertions (real helper, purpose/session binding, expiry, malformed state and bounded storage).\n";
