<?php
/** Keep dynamic results and account pages out of search indexes, not public records. */
function tadlCrawlerPageDirectives($request) {
	$controller = strtolower((string)$request->getController());
	if (in_array($controller, ['search', 'multisearch', 'browse', 'collectioncontents'], true)) { return 'noindex, follow'; }
	if (in_array($controller, ['loginreg', 'lightbox', 'accountprofile', 'mediapreference'], true)) { return 'noindex, nofollow'; }
	return '';
}

/** Add nofollow to server-rendered media controls without rewriting native callbacks. */
function tadlNofollowMediaLinks($html) {
	return preg_replace_callback('~<a\b(?:[^\"\'<>]|\"[^\"]*\"|\'[^\']*\')*>~i', static function ($link) {
		$found = false;
		$tag = preg_replace_callback('~\s+([a-z][a-z0-9_:.-]*)(?:\s*=\s*(?:\"([^\"]*)\"|\'([^\']*)\'|([^\s\"\'=<>`]+)))?~i', static function ($attribute) use (&$found) {
			if (strtolower($attribute[1]) !== 'rel') { return $attribute[0]; }
			$found = true;
			$value = html_entity_decode(($attribute[2] ?? '') ?: ($attribute[3] ?? '') ?: ($attribute[4] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$tokens = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
			if (!in_array('nofollow', array_map('strtolower', $tokens), true)) { $tokens[] = 'nofollow'; }
			return ' rel="'.htmlspecialchars(implode(' ', $tokens), ENT_QUOTES, 'UTF-8').'"';
		}, $link[0]);
		return $found ? $tag : substr($tag, 0, -1).' rel="nofollow">';
	}, (string)$html);
}
