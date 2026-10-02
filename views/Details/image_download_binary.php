<?php
$download = $this->getVar('image_download');
$stream = fopen($download['path'], 'rb');
if (!$stream || headers_sent()) { if (is_resource($stream)) { fclose($stream); } return; }
header('Content-Type: '.$download['mime']);
header('Content-Disposition: attachment; filename="'.$this->getVar('image_download_name').'"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
set_time_limit(0);
fpassthru($stream);
fclose($stream);
exit();
