<?php
/** Render every actual advanced form with synthetic native field HTML. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$assertions = 0;
function uiCheck($condition, $message) { $GLOBALS['assertions']++; if (!$condition) { throw new RuntimeException($message); } }
function _t($value) { return $value; }
function _p($value) { echo $value; }
function caNavUrl($request, $module, $controller, $action) { return '/'.$controller.'/'.$action; }
function caGetUserAccessValues($request) { return [1]; }
function caDetailLink($request, $label, $class, $table, $id) { return '<a class="'.$class.'" href="/Detail/objects/'.$id.'">'.$label.'</a>'; }
class ca_objects { function getTypeList($options = []) { return [1 => ['name_singular' => 'Synthetic image']]; } }
class ca_entities { function getTypeIDForCode($code) { return 1; } }
$directory = sys_get_temp_dir().'/tadl-ui-'.bin2hex(random_bytes(8)); mkdir($directory, 0700);
foreach (['ca_objects','ca_entities','ca_occurrences','ca_places','ca_collections'] as $table) { file_put_contents($directory.'/'.$table.'.php', '<?php'); }
define('__CA_MODELS_DIR__', $directory);
register_shutdown_function(static function () use ($directory) { foreach (glob($directory.'/*') as $file) { unlink($file); } rmdir($directory); });
class UIFormView {
	public object $request;
	public array $vars = [];
	function __construct() { $this->request = new stdClass(); }
	function getVar($tag) { return $this->vars[$tag] ?? null; }
	function render($path) { ob_start(); include dirname(__DIR__).'/views/Search/'.$path; return ob_get_clean(); }
}
$forms = [];
foreach (glob(dirname(__DIR__).'/views/Search/*advanced_search*html.php') as $file) {
	$view = new UIFormView(); $compiled = $view->render(basename($file));
	preg_match_all('~\{\{\{(.*?)\}\}\}~s', $compiled, $matches);
	foreach (array_unique($matches[1]) as $tag) {
		if (in_array($tag, ['form','/form'], true)) { $view->vars[$tag] = $tag === 'form' ? '<form id="caAdvancedSearch">' : '</form>'; continue; }
		[$field, $options] = array_pad(explode('%', $tag, 2), 2, ''); parse_str($options, $settings);
		if (in_array($field, ['reset','submit'], true)) { $view->vars[$tag] = '<button type="button">'.ucfirst($field).'</button>'; continue; }
		$name = $field.(isset($settings['restrictToRelationshipTypes']) ? '/'.$settings['restrictToRelationshipTypes'] : '').'[]';
		$id = str_replace('.', '_', $field).'[]';
		$view->vars[$tag] = '<input name="'.htmlspecialchars($name, ENT_QUOTES).'" id="'.htmlspecialchars($id, ENT_QUOTES).'" value="Synthetic value" />';
	}
	$html = $view->render(basename($file));
	foreach ($view->vars as $tag => $value) { $html = str_replace('{{{'.$tag.'}}}', $value, $html); }
	$forms[basename($file)] = $html;
	$dom = new DOMDocument(); $dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	$xpath = new DOMXPath($dom); $ids = [];
	foreach ($xpath->query('//*[@id]') as $node) { uiCheck(!isset($ids[$node->getAttribute('id')]), basename($file).': duplicate field ID'); $ids[$node->getAttribute('id')] = true; }
	foreach ($xpath->query('//label[@for]') as $label) {
		$control = $dom->getElementById($label->getAttribute('for'));
		uiCheck($control && in_array($control->tagName, ['input','select','textarea'], true), basename($file).': label does not identify its input');
	}
	if (str_contains($file, 'ca_objects_')) {
		foreach (['creator','publisher'] as $role) {
			$control = $dom->getElementById('ca_entities_preferred_labels_displayname_'.$role);
			uiCheck($control && $control->getAttribute('name') === 'ca_entities.preferred_labels.displayname/'.$role.'[]', 'ID normalization must preserve relationship-specific submitted field names.');
		}
	}
}
// Native widget scripts must follow the new ID without changing submitted names.
$view = new UIFormView(); $tag = '{{{synthetic%label=Test}}}';
$view->vars['synthetic%label=Test'] = '<input name="synthetic[]" id="old[]"><script>jQuery("#old[]").syntheticWidget();</script>';
ob_start(); tadlAdvancedField($view, 'Test', 'Help', 'new-id', $tag); $html = ob_get_clean();
uiCheck(str_contains($html, 'name="synthetic[]"') && str_contains($html, 'jQuery("#new-id")'), 'Native widget initialization must follow normalized IDs.');
$view->vars['synthetic%label=Test'] = '<input type="hidden" name="state" id="state" /><input name="synthetic[]" id="old[]" />';
ob_start(); tadlAdvancedField($view, 'Test', 'Help', 'new-id', $tag); $html = ob_get_clean();
uiCheck(str_contains($html, 'id="state"') && str_contains($html, 'id="new-id" />') && !str_contains($html, '/ id='), 'Native self-closing controls must remain valid; hidden controls must retain their IDs.');

// The gallery sidebar uses the shared detail field markup and empty-field rules.
class UIGalleryObject {
	function __construct(public string $description = '') {}
	function getWithTemplate($template, $options) {
		uiCheck(($options['checkAccess'] ?? null) === [1], 'Gallery metadata must retain native access filtering.');
		return match ($template) {
			'^ca_objects.idno' => 'SYNTHETIC.42', '^ca_objects.description' => $this->description,
			default => '<a href="/Detail/entities/7">Synthetic person</a> (contributor)'
		};
	}
}
$view = new UIFormView();
$view->vars = ['object' => new UIGalleryObject(), 'set_item_num' => 1, 'set_num_items' => 3,
	'label' => '<Synthetic & title>', 'table' => 'ca_objects', 'row_id' => 42];
$gallery = function () { include dirname(__DIR__).'/views/Gallery/set_item_info_html.php'; };
ob_start(); $gallery->call($view); $html = ob_get_clean();
$dom = new DOMDocument(); $dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($dom);
uiCheck($xpath->query('//div[@class="detail"]/div[@class="unit"]/label')->length === 2, 'Gallery must use shared detail units and omit an empty description.');
uiCheck($xpath->query('//h2')->item(0)->textContent === '<Synthetic & title>', 'Gallery titles must be escaped.');
uiCheck(!str_contains($html, 'class="units"') && str_contains($html, '(contributor)') && str_contains($html, '>View record</a>'), 'Gallery units, relationship labels and link case must match detail pages.');
$view->vars['object']->description = str_repeat('Synthetic description. ', 40);
ob_start(); $gallery->call($view); $html = ob_get_clean();
uiCheck(str_contains($html, 'tadl-long-field-details'), 'Long gallery descriptions must use the shared disclosure.');

$process = proc_open([getenv('TADL_TEST_NODE') ?: 'node', __DIR__.'/user_features_js_test.js'], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
if (!is_resource($process)) { throw new RuntimeException('Unable to run viewer keyboard regressions.'); }
fclose($pipes[0]); $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
uiCheck(proc_close($process) === 0, 'Viewer keyboard regressions failed: '.$errors);
echo $output;
echo "UI consistency passed: {$assertions} assertions (six actual advanced forms, native widget references and gallery metadata).\n";
