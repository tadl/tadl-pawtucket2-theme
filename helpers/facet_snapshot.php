<?php
/** Restrict reuse to native scalar object facets without additional permission rules. */
function tadlFacetSnapshotTables($request, $info, array $options) {
    if (!$request || !method_exists($request, 'isLoggedIn') || $request->isLoggedIn() || !is_array($info)
        || !empty($options['user_id']) || empty($options['checkAccess'])
        || !empty($info['relative_to']) || !empty($info['filter']) || !empty($info['filter_on_interstitial']) || !empty($info['template'])) { return null; }
    foreach ((array)($info['groupings'] ?? []) as $group => $label) {
        if (!in_array($group, ['label','relationship_types','type'], true)) { return null; }
    }
    $type = $info['type'] ?? null;
    $related = $type === 'fieldList' && ($info['field'] ?? null) === 'type_id' ? 'ca_list_items' : ($info['table'] ?? null);
    if (!(($type === 'fieldList' && ($info['field'] ?? null) === 'type_id')
        || ($type === 'authority' && in_array($related, ['ca_entities','ca_places','ca_list_items'], true)))) { return null; }
    foreach (array_unique(['ca_objects','ca_list_items',$related]) as $table) {
        if (tadlHasAdditionalRecordRestrictions($table) || caACLIsEnabled($table)
            || caGetBundleAccessLevel($table, 'preferred_labels') < __CA_BUNDLE_ACCESS_READONLY__) { return null; }
    }
    if (caGetBundleAccessLevel('ca_objects', $type === 'fieldList' ? 'type_id' : $related) < __CA_BUNDLE_ACCESS_READONLY__) { return null; }
    $tables = [
        'ca_objects'=>['object_id','access','deleted','type_id','source_id','parent_id','hier_object_id','hier_left','hier_right','is_deaccessioned'],
        'ca_lists'=>null, 'ca_list_items'=>['item_id','list_id','type_id','parent_id','access','deleted','is_enabled','idno','idno_sort','item_value','rank','hier_left','hier_right'],
        'ca_list_item_labels'=>null, 'ca_locales'=>null,
    ];
    if ($type === 'authority') {
        $relationship = ['ca_entities'=>'ca_objects_x_entities','ca_places'=>'ca_objects_x_places','ca_list_items'=>'ca_objects_x_vocabulary_terms'][$related];
        if (($info['relationship_table'] ?? null) !== $relationship) { return null; }
        $model = Datamodel::getInstanceByTableName($related, true);
        $fields = [$model->primaryKey(),'access','deleted','type_id','source_id','parent_id','hier_left','hier_right','idno','idno_sort'];
        foreach (['HIERARCHY_ID_FLD','HIERARCHY_PARENT_ID_FLD','ID_NUMBERING_ID_FIELD'] as $property) {
            if ($field = $model->getProperty($property)) { $fields[] = $field; }
        }
        foreach ((array)($info['order_by_label_fields'] ?? []) as $field) { if ($model->hasField($field)) { $fields[] = $field; } }
        $tables[$related] = array_unique(array_merge($tables[$related] ?? [], $fields));
        $tables[$model->getLabelTableName()] = null;
        $tables[$relationship] = ['relation_id','object_id',$model->primaryKey(),'type_id'];
        $tables['ca_relationship_types'] = null;
        $tables['ca_relationship_type_labels'] = null;
    }
    return $tables;
}

/** Read actual current inputs, including visibility flags; persist only their digest. */
function tadlFacetSnapshot($db, array $tables, array &$known) {
    $digests = [];
    try {
        foreach ($tables as $table => $wanted) {
            $model = Datamodel::getInstanceByTableName($table, true);
            $fields = [];
            $definitions = $model->getFieldsArray();
            foreach ($wanted ?? array_keys($definitions) as $field) {
                if ($field === 'view_count' || !isset($definitions[$field])) { continue; }
                $definition = $definitions[$field];
                // Date ranges are model bundles backed by two SQL columns.
                if (isset($definition['START'], $definition['END'])) {
                    $fields[] = $definition['START']; $fields[] = $definition['END'];
                } else { $fields[] = $field; }
            }
            $fields = array_values(array_unique($fields));
            sort($fields, SORT_STRING);
            foreach (array_merge([$table], $fields) as $identifier) {
                if (!preg_match('/^[a-z_][a-z0-9_]*$/D', $identifier)) { return null; }
            }
            $identity = $table.':'.join(',', $fields);
            if (!isset($known[$identity])) {
                $hash = hash_init('sha256');
                $rows = $db->query('SELECT '.join(',', array_map(static fn($field) => '`'.$field.'`', $fields)).' FROM '.$table.' ORDER BY '.$model->primaryKey());
                if (!$rows) { return null; }
                while ($rows->nextRow()) { hash_update($hash, serialize($rows->getRow())); }
                $known[$identity] = hash_final($hash);
            }
            $digests[$identity] = $known[$identity];
        }
        ksort($digests, SORT_STRING);
        return hash('sha256', serialize($digests));
    } catch (Throwable $error) { return null; }
}

function tadlFacetSnapshotKey($identity, $snapshot) {
    global $g_ui_locale;
    static $source = null;
    $source ??= hash_file('sha256', __FILE__).hash_file('sha256', __DIR__.'/catalogue_browse.php').hash_file('sha256', __CA_LIB_DIR__.'/Browse/BrowseEngine.php')
        .hash_file('sha256', __CA_APP_DIR__.'/helpers/utilityHelpers.php').hash_file('sha256', __CA_APP_DIR__.'/helpers/displayHelpers.php');
    return hash('sha256', serialize([defined('__CA_BASE_DIR__') ? __CA_BASE_DIR__ : __DIR__, $source, $g_ui_locale,
        caGetUserLocaleRules(), caGetArticlesForLocale($g_ui_locale), array_map('caGetBlankLabelText', ['ca_entities','ca_places','ca_list_items']), $identity, $snapshot]));
}

function tadlEncodeFacetSnapshot(array $content) {
    $json = json_encode($content, JSON_THROW_ON_ERROR);
    if (strlen($json) > 16*1024*1024) { return null; }
    $data = gzcompress($json);
    return strlen($data) <= 2*1024*1024 ? ['schema'=>1,'data'=>base64_encode($data)] : null;
}

function tadlDecodeFacetSnapshot($stored) {
    if (!is_array($stored) || ($stored['schema'] ?? null) !== 1 || !is_string($stored['data'] ?? null)
        || strlen($stored['data']) > 3*1024*1024) { return null; }
    $raw = base64_decode($stored['data'], true);
    if ($raw === false || ($json = @gzuncompress($raw, 16*1024*1024)) === false) { return null; }
    $content = json_decode($json, true);
    return tadlFacetCacheEntryValid($content) ? $content : null;
}

function tadlFacetCacheEntryValid($content): bool {
    if (!is_array($content) || !is_bool($content['available'] ?? null) || !is_array($content['content'] ?? null)) { return false; }
    foreach ($content['content'] as $row) {
        if (!is_array($row) || !is_string($row['label'] ?? null)
            || !(is_int($row['id'] ?? null) || is_string($row['id'] ?? null))) { return false; }
    }
    return true;
}
