<?php
/** Actual paged result templates followed by detail navigation over saved native context. */
require_once TEST_THEME.'/views/Details/detail_field_helpers.php';
foreach ($authorityBrowses + ['families'=>['ca_entities','entity_id','family','families']] as $route => [$table,$primaryKey,$singular,$plural]) {
    foreach (['images'=>18, 'list'=>25] as $display => $size) {
        $request = testMediaRequest('all', ['view'=>$display, 's'=>$size, 'per_page'=>(string)$size], 'Browse', $route);
        $values = [
            'result'=>new SearchResult($table, range(30000,30600)), 'criteria'=>[], 'facets'=>[], 'table'=>$table,
            'primaryKey'=>$primaryKey, 't_instance'=>new TestModel($table), 'browse_type'=>$route, 'find_type'=>'browse',
            'browseInfo'=>['table'=>$table, 'labelSingular'=>$singular, 'labelPlural'=>$plural],
            'config'=>new TestConfig(['cache_timeout'=>0]), 'access_values'=>[1], 'options'=>[],
            'view'=>$display, 'views'=>['images'=>[], 'list'=>[]], 'key'=>'synthetic-authority-key',
            'sort'=>'Name', 'sort_direction'=>'desc', 'sortBy'=>['Name'=>$table.'.preferred_labels'],
            'start'=>$size, 'hits_per_block'=>$size, 'export_formats'=>[], 'letter'=>'a'
        ];
        (new TestView($request, $values))->render('Browse/browse_results_html.php');
        // Native BrowseController activates this context before rendering the view.
        ResultContext::$lastFind[$table] = ['browse',null,false];
        $saved = ResultContext::$saved[$table.':browse:'];
        $return = $saved['tadl_result_return'];
        testAssert($return['kind'] === $route && str_contains($return['url'], '/Browse/'.$route.'/key/synthetic-authority-key/')
            && str_contains($return['url'], '/view/'.$display.'/sort/Name/direction/desc/s/'.$size.'/n/'.$size.'/')
            && str_contains($return['url'], '/l/a/per_page/'.$size), $route.': page-two return must preserve the browse key, display, sort, direction, letter and page size.');
        foreach (array_slice($saved['ids'], $size, 4) as $offset => $id) {
            $item = new TestModel($table); $item->load($id);
            // A fresh detail request has none of the source browse parameters.
            $detail = new TestView(testMediaRequest('all', [], 'Detail', substr($table,3)), [
                'item'=>$item, 'resultsURL'=>'/Browse/'.$route.'?key=synthetic-authority-key',
                'previousLink'=>caDetailLink($request,'Previous','',$table,$saved['ids'][$size+$offset-1]),
                'nextLink'=>caDetailLink($request,'Next','',$table,$saved['ids'][$size+$offset+1])
            ]);
            $html = tadlDetailNavigation($detail, $route, 'Browse '.$plural, 'Browse', $route);
            $document = new DOMDocument(); $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $links = (new DOMXPath($document))->query('//nav/a'); $back = $links->item(0);
            $label = 'Back to '.$plural;
            testAssert($links->length === 3 && $back->textContent === $label && $back->getAttribute('href') === $return['url']
                && $back->getAttribute('title') === $label && $back->getAttribute('aria-label') === $label,
                $route.': entering a page-two result and advancing three records must retain the same contextual return, without a duplicate generic Browse action.');
        }
        $values['find_type'] = 'search'; $values['result'] = new SearchResult($table, [30000,30001]);
        $request->setController('Search');
        (new TestView($request,$values))->render('Browse/browse_results_html.php');
        testAssert(ResultContext::$saved[$table.':search:'.$route]['tadl_result_return']['kind'] === 'results', $route.': a subsequent search must replace the browse-specific return.');
    }
}
testAssert(ResultContext::$saved['ca_collections:collections:']['tadl_result_return']['kind'] === 'collections'
    && str_contains(ResultContext::$saved['ca_collections:collections:']['tadl_result_return']['url'], '/Collections/Index/page/2/view/tiles/per_page/90'),
    'The collection index must also save its originating page, view and independent page-size preference.');
