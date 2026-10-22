<?php
/** Bounded cache operations and fresh access/identity over actual helper SQL. */
$batchIDs = range(800000,801200);
foreach ($batchIDs as $id) { testRepresentation($id); }
$cacheKeysBefore = array_keys(ExternalCache::$values['tadlMediaUsability'] ?? []);
$fetches = ExternalCache::$fetches; $saves = ExternalCache::$saves; $before = Db::$mediaRows;
$known = []; tadlReadMediaUsability(new Db(), $batchIDs, [1], $known);
testAssert(count(array_filter($known)) === 1201 && Db::$mediaRows - $before === 1201, 'Cold batch checks must decode every requested current descriptor.');
testAssert(ExternalCache::$fetches - $fetches === 3 && ExternalCache::$saves - $saves === 3, '1201 representations must use three bounded cache reads/writes, not one per representation.');
$batchKeys = array_values(array_diff(array_keys(ExternalCache::$values['tadlMediaUsability']), $cacheKeysBefore));
testAssert(count($batchKeys) === 3, 'Each batch must have one independent cache entry.');
foreach ($batchKeys as $key) {
    $entries = ExternalCache::$values['tadlMediaUsability'][$key]['entries'];
    testAssert(count($entries) <= 500 && !array_filter($entries, static fn($entry) => array_keys($entry) !== ['fingerprint', 'usable']
        || !is_string($entry['fingerprint']) || !is_bool($entry['usable'])), 'Cache payloads must stay bounded and contain only fingerprints and boolean validity.');
}
$fetches = ExternalCache::$fetches; $saves = ExternalCache::$saves; $before = Db::$mediaRows;
$known = []; tadlReadMediaUsability(new Db(), array_reverse($batchIDs), [1], $known);
testAssert(count(array_filter($known)) === 1201 && Db::$mediaRows === $before, 'Stable batches must reuse validity regardless of input ordering.');
testAssert(ExternalCache::$fetches - $fetches === 3 && ExternalCache::$saves === $saves, 'Warm batches must use three reads and avoid unnecessary cache writes.');
$before = Db::$mediaRows; $fetches = ExternalCache::$fetches;
$known = []; tadlReadMediaUsability(new Db(), [800499,800000,800999,801200], [1], $known);
testAssert(count(array_filter($known)) === 4 && Db::$mediaRows === $before && ExternalCache::$fetches - $fetches === 3,
    'Partially overlapping result sets must reuse existing buckets without decoding descriptors again.');
$known = []; tadlReadMediaUsability(new Db(), [800001], [1], $known);
testAssert($known === [800001=>true] && Db::$mediaRows === $before, 'A subset read must not evict other entries from the same bucket.');

$replace = Db::$pdo->prepare('UPDATE ca_object_representations SET media = ? WHERE representation_id = 800500');
ExternalCache::$clock += 3600;
$before = Db::$mediaRows; $saves = ExternalCache::$saves;
$known = []; tadlReadMediaUsability(new Db(), $batchIDs, [1], $known);
testAssert(count(array_filter($known)) === 1201 && Db::$mediaRows === $before && ExternalCache::$saves === $saves,
    'Unchanged descriptors must remain warm beyond five minutes without extending their expiry.');
$replace->execute([caSerializeForDatabase([])]);
$before = Db::$mediaRows; $saves = ExternalCache::$saves;
$known = []; tadlReadMediaUsability(new Db(), $batchIDs, [1], $known);
testAssert(count(array_filter($known)) === 1200 && !$known[800500] && Db::$mediaRows === $before + 1 && ExternalCache::$saves === $saves + 1,
    'A changed descriptor must decode only that entry and refresh only its batch.');
Db::$pdo->exec('UPDATE ca_object_representations SET access = 0 WHERE representation_id = 800501');
Db::$pdo->exec('UPDATE ca_object_representations SET deleted = 1 WHERE representation_id = 800502');
$known = []; tadlReadMediaUsability(new Db(), $batchIDs, [1], $known);
testAssert(!$known[800501] && !$known[800502] && count(array_filter($known)) === 1198, 'Warm descriptor cache must never retain visibility after access/deletion changes.');
Db::$pdo->exec('UPDATE ca_object_representations SET access = 1 WHERE representation_id = 800501');
Db::$pdo->exec('UPDATE ca_object_representations SET deleted = 0 WHERE representation_id = 800502');
$known = []; tadlReadMediaUsability(new Db(), $batchIDs, [1], $known);
testAssert($known[800501] && $known[800502], 'Restored visibility must be recognized immediately, without waiting for cache expiry.');

ExternalCache::$clock += 86400;
$before = Db::$mediaRows;
$known = []; tadlReadMediaUsability(new Db(), $batchIDs, [1], $known);
testAssert(count(array_filter($known)) === 1200 && Db::$mediaRows === $before + 1201,
    'Expired validity entries must still refresh through the current native decoder.');

foreach ($batchKeys as $key) { ExternalCache::$values['tadlMediaUsability'][$key] = 'Synthetic invalid cache payload'; }
$known = []; tadlReadMediaUsability(new Db(), $batchIDs, [1], $known);
testAssert(count(array_filter($known)) === 1200, 'Invalid cache data must fall back to current native descriptor checks.');
ExternalCache::$failReads = ExternalCache::$failWrites = true;
try {
    $known = []; tadlReadMediaUsability(new Db(), [800000,800500], [1], $known);
    testAssert($known === [800000=>true,800500=>false], 'Cache outages must preserve correct native media filtering.');
} finally { ExternalCache::$failReads = ExternalCache::$failWrites = false; }
