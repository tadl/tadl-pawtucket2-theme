<?php
/** Actual server composition/shared cards over the parent suite's synthetic native boundaries. */
require_once TEST_THEME.'/helpers/server_results.php';
class AppController {
    static function instanceExists() { return true; }
    static function getInstance() { return new self(); }
    function getResponse() { return new class { function addHeader($name,$value) { $GLOBALS['serverResponseHeaders'][$name]=$value; } }; }
}
function caGetBrowseConfig() { return new TestConfig(['cache_timeout'=>0]); }
function caGetSearchConfig() { return new TestConfig(['baseCriteria'=>[]]); }
function caGetInfoForBrowseType($type) {
    return ['table'=>'ca_objects', 'views'=>['images'=>[], 'list'=>[]], 'sortBy'=>['Identifier'=>'ca_objects.idno','Title'=>'ca_object_labels.name'], 'options'=>[]];
}
function caGetAvailablePrintTemplates($kind, $options) { return [['name'=>'Synthetic PDF','code'=>'synthetic','type'=>'pdf']]; }

// A new collection with enough synthetic members to exercise both page-size policies.
testCollection(888888,888888,1,2);
foreach (range(1000,1143) as $id) { testCollectionObject(888888,$id); }
function testServerCollectionHTML(array $params = [], array $cookies = []) {
    $request = testMediaRequest('all', $params, 'Detail', 'collections');
    $request->cookies += $cookies;
    $view = new TestView($request, ['collection_result_params'=>[
        'collection_id'=>888888, 'collection_view'=>'flat', 's'=>max(0,(int)($params['s'] ?? 0)),
        'view'=>tadlDisplayView($request), 'sort'=>$params['sort'] ?? 'Identifier', 'direction'=>$params['direction'] ?? 'asc',
        'per_page'=>tadlResultPageChoice($request)
    ]]);
    return tadlServerCollectionResults($view);
}
foreach (['images'=>[9,18,72], 'list'=>[25,50,100]] as $display => $sizes) {
    foreach ($sizes as $size) {
        $html = testServerCollectionHTML(['view'=>$display,'per_page'=>(string)$size,'s'=>$size]);
        $document = new DOMDocument(); $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); $xpath = new DOMXPath($document);
        $cards = $xpath->query('//div[@id and starts-with(@id,"row")]');
        testAssert($cards->length === min($size,144-$size) && $cards->item(0)->getAttribute('id') === 'row'.(1000+$size), 'Server-rendered collection pages must use the real scope, offset and bounded size.');
        testAssert(($GLOBALS['serverResponseHeaders']['Cache-Control'] ?? null) === 'private, no-store', 'The containing response must retain private no-store semantics for embedded result cards.');
        testAssert(str_contains($html,'Collection items') && !str_contains($html,'<H1>') && !str_contains($html,'id="browseResultsContainer"'), 'Embedded results must have one collection toolbar without a nested page/duplicate result container.');
        testAssert(str_contains($html,'/Detail/collections/888888/') && !str_contains($html,'.load(') && !str_contains($html,'.jscroll(') && !str_contains($html,'Loading'), 'Server collection paging must use normal detail URLs without loaders.');
        $context = ResultContext::$saved['ca_objects:search:objects'];
        testAssert($context['count'] === 144 && $context['per_page'] === $size && $context['view'] === $display && $context['search'] === 'collection_id:888888' && $context['history_search'] === 'collection_id:888888' && ResultContext::$lastFind['ca_objects'] === ['search','objects',false], 'Server-rendered collections must activate their filtered native detail navigation context.');
        $return = $context['tadl_result_return'];
        testAssert($return['kind'] === 'collection' && str_contains($return['url'], '/Detail/collections/888888/') && str_contains($return['url'], '/view/'.$display.'/') && str_contains($return['url'], '/s/'.$size.'/') && str_contains($return['url'], '/n/'.$size.'/per_page/'.$size.'/collection_view/flat'), 'The saved object return must restore the actual containing collection, view, offset and page size.');
    }
}
$outer = testMediaRequest('all', ['search'=>'unrelated','key'=>'stale','getFacet'=>1,'tadl_collection_controls'=>1,'place_id'=>9,'sort'=>'invalid','view'=>'invalid','direction'=>'invalid'], 'Detail', 'collections');
$before = $outer->params;
$inner = tadlServerResultsRequest($outer,'Search',['search'=>'entity_id:90001','s'=>0]);
testAssert($outer->params === $before && $outer->getController() === 'Detail' && $inner !== $outer, 'Result composition must not mutate the containing detail request.');
testAssert($inner->getController() === 'Search' && $inner->getParameter('key') === '' && $inner->getParameter('getFacet') === '' && $inner->getParameter('place_id') === '' && $inner->getParameter('tadl_collection_controls') === '' && $inner->getParameter('sort') === 'Identifier' && $inner->getParameter('view') === 'images' && $inner->getParameter('direction') === 'asc', 'Embedded results must isolate scope and validate display state before querying.');
foreach ([['ca_entities',90001,false],['ca_entities',90002,true],['ca_places',50000,false],['ca_occurrences',999999,false]] as [$table,$id,$family]) {
    $request = testMediaRequest('all', ['key'=>'stale','search'=>'unrelated','s'=>72], 'Detail', substr($table,3));
    $html = tadlServerRelatedResults(new TestView($request,['tadl_related_scope'=>['table'=>$table,'id'=>$id,'family'=>$family]]));
    testAssert(!str_contains($html,'unrelated') && !str_contains($html,'Loading') && !str_contains($html,'.load('), 'Related previews must render their selected authority scope immediately.');
    testAssert($table === 'ca_occurrences' ? str_contains($html,'No related items are currently available.') : str_contains($html,'row1'), 'Related previews must retain direct/family/descendant-place membership and empty messages.');
}
// Media-only mode must use current public/usable representations before displaying count/cards.
$request = testMediaRequest('only', ['search'=>'synthetic','per_page'=>'9'], 'Detail', 'objects');
$inner = tadlServerResultsRequest($request,'Search',['search'=>'entity_id:42']);
$html = tadlServerObjectResults(new TestView($request,[]),$inner,new SearchResult('ca_objects',range(1,13)),'authority',caGetInfoForBrowseType('objects'),'entity_id:42');
testAssert(str_contains($html,'data-tadl-result-count="6"') && !str_contains($html,'id="row6"'), 'Embedded authority results must retain native media filtering.');
$inner->setParameter('per_page','9');
$html = tadlServerObjectResults(new TestView($request,[]),$inner,new SearchResult('ca_objects',range(1000,1020)),'authority',caGetInfoForBrowseType('objects'),'entity_id:42');
testAssert(str_contains($html,'/search/entity_id%3A42') && str_contains($html,'/s/9'), 'Ordinary preview paging must carry its query without relying on session state.');
testAssert(ResultContext::$saved['ca_objects:search:objects']['tadl_result_return']['kind'] === 'results' && str_contains(ResultContext::$saved['ca_objects:search:objects']['tadl_result_return']['url'], '/Search/objects/') && str_contains(ResultContext::$saved['ca_objects:search:objects']['tadl_result_return']['url'], '/search/entity_id%3A42'), 'A subsequent related search must replace the previous collection return with its own query.');

// Render the actual lightbox page, including its bounded loop and native actions.
class Timer {}
class ca_set_items { static function getNumCommentsForIDs($ids) { return array_fill_keys($ids,0); } }
function caGetLightboxDisplayName() { return ['singular'=>'lightbox','plural'=>'lightboxes']; }
function caGetListItemIdno($id) { return 'image'; }
function caExtractArrayValuesFromArrayOfArrays($rows,$field) { return array_column($rows,$field); }
function caProcessTemplateForIDs($template,$table,$ids,$options) { return array_map(fn($id)=>'Synthetic item '.$id,$ids); }
function caGetDetailForType($table,$type,$options) { return 'objects'; }
class ServerTestSet {
    function get($field) { return $field === 'set_id' ? 7 : ''; }
    function getPrimaryKey() { return 7; }
    function getLabelForDisplay() { return 'Synthetic lightbox'; }
}
class ServerLightboxView extends TestView {
    function render($file) {
        if ($file === 'Browse/browse_refine_subview_html.php') { return ''; }
        if ($file === 'Lightbox/set_detail_item_html.php') { return '<p>'.$this->getVar('caption').'</p>'; }
        return parent::render($file);
    }
}
foreach ([0,36,108] as $start) {
    foreach ([false,true] as $write) {
        $items = []; foreach (range(1000,1143) as $id) { $items[$id]=[['item_id'=>$id+5000]]; }
        $view = new ServerLightboxView(testMediaRequest('all',[],'Lightbox','setDetail'), [
            'set_config'=>new TestConfig(['caption_template'=>'^ca_objects.preferred_labels']), 'result'=>new SearchResult('ca_objects',range(1000,1143)),
            'set'=>new ServerTestSet(), 'setItemInfo'=>$items, 'write_access'=>$write, 'views'=>['thumbnail'=>['data'=>null]],
            'view'=>'thumbnail', 'criteria'=>[], 'sort'=>'Title', 'secondarySort'=>'Identifier', 'sortDirection'=>'desc',
            'key'=>'synthetic-key', 'hits_per_block'=>36, 'start'=>$start, 'export_formats'=>[]
        ]);
        $html = $view->render('Lightbox/set_detail_html.php');
        $document = new DOMDocument(); $document->loadHTML($html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING); $xpath = new DOMXPath($document);
        testAssert($xpath->query('//*[@id="lbSetResultLoadContainer"]/div[starts-with(@id,"row-")]')->length === 36, 'Lightbox result pages must be fully server rendered and bounded.');
        testAssert($xpath->query('//nav[@class="tadl-lightbox-pagination"]')->length === 2 && $xpath->query('//*[@id="lbSetResultLoadContainer"]//nav')->length === 0, 'Lightbox pagers must sit above/below the sortable items.');
        $label = $start === 0 ? 'Next' : 'Previous';
        $link = $xpath->query('//nav[@class="tadl-lightbox-pagination"]/a[.="'.$label.'"]')->item(0);
        testAssert($link && str_contains($link->getAttribute('href'),'/set_id/7/') && str_contains($link->getAttribute('href'),'/n/36/') && str_contains($link->getAttribute('href'),'/sort/Title/') && str_contains($link->getAttribute('href'),'/direction/desc/'), 'Lightbox page links must retain set, view, query, size and sort.');
        testAssert(!str_contains($html,'pageLoadList') && !str_contains($html,'jscroll-next') && !str_contains($html,'.load('), 'Lightbox pages must not append results while scrolling.');
        testAssert(str_contains($html,'data-order-url="/Lightbox/AjaxReorderItems') === $write, 'Paging must preserve the native write-access gate for reorder actions.');
    }
}

foreach (glob(TEST_THEME.'/views/Search/ca_*_search_subview*html.php') as $alias) {
    $view = new TestView(testMediaRequest('all',[],'MultiSearch','Index'), ['result'=>new SearchResult('ca_objects',range(1000,1010)), 'block'=>'objects', 'blockInfo'=>['table'=>'ca_objects','displayName'=>'Objects'], 'search'=>'synthetic','itemsPerPage'=>6]);
    $html = $view->render($alias);
    testAssert(substr_count($html,'class="tadl-search-card"') === 6 && !str_contains($html,'.hscroll(') && !str_contains($html,'.jscroll('), basename($alias).': legacy overview aliases must use bounded server-rendered previews.');
}
