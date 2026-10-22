<?php
require_once(__DIR__.'/result_cache.php');

/** Keep application of validated result IDs and counts local to this request. */
function tadlMediaFacetScope($request, ?array $ids = null, ?array $allIDs = null) {
	static $scopes = null;
	$scopes ??= new WeakMap();
	if ($ids !== null) {
		$excluded = array_values(array_diff($allIDs ?? $ids, $ids));
		$subtract = count($excluded) < count($ids);
		$scopes[$request] = (object)['ids' => $ids, 'total' => count($allIDs ?? $ids), 'countIDs' => $subtract ? $excluded : $ids, 'subtract' => $subtract, 'counts' => []];
	}
	return $scopes[$request] ?? null;
}

/** Run native facet rules over the displayed objects without changing the shared browse cache. */
function tadlMediaCountedFacet($request, $items, array $info, $browse, $name) {
	if (!$browse || !$name || !method_exists($browse, 'getCriteria')) { return null; }
	$scope = tadlMediaFacetScope($request);
	if (!$scope) {
		$result = $browse->getResults();
		tadlFilterMediaResult($request, $result);
		$scope = tadlMediaFacetScope($request);
	}
	if (!$scope) { return null; }
	// Native empty-ID restrictions mean "unrestricted"; never query that case.
	if (!$scope->ids) { return []; }
	if (!$scope->countIDs) { return $items; }
	if (!array_key_exists($name, $scope->counts)) {
		$cache = $scope->total < 128 ? null : tadlResultCache($request);
		$compute = static fn() => tadlNativeMediaFacetCounts($request, $info, $browse, $name, $scope);
		if ($cache) {
			$cohort = $scope->countIDs; sort($cohort, SORT_NUMERIC);
			$definition = $info; unset($definition['content'], $definition['tadl_browse']);
			$scope->counts[$name] = $cache->remember('media_counts', [$name,$definition,$browse->getCriteria(),$cohort], $compute, static function ($counts) {
					if (!is_array($counts) || count($counts) > 200000) { return false; }
					foreach ($counts as $count) { if (!is_int($count) || $count < 0) { return false; } }
					return true;
				});
		} else { $scope->counts[$name] = $compute(); }
	}
	foreach ($items as $key => &$item) {
		if (!is_array($item) || !isset($item['content_count'])) { continue; }
		$id = $item['id'] ?? $item['item_id'] ?? $key;
		$count = $scope->subtract ? (int)$item['content_count'] - ($scope->counts[$name][$id] ?? 0) : ($scope->counts[$name][$id] ?? 0);
		if ($count <= 0) { unset($items[$key]); }
		else { $item['content_count'] = $count; }
	}
	unset($item);
	return $items;
}

/** Compute only the smaller cohort; the isolated native counter remains request-local. */
function tadlNativeMediaFacetCounts($request, array $info, $browse, $name, $scope): array {
	// An unfiltered anonymous browse already supplies the exact cohort. Seed
	// that cohort once with an intrinsic access facet, avoiding four native
	// SearchResult expansions of the same primary-key filter.
	$criteria = (array)$browse->getCriteria();
	$plain = method_exists($request, 'isLoggedIn') && !$request->isLoggedIn()
		&& (!$criteria || $criteria === ['_search'=>['*'=>true]]);
	if ($plain && empty($info['filter'])) {
		if (!isset($scope->counter)) {
			$counter = caGetBrowseInstance('ca_objects');
			$access = (array)caGetUserAccessValues($request);
			$counter->setContext('tadl_media_counts:'.hash('sha256', json_encode([$access,$scope->countIDs])));
			$counter->addFacetConfiguration('_tadl_access', ['type'=>'fieldList','field'=>'access','multiple'=>true,'dontExpandHierarchically'=>true]);
			if (!$counter->addCriteria('_tadl_access', $access)
				|| !$counter->addResultFilter('ca_objects.object_id', 'in', join(',', $scope->countIDs))
				|| !$counter->execute(['checkAccess'=>$access,'request'=>$request,'noCache'=>true,'dontCheckFacetAvailability'=>true])) {
				throw new RuntimeException('Unable to count media facets.');
			}
			$scope->counter = $counter;
		}
		$counter = $scope->counter;
	} else {
		$counter = caGetBrowseInstance('ca_objects');
		foreach ($criteria as $facet => $values) {
			$counter->addCriteria($facet, array_keys($values));
		}
		// Native facets only restrict counts to result IDs when a criterion exists.
		// This counter is never executed or saved; the fixed ID filter supplies its scope.
		if (!$counter->numCriteria()) { $counter->addCriteria('_search', ['*']); }
		$info['filter'] = caNormalizeValueArray((array)($info['filter'] ?? []));
		$info['filter']['object_id'][] = ['IN', $scope->countIDs];
	}
	$info['show_all_when_first_facet'] = false;
	unset($info['content'], $info['tadl_browse']);
	$counter->addFacetConfiguration($name, $info);
	$counted = $counter->getFacetContent($name, ['checkAccess' => (array)caGetUserAccessValues($request)]);
	$counts = [];
	foreach ((array)$counted as $key => $item) {
		if (is_array($item) && isset($item['content_count'])) {
			$counts[$item['id'] ?? $item['item_id'] ?? $key] = (int)$item['content_count'];
		}
	}
	return $counts;
}
