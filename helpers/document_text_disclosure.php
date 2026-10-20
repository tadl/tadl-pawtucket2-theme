<?php
require_once(__DIR__.'/document_text.php');
require_once(__DIR__.'/record_access.php');

/** Read at most 1024 characters per source to decide whether to offer the disclosure. */
function tadlDocumentTextPreviewAvailable($rows, $metadata = false) {
 while ($rows->nextRow()) {
  $preview = (string)$rows->get('text_preview');
  if (($metadata ? tadlDocumentTextFromMetadata($preview) : tadlDocumentTextNormalize($preview)) !== ''
   || (int)$rows->get('text_length') > strlen($preview)) { return true; }
 }
 return false;
}

/** Scalar discovery avoids loading representation models or complete OCR into a closed page. */
function tadlObjectDocumentTextAvailable($request, $object) {
 if (!$object || !method_exists($object, 'getRepresentations')) { return false; }
 $access = array_map('intval', (array)caGetUserAccessValues($request));
 if (!$access || !tadlDocumentTextReadable($request, $object, $access, 'ca_object_representations')) { return false; }
 $db = $object->getDb();
 $rows = $db->query("SELECT DISTINCT r.representation_id FROM ca_objects_x_object_representations oxr
  INNER JOIN ca_object_representations r ON r.representation_id = oxr.representation_id
  WHERE oxr.object_id = ? AND r.mimetype = 'application/pdf' AND r.deleted = 0 AND r.access IN (?)", [(int)$object->getPrimaryKey(), $access]);
 if (!$rows) { throw new RuntimeException('Unable to inspect document text.'); }
 $pdfs = [];
 while ($rows->nextRow()) { $pdfs[] = (int)$rows->get('representation_id'); }
 $pdfs = tadlReadableIDs($request, 'ca_object_representations', $pdfs, $access, 'media');
 if (!$pdfs) { return false; } // A private paired PDF never enables a TIFF's migrated text.
 if (caGetBundleAccessLevel('ca_object_representations', 'media_content') >= __CA_BUNDLE_ACCESS_READONLY__) {
  foreach (array_chunk($pdfs, 500) as $chunk) {
   $rows = $db->query('SELECT LENGTH(media_content) text_length, SUBSTRING(media_content, 1, 1024) text_preview FROM ca_object_representations WHERE representation_id IN (?) AND deleted = 0 AND access IN (?)', [$chunk, $access]);
   if (!$rows) { throw new RuntimeException('Unable to inspect extracted text.'); }
   if (tadlDocumentTextPreviewAvailable($rows)) { return true; }
  }
 }
 $bundles = [];
 foreach (['transcription', 'pdf_text'] as $bundle) { if ($object->hasElement($bundle) && $object->isReadable($request, $bundle)) { $bundles[] = $bundle; } }
 if (!$bundles) { return false; }
 $rows = $db->query('SELECT LENGTH(v.value_longtext1) text_length, SUBSTRING(v.value_longtext1, 1, 1024) text_preview
  FROM ca_attributes a INNER JOIN ca_attribute_values v ON v.attribute_id = a.attribute_id
  INNER JOIN ca_metadata_elements e ON e.element_id = v.element_id
  WHERE a.table_num = ? AND a.row_id = ? AND e.element_code IN (?)', [$object->tableNum(), (int)$object->getPrimaryKey(), $bundles]);
 if (!$rows) { throw new RuntimeException('Unable to inspect transcribed text.'); }
 return tadlDocumentTextPreviewAvailable($rows, true);
}

function tadlObjectDocumentTextDisclosure($request, $object) {
 if (!tadlObjectDocumentTextAvailable($request, $object)) { return ''; }
 $url = htmlspecialchars(caNavUrl($request, '', 'DocumentText', 'View', ['object_id' => (int)$object->getPrimaryKey()]), ENT_QUOTES, 'UTF-8');
 return '<details class="tadl-document-text" data-document-text-url="'.$url.'"><summary>'.htmlspecialchars(_t('Document text'), ENT_QUOTES, 'UTF-8').'</summary>'
  .'<p class="sr-only" data-document-text-status role="status"></p><div class="tadl-document-text-body" data-document-text-body>'
  .'<p><a href="'.$url.'" rel="nofollow">'.htmlspecialchars(_t('Open document text'), ENT_QUOTES, 'UTF-8').'</a></p></div></details>';
}
