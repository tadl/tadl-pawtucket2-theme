<?php
/** Name image links from their public record, preserving native callbacks and script bytes. */
function tadlMediaImageNames($html, $label) {
 $label = trim(html_entity_decode((string)$label, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
 if ($label === '') { $label = _t('View image'); }
 $label = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
 return preg_replace_callback('~<!--.*?-->|<(script|style)\b(?:[^>\'\"]+|\'[^\']*\'|\"[^\"]*\")*>.*?</\1\s*>|<[a-z][a-z0-9:-]*(?:[^>\'\"]+|\'[^\']*\'|\"[^\"]*\")*>~is', static function ($token) use ($label) {
  if (!preg_match('~^<img(?=[\s/>])~i', $token[0])) { return $token[0]; }
  $tag = preg_replace_callback('~\s+([^\s=/>]+)(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?~', static function ($attribute) {
   return in_array(strtolower($attribute[1]), ['alt', 'title'], true) ? '' : $attribute[0];
  }, $token[0]);
  return preg_replace_callback('~\s*(/?)>$~', static fn($end) => ' alt="'.$label.'"'.$end[1].'>', $tag);
 }, (string)$html);
}

/** Tallest photo's ratio, using only dimensions in the already checked slides. */
function tadlMediaPhotoFrameRatio(array $slides): ?float {
 if (count($slides) < 2) { return null; }
 $ratio = null;
 foreach ($slides as $slide) {
  if (trim((string)$slide) === '') { return null; }
  $document = new DOMDocument();
  $errors = libxml_use_internal_errors(true);
  try {
   $document->loadHTML((string)$slide, LIBXML_NONET);
  } finally {
   libxml_clear_errors();
   libxml_use_internal_errors($errors);
  }
  // Mixed players and documents retain their native layout and initialization.
  foreach (['video', 'audio', 'object', 'embed', 'iframe', 'canvas'] as $tag) {
   if ($document->getElementsByTagName($tag)->length) { return null; }
  }
  $images = $document->getElementsByTagName('img');
  if ($images->length !== 1) { return null; }
  $image = $images->item(0);
  $width = $image->getAttribute('width');
  $height = $image->getAttribute('height');
  if (!preg_match('/^[1-9][0-9]*$/D', $width) || !preg_match('/^[1-9][0-9]*$/D', $height)) { return null; }
  $image_ratio = (float)$width / (float)$height;
  if (!is_finite($image_ratio) || $image_ratio <= 0) { return null; }
  $ratio = $ratio === null ? $image_ratio : min($ratio, $image_ratio);
 }
 return $ratio;
}
