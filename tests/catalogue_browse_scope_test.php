<?php
/** Real theme adapter/membership SQL; synthetic native sort and publication boundaries. */
ob_start(); require __DIR__.'/catalogue_scope_test.php'; ob_end_clean();
$checks = 0;
$directory = sys_get_temp_dir().'/tadl-browse-scope-'.bin2hex(random_bytes(6));
mkdir($directory.'/Browse', 0700, true);
file_put_contents($directory.'/Browse/ObjectBrowse.php', '<?php // synthetic native boundary');
define('__CA_LIB_DIR__', $directory);

class ObjectBrowse {
    protected $opo_db;
    public array $filters = [], $ids = [], $sortInputs = [], $nativeOptions = [], $criteria = [];
    function __construct($id = null, public string $context = '') { $this->opo_db = $GLOBALS['scopeDB']; }
    function addCriteria($name, $values) { $this->criteria[$name] = array_fill_keys($values, true); }
    function addResultFilter($field, $operator, $value) {
        $this->filters[] = ['field'=>substr($field, strrpos($field, '.') + 1), 'operator'=>$operator, 'value'=>$value];
        return true;
    }
    function getResultFilters() { return $this->filters; }
    function clearResultFilters() { $this->filters = []; }
    function execute($options = null) {
        scopeCheck($this->criteria === ['_search'=>['*'=>true]], 'Exercise the controller wildcard path; native truly empty criteria have different filter behavior.');
        $this->nativeOptions = $options;
        $where = ['deleted = 0', 'access IN (?)']; $params = [$options['checkAccess']];
        foreach ($this->filters as $filter) {
            scopeCheck($filter['field'] === 'object_id' && $filter['operator'] === 'in', 'Use the native intrinsic object-ID filter contract.');
            $where[] = 'object_id IN (?)'; $params[] = array_map('intval', explode(',', $filter['value']));
        }
        $rows = $this->opo_db->query('SELECT object_id FROM ca_objects WHERE '.join(' AND ', $where), $params);
        $this->ids = [];
        while ($rows->nextRow()) { $this->ids[] = (int)$rows->get('object_id'); }
        return true;
    }
    function getResults($options = null) {
        $this->sortInputs[] = $this->ids;
        $direction = ($options['sort_direction'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
        $ids = [];
        if ($this->ids) {
            $rows = $this->opo_db->query('SELECT object_id FROM ca_objects WHERE object_id IN (?) ORDER BY sort_value '.$direction.', object_id '.$direction, [$this->ids]);
            while ($rows->nextRow()) { $ids[] = (int)$rows->get('object_id'); }
        }
        return new SearchResult(array_slice($ids, $options['start'] ?? 0, $options['limit'] ?? null), 'ca_objects');
    }
}

function browseScopeFixture($request, $context = '') {
    $browse = new TadlObjectBrowse(null, $context, $request);
    // BrowseController adds this before execute even when no user filters exist.
    $browse->addCriteria('_search', ['*']);
    return $browse;
}

try {
    require dirname(__DIR__).'/helpers/catalogue_browse.php';
    $db->pdo->exec('ALTER TABLE ca_objects ADD COLUMN sort_value INTEGER DEFAULT 0;
        INSERT INTO ca_collections VALUES (40,"gamma",NULL,40,0,0),(41,"gamma.series",40,40,0,1),(42,"gamma.folder",41,40,0,1)');
    $insertObject = $db->pdo->prepare('INSERT INTO ca_objects VALUES (?,0,?,?)');
    $insertMembership = $db->pdo->prepare('INSERT INTO ca_objects_x_collections VALUES (?,?)');
    foreach (range(1001,1101) as $id) {
        $insertObject->execute([$id, $id === 1101 ? 0 : 1, 1200 - $id]);
        $insertMembership->execute([$id, $id % 2 ? 41 : 42]);
    }
    // Duplicate/shared relationships must not duplicate results or broaden the sort.
    $insertMembership->execute([1001,42]); $insertMembership->execute([1001,20]);
    $request = new ScopeRequest('gamma');
    $cohort = tadlCatalogueObjectIDs($request);
    scopeCheck($cohort === range(1001,1101), 'The complete cohort must be unique, deterministic, and leave publication decisions to native browse.');
    $queries = $db->queries;
    scopeCheck(tadlCatalogueObjectIDs($request) === $cohort && $db->queries === $queries, 'Read the complete scalar cohort once per request.');
    scopeCheck(tadlCatalogueIDs($request, 'ca_objects', [200,1001,1101]) === [1001,1101] && $db->queries === $queries, 'Later card/media guards must reuse the early cohort, including negative membership.');

    $options = ['noCache'=>true, 'checkAccess'=>[1], 'request'=>$request, 'showAllForNoCriteriaBrowse'=>true, 'user_id'=>0];
    $browse = browseScopeFixture($request, 'synthetic-result-context');
    scopeCheck($browse->execute($options), 'Scoped native execution should succeed.');
    scopeCheck($browse->nativeOptions === $options && $browse->context === 'synthetic-result-context', 'Retain native access/options and result context unchanged.');
    $ascending = ['sort'=>'ca_objects.date.dates_value', 'sort_direction'=>'asc'];
    $first = $browse->getResults($ascending + ['start'=>0, 'limit'=>72])->ids;
    $second = $browse->getResults($ascending + ['start'=>72, 'limit'=>72])->ids;
    scopeCheck(array_merge($first, $second) === array_reverse(range(1001,1100)), 'Date ordering must remain global within the cohort before both 72-result pages are sliced.');
    scopeCheck(count($first) === 72 && count($second) === 28 && !array_intersect($first,$second), 'Do not skip or duplicate results at page boundaries.');
    scopeCheck($browse->sortInputs === [range(1001,1100),range(1001,1100)], 'Only native-readable institution objects may reach expensive metadata sorting.');
    scopeCheck($browse->getResults(['sort'=>'ca_objects.date.dates_value', 'sort_direction'=>'desc'])->ids === range(1001,1100), 'Descending ordering must retain the same scoped/native-readable set.');
    $browse->execute($options);
    scopeCheck(count($browse->filters) === 1, 'Repeated execution must not accumulate identical scope filters.');
    $browse->clearResultFilters(); $browse->execute($options);
    scopeCheck(count($browse->filters) === 1 && count($browse->ids) === 100, 'A caller clearing native filters must not clear the institution boundary permanently.');

    // Native publication access can differ without changing membership.
    $staffOptions = $options; $staffOptions['checkAccess'] = [0,1];
    $browse->execute($staffOptions);
    scopeCheck($browse->ids === range(1001,1101), 'Do not replace native caller publication checks with a hard-coded public-only cohort.');
    $browse->addResultFilter('ca_objects.object_id', 'in', '1001,1002,200');
    $browse->execute($options);
    scopeCheck($browse->ids === [1001,1002], 'Existing native result filters must intersect the institution boundary.');

    foreach (['missing', 'gamma.series'] as $root) {
        $denied = browseScopeFixture(new ScopeRequest($root));
        $denied->execute($options);
        scopeCheck($denied->ids === [] && $denied->filters[0]['value'] === '0', 'Missing/non-root configuration must fail closed before sorting.');
    }
    $db->pdo->exec('INSERT INTO ca_collections VALUES (43,"gamma",NULL,43,0,1)');
    $ambiguous = browseScopeFixture(new ScopeRequest('gamma')); $ambiguous->execute($options);
    scopeCheck($ambiguous->ids === [], 'An ambiguous root must fail closed.');
    $db->pdo->exec('DELETE FROM ca_collections WHERE collection_id=43');
    $unscoped = browseScopeFixture(new ScopeRequest('')); $unscoped->execute($options);
    scopeCheck(!$unscoped->filters && in_array(200,$unscoped->ids,true), 'Explicitly disabled scoping must preserve ordinary native behavior.');

    $insertObject->execute([1102,1,98]); $insertMembership->execute([1102,41]);
    $db->pdo->exec('UPDATE ca_collections SET hier_collection_id=2,parent_id=20 WHERE collection_id=42;
        UPDATE ca_objects SET access=0 WHERE object_id=1001');
    $fresh = browseScopeFixture(new ScopeRequest('gamma')); $fresh->execute($options);
    $expected = array_values(array_filter(range(1003,1102), static fn($id) => ($id % 2 === 1 && $id !== 1101) || $id === 1102));
    scopeCheck($fresh->ids === $expected, 'A new request must observe imports, hierarchy moves and publication changes without cache TTLs or invalidation.');
    echo 'Catalogue browse scope passed: '.$checks.' assertions (synthetic SQL/native boundaries, early sort filtering, 72-result paging, native options, fail-closed roots and fresh membership).'.PHP_EOL;
} finally {
    unlink($directory.'/Browse/ObjectBrowse.php'); rmdir($directory.'/Browse'); rmdir($directory);
}
