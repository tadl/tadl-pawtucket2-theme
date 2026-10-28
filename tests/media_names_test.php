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

$literal = 'Price $1 and a backslash \\ in the title';
$document->loadHTML(tadlMediaImageNames('<img src="/image">', $literal), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
if ($document->getElementsByTagName('img')->item(0)->getAttribute('alt') !== $literal) { throw new RuntimeException('Image labels must not interpret replacement-string syntax.'); }

$landscape = '<div><img src="/synthetic-wide.svg" width="900" height="600"></div><script>nativePlayerCallback()</script>';
$shorter = '<img src="/synthetic-short.svg" width="900" height="540">';
$portrait = '<img src="/synthetic-tall.svg" width="600" height="900">';
foreach ([
 [[$landscape, $shorter], 1.5],
 [[$landscape, $portrait, $shorter], 2 / 3],
 [[$landscape], null],
 [[$landscape, ''], null],
 [[$landscape, '<img src="/missing-dimensions.svg">'], null],
 [[$landscape, '<img width="100%" height="300">'], null],
 [[$landscape, '<img width="0" height="300">'], null],
 [[$landscape, '<img width="600" height="-1">'], null],
 [[$landscape, '<img width="600" height="400"><img width="600" height="400">'], null],
 [[$landscape, '<video poster="/poster.svg"></video>'], null],
 [[$landscape, '<audio controls></audio>'], null],
 [[$landscape, '<object data="/document.pdf"></object>'], null]
] as [$slides, $expected]) {
 if (tadlMediaPhotoFrameRatio($slides) !== $expected) { throw new RuntimeException('Photo frames must use existing image dimensions and leave other media alone.'); }
}
echo "Photo frame dimensions passed.\n";
