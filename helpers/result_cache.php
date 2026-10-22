<?php
require_once(__DIR__.'/record_access.php');
require_once(__DIR__.'/facet_snapshot.php');
require_once(__DIR__.'/media_identity.php');

/** Shared anonymous result data, validated against current catalogue and permission inputs. */
final class TadlResultCache {
    private array $pending = [];
    private array $locks = [];
    private array $local = [];
    private ?string $scope = null;
    public array $snapshots = [];
    public string $revision;

    public function __construct(private $db, private $request) {
        $this->revision = $this->readRevision();
    }

    /** Native change logs cover metadata writes; live scalar snapshots cover visibility and relationships. */
    private function readRevision(): string {
        $version = $this->db->query('SELECT COUNT(*) change_count, MAX(log_id) last_change FROM ca_change_log');
        if (!$version || !$version->nextRow()) { throw new RuntimeException('Catalogue revision unavailable.'); }
        $stamp = $version->getRow();
        // Count plus maximum also notices transactions committing log IDs out of order.
        $tables = ['ca_objects'=>['object_id','access','deleted','type_id','source_id','parent_id','hier_object_id','hier_left','hier_right','is_deaccessioned'],
            'ca_object_labels'=>null, 'ca_lists'=>null, 'ca_list_item_labels'=>null, 'ca_locales'=>null,
            'ca_relationship_types'=>null, 'ca_relationship_type_labels'=>null];
        foreach (['ca_entities','ca_places','ca_occurrences','ca_collections','ca_list_items'] as $table) {
            $model = Datamodel::getInstanceByTableName($table, true);
            $fields = [$model->primaryKey(),'access','deleted','type_id','source_id','parent_id','hier_left','hier_right','idno','idno_sort','list_id','item_value','is_enabled','rank'];
            foreach (['HIERARCHY_ID_FLD','HIERARCHY_PARENT_ID_FLD'] as $property) {
                if ($field = $model->getProperty($property)) { $fields[] = $field; }
            }
            $tables[$table] = array_unique($fields);
            $tables[$model->getLabelTableName()] = null;
        }
        foreach (['ca_objects_x_entities','ca_objects_x_places','ca_objects_x_occurrences','ca_objects_x_collections','ca_objects_x_vocabulary_terms','ca_objects_x_object_representations'] as $table) {
            $tables[$table] = ['relation_id','object_id','entity_id','place_id','occurrence_id','collection_id','item_id','representation_id','type_id','is_primary','rank'];
        }
        $known = [];
        $scalar = tadlFacetSnapshot($this->db, $tables, $known);
        if ($scalar === null) { throw new RuntimeException('Catalogue inputs unavailable.'); }
        $indexed = tadlIndexedMediaIdentity($this->db);
        $rows = $this->db->query('SELECT representation_id, access, deleted, '.($indexed ? 'tadl_media_fingerprint' : 'SHA2(media,256)').' fingerprint
            FROM ca_object_representations '.($indexed ? 'FORCE INDEX (i_tadl_media_identity)' : '').' ORDER BY representation_id');
        if (!$rows) { throw new RuntimeException('Media revision unavailable.'); }
        $media = hash_init('sha256');
        while ($rows->nextRow()) { hash_update($media, serialize($rows->getRow())); }
        $this->snapshots = $known;
        return hash('sha256', serialize([$stamp, $scalar, hash_final($media)]));
    }

    public function key(string $kind, array $identity): string {
        global $g_ui_locale;
        if ($this->scope !== null) { return hash('sha256', serialize([$this->scope, $kind, $identity])); }
        static $source;
        $source ??= hash('sha256', join('', array_map(static fn($file) => hash_file('sha256', $file), [
            __FILE__, __DIR__.'/catalogue_browse.php', __DIR__.'/media_filter_helpers.php', __DIR__.'/media_facet_counts.php',
            __DIR__.'/facet_snapshot.php', __DIR__.'/record_access.php', __DIR__.'/media_usability.php', __DIR__.'/media_identity.php',
            __CA_LIB_DIR__.'/Browse/ObjectBrowse.php', __CA_LIB_DIR__.'/Browse/BrowseEngine.php', __CA_LIB_DIR__.'/BaseFindEngine.php',
            __CA_LIB_DIR__.'/Search/SearchEngine.php', __CA_APP_DIR__.'/helpers/utilityHelpers.php', __CA_APP_DIR__.'/helpers/displayHelpers.php'
        ])));
        // Configuration is hashed, not copied into the cache payload.
        $configuration = $this->request->config->toJson().Configuration::load('browse.conf')->toJson().Configuration::load('search.conf')->toJson();
        if (defined('__CA_THEME_DIR__')) { $configuration = str_replace(__CA_THEME_DIR__, '@theme@', $configuration); }
        $configuration = hash('sha256', $configuration);
        $this->scope = hash('sha256', serialize([__CA_BASE_DIR__, $source, $configuration, $g_ui_locale, caGetUserLocaleRules(),
            caGetArticlesForLocale($g_ui_locale), array_map('caGetBlankLabelText', ['ca_entities','ca_places','ca_list_items']),
            caGetUserAccessValues($this->request), tadlMediaPreference($this->request), $this->revision]));
        return hash('sha256', serialize([$this->scope, $kind, $identity]));
    }

    private function fetch(string $key) {
        try {
            $stored = ExternalCache::fetch($key, 'tadlResultData');
            if (!is_array($stored) || ($stored['schema'] ?? null) !== 1 || !is_string($stored['data'] ?? null)
                || strlen($stored['data']) > 6*1024*1024) { return null; }
            $bytes = base64_decode($stored['data'], true);
            $json = $bytes === false ? false : @gzuncompress($bytes, 32*1024*1024);
            $value = $json === false ? null : json_decode($json, true);
            return is_array($value) && array_key_exists('value', $value) ? $value : null;
        } catch (Throwable $error) { return null; }
    }

    /** Return scalar arrays through the native cache backend; misses remain bounded and coordinated. */
    public function remember(string $kind, array $identity, callable $compute, callable $valid) {
        try { $key = $this->key($kind, $identity); }
        catch (Throwable $error) { return $compute(); }
        if (array_key_exists($key, $this->local)) { return $this->local[$key]; }
        $entry = $this->fetch($key);
        if ($entry !== null && $valid($entry['value'])) { return $this->local[$key] = $entry['value']; }
        // Named locks coordinate workers without introducing another Redis client or service.
        $lock = 'tadl:'.substr($key, 0, 58);
        try {
            $row = $this->db->query('SELECT GET_LOCK(?, 0) acquired', [$lock]);
            $owned = $row && $row->nextRow() && (int)$row->get('acquired') === 1;
            if ($owned) { $this->locks[$lock] = true; }
            else {
                $deadline = microtime(true) + 2;
                do {
                    usleep(50000);
                    $entry = $this->fetch($key);
                    if ($entry !== null && $valid($entry['value'])) { return $this->local[$key] = $entry['value']; }
                } while (microtime(true) < $deadline);
            }
        } catch (Throwable $error) { /* Cache coordination is optional. */ }
        $value = $compute();
        if ($valid($value)) {
            $this->local[$key] = $value;
            // Publish once at request end, after checking that all inputs stayed unchanged.
            $this->pending[$key] = $value;
        }
        return $value;
    }

    public function flush(): void {
        try {
            if ($this->pending && $this->readRevision() === $this->revision) {
                foreach ($this->pending as $key => $value) {
                    $json = json_encode(['value'=>$value], JSON_THROW_ON_ERROR);
                    if (strlen($json) > 32*1024*1024) { continue; }
                    $compressed = gzcompress($json);
                    if (strlen($compressed) <= 4*1024*1024) {
                        ExternalCache::save($key, ['schema'=>1,'data'=>base64_encode($compressed)], 'tadlResultData', 3600);
                    }
                }
            }
        } catch (Throwable $error) { /* Cache failures never replace a native response. */ }
        finally {
            $this->pending = [];
            foreach ($this->locks as $lock => $unused) {
                try { $this->db->query('SELECT RELEASE_LOCK(?)', [$lock]); } catch (Throwable $error) {}
            }
            $this->locks = [];
        }
    }
}

/** Scope reuse to ordinary anonymous access; privileged and restricted callers keep native execution. */
function tadlResultCache($request): ?TadlResultCache {
    static $requests;
    $requests ??= new WeakMap();
    if (!is_object($request)) { return null; }
    if (isset($requests[$request])) { return $requests[$request]['cache']; }
    $requests[$request] = ['cache'=>null];
    try {
        if (!method_exists($request, 'isLoggedIn') || $request->isLoggedIn() || !method_exists($request, 'getUserID')
            || $request->getUserID() || !isset($request->config) || !method_exists($request->config, 'toJson')
            || $request->config->get('disable_tadl_result_cache')
            || !class_exists('ExternalCache') || !function_exists('caUserIsPrivileged') || caUserIsPrivileged($request)
            || !caGetUserAccessValues($request)) { return null; }
        foreach (['ca_objects','ca_object_representations','ca_entities','ca_places','ca_occurrences','ca_collections','ca_list_items'] as $table) {
            if (tadlHasAdditionalRecordRestrictions($table) || caACLIsEnabled($table)
                || caGetBundleAccessLevel($table, 'preferred_labels') < __CA_BUNDLE_ACCESS_READONLY__) { return null; }
        }
        foreach (['ca_objects'=>['type_id','ca_object_representations','ca_entities','ca_places','ca_occurrences','ca_collections','ca_list_items'],
            'ca_object_representations'=>['media']] as $table=>$bundles) {
            foreach ($bundles as $bundle) { if (caGetBundleAccessLevel($table, $bundle) < __CA_BUNDLE_ACCESS_READONLY__) { return null; } }
        }
        $db = Datamodel::getInstanceByTableName('ca_objects', true)->getDb();
        if ($db->getConfig()->get('db_type') !== 'mysqli') { return null; }
        $cache = new TadlResultCache($db, $request);
        $requests[$request] = ['cache'=>$cache];
        register_shutdown_function([$cache, 'flush']);
        return $cache;
    } catch (Throwable $error) { return null; }
}

function tadlCachedIDsValid($ids): bool {
    if (!is_array($ids) || count($ids) > 200000 || !array_is_list($ids)) { return false; }
    foreach ($ids as $id) { if (!(is_int($id) || (is_string($id) && ctype_digit($id))) || (int)$id <= 0) { return false; } }
    return count(array_unique($ids)) === count($ids);
}
