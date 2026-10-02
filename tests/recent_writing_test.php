<?php
/** Actual home template/client; synthetic browser/feed boundaries, no network. */
error_reporting(E_ALL);
function caNavUrl($request, $module, $controller, $action) { return '/synthetic/'.$controller.'/'.$action; }
class WritingView {
	public $request;
	function render($name) { return ''; }
	function page() { ob_start(); include dirname(__DIR__).'/views/Front/front_page_html.php'; return ob_get_clean(); }
}
$html = (new WritingView())->page();
$document = new DOMDocument(); $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($document);
if ($xpath->query('//*[@id="tadl-recent-writing"]//a[contains(@class,"tadl-blog-card")]')->length !== 2) { throw new RuntimeException('Home must retain two fallback cards.'); }
$more = $xpath->query('//a[contains(@class,"tadl-section-link")]')->item(0)->getAttribute('href');
parse_str(parse_url($more, PHP_URL_QUERY), $params);
if (($params['field_bl_type_target_id'][295] ?? null) !== '295' || ($params['field_bl_tags_target_id'][414] ?? null) !== '414') { throw new RuntimeException('View More must retain both local history filters.'); }
$js = <<<'JS'
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = require('node:fs').readFileSync(0, 'utf8');
function node(tag) { return {tag, children:[], append(...children){this.children.push(...children);}}; }
const good = (title, slug) => ({title:{rendered:title},post_url:'/posts/'+slug,featured_image_urls:{thumbnail:'/sites/default/files/'+slug+'.jpg'}});
async function fixture(responses, {present=true, loading=false}={}) {
 const fallback=node('fallback'), grid=node('div'); grid.children=[fallback];
 grid.replaceChildren=function(...cards){this.children=cards;};
 const calls=[], timers=new Map(), listeners={}; let timer=0;
 const context=vm.createContext({URL,AbortController,
  document:{readyState:loading?'loading':'complete',getElementById(id){assert.equal(id,'tadl-recent-writing');return present?grid:null;},createElement:node,addEventListener(event,handler){listeners[event]=handler;}},
  setTimeout(handler,delay){timers.set(++timer,{handler,delay});return timer;},clearTimeout(id){timers.delete(id);},
  fetch:async(url,options)=>{calls.push({url,options});const result=responses.shift();if(result instanceof Error)throw result;return result;}
 });
 new vm.Script(source).runInContext(context);
 await new Promise(setImmediate);
 return {grid,fallback,calls,timers,listeners};
}
(async()=>{
 let f=await fixture([{ok:true,status:200,json:async()=>[good('<script>literal title</script>','new'),good('Earlier history','old'),good('Extra','extra')]}]);
 assert.equal(f.grid.children.length,2);assert.equal(f.grid.children[0].href,'https://www.tadl.org/posts/new');
 assert.equal(f.grid.children[0].children[1].textContent,'<script>literal title</script>');
 assert.equal(f.grid.children[0].children[0].children[0].src,'https://www.tadl.org/sites/default/files/new.jpg');
 assert.equal(f.grid.children[0].children[0].children[0].alt,'');
 assert.equal(f.calls[0].url,'https://feeds.tools.tadl.org/local_history_posts.json?limit=2');
 assert.equal(f.calls[0].options.credentials,'omit');assert.equal(f.calls[0].options.referrerPolicy,'no-referrer');assert.equal(f.timers.size,0);
 const unsafe=[null,good('Bad','bad')];unsafe[1].post_url='javascript:alert(1)';
 const external=good('External','external');external.post_url='https://example.com/posts/external';unsafe.push(external);
 const image=good('Valid without unsafe image','safe');image.featured_image_urls.thumbnail='https://example.com/track.png';unsafe.push(image);
 f=await fixture([{ok:true,status:200,json:async()=>unsafe}]);
 assert.equal(f.grid.children.length,1);assert.equal(f.grid.children[0].children[0].children.length,0);
 for(const response of [new Error('network down'),{ok:false,status:500},{ok:true,status:200,json:async()=>[]},{ok:true,status:200,json:async()=>({unexpected:true})},{ok:true,status:200,json:async()=>{throw new Error('invalid JSON');}}]){
  f=await fixture([response]);assert.equal(f.grid.children[0],f.fallback);assert.equal(f.timers.size,0);
 }
 f=await fixture([{ok:false,status:503},{ok:true,status:200,json:async()=>[good('Ready after warmup','ready')]}]);
 assert.equal(f.grid.children[0],f.fallback);const retry=[...f.timers.values()].find(t=>t.delay===60000);assert(retry);retry.handler();await new Promise(setImmediate);
 assert.equal(f.calls.length,2);assert.equal(f.grid.children[0].children[1].textContent,'Ready after warmup');
 f=await fixture([{ok:false,status:503},{ok:false,status:503}]);[...f.timers.values()].find(t=>t.delay===60000).handler();await new Promise(setImmediate);
 assert.equal([...f.timers.values()].filter(t=>t.delay===60000).length,1);assert.equal(f.grid.children[0],f.fallback); // No repeated retry scheduled.
 f=await fixture([],{present:false});assert.equal(f.calls.length,0);
 f=await fixture([{ok:true,status:200,json:async()=>[good('Loaded','loaded')]}],{loading:true});assert.equal(f.calls.length,0);f.listeners.DOMContentLoaded();await new Promise(setImmediate);assert.equal(f.calls.length,1);
 process.stdout.write('Recent writing client passed: refresh, safe rendering, fallbacks, cold-cache retry, home-only loading.\n');
})().catch(error=>{console.error(error);process.exitCode=1;});
JS;
$process = proc_open([getenv('TADL_TEST_NODE') ?: 'node', '-e', $js], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
fwrite($pipes[0], file_get_contents(dirname(__DIR__).'/assets/pawtucket/js/recent-writing.js')); fclose($pipes[0]);
$output = stream_get_contents($pipes[1]); fclose($pipes[1]);
$error = stream_get_contents($pipes[2]); fclose($pipes[2]);
if (proc_close($process) !== 0) { throw new RuntimeException($error.$output); }
echo "Home template passed: fallback cards and exact View More filters.\n".$output;
