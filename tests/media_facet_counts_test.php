<?php
require dirname(__DIR__).'/helpers/media_facet_counts.php';

function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function caGetUserAccessValues($request) { return [1]; }
function caNormalizeValueArray($values) { return $values; } // Fixtures use native normalized predicates.
function caGetBrowseInstance($table) {
    check($table === 'ca_objects', 'Only object facets may use this counter.');
    return new SyntheticFacetCounter();
}
function tadlFilterMediaResult($request, $result) {
    if ($result !== null) { tadlMediaFacetScope($request, $request->visible, $result); }
}
class SyntheticFacetCounter {
    static PDO $db;
    static int $queries = 0;
    static int $executions = 0;
    static array $lastIDs = [], $lastCriteria = [];
    public array $criteria = [], $info = [];
    private ?array $scopeIDs = null;
    function getCriteria() { return $this->criteria; }
    function numCriteria() { return count($this->criteria); }
    function addCriteria($facet, $values) { $this->criteria[$facet] = array_fill_keys($values, true); return true; }
    function setContext($value) { check(str_starts_with($value,'tadl_media_counts:'), 'Fast cohort must use an isolated native cache context.'); }
    function addResultFilter($field,$operator,$ids) {
        check($field === 'ca_objects.object_id' && $operator === 'in', 'Fast cohort must restrict native results by object ID.');
        $this->scopeIDs = array_map('intval',explode(',',$ids)); return true;
    }
    function execute($options) {
        check($options['checkAccess'] === [1] && $options['noCache'] === true && $options['dontCheckFacetAvailability'] === true,
            'Fast cohort must recompute native visibility without unrelated facet availability work.');
        check($this->criteria === ['_tadl_access'=>[1=>true]] && $this->scopeIDs, 'Fast cohort must use intrinsic access and a nonempty ID restriction.');
        self::$executions++; return true;
    }
    function addFacetConfiguration($name, $info) { $this->info = $info; }
    function getResults() { return range(1,7); }
    function getFacetContent($name, $options) {
        check($options === ['checkAccess'=>[1]] && $this->numCriteria() > 0, 'Native counter requires access and a criterion to constrain its scope.');
        check($this->info['show_all_when_first_facet'] === false, 'Native full-catalogue facet override must be disabled.');
        self::$queries++; self::$lastCriteria = $this->criteria;
        $where = []; $params = [];
        $filters = $this->scopeIDs === null ? $this->info['filter']['object_id'] : [['IN',$this->scopeIDs]];
        foreach ($filters as [$operator,$value]) {
            check(in_array($operator, ['IN','>='], true), 'Unexpected synthetic predicate.');
            if ($operator === 'IN') {
                self::$lastIDs = $value;
                $where[] = 'object_id IN ('.join(',',array_fill(0,count($value),'?')).')';
                array_push($params,...$value);
            } else { $where[] = 'object_id >= ?'; $params[] = $value; }
        }
        $query = self::$db->prepare('SELECT type_id id, COUNT(*) content_count FROM objects WHERE '.join(' AND ',$where).' GROUP BY type_id');
        $query->execute($params);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }
}
SyntheticFacetCounter::$db = new PDO('sqlite::memory:');
SyntheticFacetCounter::$db->exec('CREATE TABLE objects (object_id INTEGER PRIMARY KEY, type_id INTEGER); INSERT INTO objects VALUES (1,1),(2,1),(3,2),(4,2),(5,2),(6,2),(7,3)');
$items = [1=>['id'=>1,'label'=>'Synthetic images','content_count'=>2],2=>['id'=>2,'label'=>'Synthetic documents','content_count'=>4],3=>['id'=>3,'label'=>'Synthetic audio','content_count'=>1]];
$request = (object)['visible'=>range(1,5)]; $browse = new SyntheticFacetCounter();
$info = ['type'=>'fieldList','field'=>'type_id'];
$counted = tadlMediaCountedFacet($request,$items,$info,$browse,'type_facet');
check(array_column($counted,'content_count','id') === [1=>2,2=>3], 'Counts must subtract excluded objects and remove zero-hit values.');
check(SyntheticFacetCounter::$lastIDs === [6,7], 'Count the smaller excluded cohort, not all five included objects.');
check(SyntheticFacetCounter::$lastCriteria === ['_search'=>['*'=>true]] && $browse->criteria === [], 'An isolated counter must not change the original browse criteria.');
$before = SyntheticFacetCounter::$queries;
check(tadlMediaCountedFacet($request,$items,$info,$browse,'type_facet') === $counted && SyntheticFacetCounter::$queries === $before, 'Repeated facet rendering must reuse request-local counts.');

$other = (object)['visible'=>[1,3]];
$counted = tadlMediaCountedFacet($other,$items,$info,$browse,'type_facet');
check(array_column($counted,'content_count','id') === [1=>1,2=>1] && SyntheticFacetCounter::$lastIDs === [1,3], 'A different request must count the smaller included cohort without reusing another visitor scope.');
$browse->criteria = ['term_facet'=>[99=>true]];
$filteredInfo = $info + ['filter'=>['object_id'=>[['>=',2]]]];
tadlMediaFacetScope($other,[1,3],range(1,7));
$counted = tadlMediaCountedFacet($other,$items,$filteredInfo,$browse,'type_facet');
check(array_column($counted,'content_count','id') === [2=>1], 'Configured primary-key filters must remain in addition to the media scope.');
check(SyntheticFacetCounter::$lastCriteria === $browse->criteria, 'Applied criteria must be copied to the isolated counter.');

tadlMediaFacetScope($other,[],range(1,7)); $before = SyntheticFacetCounter::$queries;
check(tadlMediaCountedFacet($other,$items,$info,$browse,'type_facet') === [] && SyntheticFacetCounter::$queries === $before, 'An empty media result must never become an unrestricted native count query.');
tadlMediaFacetScope($other,range(1,7),range(1,7));
check(tadlMediaCountedFacet($other,$items,$info,$browse,'type_facet') === $items && SyntheticFacetCounter::$queries === $before, 'If nothing was excluded, native counts already match and need no queries.');
check(tadlMediaCountedFacet(new stdClass(),$items,$info,new stdClass(),'type_facet') === null, 'Unsupported adapters must retain the existing safe count fallback.');
$anonymous = new class { function isLoggedIn() { return false; } };
$plainBrowse = new SyntheticFacetCounter(); $plainBrowse->criteria = ['_search'=>['*'=>true]];
tadlMediaFacetScope($anonymous,range(1,5),range(1,7)); $before = SyntheticFacetCounter::$executions;
$expected = [1=>2,2=>3];
check(array_column(tadlMediaCountedFacet($anonymous,$items,$info,$plainBrowse,'type_facet'),'content_count','id') === $expected,
    'Fast anonymous counts must equal the normal smaller-cohort counts.');
check(array_column(tadlMediaCountedFacet($anonymous,$items,$info,$plainBrowse,'other_type_facet'),'content_count','id') === $expected
    && SyntheticFacetCounter::$executions === $before+1, 'All anonymous facets must share one request-local native cohort execution.');
$loggedIn = new class { function isLoggedIn() { return true; } };
tadlMediaFacetScope($loggedIn,range(1,5),range(1,7)); $before = SyntheticFacetCounter::$executions;
tadlMediaCountedFacet($loggedIn,$items,$info,$plainBrowse,'type_facet');
check(SyntheticFacetCounter::$executions === $before, 'Logged-in results must retain the general native facet path.');
echo "Media facet counts passed: current scope, smaller cohorts, zero results, native filters/criteria, request isolation and bounded recounts.\n";
