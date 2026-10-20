<?php
/** Normalize native sanitized display markup without changing stored cataloguing data. */
function tadlPresentationHTML($html) {
 $html = (string)$html;
 // Native rich-text reads are already sanitized. Reuse its policy where available.
 if (function_exists('caGetHTMLPurifier') && class_exists('HTMLPurifier_Config')) {
  $purifier = caGetHTMLPurifier();
  $config = HTMLPurifier_Config::inherit($purifier->config);
  $config->set('HTML.ForbiddenAttributes', (array)$purifier->config->get('HTML.ForbiddenAttributes') + ['style' => true, 'face' => true, 'size' => true, 'color' => true, 'align' => true]);
  return $purifier->purify($html, $config);
 }
 // Synthetic/native-boundary rendering also preserves byte-for-byte semantic markup.
 return preg_replace_callback('~<!--.*?-->|<(script|style)\b(?:[^>\'\"]+|\'[^\']*\'|\"[^\"]*\")*>.*?</\1\s*>|<[a-z][a-z0-9:-]*(?:[^>\'\"]+|\'[^\']*\'|\"[^\"]*\")*>~is', static function ($token) {
  if (preg_match('~^<style\b~i', $token[0])) { return ''; }
  if (preg_match('~^<(?:script)\b|^<!--~i', $token[0])) { return $token[0]; }
  return preg_replace_callback('~\s+([^\s=/>]+)(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?~', static function ($attribute) {
   return in_array(strtolower($attribute[1]), ['style', 'face', 'size', 'color', 'align'], true) ? '' : $attribute[0];
  }, $token[0]);
 }, $html);
}
