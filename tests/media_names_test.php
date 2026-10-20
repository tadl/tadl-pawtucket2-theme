<?php
require dirname(__DIR__).'/helpers/media_names.php';
function _t($text) { return $text; }
$html = '<a href="/item" onclick="nativeCallback()"><img src="/image" alt="import_file_123.tif" title="filename"></a><script>const literal = `<img alt="script stays">`;</script>';
$output = tadlMediaImageNames($html, 'Synthetic & "public" title');
$document = new DOMDocument(); $document->loadHTML($output, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
if ($document->getElementsByTagName('img')->item(0)->getAttribute('alt') !== 'Synthetic & "public" title'
 || !str_contains($output, 'onclick="nativeCallback()"') || !str_contains($output, '`<img alt="script stays">`')
 || str_contains($output, 'import_file_123.tif')) { throw new RuntimeException('Native image names must use record text and preserve scripts/callbacks.'); }
echo "Media image names passed.\n";
