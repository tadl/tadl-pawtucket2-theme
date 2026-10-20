<?php
/** Small, validated display preferences; never used for record access or scope. */
function tadlDisplayPreferences($request) {
	$cookies = method_exists($request, 'getParameters') ? (array)$request->getParameters(['COOKIE']) : [];
	$value = $cookies['tadlDisplayPreferences'] ?? '';
	$preferences = is_string($value) && strlen($value) <= 512 ? json_decode($value, true) : null;
	if (!is_array($preferences)) { return []; }
	$valid = [];
	foreach (['view' => ['images', 'list'], 'collection_view' => ['flat', 'hierarchy'], 'images_per_page' => array_map('strval', range(9, 72, 9)), 'list_per_page' => ['25', '50', '100']] as $key => $allowed) {
		if (isset($preferences[$key]) && is_scalar($preferences[$key]) && in_array((string)$preferences[$key], $allowed, true)) {
			$valid[$key] = (string)$preferences[$key];
		}
	}
	return $valid;
}

function tadlDisplayView($request, $default = 'images') {
	$explicit = $request->getParameter('view', pString);
	return is_string($explicit) && $explicit !== '' ? $explicit : (tadlDisplayPreferences($request)['view'] ?? $default);
}

function tadlResultPagePolicy($view) {
	return $view === 'list'
		? ['key'=>'list_per_page', 'choices'=>[25,50,100], 'default'=>25, 'max'=>100]
		: ['key'=>'images_per_page', 'choices'=>range(9,72,9), 'default'=>18, 'max'=>72];
}

function tadlPreferredPageChoice($request, $view) {
	$policy = tadlResultPagePolicy($view);
	return tadlDisplayPreferences($request)[$policy['key']] ?? (string)$policy['default'];
}

function tadlResultPageChoice($request, $view = null) {
	$view = $view ?? tadlDisplayView($request);
	$policy = tadlResultPagePolicy($view);
	$choice = $request->getParameter('per_page', pString);
	if ($choice === null || $choice === '') {
		$choice = $request->getParameter('n', pString);
		if ($choice === null || $choice === '') { $choice = tadlPreferredPageChoice($request, $view); }
	}
	return is_scalar($choice) && in_array((string)$choice, array_merge(['all'], array_map('strval', $policy['choices'])), true) ? (string)$choice : (string)$policy['default'];
}

/** All remains bounded, including crafted requests and a changed result total. */
function tadlResultPageSize($request, $total = null, $view = null) {
	$view = $view ?? tadlDisplayView($request);
	$policy = tadlResultPagePolicy($view);
	$choice = tadlResultPageChoice($request, $view);
	$size = $choice === 'all' ? ($total !== null && $total > $policy['max'] ? $policy['default'] : $policy['max']) : (int)$choice;
	return $total === null ? $size : max(1, min($size, max(0, (int)$total)));
}

function tadlPerPagePicker($request, $total, $size, $route, array $params = []) {
	if ($total < 1) { return ''; }
	static $sequence = 0;
	$id = 'tadl-per-page-'.(++$sequence);
	$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	$view = $params['view'] ?? tadlDisplayView($request);
	$policy = tadlResultPagePolicy($view);
	$choice = tadlResultPageChoice($request, $view);
	$selected = $total <= $policy['max'] && $size >= $total ? 'all' : ($choice === 'all' ? (string)$policy['default'] : $choice);
	[$module, $controller, $action] = $route;
	$output = '<form class="tadl-per-page" data-tadl-per-page-form method="get" action="'.$escape(caNavUrl($request, $module, $controller, $action)).'">';
	unset($params['n'], $params['per_page']);
	foreach ($params as $key => $value) {
		if (is_scalar($value)) { $output .= '<input type="hidden" name="'.$escape($key).'" value="'.$escape($value).'">'; }
	}
	$output .= '<input type="hidden" name="n" value="'.$size.'">';
	$output .= '<label class="sr-only" for="'.$id.'">'.$escape(_t('Items per page')).'</label><select id="'.$id.'" name="per_page">';
	foreach ($policy['choices'] as $count) {
		if ($count >= $total && $total <= $policy['max']) { break; }
		$output .= '<option value="'.$count.'"'.($selected === (string)$count ? ' selected' : '').'>'.$count.'</option>';
	}
	if ($total <= $policy['max']) { $output .= '<option value="all"'.($selected === 'all' ? ' selected' : '').'>'.$escape(_t('All')).'</option>'; }
	return $output.'</select><span>'.$escape(_t('per page')).'</span><button class="btn btn-default" type="submit">'.$escape(_t('Apply')).'</button></form>';
}
