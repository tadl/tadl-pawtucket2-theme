<?php
/** Render the actual browser and recursive child list over synthetic native boundaries. */
error_reporting(E_ALL);
set_error_handler(function ($severity,$message,$file,$line) {
 if (!(error_reporting() & $severity)) { return false; }
 throw new ErrorException($message,0,$severity,$file,$line);
});
$assertions=0;
define('__CA_ACL_READONLY_ACCESS__', 1);
function hierarchyCheck($condition,$message) { global $assertions; $assertions++; if (!$condition) { throw new RuntimeException($message); } }
$GLOBALS['hierarchyMode']='only';
$GLOBALS['hierarchyRecords']=[
 1=>['name'=>'Synthetic collection','children'=>[30,20,10,40,45,50]],
 10=>['name'=>'alpha','children'=>[]],
 20=>['name'=>'Series 2','children'=>[24,23,22,21,25,26,27]],
 30=>['name'=>'Series 10','children'=>[]],
 40=>['name'=>'Private series','children'=>[],'access'=>0],
 45=>['name'=>'Restricted series','children'=>[451],'readable'=>false],
 451=>['name'=>'Restricted descendant','children'=>[]],
 50=>['name'=>'Empty series','children'=>[],'media'=>false],
 21=>['name'=>'Drawer 1','children'=>[213,212,211]],
 22=>['name'=>'drawer 2','children'=>[]],
 23=>['name'=>'Drawer 4','children'=>[]],
 24=>['name'=>'Drawer 10','children'=>[]],
 25=>['name'=>'Private drawer','children'=>[],'access'=>0],
 26=>['name'=>'Empty drawer','children'=>[],'media'=>false],
 27=>['name'=>'Restricted drawer','children'=>[],'readable'=>false],
 211=>['name'=>'Volume 1','children'=>[]],
 212=>['name'=>'Volume 2','children'=>[]],
 213=>['name'=>'Volume 10','children'=>[]]
];
$GLOBALS['hierarchyObjects'] = [10=>[101], 211=>[201,202], 212=>[202,203], 213=>[204], 23=>[205], 24=>[206], 26=>[207], 30=>[301], 40=>[401]];
$GLOBALS['hierarchyWithoutMedia'] = [101,203,207];
$GLOBALS['hierarchyBrowseCalls'] = 0;
class HierarchyConfig {
 function __construct(private $sort=null) {}
 function get($field) {
  return match($field) { 'detail_child_collection_sort'=>$this->sort, 'always_link_to_detail'=>1, 'max_levels'=>4, default=>null };
 }
}
class HierarchyResult {
 protected $position=-1;
 function __construct(protected array $ids) {}
 function numHits() { return count($this->ids); }
 function nextHit() { return ++$this->position < count($this->ids); }
 function seek($position) { $this->position=$position-1; }
 function get($field,$options=[]) {
  $id=$this->ids[$this->position]; $row=$GLOBALS['hierarchyRecords'][$id];
  if ($field==='ca_collections.children.collection_id') {
   hierarchyCheck(($options['checkAccess']??null)===[1], 'Native child access filtering lost');
   hierarchyCheck(($options['sort']??null)==='ca_collections.preferred_labels.name', 'Child lookup still requests rank order');
   return array_values(array_filter($row['children'],fn($child)=>($GLOBALS['hierarchyRecords'][$child]['access']??1)===1));
  }
  return match($field) {
   'ca_collections.collection_id'=>$id,
   'ca_collections.preferred_labels', 'ca_collections.preferred_labels.name'=>$row['name'],
   'ca_collections.type_id'=>isset($options['convertCodesToDisplayText'])?'Collection':'box',
   'access'=>$row['access']??1, 'deleted'=>0,
   'parent_id'=>array_search(true, array_map(fn($record)=>in_array($id,$record['children'],true),$GLOBALS['hierarchyRecords']), true) ?: null,
   default=>null
  };
 }
 function getWithTemplate($template) { return ''; }
}
class HierarchyCollection extends HierarchyResult {
 function __construct($id=0) { parent::__construct([]); if ($id) { $this->load($id); } }
 function load($id) { $this->ids=[$id]; $this->position=0; return isset($GLOBALS['hierarchyRecords'][$id]); }
 function getPrimaryKey() { return $this->get('ca_collections.collection_id'); }
 function isReadable($request,$bundle=null) { return $GLOBALS['hierarchyRecords'][$this->getPrimaryKey()]['readable']??true; }
}
class Datamodel { static function getInstance($table,$initialize) { return new HierarchyCollection(); } }
function caACLIsEnabled($record,$options) { return false; }
class Db {
 function query($sql,$params) {
  $rows=[];
  if (str_starts_with($sql,'SELECT collection_id')) {
   foreach ($params[0] as $parent) {
    foreach ($GLOBALS['hierarchyRecords'][$parent]['children'] as $id) {
     if (in_array($GLOBALS['hierarchyRecords'][$id]['access']??1,$params[1],true)) { $rows[]=['collection_id'=>$id]; }
    }
   }
  } else {
   hierarchyCheck($sql==='SELECT DISTINCT object_id, collection_id FROM ca_objects_x_collections WHERE collection_id IN (?)','Unexpected count query');
   foreach ($params[0] as $id) { foreach ($GLOBALS['hierarchyObjects'][$id]??[] as $object) { $rows[]=['object_id'=>$object,'collection_id'=>$id]; } }
  }
  return new class($rows) {
   private $index=-1;
   function __construct(private $rows) {}
   function nextRow() { return ++$this->index<count($this->rows); }
   function get($key) { return $this->rows[$this->index][$key]; }
  };
 }
}
class HierarchyObjectResult {
 function __construct(public $ids) {}
 function getPrimaryKeyValues($limit) { hierarchyCheck($limit===PHP_INT_MAX,'Count hit list was capped'); return $this->ids; }
}
function caGetBrowseInstance($table) {
 hierarchyCheck($table==='ca_objects','Counts must use native object visibility');
 return new class {
  private $scope=[];
  function addCriteria($facet,$terms) { preg_match_all('/collection_id:(\d+)/',$terms[0],$matches); $this->scope=array_map('intval',$matches[1]); }
  function execute($options) { $GLOBALS['hierarchyBrowseCalls']++; hierarchyCheck($options['checkAccess']===[1] && $options['noCache']===true,'Count search lost access/freshness'); }
  function getResults() {
   $ids=[];
   foreach ($this->scope as $id) { $ids=array_merge($ids,$GLOBALS['hierarchyObjects'][$id]??[]); }
   return new HierarchyObjectResult(array_unique($ids));
  }
 };
}
function tadlFilterMediaResult($request,$result) {
 if (tadlMediaPreference($request)==='only') { $result->ids=array_diff($result->ids,$GLOBALS['hierarchyWithoutMedia']); }
}
function caMakeSearchResult($table,$ids) { hierarchyCheck($table==='ca_collections','Wrong hierarchy table'); return new HierarchyResult($ids); }
function tadlMediaPreference($request) { return $GLOBALS['hierarchyMode']; }
function caGetUserAccessValues($request) { return [1]; }
function tadlMediaEligibleIDs($table,$ids,$access) {
 hierarchyCheck($access===[1],'Media filtering lost native access context');
 return $table==='ca_collections' ? array_values(array_filter($ids,fn($id)=>$GLOBALS['hierarchyRecords'][$id]['media']??true)) : $ids;
}
function caDetailUrl($request,$table,$id) { return '/Detail/collections/'.$id; }
function caDetailLink($request,$label,$class,$table,$id) { return '<a href="'.caDetailUrl($request,$table,$id).'">'.$label.'</a>'; }
function caNavUrl($request,$module,$controller,$action,$options) { return '/Collections/'.$action.'/collection_id/'.$options['collection_id']; }
function caBusyIndicatorIcon($request) { return '<span>Loading</span>'; }
function _t($text) { return $text; }
class HierarchyView {
 public $request;
 function __construct(private $sort=null) { $this->request=new stdClass(); }
 function getVar($field) {
  $root=new HierarchyCollection(1);
  return match($field) {
   'access_values'=>[1], 'collections_config'=>new HierarchyConfig($this->sort), 'item'=>$root,
   'collection_id'=>20, 'exclude_collection_type_ids','non_linkable_collection_type_ids'=>[],
   'collection_type_icons'=>['box'=>''], default=>null
  };
 }
 function render($file) {
  ob_start(); include dirname(__DIR__).'/views/Collections/'.$file; return ob_get_clean();
 }
}
function hierarchyOrder($html,$labels) {
 $previous=-1;
 foreach($labels as $label) {
  $position=strpos($html,$label);
  hierarchyCheck($position!==false && $position>$previous,'Sibling order failed for '.$label);
  $previous=$position;
 }
}
require dirname(__DIR__).'/views/Collections/hierarchy_helpers.php';
hierarchyCheck(tadlCollectionHierarchyIDs([], 'ca_collections.preferred_labels.name')===[], 'Empty sibling list changed');
hierarchyCheck(tadlCollectionHierarchyIDs([24,22,21,23],'ca_collections.preferred_labels.name')===[21,22,23,24], 'Natural/case-insensitive ordering failed');
hierarchyCheck(tadlCollectionHierarchyIDs([24,22,21,23],'ca_collections.rank')===[24,22,21,23], 'Explicit non-name sort was overridden');
// Sorting must never introduce excluded IDs; ties retain input order.
hierarchyCheck(tadlCollectionHierarchyIDs([24,21],'ca_collections.preferred_labels.name')===[21,24], 'Sorting expanded a filtered sibling set');
$GLOBALS['hierarchyRecords'][214]=['name'=>'volume 2','children'=>[]];
hierarchyCheck(tadlCollectionHierarchyIDs([214,212],'ca_collections.preferred_labels.name')===[214,212], 'Equal labels lost stable ordering');
foreach([null,'ca_collections.preferred_labels.name'] as $sort) {
 $before=$GLOBALS['hierarchyBrowseCalls'];
 $html=(new HierarchyView($sort))->render('collection_hierarchy_html.php');
 hierarchyOrder($html,['alpha','Series 2','Series 10']);
 hierarchyCheck(!str_contains($html,'Private series') && !str_contains($html,'Empty series') && !str_contains($html,'Restricted series'), 'Root browser displayed filtered collections');
 hierarchyCheck(str_contains($html,'data-child-list-url="/Collections/childList/collection_id/20"') && str_contains($html,'href="/Detail/collections/20"'), 'AJAX route/direct-link fallback changed');
 hierarchyCheck(str_contains($html,"window.history.pushState") && str_contains($html,"popstate"), 'Browser history support disappeared');
 hierarchyCheck($GLOBALS['hierarchyBrowseCalls']===$before+1,'Left-column totals must share one native object search');
 hierarchyCheck(str_contains($html,'Series 2 <span class="tadl-collection-record-count">(5)</span></a>'),'Left-column title must include unique descendant media counts inside its link');
 hierarchyCheck(str_contains($html,'alpha <span class="tadl-collection-record-count">(0)</span>'),'Visible empty branch must show zero');
 hierarchyCheck(!preg_match('~<br\s*/?>\s*<small>|\(\d+ records?\)~',$html),'Counts must be inline numeric suffixes');
}
// Include the recursive template once (it defines native printLevel), then exercise both preference modes.
$html=(new HierarchyView())->render('child_list_html.php');
hierarchyOrder($html,['Drawer 1','drawer 2','Drawer 4','Drawer 10']);
hierarchyOrder($html,['Volume 1','Volume 2','Volume 10']);
hierarchyCheck(!str_contains($html,'Private drawer') && !str_contains($html,'Empty drawer') && !str_contains($html,'Restricted drawer'), 'Recursive child list displayed excluded collections');
hierarchyCheck(str_contains($html,'Drawer 1 <span class="tadl-collection-record-count">(3)</span></a>') && str_contains($html,'Volume 2 <span class="tadl-collection-record-count">(1)</span></a>'),'Right column must use branch totals and leaf totals in the same inline format');
$GLOBALS['hierarchyMode']='all';
$before=$GLOBALS['hierarchyBrowseCalls'];
$html=printLevel(new stdClass(),[20],new HierarchyConfig(),1,['exclude_collection_type_ids'=>[],'non_linkable_collection_type_ids'=>[],'collection_type_icons'=>['box'=>''],'collapse_levels'=>false]);
hierarchyCheck(str_contains($html,'Empty drawer') && !str_contains($html,'Private drawer'), 'All-items mode bypassed access or retained media filtering');
hierarchyOrder($html,['Drawer 1','drawer 2','Drawer 4','Drawer 10','Empty drawer']);
hierarchyCheck($GLOBALS['hierarchyBrowseCalls']===$before+1 && str_contains($html,'Series 2 <span class="tadl-collection-record-count">(7)</span>'),'Recursive all-items totals must use one search and include records without media');
$html=printLevel(new stdClass(),[20],new HierarchyConfig(),1,['exclude_collection_type_ids'=>[],'non_linkable_collection_type_ids'=>['box'],'collection_type_icons'=>['box'=>''],'collapse_levels'=>true]);
hierarchyCheck(str_contains($html,"<span class='nonLinkedCollection'> Series 2 <span class=\"tadl-collection-record-count\">(7)</span></span>") && str_contains($html,'Drawer 1 <span class="tadl-collection-record-count">(4)</span></a>'),'Nonlinked and collapsible titles must retain their inline count suffix');
hierarchyCheck(printLevel(new stdClass(),[45],new HierarchyConfig(),1,['exclude_collection_type_ids'=>[],'non_linkable_collection_type_ids'=>[],'collection_type_icons'=>['box'=>''],'collapse_levels'=>false])==='','Unavailable AJAX root must not display a title or descendant counts');
echo 'Collection hierarchy passed: '.$assertions.' assertions (rendered siblings, recursive natural ordering, access/media filters and history links).'.PHP_EOL;
