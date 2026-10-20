<?php
require dirname(__DIR__).'/helpers/rich_text.php';
$html = '<p style="font-size:8px;color:white"><strong>Heading</strong> <a href="/public" style="text-decoration:none">Public link</a><span style="font-family:bad"> with <em>emphasis</em></span></p>';
$out = tadlPresentationHTML($html);
if (str_contains($out, 'style=') || !str_contains($out, '<a href="/public">Public link</a>') || !str_contains($out, '<strong>Heading</strong>') || !str_contains($out, '<em>emphasis</em>')) { throw new RuntimeException('Rich text must use theme presentation while retaining semantic text and links.'); }
echo "Rich-text presentation passed.\n";
