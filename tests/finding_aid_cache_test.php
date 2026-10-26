<?php
require dirname(__DIR__).'/helpers/finding_aid.php';
class ExternalCache {
 static array $values = []; static bool $fail = false;
 static function fetch($key, $namespace) { if (self::$fail) { throw new RuntimeException('Synthetic cache failure'); } return self::$values[$namespace][$key] ?? false; }
 static function save($key, $value, $namespace, $ttl) {
  if ($ttl <= 0 || $ttl > 86400) { throw new RuntimeException('Invalid lifetime'); }
  if (self::$fail) { throw new RuntimeException('Synthetic cache failure'); }
  // Match the native file backend: every value must survive JSON serialization.
  self::$values[$namespace][$key] = json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
 }
}
$count = 0;
$generate = static function () use (&$count) { $count++; return "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n".str_repeat('Synthetic finding aid ', 100).'%%EOF'; };
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

ExternalCache::$values = [];
$first = tadlFindingAidCachedPDF($data, 'binary-renderer', $generate);
$cached = tadlFindingAidCachedPDF($data, 'binary-renderer', static function () { throw new RuntimeException('Binary PDF was not cached.'); });
if ($first !== $cached) { throw new RuntimeException('JSON backends must preserve exact binary PDF bytes.'); }
$key = array_key_first(ExternalCache::$values['tadlFindingAidPDF']);
foreach ([
 false,
 $first, // Raw artifacts from an older cache schema cannot be trusted.
 ['schema' => 1, 'pdf' => base64_encode($first)],
 ['schema' => 2, 'pdf' => '%%%invalid%%%'],
 ['schema' => 2, 'pdf' => base64_encode('%PDF-1.4 truncated')],
 ['schema' => 2, 'pdf' => str_repeat('A', 4 * intdiv(8 * 1024 * 1024 + 2, 3) + 4)]
] as $corrupt) {
 ExternalCache::$values['tadlFindingAidPDF'][$key] = $corrupt;
 $before = $count;
 if (tadlFindingAidCachedPDF($data, 'binary-renderer', $generate) !== $first || $count !== $before + 1) {
  throw new RuntimeException('Malformed and oversized artifacts must regenerate safely.');
 }
}
foreach ([
 array_replace($data, ['fields' => ['description' => 'Changed public metadata']]),
 array_replace($data, ['fields' => []]),
 array_replace($data, ['collections' => []]),
 array_replace($data, ['collections' => [42 => ['title' => 'Renamed unit', 'parent_id' => 7]]])
] as $changed) {
 $before = $count;
 tadlFindingAidCachedPDF($changed, 'binary-renderer', $generate);
 if ($count !== $before + 1) { throw new RuntimeException('Current fields, permissions and hierarchy must identify each artifact.'); }
}
ExternalCache::$values = [];
$large = '%PDF-1.4'.str_repeat('x', 8 * 1024 * 1024).'%%EOF';
if (tadlFindingAidCachedPDF($data, 'large-renderer', static fn() => $large) !== $large || ExternalCache::$values) {
 throw new RuntimeException('Large valid artifacts may be served but must not enter the shared cache.');
}
echo "Finding aid binary cache portability and bounds passed.\n";
