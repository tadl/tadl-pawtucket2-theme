<?php
// The native dispatcher loads theme controllers first. Guard before loading the
// upstream controller, including its private set loaders, without copying it.
require_once(__CA_MODELS_DIR__.'/ca_sets.php');
require_once(__CA_MODELS_DIR__.'/ca_lists.php');
require_once(__DIR__.'/../helpers/gallery_access.php');
tadlGuardGalleryRequest(AppController::getInstance()->getRequest());
require_once(__CA_APP_DIR__.'/controllers/GalleryController.php');
