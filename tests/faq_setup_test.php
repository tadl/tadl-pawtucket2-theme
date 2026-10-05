<?php
/** Actual maintenance helper with synthetic native ORM/config/transaction boundaries. */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
define('ACCESS_WRITE', 1);
$assertions = 0;
function setupCheck($condition, $message) { $GLOBALS['assertions']++; if (!$condition) { throw new RuntimeException($message); } }
class View {
	function __construct($request, $path) {}
	function getTagList($path) { preg_match_all('/\{\{\{([^}]+)\}\}\}/', file_get_contents($path), $matches); return $matches[1]; }
}
class Configuration {
	static public bool $badFields = false;
	static function load($path) { setupCheck(is_readable($path), 'Use the installed theme field config.'); return new self(); }
	function get($key) {
		return ['faq_category' => ['label' => 'FAQ category'], 'faq_question' => ['label' => 'FAQ question'], 'faq_answer' => ['label' => 'FAQ answer', 'usewysiwygeditor' => self::$badFields ? 0 : 1]];
	}
}
class SetupModel {
	protected array $values = [];
	function load($criteria) {
		foreach (static::$rows as $row) {
			if (is_array($criteria) ? !array_diff_assoc($criteria, $row) : $row[static::$key] == $criteria) { $this->values = $row; return true; }
		}
		return false;
	}
	function get($field) { return $this->values[$field] ?? null; }
	function getPrimaryKey() { return $this->get(static::$key); }
	function getFieldValuesArray() { return $this->values; }
	function setTransaction($transaction) {}
	function setMode($mode) { setupCheck($mode === ACCESS_WRITE, 'Use explicit native template write mode.'); }
	function purify($enabled) { setupCheck($enabled === false, 'Trusted template compilation matches native scans.'); }
	function set($values) { $this->values = array_replace($this->values, $values); return true; }
	function numErrors() { return 0; }
	function insert() { $this->values[static::$key] = 7; static::$rows[] = $this->values; return 7; }
	function update() { foreach (static::$rows as &$row) { if ($row[static::$key] === $this->getPrimaryKey()) { $row = $this->values; return true; } } return false; }
	static function find($criteria, $options) {
		$rows = array_values(array_filter(static::$rows, static fn($row) => !array_diff_assoc($criteria, $row)));
		return $options['returnAs'] === 'ids' ? array_column($rows, static::$key) : $rows;
	}
}
class ca_site_templates extends SetupModel { static public array $rows = []; static protected string $key = 'template_id'; }
class ca_editor_uis extends SetupModel { static public array $rows = []; static protected string $key = 'ui_id'; }
class ca_editor_ui_bundle_placements extends SetupModel { static public array $rows = []; static protected string $key = 'placement_id'; }
class ca_editor_ui_screens extends SetupModel {
	static public array $rows = []; static protected string $key = 'screen_id'; static public bool $fail = false;
	function addPlacement($bundle, $code, $settings, $rank, $options) {
		setupCheck($options === ['additional_settings' => []] && $settings === [], 'Native placement settings remain defaults.');
		if (self::$fail) { return false; }
		ca_editor_ui_bundle_placements::$rows[] = ['placement_id' => count(ca_editor_ui_bundle_placements::$rows) + 1, 'screen_id' => $this->getPrimaryKey(), 'bundle_name' => $bundle, 'placement_code' => $code, 'rank' => $rank]; return true;
	}
}
class Transaction {
	private array $before;
	function __construct() { $this->before = [ca_site_templates::$rows, ca_editor_ui_bundle_placements::$rows]; }
	function getDb() { return new class { function numErrors() { return 0; } }; }
	function commit() {}
	function rollback() { [ca_site_templates::$rows, ca_editor_ui_bundle_placements::$rows] = $this->before; }
}
require dirname(__DIR__).'/helpers/faq_setup.php';
$fixture = sys_get_temp_dir().'/tadl-faq-setup-test-'.bin2hex(random_bytes(8)); mkdir($fixture, 0700);
register_shutdown_function(static function () use ($fixture) { foreach (glob($fixture.'/*') as $path) { unlink($path); } rmdir($fixture); });
function resetSetupFixture() {
	ca_site_templates::$rows = [['template_id' => 99, 'template_code' => 'synthetic_other', 'title' => 'Synthetic other template', 'deleted' => 0]];
	ca_editor_uis::$rows = [['ui_id' => 13, 'editor_code' => 'site_page_editor_ui', 'editor_type' => 235, 'is_system_ui' => 1]];
	ca_editor_ui_screens::$rows = [['screen_id' => 34, 'ui_id' => 13, 'is_default' => 1]];
	ca_editor_ui_bundle_placements::$rows = [];
	foreach (['ca_site_pages.title', 'path', 'ca_site_pages.description', 'ca_site_pages_content', 'access', 'keywords'] as $index => $bundle) {
		ca_editor_ui_bundle_placements::$rows[] = ['placement_id' => $index + 1, 'screen_id' => 34, 'bundle_name' => $bundle, 'placement_code' => 'existing_'.$index, 'rank' => $index + 1];
	}
	ca_editor_ui_screens::$fail = Configuration::$badFields = false;
}
$theme = dirname(__DIR__);
resetSetupFixture(); $beforeTemplates = ca_site_templates::$rows; $beforePlacements = ca_editor_ui_bundle_placements::$rows;
$plan = tadlSetupFAQ($theme);
setupCheck($plan['template_action'] === 'insert' && $plan['add_bundles'] === ['rank', 'locale_id'] && !$plan['applied'], 'Dry run must report exactly the missing template and two fields.');
setupCheck(ca_site_templates::$rows === $beforeTemplates && ca_editor_ui_bundle_placements::$rows === $beforePlacements, 'Inspection must never write.');
try { tadlSetupFAQ($theme, true); throw new LogicException('Missing backup was accepted.'); } catch (RuntimeException $error) { setupCheck(str_contains($error->getMessage(), 'backup'), 'Require a backup before writes.'); }
$plan = tadlSetupFAQ($theme, true, $fixture.'/before.json');
setupCheck($plan['applied'] && $plan['template_id'] === 7, 'Apply must register only FAQ template.');
setupCheck(ca_site_templates::$rows[0] === $beforeTemplates[0] && array_slice(ca_editor_ui_bundle_placements::$rows, 0, 6) === $beforePlacements, 'Preserve unrelated templates and all existing editor settings.');
$template = ca_site_templates::$rows[1];
setupCheck(array_keys($template['tags']) === ['faq_category', 'faq_question', 'faq_answer'] && $template['title'] === 'FAQ entry' && $template['template_code'] === 'faq_entry', 'Stored metadata must match the actual FAQ template.');
$backup = json_decode(file_get_contents($fixture.'/before.json'), true, 512, JSON_THROW_ON_ERROR);
setupCheck($backup['template_before'] === null && $backup['placements_before'] === $beforePlacements && (fileperms($fixture.'/before.json') & 0777) === 0600, 'Backup must preserve configuration privately before writes.');
$appliedRows = [ca_site_templates::$rows, ca_editor_ui_bundle_placements::$rows];
$plan = tadlSetupFAQ($theme, true);
setupCheck($plan['template_action'] === 'unchanged' && !$plan['add_bundles'] && !$plan['applied'] && $appliedRows === [ca_site_templates::$rows, ca_editor_ui_bundle_placements::$rows], 'Repeated setup must perform no writes or duplicate placements.');
ca_site_templates::$rows[1]['template'] = '<p>Synthetic previous version</p>'; ca_site_templates::$rows[1]['title'] = 'Synthetic custom title';
$plan = tadlSetupFAQ($theme, true, $fixture.'/update.json');
setupCheck($plan['template_action'] === 'update' && ca_site_templates::$rows[1]['template'] === file_get_contents($theme.'/templates/faq_entry.tmpl') && ca_site_templates::$rows[1]['title'] === 'Synthetic custom title', 'Update only FAQ compilation, preserving custom titles.');
resetSetupFixture(); ca_editor_ui_screens::$fail = true;
try { tadlSetupFAQ($theme, true, $fixture.'/failed.json'); throw new LogicException('Failed placement was accepted.'); } catch (RuntimeException $error) { setupCheck(str_contains($error->getMessage(), 'placement'), 'Report native placement failure.'); }
setupCheck(ca_site_templates::$rows === $beforeTemplates && ca_editor_ui_bundle_placements::$rows === $beforePlacements && is_file($fixture.'/failed.json'), 'Failure must roll back template and placements while retaining backup.');
foreach (['fields', 'ui', 'screen', 'deleted'] as $invalid) {
	resetSetupFixture();
	match ($invalid) {
		'fields' => Configuration::$badFields = true,
		'ui' => ca_editor_uis::$rows = [],
		'screen' => ca_editor_ui_screens::$rows[] = ['screen_id' => 35, 'ui_id' => 13, 'is_default' => 1],
		'deleted' => ca_site_templates::$rows[] = ['template_id' => 7, 'template_code' => 'faq_entry', 'deleted' => 1],
	};
	$before = [ca_site_templates::$rows, ca_editor_ui_bundle_placements::$rows];
	try { tadlSetupFAQ($theme, true, $fixture.'/'.$invalid.'.json'); throw new LogicException('Invalid setup was accepted.'); } catch (RuntimeException $error) { setupCheck($before === [ca_site_templates::$rows, ca_editor_ui_bundle_placements::$rows], 'Invalid '.$invalid.' setup must not write.'); }
}
echo 'FAQ activation passed: '.$assertions.' assertions (synthetic native ORM/config/transaction boundaries).'.PHP_EOL;
