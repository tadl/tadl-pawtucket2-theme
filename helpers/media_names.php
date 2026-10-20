<?php
/** Name image links from their public record, preserving native callbacks and script bytes. */
function tadlMediaImageNames($html, $label) {
 $label = trim(html_entity_decode(strip_tags((string)$label), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
 if ($label === '') { $label = _t('View image'); }
 $label = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
 return preg_replace_callback('~<!--.*?-->|<(script|style)\b(?:[^>\'\"]+|\'[^\']*\'|\"[^\"]*\")*>.*?</\1\s*>|<[a-z][a-z0-9:-]*(?:[^>\'\"]+|\'[^\']*\'|\"[^\"]*\")*>~is', static function ($token) use ($label) {
  if (!preg_match('~^<img(?=[\s/>])~i', $token[0])) { return $token[0]; }
  $tag = preg_replace_callback('~\s+([^\s=/>]+)(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?~', static function ($attribute) {
   return in_array(strtolower($attribute[1]), ['alt', 'title'], true) ? '' : $attribute[0];
  }, $token[0]);
  return preg_replace('~\s*(/?)>$~', ' alt="'.$label.'"$1>', $tag);
 }, (string)$html);
}
