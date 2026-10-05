<?php
/** Run explicitly as the application's maintenance user; defaults to inspection. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$options = getopt('', ['providence-root:', 'theme-root:', 'backup-file:', 'apply', 'help']);
if (isset($options['help']) || empty($options['providence-root'])) {
	fwrite(STDOUT, "Usage: php support/activate-faq.php --providence-root=/path/to/providence [--theme-root=/path/to/tadl] [--apply --backup-file=/private/path/faq-before.json]\nDefaults to read-only inspection. Applying writes only FAQ template metadata and missing native Site page editor placements.\n");
	exit(isset($options['help']) ? 0 : 1);
}
try {
	$providenceRoot = realpath($options['providence-root']);
	$themeRoot = realpath($options['theme-root'] ?? dirname(__DIR__));
	if (!$providenceRoot || !is_file($providenceRoot.'/setup.php') || !$themeRoot) {
		throw new RuntimeException('Providence/theme paths are invalid.');
	}
	if (isset($options['backup-file'])) {
		$backupParent = realpath(dirname($options['backup-file']));
		if (!$backupParent || $options['backup-file'][0] !== '/') {
			throw new RuntimeException('Backup must use an absolute path in an existing private directory.');
		}
		$webRoots = [$providenceRoot, $themeRoot];
		if (basename(dirname($themeRoot)) === 'themes') { $webRoots[] = dirname($themeRoot, 2); }
		foreach ($webRoots as $webRoot) {
			if ($backupParent === $webRoot || strpos($backupParent, $webRoot.'/') === 0) {
				throw new RuntimeException('Backup must be outside the application web roots.');
			}
		}
	}
	chdir($providenceRoot);
	require $providenceRoot.'/setup.php';
	if (__CA_APP_TYPE__ !== 'PROVIDENCE') { throw new RuntimeException('Bootstrap must be a Providence installation.'); }
	require_once __CA_LIB_DIR__.'/View.php';
	require_once __CA_LIB_DIR__.'/Db/Transaction.php';
	foreach (['ca_site_templates', 'ca_editor_uis', 'ca_editor_ui_screens', 'ca_editor_ui_bundle_placements'] as $model) {
		require_once __CA_MODELS_DIR__.'/'.$model.'.php';
	}
	if (isset($options['apply'])) {
		$db = new Db();
		$engines = $db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('ca_site_templates','ca_editor_ui_screens','ca_editor_ui_bundle_placements')");
		$count = 0;
		while ($engines->nextRow()) {
			if (strcasecmp($engines->get('ENGINE'), 'InnoDB') !== 0) { throw new RuntimeException('FAQ setup requires InnoDB configuration tables for rollback.'); }
			$count++;
		}
		if ($count !== 3) { throw new RuntimeException('FAQ configuration tables are missing.'); }
	}
	require dirname(__DIR__).'/helpers/faq_setup.php';
	$plan = tadlSetupFAQ($themeRoot, isset($options['apply']), $options['backup-file'] ?? null);
	fwrite(STDOUT, json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL);
} catch (Throwable $error) {
	fwrite(STDERR, 'FAQ setup failed: '.$error->getMessage().PHP_EOL);
	exit(1);
}
