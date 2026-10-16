<?php
/** Annotate native account controls while retaining their submitted names and widgets. */
function tadlAccessibleAccountControl($html, $field, $error = null) {
	$purposes = ['fname' => 'given-name', 'lname' => 'family-name', 'email' => 'email', 'password' => 'new-password'];
	if (!isset($purposes[$field])) { return $html; }
	$updated = false;
	$html = preg_replace_callback('~<(?:input|select|textarea)\b[^>]*>~i', static function ($match) use ($field, $purposes, $error, &$updated) {
		$tag = $match[0];
		if ($updated || preg_match('~\btype\s*=\s*([\'"])hidden\1~i', $tag)) { return $tag; }
		$updated = true;
		$attributes = ['id' => $field, 'autocomplete' => $purposes[$field]];
		if ($field === 'email') { $attributes['type'] = 'email'; }
		if ($error) { $attributes['aria-invalid'] = 'true'; }
		foreach ($attributes as $name => $value) {
			$tag = preg_replace('~\s+'.preg_quote($name, '~').'\s*=\s*([\'"]).*?\1~is', '', $tag);
			$ending = preg_match('~/\s*>$~', $tag) ? ' />' : '>';
			$tag = preg_replace('~\s*/?>$~', ' '.$name.'="'.htmlspecialchars($value, ENT_QUOTES, 'UTF-8').'"'.$ending, $tag);
		}
		if ($error) {
			$descriptions = 'tadl-error-'.$field;
			if (preg_match('~\saria-describedby=([\'"])(.*?)\1~i', $tag, $existing)) {
				$descriptions = $existing[2].' '.$descriptions;
				$tag = str_replace($existing[0], '', $tag);
			}
			$ending = preg_match('~/\s*>$~', $tag) ? ' />' : '>';
			$tag = preg_replace('~\s*/?>$~', ' aria-describedby="'.htmlspecialchars($descriptions, ENT_QUOTES, 'UTF-8').'"'.$ending, $tag);
		}
		return $tag;
	}, (string)$html);
	return $html.($error ? '<p class="tadl-form-error" id="tadl-error-'.$field.'">'.htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8').'</p>' : '');
}
