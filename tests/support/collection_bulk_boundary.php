<?php
if (!defined('__CA_BUNDLE_ACCESS_READONLY__')) { define('__CA_BUNDLE_ACCESS_READONLY__', 1); }
/** Native bulk-visibility boundary for existing synthetic hierarchy model fixtures. */
function caGetBundleAccessLevel($table, $bundle) { $GLOBALS['bulkCollectionBundle'] = $bundle; return 1; }
function caGetTypeRestrictionsForUser($table) { return null; }
function caGetSourceRestrictionsForUser($table) { return null; }
function caSourceAccessControlIsEnabled($model) { return false; }
class BulkCollectionBoundary {
 private $bundle;
 function __construct() { $this->bundle = $GLOBALS['bulkCollectionBundle'] ?? null; unset($GLOBALS['bulkCollectionBundle']); }
 function tableName() { return 'ca_collections'; }
 function get($key) { return 0; }
 function getAppConfig() { return new class { function get($key) { return false; } }; }
 function getDb() { return $this; }
 function query($sql, $params) {
  if (!str_contains($sql, 'collection_id IN (?) AND deleted = 0 AND access IN (?)')) { throw new RuntimeException('Unexpected bulk native permission query.'); }
  $ids = [];
  foreach ($params[0] as $id) {
   $record = Datamodel::getInstance('ca_collections', false);
   if ($record->load($id) && !$record->get('deleted') && in_array((int)$record->get('access'), $params[1], true)
    && $record->isReadable($GLOBALS['bulkCollectionRequest'], $this->bundle)
    && (!caACLIsEnabled($record, ['forPawtucket' => true]) || $record->checkACLAccessForUser($GLOBALS['bulkCollectionRequest']->user) >= 1)) { $ids[] = $id; }
  }
  return new class($ids) {
   private $position = -1;
   function __construct(private $ids) {}
   function nextRow() { return ++$this->position < count($this->ids); }
   function get($key) { return $this->ids[$this->position]; }
  };
 }
}
