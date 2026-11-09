<?php
require_once(__DIR__.'/../helpers/catalogue_scope.php');
tadlGuardCatalogueCompareRequest(AppController::getInstance()->getRequest());
require_once(__CA_APP_DIR__.'/controllers/CompareController.php');
