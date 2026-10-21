<?php
/** Persist descriptor validity only. Record, relationship, bundle and ACL checks run before this. */
function tadlReadMediaUsability($db, array $ids, array $access, array &$known) {
	$cache = class_exists('ExternalCache') && method_exists('ExternalCache', 'fetch');
	static $decoder = null;
	$decoder ??= hash_file('sha256', __DIR__.'/media_filter_helpers.php').hash_file('sha256', __FILE__);
	$site = defined('__CA_BASE_DIR__') ? __CA_BASE_DIR__ : __DIR__;
	$ids = array_values(array_unique($ids));
	sort($ids, SORT_NUMERIC);
	foreach (array_chunk($ids, 500) as $chunk) {
		foreach ($chunk as $id) { $known[$id] = false; }
		$missing = array_fill_keys($chunk, true);
		$fingerprintsByID = $entries = [];
		if ($cache) {
			$fingerprints = $db->query('SELECT representation_id, SHA2(media, 256) media_fingerprint FROM ca_object_representations WHERE representation_id IN (?) AND deleted = 0 AND access IN (?)', [$chunk, $access]);
			if (!$fingerprints) { throw new RuntimeException('Unable to inspect media identity.'); }
			while ($fingerprints->nextRow()) {
				$fingerprintsByID[(int)$fingerprints->get('representation_id')] = (string)$fingerprints->get('media_fingerprint');
			}
			if (!$fingerprintsByID) { continue; }
			// Cache one bounded map, rather than a network round trip for every representation.
			// Current fingerprints/access are still read on every request; only descriptor validity is reused.
			$key = hash('sha256', $site.':media-usability-batch-v1:'.$decoder.':'.join(',', $chunk));
			try { $stored = ExternalCache::fetch($key, 'tadlMediaUsability'); }
			catch (Throwable $error) { $stored = null; }
			$storedEntries = is_array($stored) && is_array($stored['entries'] ?? null) ? $stored['entries'] : [];
			$missing = array_fill_keys(array_keys($fingerprintsByID), true);
			foreach ($fingerprintsByID as $id => $fingerprint) {
				$entry = $storedEntries[$id] ?? null;
				if (is_array($entry) && ($entry['fingerprint'] ?? null) === $fingerprint && is_bool($entry['usable'] ?? null)) {
					$known[$id] = $entry['usable']; $entries[$id] = $entry; unset($missing[$id]);
				}
			}
		}
		if (!$missing) { continue; }
		$result = $db->query('SELECT representation_id, media FROM ca_object_representations WHERE representation_id IN (?) AND deleted = 0 AND access IN (?)', [array_keys($missing), $access]);
		if (!$result) { throw new RuntimeException('Unable to check related media availability.'); }
		$mediaURL = null; $changed = false;
		while ($result->nextRow()) {
			$id = (int)$result->get('representation_id');
			$known[$id] = tadlRepresentationHasUsableMedia($result, $mediaURL);
			if (isset($fingerprintsByID[$id]) && is_string($raw = $result->get('media')) && hash_equals($fingerprintsByID[$id], hash('sha256', $raw))) {
				$entries[$id] = ['fingerprint' => $fingerprintsByID[$id], 'usable' => $known[$id]];
				$changed = true;
			}
		}
		if ($cache && $changed) {
			try { ExternalCache::save($key, ['entries' => $entries], 'tadlMediaUsability', 300); }
			catch (Throwable $error) { /* Availability of this optimization is optional. */ }
		}
	}
}
