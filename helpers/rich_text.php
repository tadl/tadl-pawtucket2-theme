<?php
/** Normalize native sanitized display markup without changing stored cataloguing data. */
function tadlPresentationHTML($html) {
 $html = (string)$html;
 // Native rich-text reads are already sanitized. Reuse its policy where available.
 if (function_exists('caGetHTMLPurifier') && class_exists('HTMLPurifier_Config')) {
  $purifier = caGetHTMLPurifier();
  $config = HTMLPurifier_Config::inherit($purifier->config);
  $config->set('HTML.ForbiddenAttributes', array_replace((array)$purifier->config->get('HTML.ForbiddenAttributes'), ['style' => true, 'face' => true, 'size' => true, 'color' => true, 'align' => true]));
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

/** Reflow prose's single hard breaks, retaining blank lines and structured HTML. */
function tadlReflowProseHTML($html) {
 $html = (string)$html;
 if (stripos($html, '<br') === false) { return $html; }
 $attributes = '(?:[^>\'\"]+|\'[^\']*\'|\"[^\"]*\")*';
 $break = '<br\b'.$attributes.'>';
 $protected_depth = 0;
 // Tokenize tags, not their attribute strings or script literals. Preserve all
 // other bytes so native links and semantic formatting are not reconstructed.
 return preg_replace_callback('~<!--.*?-->|<(script|style|textarea)\b'.$attributes.'>.*?</\1\s*>|'
  .$break.'(?:(?:\s|\x{00A0}|&nbsp;|&#160;|&#x0*a0;)*'.$break.')*|</?[a-z][a-z0-9:-]*'.$attributes.'>~isu',
  static function ($token) use ($break, &$protected_depth) {
   if (!preg_match('~^<(/?)([a-z][a-z0-9:-]*)\b~i', $token[0], $tag)) { return $token[0]; }
   $name = strtolower($tag[2]);
   if (in_array($name, ['pre', 'code', 'ul', 'ol', 'table', 'address'], true)) {
    $protected_depth = max(0, $protected_depth + ($tag[1] === '/' ? -1 : 1));
   }
   if ($protected_depth || $name !== 'br') { return $token[0]; }
   return preg_match_all('~'.$break.'~isu', $token[0]) > 1 ? $token[0] : ' ';
  }, $html) ?? $html;
}
