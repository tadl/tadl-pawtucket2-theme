<?php
/** Actual bounded discovery SQL and native permission helpers over synthetic SQLite records. */
ob_start(); require __DIR__.'/media_preferences_test.php'; ob_end_clean();
require dirname(__DIR__).'/helpers/document_text_disclosure.php';
Db::$pdo->exec('ALTER TABLE ca_object_representations ADD COLUMN media_content TEXT');
Db::$pdo->exec('CREATE TABLE ca_attributes (attribute_id INTEGER PRIMARY KEY, table_num INTEGER, row_id INTEGER)');
Db::$pdo->exec('CREATE TABLE ca_attribute_values (attribute_id INTEGER, element_id INTEGER, value_longtext1 TEXT)');
Db::$pdo->exec('CREATE TABLE ca_metadata_elements (element_id INTEGER, element_code TEXT)');
class DisclosureObject {
 function getRepresentations(...$args) { throw new RuntimeException('Presence checks must not load full representation inventories.'); }
 function getPrimaryKey() { return 888001; }
 function tableName() { return 'ca_objects'; }
 function tableNum() { return 57; }
 function get($key) { return $key === 'access' ? 1 : 0; }
 function isReadable($request, $bundle = null) { return caGetBundleAccessLevel('ca_objects', $bundle) >= 1; }
 function hasElement($bundle) { return true; }
 function getDb() { return new Db(); }
}
testObject(888001); testRepresentation(888001); testRelation(888001, 888001);
$update = Db::$pdo->prepare('UPDATE ca_object_representations SET mimetype = ?, media_content = ?, access = ? WHERE representation_id = 888001');
$update->execute(['application/pdf', str_repeat('Synthetic OCR ', 100000), 1]);
$object = new DisclosureObject(); $request = testMediaRequest('all'); $before = Db::$mediaRows;
testAssert(tadlObjectDocumentTextAvailable($request, $object), 'A readable PDF with extracted text must offer the disclosure.');
testAssert(Db::$mediaRows === $before, 'Document presence must not fetch media blobs.');
$update->execute(['application/pdf', " \n\t", 1]);
testAssert(!tadlObjectDocumentTextAvailable($request, $object), 'Whitespace-only sources must omit the disclosure.');
Db::$pdo->exec("INSERT INTO ca_metadata_elements VALUES (1,'transcription'); INSERT INTO ca_attributes VALUES (1,57,888001); INSERT INTO ca_attribute_values VALUES (1,1,'<p>Synthetic imported text</p>')");
testAssert(tadlObjectDocumentTextAvailable($request, $object), 'Readable imported text must still qualify through a readable PDF.');
$update->execute(['application/pdf', '', 0]);
testAssert(!tadlObjectDocumentTextAvailable($request, $object), 'A private paired PDF must not enable imported text.');
$update->execute(['application/pdf', '', 1]); $GLOBALS['mediaBundleAccess']['ca_objects:transcription'] = 0;
testAssert(!tadlObjectDocumentTextAvailable($request, $object), 'Denied fallback bundles must be omitted before reading stored values.');
$GLOBALS['mediaBundleAccess'] = [];
Db::$pdo->exec("UPDATE ca_attribute_values SET value_longtext1 = '<div><p>&nbsp;</p></div>'");
testAssert(!tadlObjectDocumentTextAvailable($request, $object), 'Empty short rich text must not create a disclosure.');
echo "Lazy document discovery passed: actual bounded SQL and fresh visibility checks.\n";
