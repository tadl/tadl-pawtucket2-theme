<?php
/** Published FAQ entries use Providence's existing Site Pages editor. */
function tadlHomeFAQGroups($request) {
	$templates = Datamodel::getInstance('ca_site_templates', true);
	$pages = Datamodel::getInstance('ca_site_pages', true);
	if (!$templates || !$pages) { return []; }
	$template = $templates->find(['template_code' => 'faq_entry', 'deleted' => 0], ['returnAs' => 'firstModelInstance']);
	if (!$template) { return []; }
	global $g_ui_locale_id;
	$locale = $g_ui_locale_id ?: ca_locales::getDefaultCataloguingLocaleID();
	$entries = $pages->find(['template_id' => (int)$template->getPrimaryKey(), 'access' => 1, 'deleted' => 0], [
		'returnAs' => 'modelInstances', 'sort' => 'rank', 'checkAccess' => [1]
	]);
	$entries = array_values((array)$entries);
	usort($entries, static fn($a, $b) => [(int)$a->get('rank'), (int)$a->getPrimaryKey()] <=> [(int)$b->get('rank'), (int)$b->getPrimaryKey()]);
	$groups = [];
	$purifier = caGetHTMLPurifier();
	// Keep native sanitization settings, but let the theme style saved rich text.
	$forbiddenAttributes = $purifier->config->get('HTML.ForbiddenAttributes');
	$answerConfig = HTMLPurifier_Config::inherit($purifier->config);
	$answerConfig->set('HTML.ForbiddenAttributes', $forbiddenAttributes + ['style' => true]);
	foreach ($entries as $entry) {
		// Always public-only, even for staff; draft content never leaks to the home page.
		if ((int)$entry->get('access') !== 1 || $entry->get('deleted') || !$entry->isReadable($request, 'ca_site_pages_content')
			|| ((int)$entry->get('locale_id') && (int)$entry->get('locale_id') !== (int)$locale)) { continue; }
		$content = $entry->get('content');
		if (!is_array($content)) { continue; }
		$question = trim((string)($content['faq_question'] ?? ''));
		$category = trim((string)($content['faq_category'] ?? '')) ?: _t('General');
		$answer = $purifier->purify((string)($content['faq_answer'] ?? ''), $answerConfig);
		$answerText = html_entity_decode(strip_tags($answer), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		if ($question === '' || preg_replace('/[\s\x{00a0}]+/u', '', $answerText) === '') { continue; }
		$groups[$category][] = ['question' => $question, 'answer' => $answer];
	}
	return $groups;
}
