<?php
require_once(__DIR__.'/../helpers/result_exports.php');
// Guard before loading native browse classes, including direct legacy URLs.
tadlGuardResultExportRequest(AppController::getInstance()->getRequest());
require_once(__CA_APP_DIR__.'/controllers/BrowseController.php');
