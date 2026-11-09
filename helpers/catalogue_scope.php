<?php
require_once(__DIR__.'/public_errors.php');

/** Institution membership is separate from native publication and staff permissions. */
function tadlCatalogueRootCode($request): string {
    $config = $request->config ?? null;
    return $config && method_exists($config, 'get') ? trim((string)$config->get('tadl_catalogue_root_idno')) : '';
}

/** Resolve one actual top-level hierarchy; an absent/ambiguous configured root denies all. */
function tadlCatalogueScope($request) {
    static $requests;
    $requests ??= new WeakMap();
    $code = tadlCatalogueRootCode($request);
    if ($code === '') { return null; }
    if (isset($requests[$request])) { return $requests[$request]; }
    $scope = (object)['root' => 0, 'ids' => [], 'checked' => [], 'complete' => []];
    $db = Datamodel::getInstanceByTableName('ca_collections', false)->getDb();
    $rows = $db->query('SELECT collection_id, parent_id, hier_collection_id FROM ca_collections WHERE idno = ? AND deleted = 0', [$code]);
    if (!$rows) { throw new RuntimeException('Catalogue hierarchy unavailable.'); }
    $roots = [];
    while ($rows->nextRow()) { $roots[] = $rows->getRow(); }
    if (count($roots) === 1 && !$roots[0]['parent_id']
        && (int)$roots[0]['collection_id'] === (int)$roots[0]['hier_collection_id']) {
        $scope->root = (int)$roots[0]['collection_id'];
    }
    return $requests[$request] = $scope;
}

/** Read membership into the current request only; callers supply a supported material table. */
function tadlLoadCatalogueIDs($scope, string $table, array $pending, bool $all): void {
    $db = Datamodel::getInstanceByTableName('ca_collections', false)->getDb();
    $joins = '';
    $key = 'c.collection_id';
    if ($table !== 'ca_collections') {
        $joins = ' INNER JOIN ca_objects_x_collections oc ON oc.collection_id = c.collection_id
            INNER JOIN ca_objects o ON o.object_id = oc.object_id AND o.deleted = 0';
        $key = 'o.object_id';
    }
    if ($table === 'ca_object_representations') {
        $joins .= ' INNER JOIN ca_objects_x_object_representations oxr ON oxr.object_id = o.object_id
            INNER JOIN ca_object_representations r ON r.representation_id = oxr.representation_id AND r.deleted = 0';
        $key = 'r.representation_id';
    }
    $rows = $db->query('SELECT DISTINCT '.$key.' scope_id FROM ca_collections c'.$joins.'
        WHERE c.deleted = 0 AND c.hier_collection_id = ?'.($all ? '' : ' AND '.$key.' IN (?)'), $all ? [$scope->root] : [$scope->root, $pending]);
    if (!$rows) { throw new RuntimeException('Catalogue membership unavailable.'); }
    while ($rows->nextRow()) { $scope->ids[$table][(int)$rows->get('scope_id')] = true; }
    foreach ($pending as $id) { $scope->checked[$table][$id] = true; }
    $scope->complete[$table] = $all;
}

/** Batch requested IDs; large results read the cohort once. Reuse both hits and misses. */
function tadlCatalogueIDs($request, string $table, array $ids): array {
    $tables = ['ca_collections' => 'collection_id', 'ca_objects' => 'object_id', 'ca_object_representations' => 'representation_id'];
    if (!isset($tables[$table]) || !($scope = tadlCatalogueScope($request))) { return $ids; }
    if (!$scope->root || !$ids) { return []; }
    $pending = array_values(array_filter(array_unique(array_map('intval', $ids)), static fn($id) => $id > 0 && !isset($scope->checked[$table][$id])));
    if ($pending && empty($scope->complete[$table])) { tadlLoadCatalogueIDs($scope, $table, $pending, count($pending) > 2048); }
    return array_values(array_filter($ids, static fn($id) => isset($scope->ids[$table][(int)$id])));
}

/** Restrict native object sorting before it expands metadata across the shared catalogue. */
function tadlCatalogueObjectIDs($request): ?array {
    if (!$request || !($scope = tadlCatalogueScope($request))) { return null; }
    if (!$scope->root) { return []; }
    if (empty($scope->complete['ca_objects'])) { tadlLoadCatalogueIDs($scope, 'ca_objects', [], true); }
    $ids = array_map('intval', array_keys($scope->ids['ca_objects'] ?? []));
    sort($ids, SORT_NUMERIC);
    return $ids;
}

function tadlCatalogueAllows($request, string $table, $id): bool {
    return (int)$id > 0 && (bool)tadlCatalogueIDs($request, $table, [(int)$id]);
}

function tadlRequireCatalogueRecord($request, string $table, $id): void {
    if (tadlCatalogueRootCode($request) !== '' && !tadlCatalogueAllows($request, $table, $id)) { throw new TadlPublicNotFoundException('Page not found'); }
}

/** Native viewers accept identifiers as well as the legacy representation_id. */
function tadlRequireCatalogueMediaIdentifier($request, string $identifier): void {
    if (tadlCatalogueRootCode($request) === '' || $identifier === '') { return; }
    $info = caParseMediaIdentifier(urldecode($identifier), ['checkAccess'=>caGetUserAccessValues($request)]);
    if (!$info) { throw new TadlPublicNotFoundException('Page not found'); }
    tadlRequireCatalogueRecord($request, $info['subject'], $info['subject_id']);
    if ($info['type'] === 'representation') { tadlRequireCatalogueRecord($request, 'ca_object_representations', $info['id']); }
}

/** Scope new comparisons and old session entries before native rendering. */
function tadlGuardCatalogueCompareRequest($request): void {
    if (tadlCatalogueRootCode($request) === '' || strcasecmp((string)$request->getController(), 'Compare') !== 0) { return; }
    tadlRequireCatalogueMediaIdentifier($request, (string)$request->getParameter('id', pString));
    $items = (array)Session::getVar('comparison_list');
    $kept = [];
    foreach ($items as $item) {
        if (!is_array($item) || empty($item['id']) || empty($item['resolved_id'])) { continue; }
        try {
            foreach (array_unique([$item['id'], $item['resolved_id']]) as $identifier) { tadlRequireCatalogueMediaIdentifier($request, $identifier); }
        } catch (TadlPublicNotFoundException $error) { continue; }
        $kept[] = $item;
    }
    if ($items !== $kept) { Session::setVar('comparison_list', $kept); }
}

/** Native template units keep their normal ACL, label, locale and role handling. */
function tadlCatalogueTemplate($request, string $template, $item = null): string {
    if (!tadlCatalogueRootCode($request)) { return $template; }
    // Attributes can contain markup such as delimiter="<br/>". Do not stop at
    // a > inside a quoted value or the added attribute would corrupt the unit.
    return preg_replace_callback('~<unit\b(?:"[^"]*"|\x27[^\x27]*\x27|[^\x27">])*>~i',
        static function ($match) use ($request, $item) {
            if (!preg_match('~\brelativeTo=([\x27"])(ca_collections(?:\.related)?|ca_objects(?:\.related)?)\1~i', $match[0], $relative)) { return $match[0]; }
            if (str_starts_with($relative[2], 'ca_objects')) {
                $ids = $item ? (array)$item->get($relative[2].'.object_id', ['returnAsArray'=>true, 'checkAccess'=>caGetUserAccessValues($request)]) : [];
                $ids = array_map('intval', tadlCatalogueIDs($request, 'ca_objects', $ids));
                $expression = $ids ? '^ca_objects.object_id !~ /^('.join('|', $ids).')$/' : '1';
            } else {
                $root = tadlCatalogueScope($request)->root;
                $expression = $root ? '^ca_collections.hier_collection_id != '.$root : '1';
            }
            if (preg_match('~\bskipIfExpression=([\x27"])(.*?)\1~i', $match[0], $skip)) {
                $expression = '('.html_entity_decode($skip[2], ENT_QUOTES, 'UTF-8').') OR ('.$expression.')';
                return str_replace($skip[0], 'skipIfExpression="'.htmlspecialchars($expression, ENT_QUOTES, 'UTF-8').'"', $match[0]);
            }
            return substr($match[0], 0, -1).' skipIfExpression="'.htmlspecialchars($expression, ENT_QUOTES, 'UTF-8').'">';
        }, $template);
}

/** Restrict native set queries before their limits, media reads and navigation. */
function tadlCatalogueSetOptions($request, $set, array $options): array {
    if (!tadlCatalogueRootCode($request)) { return $options; }
    $table = Datamodel::getTableName($set->get('table_num'));
    $rows = (array)$set->getItemRowIDs(['checkAccess'=>$options['checkAccess'] ?? caGetUserAccessValues($request)]);
    $ids = array_map('intval', tadlCatalogueIDs($request, $table, array_keys($rows)));
    if (isset($options['row_ids'])) { $ids = array_values(array_intersect($ids, $options['row_ids'])); }
    // Empty native row_ids means unrestricted, so use an impossible key instead.
    $options['row_ids'] = $ids ?: [0];
    return $options;
}

/** The native ancestor endpoints bypass the ordinary facet/result templates. */
function tadlCatalogueAncestorRows($request, array $rows, array $info): array {
    $table = $info['table'] ?? null;
    $key = ['ca_collections'=>'collection_id', 'ca_objects'=>'object_id'][$table ?? ''] ?? null;
    if (!$key || tadlCatalogueRootCode($request) === '') { return $rows; }
    $ids = array_map(static fn($row) => ($row['NODE'] ?? $row)[$key] ?? 0, $rows);
    $allowed = array_fill_keys(tadlCatalogueIDs($request, $table, $ids), true);
    return array_values(array_filter($rows, static function ($row) use ($allowed, $key) {
        $node = $row['NODE'] ?? $row;
        return isset($allowed[(int)($node[$key] ?? 0)]);
    }));
}
