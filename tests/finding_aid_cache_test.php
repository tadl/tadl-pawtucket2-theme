<?php
require dirname(__DIR__).'/helpers/finding_aid.php';
class ExternalCache {
 static array $values = []; static bool $fail = false;
 static function fetch($key, $namespace) { if (self::$fail) { throw new RuntimeException('Synthetic cache failure'); } return self::$values[$namespace][$key] ?? false; }
 static function save($key, $value, $namespace, $ttl) { if ($ttl <= 0 || $ttl > 86400) { throw new RuntimeException('Invalid lifetime'); } self::$values[$namespace][$key] = $value; }
}
$count = 0;
$generate = static function () use (&$count) { $count++; return '%PDF-1.4'.str_repeat('Synthetic finding aid ', 100).'%%EOF'; };
$data = ['generated' => 'Synthetic date', 'fields' => ['description' => 'Public metadata'], 'collections' => [42 => ['title' => 'Synthetic folder']]];
$first = tadlFindingAidCachedPDF($data, 'renderer-1', $generate);
tadlFindingAidCachedPDF(array_replace($data, ['generated' => 'Later date']), 'renderer-1', $generate);
if ($count !== 1 || !$first) { throw new RuntimeException('Unchanged descriptions must reuse valid bytes.'); }
tadlFindingAidCachedPDF(array_replace($data, ['fields' => []]), 'renderer-1', $generate);
tadlFindingAidCachedPDF($data, 'renderer-2', $generate);
if ($count !== 3) { throw new RuntimeException('Changed access-filtered fields or renderer must invalidate bytes.'); }
ExternalCache::$fail = true; tadlFindingAidCachedPDF($data, 'renderer-1', $generate);
if ($count !== 4) { throw new RuntimeException('A failed cache must fall back to rendering.'); }
ExternalCache::$fail = false; tadlFindingAidCachedPDF($data, 'renderer-1', $generate, 0);
if ($count !== 5 || tadlFindingAidCachedPDF([], 'bad-renderer', static fn() => 'invalid') !== null) { throw new RuntimeException('Disabled caches and invalid artifacts must fail safely.'); }
echo "Finding aid artifact reuse passed.\n";
