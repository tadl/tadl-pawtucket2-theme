<?php
$bytes = $this->getVar('finding_aid_bytes');
if (!is_string($bytes) || headers_sent()) { return; }
header('Content-Type: application/pdf');
$disposition = $this->getVar('finding_aid_inline') === true ? 'inline' : 'attachment';
header('Content-Disposition: '.$disposition.'; filename="'.$this->getVar('finding_aid_name').'"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
print $bytes;
exit();
