<?php
/** Persist descriptor validity only. Record, relationship, bundle and ACL checks run before this. */
function tadlReadMediaUsability($db, array $ids, array $access, array &$known) {
 $keys = []; $fingerprintsByID = [];
 $cache = class_exists('ExternalCache') && method_exists('ExternalCache', 'fetch');
 $decoder = hash_file('sha256', __DIR__.'/media_filter_helpers.php').hash_file('sha256', __FILE__);
 $site = defined('__CA_BASE_DIR__') ? __CA_BASE_DIR__ : __DIR__;
 foreach (array_chunk($ids, 500) as $chunk) {
  $missing = array_fill_keys($chunk, true);
  if ($cache) {
   $fingerprints = $db->query('SELECT representation_id, SHA2(media, 256) media_fingerprint FROM ca_object_representations WHERE representation_id IN (?) AND deleted = 0 AND access IN (?)', [$chunk, $access]);
   if (!$fingerprints) { throw new RuntimeException('Unable to inspect media identity.'); }
   while ($fingerprints->nextRow()) {
    $id = (int)$fingerprints->get('representation_id');
    $fingerprintsByID[$id] = (string)$fingerprints->get('media_fingerprint');
    $keys[$id] = hash('sha256', $site.':'.$decoder.':'.$id.':'.$fingerprints->get('media_fingerprint'));
    try { $value = ExternalCache::fetch($keys[$id], 'tadlMediaUsability'); }
    catch (Throwable $error) { $value = null; }
    if (is_array($value) && is_bool($value['usable'] ?? null)) { $known[$id] = $value['usable']; unset($missing[$id]); }
   }
  }
  if (!$missing) { continue; }
  $result = $db->query('SELECT representation_id, media FROM ca_object_representations WHERE representation_id IN (?) AND deleted = 0 AND access IN (?)', [array_keys($missing), $access]);
  if (!$result) { throw new RuntimeException('Unable to check related media availability.'); }
  foreach ($missing as $id => $_) { $known[$id] = false; }
  $mediaURL = null;
  while ($result->nextRow()) {
   $id = (int)$result->get('representation_id');
   $known[$id] = tadlRepresentationHasUsableMedia($result, $mediaURL);
   if (isset($keys[$id]) && is_string($raw = $result->get('media')) && hash_equals($fingerprintsByID[$id], hash('sha256', $raw))) {
    try { ExternalCache::save($keys[$id], ['usable' => $known[$id]], 'tadlMediaUsability', 300); }
    catch (Throwable $error) { /* Availability of this optimization is optional. */ }
   }
  }
 }
}
