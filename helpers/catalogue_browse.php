<?php
require_once(__CA_LIB_DIR__.'/Browse/ObjectBrowse.php');
require_once(__DIR__.'/record_access.php');
require_once(__DIR__.'/facet_snapshot.php');

/** Native browse with input-validated facet reuse; no core files are replaced. */
class TadlObjectBrowse extends ObjectBrowse {
    private $tadlRequest;
    private array $tadlFacets = [];
    private array $tadlSnapshots = [];

    public function __construct($id = null, $context = '', $request = null) {
        parent::__construct($id, $context);
        $this->tadlRequest = $request ?? ($GLOBALS['g_request'] ?? null);
    }

    public function execute($options = null) {
        $this->tadlFacets = $this->tadlSnapshots = [];
        // Result visibility is still calculated natively on every request.
        return parent::execute($options);
    }

    public function getFacet($name, $options = null) {
        // Native shared facet keys omit permission state. This layer validates
        // current inputs instead of relying on their stored contents.
        $content = $this->getFacetContent($name, $options);
        if (!is_array($content)) { return $content; }
        $start = max(0, (int)($options['start'] ?? 0));
        $limit = (int)($options['limit'] ?? 0);
        return $start || $limit > 0 ? array_slice($content, $start, $limit > 0 ? $limit : null) : $content;
    }

    public function getFacetContent($name, $options = null) {
        $options = (array)$options;
        // Filtered and unusual criteria retain their native computation.
        if ($this->getCriteria() !== ['_search'=>['*'=>true]]) {
            return parent::getFacetContent($name, $options);
        }
        $info = $this->getInfoForFacet($name);
        $tables = tadlFacetSnapshotTables($this->tadlRequest, $info, $options);
        if (!$tables || !class_exists('ExternalCache')) { return parent::getFacetContent($name, $options); }
        $fullOptions = $options;
        foreach (['request','checkAvailabilityOnly','start','limit','showAllForNoCriteriaBrowse','expandResultsHierarchically',
            'omitChildRecords','omitChildRecordsForTypes','noCache','dontCheckFacetAvailability'] as $unused) { unset($fullOptions[$unused]); }
        $ids = $this->getResults()->getPrimaryKeyValues();
        if (count($ids) < 128) { return parent::getFacetContent($name, $options); }
        sort($ids, SORT_NUMERIC);
        $identity = hash('sha256', serialize([$name, $info, $fullOptions, $this->getCriteria(), $this->getTypeRestrictionList(), $this->getSourceRestrictionList(), $this->getResultFilters(), $ids]));
        if (!array_key_exists($identity, $this->tadlFacets)) {
            $snapshot = tadlFacetSnapshot($this->opo_db, $tables, $this->tadlSnapshots);
            if ($snapshot === null) { return parent::getFacetContent($name, $options); }
            $key = tadlFacetSnapshotKey($identity, $snapshot);
            $entry = null;
            try { $entry = tadlDecodeFacetSnapshot(ExternalCache::fetch($key, 'tadlFacetSnapshots')); }
            catch (Throwable $error) { /* Native computation remains available. */ }
            if ($entry === null) {
                $nativeOptions = $options;
                unset($nativeOptions['checkAvailabilityOnly']);
                $content = parent::getFacetContent($name, $nativeOptions);
                // Empty groups can still be available natively. Preserve the
                // native boolean rather than deriving it from full content.
                $available = (bool)parent::getFacetContent($name, array_merge($nativeOptions, ['checkAvailabilityOnly'=>true]));
                $entry = ['content'=>$content, 'available'=>$available];
                if (is_array($content)) {
                    // Never publish a value built across a concurrent input change.
                    $fresh = [];
                    if (tadlFacetSnapshot($this->opo_db, $tables, $fresh) === $snapshot) {
                        try {
                            $encoded = tadlEncodeFacetSnapshot($entry);
                            if ($encoded !== null) { ExternalCache::save($key, $encoded, 'tadlFacetSnapshots', 3600); }
                        } catch (Throwable $error) { /* A cache outage cannot hide facets. */ }
                    } else { $this->tadlSnapshots = []; }
                }
            }
            $this->tadlFacets[$identity] = $entry;
        }
        $entry = $this->tadlFacets[$identity];
        return !empty($options['checkAvailabilityOnly']) ? $entry['available'] : $entry['content'];
    }
}
