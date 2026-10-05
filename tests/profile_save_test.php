<?php
/** Actual theme controller/form; synthetic native ORM, session and CSRF boundaries. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$assertions = 0;
function checkProfile($condition, $message) {
	$GLOBALS['assertions']++;
	if (!$condition) { throw new RuntimeException($message); }
}
function _t($text, ...$args) {
	foreach ($args as $index => $value) { $text = str_replace('%'.($index + 1), (string)$value, $text); }
	return $text;
}
function caNavUrl($request, $module, $controller, $action, $params = []) { return '/'.$controller.'/'.$action; }
function caGenerateCSRFToken($request) { return 'synthetic-token'; }
function caValidateCSRFToken($request, $token, $options = []) { return $token === 'synthetic-token'; }
function caCheckEmailAddress($email) { return filter_var($email, FILTER_VALIDATE_EMAIL) !== false; }
define('pString', 3);
define('ACCESS_WRITE', 1);
define('__NOTIFICATION_TYPE_ERROR__', 'error');
define('__NOTIFICATION_TYPE_INFO__', 'info');

class ProfileConfig {
	public bool $disabled = false;
	function get($keys) { return $this->disabled; }
}
class ProfileRequest {
	public ca_users $user;
	public ProfileConfig $config;
	public bool $loggedIn = true;
	public bool $ajax = false;
	public string $method = 'POST';
	function __construct(public array $params) {
		$this->config = new ProfileConfig();
		$this->user = new ca_users(); $this->user->load(1); $this->user->setMode(ACCESS_WRITE);
	}
	function getRequestMethod() { return $this->method; }
	function isLoggedIn() { return $this->loggedIn; }
	function getUserID() { return 1; }
	function getParameters($methods) { checkProfile($methods === ['POST'], 'Only POST data may be read.'); return $this->params; }
	function getParameter($name, $type, $method) { checkProfile($method === 'POST', 'Native parameter filtering must use POST.'); return $this->params[$name] ?? ''; }
	function isAjax() { return $this->ajax; }
}
class ProfileResponse {
	public int $status = 200;
	public array $headers = [];
	function addHeader($name, $value) { $this->headers[$name] = $value; }
	function setHTTPResponseCode($code, $message) { $this->status = $code; }
}
class ProfileNotifications {
	public array $items = [];
	function addNotification($text, $type) { $this->items[] = [$text, $type]; }
	function has($type) { return in_array($type, array_column($this->items, 1), true); }
}
class ProfileView {
	public array $vars = [];
	function __construct(public ProfileRequest $request) {}
	function setVar($key, $value) { $this->vars[$key] = $value; }
	function getVar($key) { return $this->vars[$key] ?? null; }
	function render() {
		ob_start();
		try { include dirname(__DIR__).'/views/LoginReg/form_profile_html.php'; return ob_get_clean(); }
		catch (Throwable $error) { ob_end_clean(); throw $error; }
	}
}
class LoginRegController {
	public ProfileView $view;
	public ProfileNotifications $notification;
	public ?string $redirectURL = null;
	public string $html = '';
	function __construct(public ProfileRequest $request, public ProfileResponse $response) {
		$this->view = new ProfileView($request); $this->notification = new ProfileNotifications();
	}
	function redirect($url) { $this->redirectURL = $url; }
	function profileForm() {
		$this->view->setVar('t_user', $this->request->user);
		$this->view->setVar('profile_settings', ['phone' => ['bs_formatted_element' => '<input name="pref_phone" value="'.htmlspecialchars($this->request->user->getPreference('phone'), ENT_QUOTES, 'UTF-8').'">']]);
		$this->html = $this->view->render();
	}
}
class ca_users {
	public static array $rows = [];
	public static array $faults = [];
	public static int $lookups = 0;
	public static int $saves = 0;
	private array $data = [];
	private array $changed = [];
	private array $errors = [];
	private int $id = 0;
	private int $mode = 0;
	function load($criteria) {
		if (is_array($criteria)) {
			self::$lookups++;
			foreach (self::$rows as $id => $row) {
				if (strcasecmp($row['user_name'], $criteria['user_name']) === 0) { return $this->load($id); }
			}
			return false;
		}
		if (!isset(self::$rows[$criteria])) { return false; }
		$this->id = $criteria; $this->data = self::$rows[$criteria]; $this->changed = []; $this->errors = [];
		return true;
	}
	function getPrimaryKey() { return $this->id; }
	function get($name) { return $this->data[$name] ?? ''; }
	function purify($enabled) { checkProfile($enabled, 'Keep native model purification.'); }
	function setMode($mode) { $this->mode = $mode; }
	function set($name, $value) {
		if (isset(self::$faults['set_'.$name])) { $this->errors[] = 'Synthetic setter error'; return false; }
		$this->data[$name] = $value; $this->changed[$name] = $value; return true;
	}
	function getValidPreferences($group) { checkProfile($group === 'profile', 'Only configured profile preferences may be edited.'); return ['phone']; }
	function isValidPreferenceValue($name, $value, $postErrors = false) {
		if ($value === 'invalid') { if ($postErrors) { $this->errors[] = 'Synthetic invalid phone'; } return false; }
		return true;
	}
	function setPreference($name, $value) {
		// Native setVar clears errors; the controller must capture earlier errors first.
		$this->clearErrors();
		if (isset(self::$faults['preference'])) { $this->errors[] = 'Synthetic preference setter error'; return false; }
		$this->data['preferences'][$name] = $value;
		$this->changed['preferences'] = $this->data['preferences']; return true;
	}
	function getPreference($name) { return $this->data['preferences'][$name]; }
	function update() {
		checkProfile($this->mode === ACCESS_WRITE, 'Saving requires native write mode.');
		$this->clearErrors();
		if ($this->changed && (isset(self::$faults['save']) || ($this->get('password') === 'policy-rejected'))) {
			$this->errors[] = 'Synthetic native save/password policy error'; return false;
		}
		if ($this->changed) { self::$saves++; self::$rows[$this->id] = array_replace(self::$rows[$this->id], $this->changed); $this->changed = []; }
		return true;
	}
	function close() { $this->update(); }
	function getErrors() { return $this->errors; }
	function numErrors() { return count($this->errors); }
	function clearErrors() { $this->errors = []; }
	function inGroup($id) { return in_array($id, self::$rows[$this->id]['groups'], true); }
	function addToGroups($id) {
		if (isset(self::$faults['group'])) { return false; }
		self::$rows[$this->id]['groups'][] = $id; return 1;
	}
	function getUserGroups() { return [['name' => '<b>Synthetic group</b>', 'description' => '<script>synthetic-group</script>']]; }
	function htmlFormElement($name, $template, $options) {
		return str_replace(['^LABEL', '^ELEMENT'], [$name, '<input name="'.$name.'" value="'.htmlspecialchars($this->get($name), ENT_QUOTES, 'UTF-8').'">'], $template);
	}
}
class ca_user_groups {
	function __construct(private int $id = 7) {}
	static function find($criteria, $options) {
		checkProfile(($criteria['for_public_use'] ?? null) === 1, 'Group lookup must require public-use eligibility.');
		return (($criteria['code'] ?? '') === 'synthetic_public' || ($criteria['group_id'] ?? 0) === 7) ? new self() : null;
	}
	function getPrimaryKey() { return $this->id; }
	function get($key) { return '<b>Synthetic group</b>'; }
}
// Satisfy the native superclass include without bootstrapping any application/DB.
$fixture = sys_get_temp_dir().'/tadl-profile-test-'.bin2hex(random_bytes(8));
mkdir($fixture.'/controllers', 0700, true);
file_put_contents($fixture.'/controllers/LoginRegController.php', '<?php // Synthetic superclass is defined by the test.');
define('__CA_APP_DIR__', $fixture);
register_shutdown_function(static function () use ($fixture) {
	unlink($fixture.'/controllers/LoginRegController.php'); rmdir($fixture.'/controllers'); rmdir($fixture);
});
require dirname(__DIR__).'/controllers/AccountProfileController.php';

function profileFixture($overrides = [], $public = false) {
	ca_users::$rows = [
		1 => ['user_name' => $public ? 'person@example.org' : 'synthetic_staff', 'email' => 'person@example.org', 'fname' => 'Synthetic', 'lname' => 'Person', 'userclass' => $public ? 1 : 0, 'password' => 'original-password', 'preferences' => ['phone' => '202-555-0100'], 'groups' => []],
		2 => ['user_name' => $public ? 'taken@example.org' : 'person@example.org', 'email' => 'other@example.org', 'fname' => 'Other', 'lname' => 'Synthetic', 'userclass' => 1, 'password' => 'other-password', 'preferences' => ['phone' => '202-555-0102'], 'groups' => []]
	];
	ca_users::$lookups = ca_users::$saves = 0; ca_users::$faults = [];
	$request = new ProfileRequest(array_replace(['csrfToken' => 'synthetic-token', 'email' => 'person@example.org', 'fname' => 'Updated', 'lname' => 'Person', 'pref_phone' => '202-555-0101', 'password' => '', 'password2' => '', 'group_code' => ''], $overrides));
	return new AccountProfileController($request, new ProfileResponse());
}
function saveProfile($controller) { $controller->profileSave(); $controller->request->user->close(); }
function checkFailedProfile($controller, $before, $errorField) {
	saveProfile($controller);
	checkProfile(ca_users::$rows === $before && ca_users::$saves === 0, 'Failed '.$errorField.' validation/save persisted profile data during request close.');
	checkProfile(isset($controller->view->vars['errors'][$errorField]) && $controller->notification->has('error') && !$controller->notification->has('info'), 'Failed '.$errorField.' must render an error without success feedback.');
}

$controller = profileFixture(); saveProfile($controller);
checkProfile(ca_users::$rows[1]['preferences']['phone'] === '202-555-0101' && ca_users::$rows[1]['fname'] === 'Updated', 'Unchanged email should allow the profile/phone save even when another login uses that email.');
checkProfile(ca_users::$lookups === 0 && ca_users::$rows[1]['user_name'] === 'synthetic_staff', 'Unchanged contact email must not rename or collision-check a staff login.');
checkProfile($controller->notification->has('info') && !$controller->notification->has('error'), 'Successful phone update must report success.');
checkProfile($controller->request->user->getPreference('phone') === '202-555-0101' && ca_users::$saves === 1, 'Successful save must refresh the request user to avoid stale end-of-request overwrite.');

foreach ([['email' => 'invalid'], ['fname' => ''], ['lname' => ''], ['password' => 'abcdef', 'password2' => 'different'], ['password' => 'abc', 'password2' => 'abc'], ['pref_phone' => 'invalid'], ['group_code' => 'synthetic_private'], ['group_code' => '8'], ['group_code' => "\xFF"], ['email' => ['malformed']]] as $invalid) {
	$controller = profileFixture($invalid); $before = ca_users::$rows;
	$errorField = match (array_key_first($invalid)) { 'pref_phone' => 'phone', 'email' => is_array($invalid['email']) ? 'general' : 'email', default => array_key_first($invalid) };
	checkFailedProfile($controller, $before, $errorField);
}
$controller = profileFixture(['email' => 'taken@example.org'], true);
checkFailedProfile($controller, ca_users::$rows, 'email');
$controller = profileFixture(['email' => 'PERSON@example.org'], true); saveProfile($controller);
checkProfile(ca_users::$lookups === 1 && !$controller->notification->has('error') && ca_users::$rows[1]['user_name'] === 'PERSON@example.org', 'A case-insensitive collision with the current account must be allowed.');
$controller = profileFixture(['email' => 'new@example.org'], true); saveProfile($controller);
checkProfile(ca_users::$rows[1]['email'] === 'new@example.org' && ca_users::$rows[1]['user_name'] === 'new@example.org', 'Changed public-user email must update the native login name.');
$controller = profileFixture(['email' => 'person@example.org'], true);
ca_users::$rows[1]['user_name'] = 'synthetic_public_alias'; $controller->request->user->load(1); saveProfile($controller);
checkProfile(ca_users::$rows[1]['user_name'] === 'synthetic_public_alias' && ca_users::$lookups === 0, 'Unchanged email must not silently rename a public login either.');
$controller = profileFixture(['email' => 'new@example.org']); saveProfile($controller);
checkProfile(ca_users::$rows[1]['email'] === 'new@example.org' && ca_users::$rows[1]['user_name'] === 'synthetic_staff' && ca_users::$lookups === 0, 'Staff contact-email changes must preserve staff login identity.');

foreach (['set_fname' => 'fname', 'set_password' => 'password', 'preference' => 'phone', 'save' => 'general'] as $fault => $errorField) {
	$controller = profileFixture(['password' => 'synthetic-password', 'password2' => 'synthetic-password']); ca_users::$faults[$fault] = true;
	checkFailedProfile($controller, ca_users::$rows, $errorField);
}
$controller = profileFixture(['password' => 'policy-rejected', 'password2' => 'policy-rejected']);
checkFailedProfile($controller, ca_users::$rows, 'general');
$controller = profileFixture(['email' => 'new@example.org'], true); ca_users::$faults['set_user_name'] = true;
checkFailedProfile($controller, ca_users::$rows, 'email');
$controller = profileFixture(['password' => 'synthetic-password', 'password2' => 'synthetic-password', 'user_id' => '2', 'userclass' => '0', 'roles' => 'admin', 'pref_private' => 'attack'], true);
$other = ca_users::$rows[2]; saveProfile($controller);
checkProfile(ca_users::$rows[2] === $other && ca_users::$rows[1]['userclass'] === 1 && !isset(ca_users::$rows[1]['roles']) && !isset(ca_users::$rows[1]['preferences']['private']), 'Submitted identity/privilege/unconfigured-preference fields must be ignored.');
checkProfile(ca_users::$rows[1]['password'] === 'synthetic-password', 'Valid password changes must delegate to native model update.');

foreach (['get', 'anonymous', 'disabled', 'csrf', 'array_token', 'missing_user'] as $blocked) {
	$controller = profileFixture(); $before = ca_users::$rows;
	match ($blocked) {
		'get' => $controller->request->method = 'GET',
		'anonymous' => $controller->request->loggedIn = false,
		'disabled' => $controller->request->config->disabled = true,
		'csrf' => $controller->request->params['csrfToken'] = 'invalid-token',
		'array_token' => $controller->request->params['csrfToken'] = ['invalid-token'],
		'missing_user' => ca_users::$rows = [],
	};
	if ($blocked === 'missing_user') { $before = []; $controller->profileSave(); } else { saveProfile($controller); }
	checkProfile(ca_users::$rows === $before && ca_users::$saves === 0, $blocked.': blocked requests must not write.');
	checkProfile($blocked === 'anonymous' ? $controller->redirectURL === '/Front/Index' : $controller->response->status === ($blocked === 'get' ? 405 : 403), $blocked.': enforce method/session/config/CSRF/identity boundary.');
}
foreach (['synthetic_public', '7'] as $code) {
	$controller = profileFixture(['group_code' => $code]); saveProfile($controller);
	checkProfile(ca_users::$rows[1]['groups'] === [7] && !$controller->notification->has('error'), 'Valid native public-use group invitation should join once.');
}
$controller = profileFixture(['group_code' => 'synthetic_public']); ca_users::$faults['group'] = true; saveProfile($controller);
checkProfile(ca_users::$rows[1]['preferences']['phone'] === '202-555-0101' && str_contains($controller->html, 'profile was updated, but the group could not be joined'), 'A group write failure must truthfully distinguish a successful profile save.');

foreach ([false, true] as $ajax) {
	$controller = profileFixture(); $controller->request->ajax = $ajax;
	$controller->view->setVar('errors', ['general' => '<script>synthetic-error</script>', 'phone' => '<b>synthetic-error</b>', 'group_code' => '<img src=x onerror=synthetic>']);
	$controller->profileForm();
	$document = new DOMDocument(); $document->loadHTML($controller->html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); $xpath = new DOMXPath($document);
	checkProfile($xpath->query('//form[@action="/AccountProfile/profileSave" and @method="POST"]')->length === 1 && $xpath->query('//input[@name="csrfToken" and @value="synthetic-token"]')->length === 1, 'Direct and AJAX forms must target the fixed theme save endpoint with CSRF.');
	checkProfile(!str_contains($controller->html, '<script>synthetic-error</script>') && !str_contains($controller->html, '<script>synthetic-group</script>') && !str_contains($controller->html, '<img src=x'), 'Profile/group errors and membership content must be escaped.');
	checkProfile($xpath->query('//div[contains(@class,"alert-danger")]')->length === 3, 'Render field, general and group errors.');
	if ($ajax) { checkProfile(str_contains($controller->html, json_encode('/AccountProfile/profileSave').',') && !str_contains($controller->html, '/LoginReg/profileSave'), 'AJAX save must use the fixed endpoint too.'); }
}
echo 'Profile saves passed: '.$assertions.' assertions (synthetic native ORM/session/CSRF boundaries).'.PHP_EOL;
