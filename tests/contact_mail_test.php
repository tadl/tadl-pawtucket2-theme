<?php
/** Actual theme controller/templates; synthetic request, purifier and captured mail boundary. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$checks = 0; $sent = []; $mailSuccess = true; $captchaAccepted = true;
function contactCheck($value, $message) { $GLOBALS['checks']++; if (!$value) { throw new RuntimeException($message); } }
function _t($text, ...$values) { foreach ($values as $i=>$value) { $text=str_replace('%'.($i+1), $value, $text); } return $text; }
function caGetContactConfig() { return $GLOBALS['contactConfig']; }
function caValidateCSRFToken($request) { return ($request->params['csrfToken'] ?? null) === 'synthetic-token'; }
function caGetHTMLPurifier() { return new class { function purify($value) { return $value; } }; }
function caCheckEmailAddress($email) { return filter_var($email, FILTER_VALIDATE_EMAIL) !== false; }
function caSetPageCSSClasses($classes) {}
function caNavUrl($request, ...$args) { return '/synthetic/Contact/Form'; }
function caVerifyCaptcha($token) { if (!$GLOBALS['captchaAccepted'] || $token !== 'synthetic-captcha') { throw new CaptchaException('Synthetic CAPTCHA failure'); } return true; }
function caSendmail($to, $from, $subject, $text, $html, $cc, $bcc, $attachments, $options) {
	$GLOBALS['sent'][] = compact('to','from','subject','text','html','options'); return $GLOBALS['mailSuccess'];
}
define('pString', 1); define('__NOTIFICATION_TYPE_ERROR__', 1);
define('__CA_GOOGLE_RECAPTCHA_SECRET_KEY__', 'synthetic-not-a-real-key');
class CaptchaException extends RuntimeException {}
class MetaTagManager { static function setWindowTitle($title) {} }
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
$fields = [
	'email'=>['label'=>'Email address','email_address'=>1,'required'=>1,'use_as_from_address'=>1],
	'name'=>['label'=>'Your name','required'=>1], 'message'=>['label'=>'Message','required'=>1],
	'itemTitle'=>['label'=>'Item Title'], 'itemId'=>['label'=>'Item Identifier'], 'itemURL'=>['label'=>'Item URL']
];
$contactConfig=new ContactConfig(['contact_email'=>['questions@example.org'],'check_security'=>1,'contact_form_elements'=>$fields]);
function contactRun(array $overrides=[], $method='POST', $loggedIn=false) {
	$GLOBALS['sent']=[]; $_REQUEST=['sum'=>'7'];
	$request=new ContactRequest(array_replace(['csrfToken'=>'synthetic-token','security'=>'7','g-recaptcha-response'=>'synthetic-captcha','name'=>'Synthetic Visitor','email'=>'alice@example.org','message'=>'Synthetic question'], $overrides),$method,$loggedIn);
	$response=new ContactResponse(); $controller=new ContactController($request,$response); $controller->Send();
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
$mailSuccess=false; [$controller,$response,$messages]=contactRun();
contactCheck(!empty($controller->view->vars['errors']['display_errors']['send_error']) && $controller->rendered===['Contact/form_html.php'], 'Mail delivery failures must retain the form and show an error.');
echo "Contact mail passed: {$checks} assertions (actual controller/templates; synthetic mail/request boundaries; no mail sent).\n";
