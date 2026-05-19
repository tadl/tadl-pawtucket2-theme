<?php
if (!function_exists('tadlAdvancedSearchTabs')) {
	function tadlAdvancedSearchTabs($po_request, $ps_current) {
		$va_tabs = array(
			'objects' => _t('Objects'),
			'collections' => _t('Collections'),
			'people' => _t('People'),
			'organizations' => _t('Organizations'),
			'occurrences' => _t('Events'),
			'places' => _t('Places')
		);

		$vs_output = "<nav class='tadl-advanced-tabs' aria-label='"._t('Advanced search types')."'>";
		foreach($va_tabs as $vs_key => $vs_label) {
			$vs_class = ($vs_key === $ps_current) ? 'active' : '';
			$vs_output .= caNavLink($po_request, $vs_label, $vs_class, '', 'Search', "advanced/{$vs_key}");
		}
		return $vs_output."</nav>";
	}
}

if (!function_exists('tadlAdvancedSearchHero')) {
	function tadlAdvancedSearchHero($po_request, $ps_current, $ps_title, $ps_description) {
		print "<div class='tadl-advanced-search-hero'>";
		print "<p class='tadl-eyebrow'>"._t('Research Search')."</p>";
		print "<h1>{$ps_title}</h1>";
		print "<p>{$ps_description}</p>";
		print tadlAdvancedSearchTabs($po_request, $ps_current);
		print "</div>";
	}
}

if (!function_exists('tadlAdvancedField')) {
	function tadlAdvancedField($ps_label, $ps_help, $ps_field_id, $ps_tag, $ps_class = 'col-sm-6') {
?>
		<div class="advancedSearchField <?= $ps_class; ?>">
			<label for="<?= htmlspecialchars($ps_field_id, ENT_QUOTES, 'UTF-8'); ?>" class="formLabel" data-toggle="popover" data-trigger="hover" data-content="<?= htmlspecialchars($ps_help, ENT_QUOTES, 'UTF-8'); ?>"><?= $ps_label; ?></label>
			<?= $ps_tag; ?>
		</div>
<?php
	}
}
