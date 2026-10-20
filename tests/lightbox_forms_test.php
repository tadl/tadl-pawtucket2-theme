<?php
/** Render actual forms with punctuation and markup, preserving native field names/CSRF. */
ob_start(); require __DIR__.'/accessibility_test.php'; ob_end_clean();
class SyntheticGroup { function get($key) { return 7; } }
class LightboxFormRequest extends A11yRequest { function getUserID() { return 2; } }
$value = "Researcher's \"notes\" & </textarea><script>window.invalid=true</script>";
foreach ([
 ['Lightbox/form_user_group_html.php', ['user_group' => new SyntheticGroup(), 'name' => $value, 'description' => $value], ['name', 'description']],
 ['Lightbox/form_add_set_item_html.php', ['set' => new ca_sets(), 'display_name' => 'Lightbox', 'description_attribute' => 'description', 'set_name' => $value, 'set_description' => $value], ['name', 'description']],
 ['Lightbox/form_share_set_html.php', ['display_name' => 'Lightbox', 'user_groups' => [], 'share_message' => $value], ['share_message']]
] as [$file, $values, $fields]) {
 $html = (new A11yView($values + ['errors' => $errors], new LightboxFormRequest()))->render($file);
 $xpath = a11yDocument($html);
 foreach ($fields as $name) {
  $node = $xpath->query('//*[@name="'.$name.'"]')->item(0);
  a11yCheck($node !== null, 'Native submitted field must remain present.');
  a11yCheck(($node->tagName === 'textarea' ? $node->textContent : $node->getAttribute('value')) === $value, 'Form values must round-trip punctuation and literal markup.');
 }
 a11yCheck(!str_contains($html, '<script>window.invalid'), 'Literal field markup must not execute.');
 a11yCheck($xpath->query('//input[@name="csrfToken"]')->length === 1, 'Native CSRF token must remain present.');
}
echo "Lightbox form escaping passed.\n";
