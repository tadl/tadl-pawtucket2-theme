<?php
require dirname(__DIR__).'/helpers/rich_text.php';
$html = '<p style="font-size:8px;color:white"><strong>Heading</strong> <a href="/public" style="text-decoration:none">Public link</a><span style="font-family:bad"> with <em>emphasis</em></span></p>';
$out = tadlPresentationHTML($html);
if (str_contains($out, 'style=') || !str_contains($out, '<a href="/public">Public link</a>') || !str_contains($out, '<strong>Heading</strong>') || !str_contains($out, '<em>emphasis</em>')) { throw new RuntimeException('Rich text must use theme presentation while retaining semantic text and links.'); }
echo "Rich-text presentation passed.\n";

foreach ([
 ["Synthetic first line<br>\ncontinued prose.", "Synthetic first line \ncontinued prose."],
 ['Before<BR />after', 'Before after'],
 ['Before<br title="literal &gt; attribute">after', 'Before after'],
 ["First paragraph<br>\r\n<br />Second paragraph", "First paragraph<br>\r\n<br />Second paragraph"],
 ['First<br>&nbsp;<br>Second', 'First<br>&nbsp;<br>Second'],
 ['<p>First paragraph.</p><p>Second <strong>bold</strong> and <em>italic</em>.</p>', '<p>First paragraph.</p><p>Second <strong>bold</strong> and <em>italic</em>.</p>'],
 ['<a href="/synthetic?value=&lt;br&gt;">Guide</a><br>text', '<a href="/synthetic?value=&lt;br&gt;">Guide</a> text'],
 ['<pre>First<br>Second</pre><br>After', '<pre>First<br>Second</pre> After'],
 ['<code>First<br>Second</code>', '<code>First<br>Second</code>'],
 ['<ul><li>One<br>line<ul><li>Nested<br>line</li></ul>Outer<br>line</li></ul><br>After', '<ul><li>One<br>line<ul><li>Nested<br>line</li></ul>Outer<br>line</li></ul> After'],
 ['<address>Street<br>City</address>', '<address>Street<br>City</address>'],
 ['<table><tr><td>One<br>Two</td></tr></table>', '<table><tr><td>One<br>Two</td></tr></table>'],
 ['<script>const literal = "<br>";</script><!-- <br> --><br>Text', '<script>const literal = "<br>";</script><!-- <br> --> Text'],
 ['<span title="<br>">Literal attribute</span><br>Text', '<span title="<br>">Literal attribute</span> Text'],
 ['Before<br title="<br>">after', 'Before after'],
] as [$input, $expected]) {
 if (tadlReflowProseHTML($input) !== $expected) { throw new RuntimeException('Prose reflow changed a paragraph, structured content or native markup.'); }
 if (tadlReflowProseHTML($expected) !== $expected) { throw new RuntimeException('Prose reflow must be idempotent.'); }
}
echo "Prose reflow passed.\n";
