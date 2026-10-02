<?php
$bytes = $this->getVar('finding_aid_bytes');
if (!is_string($bytes) || headers_sent()) { return; }
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.$this->getVar('finding_aid_name').'"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
print $bytes;
exit();
