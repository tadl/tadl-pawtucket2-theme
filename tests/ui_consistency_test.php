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
function caJSONLookupServiceUrl($request, $table, $options) { return []; }
function caFormTag($request, $action, $id, $controller, $method, $encoding, $target, $options) {
	uiCheck($options['noCSRFToken'] && $options['disableUnsavedChangesWarning'], 'Read-only builder search must preserve native form options.');
	return '<form action="/Search/'.$action.'" id="'.$id.'" method="'.$method.'" enctype="'.$encoding.'">';
}
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
$pageHeader=file_get_contents(dirname(__DIR__).'/views/pageFormat/pageHeader.php');
uiCheck(str_contains($pageHeader,'rel="icon" type="image/svg+xml"') && str_contains($pageHeader,"getThemeUrlPath().'/assets/pawtucket/graphics/favicon.svg'"), 'The shared header must point browsers at the theme favicon instead of a missing root favicon.');
$favicon=new DOMDocument();
uiCheck($favicon->load(dirname(__DIR__).'/assets/pawtucket/graphics/favicon.svg',LIBXML_NONET), 'The linked favicon must be a valid local SVG asset.');
$forms = [];
foreach (glob(dirname(__DIR__).'/views/Search/*advanced_search*html.php') as $file) {
	$view = new UIFormView(); $compiled = $view->render(basename($file));
	preg_match_all('~\{\{\{(.*?)\}\}\}~s', $compiled, $matches);
	foreach (array_unique($matches[1]) as $tag) {
		if (in_array($tag, ['form','/form'], true)) { $view->vars[$tag] = $tag === 'form' ? '<form id="caAdvancedSearch" action="/Search/synthetic" method="post"><input type="hidden" name="_advanced" value="1">' : '</form>'; continue; }
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
	uiCheck($xpath->query('//form')->item(0)->getAttribute('method') === 'get' && $xpath->query('//form')->item(0)->getAttribute('action') === '/Search/synthetic', basename($file).': advanced searches must use GET without changing the native action.');
	uiCheck($xpath->query('//input[@name="_advanced" and @value="1"]')->length === 1, 'Native advanced mode must be preserved.');
	foreach ($xpath->query('//*[@id]') as $node) { uiCheck(!isset($ids[$node->getAttribute('id')]), basename($file).': duplicate field ID'); $ids[$node->getAttribute('id')] = true; }
	foreach ($xpath->query('//label[@for]') as $label) {
		$control = $dom->getElementById($label->getAttribute('for'));
		uiCheck($control && in_array($control->tagName, ['input','select','textarea'], true), basename($file).': label does not identify its input');
	}
	if (str_contains($file, 'ca_objects_')) {
		uiCheck($xpath->query('//form/input[@name="sort" and @value="Relevance"]')->length === 1 && $xpath->query('//form/input[@name="direction" and @value="asc"]')->length === 1, 'New object searches must explicitly start with Relevance ascending instead of a saved sort.');
		foreach (['creator','publisher'] as $role) {
			$control = $dom->getElementById('ca_entities_preferred_labels_displayname_'.$role);
			uiCheck($control && $control->getAttribute('name') === 'ca_entities.preferred_labels.displayname/'.$role.'[]', 'ID normalization must preserve relationship-specific submitted field names.');
		}
	}
}
// Basic/advanced object results share their complete native sort definitions.
$sortMaps = [];
foreach (['browse'=>'browseTypes', 'search'=>'advancedSearchTypes'] as $file=>$group) {
	$source = file_get_contents(dirname(__DIR__).'/conf/'.$file.'.conf');
	uiCheck(preg_match('~^'.preg_quote($group, '~').' = \{\s*objects = \{(.*?)(?=^\t\},?$)~ms', $source, $section) === 1, 'Missing object result configuration.');
	foreach (['sortBy','sortDirection'] as $map) {
		uiCheck(preg_match('~\b'.$map.'\s*=\s*\{([^}]*)\}~s', $section[1], $matches) === 1, 'Missing object sort definitions.');
		foreach (explode(',', $matches[1]) as $pair) {
			[$name,$value] = array_map('trim', explode('=', trim($pair), 2));
			$sortMaps[$file][$map][$name] = $value;
		}
	}
}
uiCheck($sortMaps['browse'] === $sortMaps['search'], 'Basic and advanced object result options must use the same labels, order, fields and directions.');
uiCheck($sortMaps['search']['sortBy'] === ['Identifier'=>'ca_objects.idno','Title'=>'ca_object_labels.name','Date'=>'ca_objects.date.dates_value','Relevance'=>'_natural','Entity'=>'ca_entity_labels.surname;ca_entity_labels.forename'], 'Object results must preserve native date/entity sorting and use one Title label.');
uiCheck(count(array_unique($sortMaps['search']['sortDirection'])) === 1 && $sortMaps['search']['sortDirection']['Relevance'] === 'asc', 'Object sort defaults must consistently use ascending direction.');
// Change only the search form method, keeping hidden metadata and native scripts intact.
$view = new UIFormView();
uiCheck(tadlAdvancedSearchForm($view) === '{{{form}}}', 'Compilation must retain the native form-discovery tag.');
foreach (["'post'", '"POST"'] as $method) {
	$view->vars['form'] = '<form action="/catalog/index.php/Search/people" method='.$method.' id="caAdvancedSearch"><input type="hidden" name="_formName" value="post"><script>syntheticNativeWidget();</script>';
	$form = tadlAdvancedSearchForm($view);
	uiCheck(str_contains($form, 'method="get"') && str_contains($form, 'value="post"') && str_contains($form, 'syntheticNativeWidget();') && str_contains($form, '/catalog/index.php/Search/people'), 'GET conversion must preserve native URLs, metadata and widget scripts.');
}
$view->vars = ['table'=>'ca_objects','type_id'=>7,'search'=>'harbor & "maps"','form_options'=>[]];
$builder = $view->render('search_builder_controls_html.php');
$dom = new DOMDocument(); $dom->loadHTML($builder, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); $xpath = new DOMXPath($dom);
uiCheck($xpath->query('//form[@id="SearchBuilderForm" and @method="get"]')->length === 1, 'Query-builder search must use GET.');
uiCheck($xpath->query('//input[@name="search"]')->item(0)->getAttribute('value') === 'harbor & "maps"' && $xpath->query('//input[@name="type_id" and @value="7"]')->length === 1, 'Builder GET must preserve escaped queries and type restrictions.');
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
uiCheck($xpath->query('//div[@class="detail"]/div[@class="unit"]/h3')->length === 2, 'Gallery must use shared detail units and omit an empty description.');
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
