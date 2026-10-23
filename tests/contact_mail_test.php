<?php
/** Actual theme controller/templates; synthetic request, purifier and captured mail boundary. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$checks = 0; $sent = []; $mailSuccess = true; $captchaAccepted = true; $subjects = [];
function contactCheck($value, $message) { $GLOBALS['checks']++; if (!$value) { throw new RuntimeException($message); } }
function _t($text, ...$values) { foreach ($values as $i=>$value) { $text=str_replace('%'.($i+1), $value, $text); } return $text; }
function caGetContactConfig() { return $GLOBALS['contactConfig']; }
function caValidateCSRFToken($request) { throw new RuntimeException('Public contact must not use the SQL token store.'); }
function caGetHTMLPurifier() { return new class { function purify($value) { return $value; } }; }
function caCheckEmailAddress($email) { return filter_var($email, FILTER_VALIDATE_EMAIL) !== false; }
function caSetPageCSSClasses($classes) {}
function caNavUrl($request, ...$args) { return '/synthetic/Contact/Form'; }
function caGenerateCSRFToken($request) { throw new RuntimeException('Public contact must not generate SQL-backed tokens.'); }
function caVerifyCaptcha($token) { if (!$GLOBALS['captchaAccepted'] || $token !== 'synthetic-captcha') { throw new CaptchaException('Synthetic CAPTCHA failure'); } return true; }
function caSendmail($to, $from, $subject, $text, $html, $cc, $bcc, $attachments, $options) {
	$GLOBALS['sent'][] = compact('to','from','subject','text','html','options'); return $GLOBALS['mailSuccess'];
}
define('pString', 1); define('pInteger', 2); define('__NOTIFICATION_TYPE_ERROR__', 1);
define('__CA_GOOGLE_RECAPTCHA_SECRET_KEY__', 'synthetic-not-a-real-key');
class CaptchaException extends RuntimeException {}
class MetaTagManager { static function setWindowTitle($title) {} }
class Session {
	static public array $values = [];
	static function getVar($key) { return self::$values[$key] ?? null; }
	static function setVar($key, $value) { self::$values[$key] = $value; return true; }
}
class ContactConfig {
	function __construct(public array $values) {}
	function get($name) { return $this->values[$name] ?? null; }
}
class ContactRequest {
	public ContactConfig $config;
	function __construct(public array $params, public string $method='POST', public bool $loggedIn=false) {
		$this->config = new ContactConfig(['ca_admin_email'=>'sender@example.org','app_display_name'=>'Synthetic Archive','page_title_delimiter'=>' | ','site_host'=>'https://archive.example.org']);
	}
	function getRequestMethod() { return $this->method; }
	function getSessionID() { return 'synthetic-contact-session'; }
	function getParameters($sources) { contactCheck($sources === ['POST'], 'CSRF must read the POST body only.'); return $this->params; }
	function isLoggedIn() { return $this->loggedIn; }
	function getParameter($key, $type, $method=null, $options=[]) { return is_string($this->params[$key] ?? '') ? ($this->params[$key] ?? '') : ''; }
	function getViewsDirectoryPath() { return dirname(__DIR__).'/views'; }
}
class ContactResponse {
	public int $status=200; public array $headers=[];
	function setHTTPResponseCode($code, $label) { $this->status=$code; }
	function addHeader($key, $value) { $this->headers[$key]=$value; }
	function setRedirect($url) { $this->status=302; }
}
class View {
	public array $vars=[];
	function __construct(public $request, public array $paths=[]) {}
	function setVar($key, $value) { $this->vars[$key]=$value; }
	function getVar($key) { return $this->vars[$key] ?? null; }
	function render($file) { ob_start(); include dirname(__DIR__).'/views/'.$file; return ob_get_clean(); }
}
class BasePawtucketController {
	public View $view; public array $rendered=[]; public object $notification;
	function __construct(public $request, public $response, $paths=null) {
		$this->view=new View($request); $this->notification=new class { function addNotification(...$args) {} };
	}
	function render($path) { $this->rendered[]=$path; }
}
$directory=sys_get_temp_dir().'/tadl-contact-'.bin2hex(random_bytes(8)); mkdir($directory.'/pawtucket',0700,true);
file_put_contents($directory.'/ApplicationError.php','<?php');
file_put_contents($directory.'/pawtucket/BasePawtucketController.php','<?php'); define('__CA_LIB_DIR__',$directory);
register_shutdown_function(static function () use ($directory) { unlink($directory.'/ApplicationError.php'); unlink($directory.'/pawtucket/BasePawtucketController.php'); rmdir($directory.'/pawtucket'); rmdir($directory); });
require dirname(__DIR__).'/controllers/ContactController.php';
class ContactTurnstileFixture extends TadlContactTurnstile {
	public int $calls = 0;
	public ?array $response = ['status'=>200, 'body'=>'{"success":true,"hostname":"archive.example.org","action":"contact"}'];
	protected function requestVerification(string $token): ?array { $this->calls++; return $this->response; }
}
class ContactControllerFixture extends ContactController {
	function setTurnstile(TadlContactTurnstile $turnstile) { $this->turnstile = $turnstile; }
}
$fields = [
	'email'=>['label'=>'Email address','email_address'=>1,'required'=>1,'use_as_from_address'=>1],
	'name'=>['label'=>'Your name','required'=>1], 'message'=>['label'=>'Message','required'=>1],
	'itemTitle'=>['label'=>'Item Title'], 'itemId'=>['label'=>'Item Identifier'], 'itemURL'=>['label'=>'Item URL']
];
$contactConfig=new ContactConfig(['contact_email'=>['questions@example.org'],'check_security'=>1,'contact_form_elements'=>$fields]);
function contactRun(array $overrides=[], $method='POST', $loggedIn=false, $turnstile=null) {
	$GLOBALS['sent']=[]; $_REQUEST=['sum'=>'7'];
	$request=new ContactRequest(['security'=>'7','g-recaptcha-response'=>'synthetic-captcha','name'=>'Synthetic Visitor','email'=>'alice@example.org','message'=>'Synthetic question'],$method,$loggedIn);
	$request->params=array_replace($request->params, ['csrfToken'=>tadlGeneratePublicCSRFToken($request, 'contact')], $overrides);
	$response=new ContactResponse(); $controller=new ContactControllerFixture($request,$response);
	if ($turnstile) { $controller->setTurnstile($turnstile); }
	$controller->Send();
	return [$controller,$response,$GLOBALS['sent']];
}
foreach ([
	'Synthetic Visitor'=>'Synthetic Visitor', '  Zoë O’Example  '=>'Zoë O’Example',
	'Example, "Visitor"'=>'Example, "Visitor"', 'Synthetic &amp; Visitor'=>'Synthetic & Visitor',
	'<b>Synthetic Visitor</b>'=>'Synthetic Visitor', "Synthetic\r\nVisitor"=>'Synthetic Visitor',
	"Synthetic\0\tVisitor"=>'Synthetic Visitor', 'Synthetic&#13;&#10;Visitor'=>'Synthetic Visitor',
	"Synthetic\u{2028}Visitor"=>'Synthetic Visitor',
	"Synthetic\r\nBcc: other@example.org"=>'Synthetic Bcc: other@example.org'
] as $name=>$expected) {
	[$controller,$response,$messages]=contactRun(['name'=>$name]);
	contactCheck(count($messages)===1 && $messages[0]['options']['replyTo']===['alice@example.org'=>$expected], 'Unexpected synthetic Reply-To: '.json_encode(['expected'=>$expected,'actual'=>$messages[0]['options']['replyTo'] ?? null]));
	contactCheck($messages[0]['from']==='sender@example.org' && $messages[0]['to']===['questions@example.org'], 'The site sender and configured recipient must stay independent of submitted headers.');
	contactCheck($controller->rendered===['Contact/success_html.php'], 'Successful sends must retain the native success page.');
	contactCheck(preg_match('/\ASynthetic Archive: Contact form email \[CF-[A-F0-9]{16}\]\z/', $messages[0]['subject'])===1 && !isset($subjects[$messages[0]['subject']]), 'Each submission must have a fresh reference in its subject without user-supplied header text.');
	$subjects[$messages[0]['subject']]=true;
}
foreach (['','not-an-address',"alice@example.org\r\nBcc: other@example.org"] as $email) {
	[$controller,$response,$messages]=contactRun(['email'=>$email]);
	contactCheck(!$messages && !empty($controller->view->vars['errors']['email']), 'Invalid email must not send.');
}
foreach (['name','message'] as $field) {
	[$controller,$response,$messages]=contactRun([$field=>'']);
	contactCheck(!$messages && !empty($controller->view->vars['errors'][$field]), 'Required field validation must remain in place.');
}
foreach (['GET','HEAD','PUT'] as $method) {
	[$controller,$response,$messages]=contactRun([], $method);
	contactCheck($response->status===405 && $response->headers['Allow']==='POST' && !$messages, 'Only POST may send contact mail.');
}
[$controller,$response,$messages]=contactRun(['csrfToken'=>'invalid']);
contactCheck($response->status===403 && !$messages, 'Invalid CSRF must not send.');
[$controller,$response,$messages]=contactRun(['security'=>'8']);
contactCheck(!$messages && !empty($controller->view->vars['errors']['security']), 'Math validation must remain in place.');
$captchaAccepted=false; [$controller,$response,$messages]=contactRun();
contactCheck(!$messages && !empty($controller->view->vars['errors']['recaptcha']), 'Configured anonymous reCAPTCHA must remain enforced.');
[$controller,$response,$messages]=contactRun([], 'POST', true);
contactCheck(count($messages)===1, 'Signed-in users must retain the native reCAPTCHA exemption.'); $captchaAccepted=true;
$contactConfig->values['from_email']='configured-sender@example.org';
[$controller,$response,$messages]=contactRun(['itemTitle'=>'Synthetic Item','itemId'=>'SYNTHETIC.42','itemURL'=>'https://archive.example.org/Detail/objects/42']);
contactCheck($messages[0]['from']==='configured-sender@example.org' && str_contains($messages[0]['text'],'SYNTHETIC.42') && $messages[0]['options']['replyTo']===['alice@example.org'=>'Synthetic Visitor'], 'Item inquiries must retain their metadata and explicit sender.');
contactCheck(preg_match('/\[CF-[A-F0-9]{16}\]\z/', $messages[0]['subject'])===1 && !isset($subjects[$messages[0]['subject']]), 'Item inquiries must also have a distinct subject reference.');
$mailSuccess=false; [$controller,$response,$messages]=contactRun();
contactCheck(!empty($controller->view->vars['errors']['display_errors']['send_error']) && $controller->rendered===['Contact/form_html.php'], 'Mail delivery failures must retain the form and show an error.');

// Real verifier/controller, with only the Cloudflare HTTP boundary replaced.
$mailSuccess=true; $captchaAccepted=false;
$turnstile=new ContactTurnstileFixture('synthetic-site-key','synthetic-secret-key',['archive.example.org','history.example.org']);
[$controller,$response,$messages]=contactRun(['security'=>'incorrect','g-recaptcha-response'=>'','cf-turnstile-response'=>'synthetic-token'], 'POST', false, $turnstile);
contactCheck(count($messages)===1 && $turnstile->calls===1, 'Turnstile must replace both legacy challenges and verify before sending.');
contactCheck($messages[0]['options']['replyTo']===['alice@example.org'=>'Synthetic Visitor'], 'Turnstile must preserve named Reply-To behavior.');
foreach ([
	['status'=>200,'body'=>'{"success":false,"error-codes":["timeout-or-duplicate"]}'],
	['status'=>200,'body'=>'{"success":true,"hostname":"other.example.org","action":"contact"}'],
	['status'=>200,'body'=>'{"success":true,"hostname":"archive.example.org","action":"login"}'],
	['status'=>200,'body'=>'{"success":true}'],
	['status'=>200,'body'=>'{"success":"true","hostname":"archive.example.org","action":"contact"}'],
	['status'=>200,'body'=>'not JSON'], ['status'=>200,'body'=>'null'],
	['status'=>503,'body'=>'{"success":true,"hostname":"archive.example.org","action":"contact"}'], null
] as $verification) {
	$turnstile->response=$verification;
	[$controller,$response,$messages]=contactRun(['cf-turnstile-response'=>'synthetic-token'], 'POST', false, $turnstile);
	contactCheck(!$messages && !empty($controller->view->vars['errors']['turnstile']) && $controller->rendered===['Contact/form_html.php'], 'Rejected/expired/replayed, mismatched or unavailable verification must block mail and retain the form.');
	contactCheck($controller->view->vars['message']==='Synthetic question', 'Verification failures must preserve the submitted message for retry.');
}
$turnstile->response=['status'=>200,'body'=>'{"success":true,"hostname":"ARCHIVE.EXAMPLE.ORG","action":"contact"}'];
contactCheck($turnstile->verify('synthetic-token'), 'Hostname comparison must be case insensitive.');
$turnstile->response=['status'=>200,'body'=>'{"success":true,"hostname":"history.example.org","action":"contact"}'];
contactCheck($turnstile->verify('synthetic-token'), 'Both configured hostnames must use the same widget and verification rules.');
foreach (['', ' ', str_repeat('x',2049), [], null] as $token) {
	$before=$turnstile->calls;
	contactCheck(!$turnstile->verify($token) && $turnstile->calls===$before, 'Missing or malformed tokens must be rejected without HTTP.');
}
[$controller,$response,$messages]=contactRun([], 'POST', false, $turnstile);
contactCheck(!$messages && !empty($controller->view->vars['errors']['turnstile']), 'Omitting the hidden token must not bypass verification.');
foreach ([['message'=>''],['csrfToken'=>'invalid']] as $overrides) {
	$before=$turnstile->calls;
	[$controller,$response,$messages]=contactRun($overrides+['cf-turnstile-response'=>'synthetic-token'], 'POST', false, $turnstile);
	contactCheck(!$messages && $turnstile->calls===$before, 'Invalid form/CSRF must not spend a single-use verification token.');
}
$before=$turnstile->calls;
[$controller,$response,$messages]=contactRun(['security'=>'incorrect'], 'POST', true, $turnstile);
contactCheck(count($messages)===1 && $turnstile->calls===$before, 'Signed-in users must retain the CAPTCHA exemption with Turnstile.');
foreach ([['synthetic-site-key','',[]],['','synthetic-secret-key',[]],['synthetic-site-key','synthetic-secret-key',[]],['synthetic-site-key','synthetic-secret-key',['https://archive.example.org']]] as $settings) {
	$incomplete=new ContactTurnstileFixture(...$settings);
	[$controller,$response,$messages]=contactRun(['cf-turnstile-response'=>'synthetic-token'], 'POST', false, $incomplete);
	contactCheck($incomplete->enabled() && !$incomplete->ready() && !$messages && $incomplete->calls===0, 'Incomplete setup must fail closed rather than falling back to arithmetic.');
}
function contactFormHTML($turnstile, $loggedIn=false) {
	$request=new ContactRequest([], 'GET', $loggedIn); $response=new ContactResponse();
	$controller=new ContactControllerFixture($request,$response); $controller->setTurnstile($turnstile); $controller->Form();
	$html=$controller->view->render('Contact/form_html.php');
	contactCheck(!str_contains($html,'synthetic-secret-key') && !in_array('synthetic-secret-key',$controller->view->vars,true), 'The secret must never enter the view or rendered HTML.');
	contactCheck(!str_contains($html,'Having trouble?') && !str_contains($html,'mailto:') && !str_contains($html,'email us'), 'The form must not display the removed email-help line or refer to it.');
	return $html;
}
$html=contactFormHTML($turnstile);
contactCheck(str_contains($html,'https://challenges.cloudflare.com/turnstile/v0/api.js') && str_contains($html,'data-action="contact"') && str_contains($html,'data-size="flexible"'), 'The anonymous form must load the managed widget with the verified action and responsive size.');
contactCheck(!str_contains($html,'name="security"') && !str_contains($html,'name="sum"') && !str_contains($html,'recaptcha/api.js'), 'Turnstile must replace both legacy CAPTCHA controls.');
contactCheck(str_contains($html,'<noscript>') && str_contains($html,'Please enable JavaScript to complete verification.'), 'Visitors without JavaScript must receive verification instructions.');
$html=contactFormHTML($turnstile,true);
contactCheck(!str_contains($html,'challenges.cloudflare.com') && !str_contains($html,'name="security"'), 'Signed-in forms must not load a challenge.');
$html=contactFormHTML(new ContactTurnstileFixture('synthetic-site-key'));
contactCheck(str_contains($html,'Please try again later.') && !str_contains($html,'challenges.cloudflare.com'), 'Partial configuration must show retry guidance without a broken widget.');
$html=contactFormHTML(new TadlContactTurnstile());
contactCheck(str_contains($html,'name="security"') && str_contains($html,'name="sum"'), 'Absent Turnstile setup must preserve the existing arithmetic form.');
$contactConfig->values['check_security']=0;
contactCheck(!str_contains(contactFormHTML(new TadlContactTurnstile()),'name="security"'), 'The arithmetic view must honor its existing configuration.');
$csrfForm = contactFormHTML(new TadlContactTurnstile());
$csrfDocument = new DOMDocument(); $csrfDocument->loadHTML($csrfForm, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
$csrfInput = (new DOMXPath($csrfDocument))->query('//input[@name="csrfToken"]')->item(0);
$formToken = $csrfInput->getAttribute('value');
$captchaAccepted = true;
[$controller,$response,$messages] = contactRun(['csrfToken' => $formToken]);
contactCheck($response->status === 200 && count($messages) === 1, 'The actual rendered contact token must pass the actual sending controller.');
$csrfRequest = new ContactRequest([], 'GET');
$mediaToken = tadlGeneratePublicCSRFToken($csrfRequest, 'media_preference');
[$controller,$response,$messages] = contactRun(['csrfToken' => $mediaToken]);
contactCheck($response->status === 403 && !$messages, 'A real media-preference token must not send a contact message.');
Session::$values['tadl_public_csrf']['contact']['expires'] = time() - 1;
[$controller,$response,$messages] = contactRun(['csrfToken' => $formToken]);
contactCheck($response->status === 403 && !$messages, 'An expired rendered contact token must not send after another page renews it.');
$formToken = tadlGeneratePublicCSRFToken($csrfRequest, 'contact');
Session::$values['tadl_public_csrf']['contact']['session'] = hash('sha256', 'synthetic-other-session');
[$controller,$response,$messages] = contactRun(['csrfToken' => $formToken]);
contactCheck($response->status === 403 && !$messages, 'A token copied from a different session must not send a contact message.');
[$controller,$response,$messages] = contactRun(['csrfToken' => ['invalid-token']]);
contactCheck($response->status === 403 && !$messages, 'An array-shaped contact token must fail closed.');
define('__TADL_TURNSTILE_SITE_KEY__','synthetic-site-key');
define('__TADL_TURNSTILE_SECRET_KEY__','synthetic-secret-key');
define('__TADL_TURNSTILE_HOSTNAMES__',['archive.example.org','history.example.org']);
$settings=TadlContactTurnstile::fromSetup();
contactCheck($settings->enabled() && $settings->ready() && $settings->siteKey()==='synthetic-site-key', 'Private setup constants must enable the production verifier.');

// The native default CSP blocks Turnstile even when the form embeds it correctly.
$headerSource=file_get_contents(dirname(__DIR__).'/conf/headers.conf');
contactCheck(preg_match('/^#!merge\s*$/m',$headerSource)===1, 'The theme must merge its header overrides without dropping unrelated security headers.');
foreach (['Content-Security-Policy','X-Content-Security-Policy'] as $header) {
	contactCheck(preg_match('/^\s*'.preg_quote($header,'/').'\s*=\s*"([^"]+)"/m',$headerSource,$match)===1, 'Each CSP override must replace the previous policy with one scalar value.');
	$policy=$match[1];
	contactCheck(str_contains($policy,'https://challenges.cloudflare.com') && !str_contains($policy,'https: ') && !str_contains($policy,'*'), 'The CSP must permit the exact Turnstile script origin without allowing arbitrary origins.');
	contactCheck(str_contains($policy,"script-src 'self'") && str_contains($policy,'www.google.com/recaptcha/') && str_contains($policy,'www.googletagmanager.com'), 'The policy must retain native script sources used elsewhere on the site.');
}
contactCheck(str_contains($headerSource,"frame-ancestors 'self'"), 'Allowing challenge scripts must preserve the native anti-framing policy.');
// An unset frame-src/default-src already permits the Cloudflare challenge frame;
// adding a restrictive frame-src would break existing native media/map frames.
contactCheck(!str_contains($headerSource,'frame-src') && !str_contains($headerSource,'default-src'), 'The override must preserve existing iframe behavior.');
contactCheck(preg_match('/^\s*Feature-Policy\s*=\s*"([^"]+)"/m',$headerSource,$featureMatch)===1, 'The malformed native feature policy must have a theme override.');
$featureDirectives=array_values(array_filter(array_map('trim',explode(';',$featureMatch[1]))));
$features=[];
foreach ($featureDirectives as $directive) {
	contactCheck(preg_match("/\A([a-z-]+) 'none'\z/",$directive,$feature)===1, 'Every disabled feature must have a correctly quoted none allowlist.');
	$features[]=$feature[1];
}
contactCheck($features===['microphone','geolocation','accelerometer','autoplay','camera','display-capture','encrypted-media','gyroscope','magnetometer','midi','usb'], 'The feature policy must preserve supported native restrictions and omit the obsolete battery/document-domain directives.');
if ($parser=getenv('TADL_TEST_CONFIGURATION')) {
	require_once $parser;
	$reflection=new ReflectionClass(Configuration::class);
	$config=$reflection->newInstanceWithoutConstructor();
	$reflection->getProperty('ops_config_settings')->setValue($config,['scalars'=>[],'lists'=>[],'assoc'=>[]]);
	contactCheck($config->loadFile(dirname($parser).'/../conf/headers.conf'), 'Native default headers must parse.');
	$before=$config->getAssoc('security');
	contactCheck($config->loadFile(dirname(__DIR__).'/conf/headers.conf'), 'Native parser must accept theme header overrides.');
	$after=$config->getAssoc('security');
	foreach ($before as $header=>$value) {
		if (in_array($header,['Content-Security-Policy','X-Content-Security-Policy'],true)) {
			contactCheck(is_string($after[$header]) && str_contains($after[$header],'https://challenges.cloudflare.com'), 'The merged policy must replace the restrictive native header.');
			$nativePolicy=is_array($value) ? implode(' ', $value) : $value;
			$withoutTurnstile=str_replace(' https://challenges.cloudflare.com','',$after[$header]);
			contactCheck(preg_replace('/\s+/',' ',$nativePolicy)===preg_replace('/\s+/',' ',$withoutTurnstile), 'The only policy change must be allowing the exact Cloudflare challenge origin.');
		} elseif ($header==='Feature-Policy') {
			$expectedPolicy=str_replace(["autoplay 'none;", "battery 'none';", "document-domain 'none';"],["autoplay 'none';",'',''],$value);
			contactCheck(preg_replace('/\s+/',' ',$after[$header])===preg_replace('/\s+/',' ',$expectedPolicy), 'Feature policy changes must be limited to fixing the quote and removing unsupported directives.');
		} else {
			contactCheck(($after[$header] ?? null)===$value, 'The native '.$header.' header must remain unchanged.');
		}
	}
}
echo "Contact mail passed: {$checks} assertions (actual controller/templates/verifier; synthetic HTTP/mail boundaries; no mail sent).\n";
