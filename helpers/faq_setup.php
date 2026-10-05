<?php
/** Explicit maintenance operation; never called by a web request. */
function tadlSetupFAQ($themeRoot, $apply = false, $backupFile = null) {
	$templatePath = $themeRoot.'/templates/faq_entry.tmpl';
	$configPath = $themeRoot.'/conf/templates.conf';
	if (!is_readable($templatePath) || !is_readable($configPath)) {
		throw new RuntimeException('FAQ template/configuration is missing or unreadable.');
	}
	$view = new View(null, $templatePath);
	$tagNames = array_values(array_unique($view->getTagList($templatePath)));
	$expected = ['faq_category', 'faq_question', 'faq_answer'];
	$sortedNames = $tagNames; $sortedExpected = $expected;
	sort($sortedNames); sort($sortedExpected);
	if ($sortedNames !== $sortedExpected) {
		throw new RuntimeException('FAQ template must contain exactly the three expected FAQ fields.');
	}
	$definitions = Configuration::load($configPath)->get('fields');
	$tags = [];
	foreach ($tagNames as $name) {
		if (!is_array($definitions[$name] ?? null) || empty($definitions[$name]['label'])) {
			throw new RuntimeException('FAQ field definition is missing: '.$name);
		}
		$tags[$name] = $definitions[$name] + ['code' => $name];
	}
	if (empty($tags['faq_answer']['usewysiwygeditor'])) {
		throw new RuntimeException('FAQ answer must enable the native rich-text editor.');
	}
	$templateHTML = file_get_contents($templatePath);
	$template = new ca_site_templates();
	$exists = $template->load(['template_code' => 'faq_entry']);
	if ($exists && $template->get('deleted')) {
		throw new RuntimeException('FAQ template is deleted; restore it explicitly before running setup.');
	}
	$action = !$exists ? 'insert' : (($template->get('template') !== $templateHTML || $template->get('tags') != $tags) ? 'update' : 'unchanged');

	$ui = new ca_editor_uis();
	if (!$ui->load(['editor_code' => 'site_page_editor_ui', 'editor_type' => 235]) || !$ui->get('is_system_ui')) {
		throw new RuntimeException('Configure the native system Site page editor UI before running setup.');
	}
	$screens = ca_editor_ui_screens::find(['ui_id' => $ui->getPrimaryKey(), 'is_default' => 1], ['returnAs' => 'ids']);
	if (count((array)$screens) !== 1) {
		throw new RuntimeException('Site page editor must have exactly one default screen.');
	}
	$screen = new ca_editor_ui_screens();
	if (!$screen->load(reset($screens))) { throw new RuntimeException('Cannot load Site page editor screen.'); }
	$placements = ca_editor_ui_bundle_placements::find(['screen_id' => $screen->getPrimaryKey()], ['returnAs' => 'arrays']);
	$existingBundles = []; $rank = 0;
	foreach ((array)$placements as $placement) {
		$existingBundles[] = preg_replace('/^ca_site_pages\./', '', $placement['bundle_name']);
		$rank = max($rank, (int)$placement['rank']);
	}
	$requiredBundles = ['title', 'path', 'description', 'ca_site_pages_content', 'access', 'rank', 'locale_id'];
	$missingBundles = array_values(array_diff($requiredBundles, $existingBundles));
	$plan = ['template_code' => 'faq_entry', 'template_action' => $action, 'template_id' => $exists ? $template->getPrimaryKey() : null,
		'ui_id' => $ui->getPrimaryKey(), 'screen_id' => $screen->getPrimaryKey(), 'add_bundles' => $missingBundles, 'applied' => false];
	if (!$apply || ($action === 'unchanged' && !$missingBundles)) { return $plan; }
	if (!$backupFile) { throw new RuntimeException('An exclusive backup file is required before applying changes.'); }
	$backupJSON = json_encode(['version' => 1, 'created_at' => gmdate('c'), 'plan' => $plan,
		'template_before' => $exists ? $template->getFieldValuesArray() : null,
		'screen_before' => $screen->getFieldValuesArray(), 'placements_before' => $placements], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
	$previousMask = umask(0077);
	try { $backup = fopen($backupFile, 'x'); } finally { umask($previousMask); }
	if (!$backup) { throw new RuntimeException('Cannot create exclusive FAQ setup backup.'); }
	try {
		if (fwrite($backup, $backupJSON) !== strlen($backupJSON) || !fflush($backup)) {
			throw new RuntimeException('FAQ setup backup could not be written completely.');
		}
	} finally { fclose($backup); }

	$transaction = new Transaction();
	try {
		$template->setTransaction($transaction);
		$screen->setTransaction($transaction);
		if ($action !== 'unchanged') {
			$template->setMode(ACCESS_WRITE);
			$template->purify(false); // Trusted template source, as in native template scans.
			$values = ['template' => $templateHTML, 'tags' => $tags, 'deleted' => 0];
			if (!$exists) { $values += ['template_code' => 'faq_entry', 'title' => 'FAQ entry', 'description' => 'One question and answer for the home-page FAQ.']; }
			if ($template->set($values) === false || $template->numErrors()) { throw new RuntimeException('Native FAQ template validation failed.'); }
			$saved = $exists ? $template->update() : $template->insert();
			if (!$saved || $template->numErrors()) { throw new RuntimeException('Native FAQ template save failed.'); }
		}
		foreach ($missingBundles as $bundle) {
			if (!$screen->addPlacement($bundle, 'tadl_faq_'.$bundle, [], ++$rank, ['additional_settings' => []]) || $screen->numErrors()) {
				throw new RuntimeException('Native Site page editor placement failed: '.$bundle);
			}
		}
		if ($transaction->getDb()->numErrors()) { throw new RuntimeException('FAQ setup database operation failed.'); }
		$transaction->commit();
	} catch (Throwable $error) {
		$transaction->rollback();
		throw $error;
	}
	$plan['template_id'] = $template->getPrimaryKey();
	$plan['applied'] = true;
	return $plan;
}
