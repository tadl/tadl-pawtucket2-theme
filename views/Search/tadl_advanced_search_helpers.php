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
			$vs_output .= "<a href='".htmlspecialchars(caNavUrl($po_request, '', 'Search', "advanced/{$vs_key}"), ENT_QUOTES, 'UTF-8')."' class='{$vs_class}'".(($vs_key === $ps_current) ? " aria-current='page'" : "").">".htmlspecialchars($vs_label, ENT_QUOTES, 'UTF-8')."</a>";
		}
		return $vs_output."</nav>";
	}
}

if (!function_exists('tadlAdvancedSearchHero')) {
	function tadlAdvancedSearchHero($po_request, $ps_current, $ps_title, $ps_description) {
		print "<div class='tadl-advanced-search-hero'>";
		print "<h1>{$ps_title}</h1>";
		print "<p>{$ps_description}</p>";
		print tadlAdvancedSearchTabs($po_request, $ps_current);
		print "</div>";
	}
}

if (!function_exists('tadlAdvancedField')) {
	function tadlAdvancedField($view, $ps_label, $ps_help, $ps_field_id, $ps_tag, $ps_class = 'col-sm-6') {
		// Compilation discovers the native tag first; final rendering supplies its HTML.
		$control = $view->getVar(substr($ps_tag, 3, -3));
		if (is_string($control) && $control !== '') {
			$index = 0; $replacements = [];
			$control = preg_replace_callback('~<(input|select|textarea)\b[^>]*>~i', static function ($match) use ($ps_field_id, &$index, &$replacements) {
				if (preg_match('~\btype=[\'"]hidden[\'"]~i', $match[0])) { return $match[0]; }
				$id = $ps_field_id.($index++ ? '_'.$index : '');
				if (preg_match('~\bid=[\'"]([^\'"]+)[\'"]~i', $match[0], $old)) { $replacements[$old[1]] = $id; }
				$tag = preg_replace('~\s+id=[\'"][^\'"]*[\'"]~i', '', $match[0]);
				$ending = preg_match('~/\s*>$~', $tag) ? ' />' : '>';
				return preg_replace('~\s*/?>$~', ' id="'.htmlspecialchars($id, ENT_QUOTES, 'UTF-8').'"'.$ending, $tag);
			}, $control);
			// Native date/autocomplete scripts may refer to the generated DOM ID.
			$control = preg_replace_callback('~(<script\b[^>]*>)(.*?)(</script>)~is', static fn($match) => $match[1].strtr($match[2], $replacements).$match[3], $control);
			$ps_tag = $control;
		}
?>
		<div class="advancedSearchField <?= $ps_class; ?>">
			<label for="<?= htmlspecialchars($ps_field_id, ENT_QUOTES, 'UTF-8'); ?>" class="formLabel" data-toggle="popover" data-trigger="hover focus" data-container="body" data-placement="top" data-content="<?= htmlspecialchars($ps_help, ENT_QUOTES, 'UTF-8'); ?>"><?= $ps_label; ?></label>
			<?= $ps_tag; ?>
		</div>
<?php
	}
}
