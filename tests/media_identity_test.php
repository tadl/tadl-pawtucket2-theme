<?php
require dirname(__DIR__).'/helpers/media_identity.php';
class IdentityConfig { function __construct(private $driver) {} function get($name) { return $this->driver; } }
class IdentityRows {
    private $position = -1;
    function __construct(private array $rows) {}
    function nextRow() { return ++$this->position < count($this->rows); }
    function get($name) { return $this->rows[$this->position][$name] ?? null; }
}
class IdentityDb {
    public $queries = 0;
    function __construct(public array $rows, public string $driver = 'mysqli', public bool $fail = false) {}
    function getConfig() { return new IdentityConfig($this->driver); }
    function query($sql) {
        $this->queries++;
        if ($this->fail) { throw new RuntimeException('Synthetic schema read unavailable'); }
        if (!str_contains($sql,"s.IS_VISIBLE='YES'")) { throw new RuntimeException('Invisible trial indexes must not be used by normal requests.'); }
        return new IdentityRows($this->rows);
    }
}
$rows=[];
foreach (['representation_id','deleted','access','tadl_has_media','tadl_media_fingerprint'] as $name) {
    $rows[]=['column_name'=>$name,'extra'=>str_starts_with($name,'tadl_') ? 'VIRTUAL GENERATED INVISIBLE' : '',
        'expression'=>match($name) { 'tadl_has_media'=>"((`media` is not null) and (`media` <> _utf8mb4\\'\\'))", 'tadl_media_fingerprint'=>'sha2(`media`,256)', default=>'' }];
}
$assertions=0;
$check=function($condition) use (&$assertions) { if(!$condition) { throw new RuntimeException('Optional index detection failed.'); } $assertions++; };
$db=new IdentityDb($rows); $check(tadlIndexedMediaIdentity($db)); $check(tadlIndexedMediaIdentity($db) && $db->queries===1);
foreach (['sqlite','pgsql','mysql'] as $driver) { $db=new IdentityDb($rows,$driver); $check(!tadlIndexedMediaIdentity($db) && $db->queries===0); }
foreach ([[],array_slice($rows,0,4),array_reverse($rows)] as $incomplete) { $check(!tadlIndexedMediaIdentity(new IdentityDb($incomplete))); }
$wrong=$rows; $wrong[4]['expression']='md5(`media`)'; $check(!tadlIndexedMediaIdentity(new IdentityDb($wrong)));
$wrong=$rows; $wrong[3]['expression']='1'; $check(!tadlIndexedMediaIdentity(new IdentityDb($wrong)));
$wrong=$rows; $wrong[4]['extra']=''; $check(!tadlIndexedMediaIdentity(new IdentityDb($wrong)));
$db=new IdentityDb($rows,'mysqli',true); $check(!tadlIndexedMediaIdentity($db)); $check(!tadlIndexedMediaIdentity($db) && $db->queries===1);
echo json_encode(['status'=>'passed','assertions'=>$assertions,'records'=>'synthetic schema only']),"\n";
