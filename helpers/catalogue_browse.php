<?php
require_once(__CA_LIB_DIR__.'/Browse/ObjectBrowse.php');
require_once(__DIR__.'/record_access.php');
require_once(__DIR__.'/facet_snapshot.php');
require_once(__DIR__.'/result_cache.php');

/** Native browse with input-validated result and facet reuse; no core files are replaced. */
class TadlObjectBrowse extends ObjectBrowse {
    private $tadlRequest;
    private array $tadlFacets = [];
    private array $tadlSnapshots = [];
    private ?TadlResultCache $tadlResultCache = null;
    private ?array $tadlResultIdentity = null;
    private array $tadlOrder = [];

    public function __construct($id = null, $context = '', $request = null) {
        parent::__construct($id, $context);
        $this->tadlRequest = $request ?? ($GLOBALS['g_request'] ?? null);
    }

    public function execute($options = null) {
        $this->tadlFacets = $this->tadlSnapshots = [];
        $this->tadlResultIdentity = null;
        $this->tadlOrder = [];
        $options = (array)$options;
        $this->tadlResultCache = !empty($options['user_id']) || !empty($options['noCache']) || !empty($options['no_cache'])
            ? null : tadlResultCache($this->tadlRequest);
        if (!$this->tadlResultCache) { return parent::execute($options); }
        $this->tadlSnapshots =& $this->tadlResultCache->snapshots;
        $criteria = $this->getCriteria();
        $context = $this->getContext();
        $identity = [$context, $criteria, $this->getTypeRestrictionList(), $this->getSourceRestrictionList(), $this->getResultFilters(), $this->getInfoForFacets(), array_diff_key($options, ['request'=>true])];
        // Full-text searches must still observe the current native search index.
        // Only the native wildcard query (including intrinsic scoped-ID filters) is hydrated.
        if ($criteria !== ['_search'=>['*'=>true]] || !class_exists('BrowseCache')
            || !empty($options['noCache']) || !empty($options['no_cache']) || !empty($options['limitToModifiedOn'])) {
            $ok = parent::execute($options);
        } else {
            $computed = $nativeOK = false;
            $entry = $this->tadlResultCache->remember('browse', $identity, function () use ($options, &$computed, &$nativeOK) {
                $computed = true;
                if (!($nativeOK = parent::execute($options))) { return null; }
                $cache = new BrowseCache($this->getBrowseID());
                if ($cache->getResults() !== parent::getResults()->getPrimaryKeyValues(PHP_INT_MAX)) { return null; }
                // Native cache parameters may also contain rendered facet HTML.
                $parameters = array_intersect_key($cache->getParameters(), array_fill_keys([
                    'criteria','criteria_display_strings','table_num','context','created_on','sort','sort_direction','filterDeaccessionedRecords'
                ], true));
                return ['ids'=>$cache->getResults(), 'parameters'=>$parameters, 'facets'=>$cache->getFacets(),
                    'types'=>$cache->getTypeRestrictions(), 'sources'=>$cache->getSourceRestrictions(),
                    'terms'=>$this->searched_terms, 'description'=>$this->seach_result_desc];
            }, fn($entry) => is_array($entry) && tadlCachedIDsValid($entry['ids'] ?? null)
                && is_array($entry['parameters'] ?? null) && is_array($entry['facets'] ?? null)
                && ($entry['parameters']['criteria'] ?? null) === $criteria
                && (int)($entry['parameters']['table_num'] ?? 0) === (int)$this->getSubject()
                && is_array($entry['types'] ?? null) && is_array($entry['sources'] ?? null)
                && is_array($entry['terms'] ?? null) && is_array($entry['description'] ?? null));
            $ok = $computed && $nativeOK;
            if (!$computed && is_array($entry)) {
                try {
                    // Hydrate through public native APIs so facet chooser requests and
                    // ResultContext keep their ordinary browse-key behavior.
                    $cache = new BrowseCache();
                    foreach ($entry['parameters'] as $name=>$value) { $cache->setParameter($name, $value); }
                    $cache->setResults($entry['ids']);
                    $cache->setTypeRestrictions($entry['types'] ?? null);
                    $cache->setSourceRestrictions($entry['sources'] ?? null);
                    $facets = $entry['facets']; $cache->setFacets($facets);
                    $cache->save();
                    $ok = parent::reload($cache->getCacheKey());
                    // Native keys omit access scope. Another worker can overwrite
                    // that compatibility entry between save and reload.
                    $ok = $ok && parent::getResults()->getPrimaryKeyValues(PHP_INT_MAX) === $entry['ids']
                        && $this->getCriteria() === $criteria;
                    if ($ok) { $this->searched_terms = $entry['terms']; $this->seach_result_desc = $entry['description']; }
                } catch (Throwable $error) { $ok = false; }
                if (!$ok) {
                    $this->setContext($context);
                    $this->removeAllCriteria();
                    foreach ((array)$criteria as $name=>$values) { $this->addCriteria($name, array_keys($values)); }
                    $ok = parent::execute($options);
                }
            }
        }
        if ($ok && $this->tadlResultCache && empty($options['noCache']) && empty($options['no_cache'])) {
            $this->tadlResultIdentity = [$identity, parent::getResults()->getPrimaryKeyValues(PHP_INT_MAX)];
        }
        return $ok;
    }

    public function getResults($options = null) {
        if (!$this->tadlResultCache || $this->tadlResultIdentity === null) { return parent::getResults($options); }
        $options = (array)$options;
        $start = max(0, (int)($options['start'] ?? 0)); $limit = $options['limit'] ?? null;
        unset($options['start'], $options['limit']);
        if (!empty($options['sort'])) { $this->tadlOrder = $options; }
        else { $options = array_merge($this->tadlOrder, $options); }
        $made = null;
        $ids = $this->tadlResultCache->remember('ordered', [$this->tadlResultIdentity, $options], function () use ($options, &$made) {
            $made = parent::getResults($options);
            return $made->getPrimaryKeyValues(PHP_INT_MAX);
        }, 'tadlCachedIDsValid');
        if ($made && !$start && !$limit) { return $made; }
        if ($start || $limit) { $ids = array_slice($ids, $start, $limit === null ? null : max(0, (int)$limit)); }
        $result = new ObjectBrowseResult();
        $result->init(new WLPlugSearchEngineBrowseEngine($ids, [], $this->getSubject()), [], $options);
        return $result;
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
        // Without a complete result revision, reuse only the narrower wildcard snapshot.
        if (!$this->tadlResultCache && $this->getCriteria() !== ['_search'=>['*'=>true]]) {
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
        if ($this->tadlResultCache) {
            $entry = $this->tadlResultCache->remember('facet', [$identity], function () use ($name,$options) {
                unset($options['checkAvailabilityOnly']);
                return ['content'=>parent::getFacetContent($name,$options),
                    'available'=>(bool)parent::getFacetContent($name,array_merge($options,['checkAvailabilityOnly'=>true]))];
            }, 'tadlFacetCacheEntryValid');
            return !empty($options['checkAvailabilityOnly']) ? $entry['available'] : $entry['content'];
        }
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
