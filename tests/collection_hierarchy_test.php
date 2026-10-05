<?php
/** Render the actual browser and recursive child list over synthetic native boundaries. */
error_reporting(E_ALL);
set_error_handler(function ($severity,$message,$file,$line) {
 if (!(error_reporting() & $severity)) { return false; }
 throw new ErrorException($message,0,$severity,$file,$line);
});
$assertions=0;
function hierarchyCheck($condition,$message) { global $assertions; $assertions++; if (!$condition) { throw new RuntimeException($message); } }
$GLOBALS['hierarchyMode']='only';
$GLOBALS['hierarchyRecords']=[
 1=>['name'=>'Synthetic collection','children'=>[30,20,10,40,50]],
 10=>['name'=>'alpha','children'=>[]],
 20=>['name'=>'Series 2','children'=>[24,23,22,21,25,26]],
 30=>['name'=>'Series 10','children'=>[]],
 40=>['name'=>'Private series','children'=>[],'access'=>0],
 50=>['name'=>'Empty series','children'=>[],'media'=>false],
 21=>['name'=>'Drawer 1','children'=>[213,212,211]],
 22=>['name'=>'drawer 2','children'=>[]],
 23=>['name'=>'Drawer 4','children'=>[]],
 24=>['name'=>'Drawer 10','children'=>[]],
 25=>['name'=>'Private drawer','children'=>[],'access'=>0],
 26=>['name'=>'Empty drawer','children'=>[],'media'=>false],
 211=>['name'=>'Volume 1','children'=>[]],
 212=>['name'=>'Volume 2','children'=>[]],
 213=>['name'=>'Volume 10','children'=>[]]
];
class HierarchyConfig {
 function __construct(private $sort=null) {}
 function get($field) {
  return match($field) { 'detail_child_collection_sort'=>$this->sort, 'always_link_to_detail'=>1, 'max_levels'=>4, default=>null };
 }
}
class HierarchyResult {
 private $position=-1;
 function __construct(private array $ids) {}
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
   'ca_objects.object_id'=>[200], default=>null
  };
 }
 function getWithTemplate($template) { return ''; }
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
  $root=new HierarchyResult([1]); $root->nextHit();
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
 $html=(new HierarchyView($sort))->render('collection_hierarchy_html.php');
 hierarchyOrder($html,['alpha','Series 2','Series 10']);
 hierarchyCheck(!str_contains($html,'Private series') && !str_contains($html,'Empty series'), 'Root browser displayed filtered collections');
 hierarchyCheck(str_contains($html,'data-child-list-url="/Collections/childList/collection_id/20"') && str_contains($html,'href="/Detail/collections/20"'), 'AJAX route/direct-link fallback changed');
 hierarchyCheck(str_contains($html,"window.history.pushState") && str_contains($html,"popstate"), 'Browser history support disappeared');
}
// Include the recursive template once (it defines native printLevel), then exercise both preference modes.
$html=(new HierarchyView())->render('child_list_html.php');
hierarchyOrder($html,['Drawer 1','drawer 2','Drawer 4','Drawer 10']);
hierarchyOrder($html,['Volume 1','Volume 2','Volume 10']);
hierarchyCheck(!str_contains($html,'Private drawer') && !str_contains($html,'Empty drawer'), 'Recursive child list displayed excluded collections');
$GLOBALS['hierarchyMode']='all';
$html=printLevel(new stdClass(),[20],new HierarchyConfig(),1,['exclude_collection_type_ids'=>[],'non_linkable_collection_type_ids'=>[],'collection_type_icons'=>['box'=>''],'collapse_levels'=>false]);
hierarchyCheck(str_contains($html,'Empty drawer') && !str_contains($html,'Private drawer'), 'All-items mode bypassed access or retained media filtering');
hierarchyOrder($html,['Drawer 1','drawer 2','Drawer 4','Drawer 10','Empty drawer']);
echo 'Collection hierarchy passed: '.$assertions.' assertions (rendered siblings, recursive natural ordering, access/media filters and history links).'.PHP_EOL;
