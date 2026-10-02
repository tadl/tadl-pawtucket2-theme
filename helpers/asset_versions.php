<?php
/** Keep native asset ordering/loading; version only this theme's CSS and JS. */
function tadlAssetLoadHTML($request, $options = null) {
	return tadlVersionThemeAssets(
		AssetLoadManager::getLoadHTML($request, $options),
		$request->getThemeUrlPath(),
		$request->getThemeDirectoryPath()
	);
}

/** Content hashes also detect deployments that preserve file modification times. */
function tadlVersionThemeAssets($html, $theme_url, $theme_directory) {
	$asset_directory = realpath($theme_directory.'/assets');
	if ($asset_directory === false) { return $html; }
	$asset_prefix = rtrim($theme_url, '/').'/assets/';
	$versions = [];
	// Consume comments and complete scripts so inline code is never rewritten.
	return preg_replace_callback('~<!--.*?-->|<(script|style)\b(?:[^>\'\"]+|\'[^\']*\'|\"[^\"]*\")*>.*?</\1\s*>|<[a-z][a-z0-9:-]*(?:[^>\'\"]+|\'[^\']*\'|\"[^\"]*\")*>~is', function ($token) use ($asset_directory, $asset_prefix, &$versions) {
		if (!preg_match('~^<(link|script)(?=[\s/>])(?:[^>\'\"]+|\'[^\']*\'|\"[^\"]*\")*>~i', $token[0], $tag)) { return $token[0]; }
		$attribute_name = strtolower($tag[1]) === 'link' ? 'href' : 'src';
		return preg_replace_callback('~\s+'.$attribute_name.'\s*=\s*([\'\"])(.*?)\1~is', function ($attribute) use ($asset_directory, $asset_prefix, $attribute_name, &$versions) {
			$url = html_entity_decode($attribute[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$fragment_parts = explode('#', $url, 2);
			$url_parts = explode('?', $fragment_parts[0], 2);
			if (strpos($url_parts[0], $asset_prefix) !== 0) { return $attribute[0]; }
			$relative_path = substr($url_parts[0], strlen($asset_prefix));
			if (!preg_match('~\A[A-Za-z0-9_./-]+\.(?:css|js)\z~i', $relative_path)
				|| in_array('..', explode('/', $relative_path), true)) { return $attribute[0]; }
			$path = realpath($asset_directory.'/'.$relative_path);
			if ($path === false || strpos($path, $asset_directory.'/') !== 0 || !is_file($path) || !is_readable($path)) { return $attribute[0]; }
			if (!array_key_exists($path, $versions)) { $versions[$path] = hash_file('sha256', $path); }
			if ($versions[$path] === false) { return $attribute[0]; }
			$query = array_filter(explode('&', $url_parts[1] ?? ''), function ($parameter) {
				return $parameter !== '' && rawurldecode(explode('=', $parameter, 2)[0]) !== 'v';
			});
			$query[] = 'v='.substr($versions[$path], 0, 16);
			$versioned_url = $url_parts[0].'?'.implode('&', $query)
				.(isset($fragment_parts[1]) ? '#'.$fragment_parts[1] : '');
			return ' '.$attribute_name.'='.$attribute[1]
				.htmlspecialchars($versioned_url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').$attribute[1];
		}, $tag[0], 1).substr($token[0], strlen($tag[0]));
	}, $html);
}
