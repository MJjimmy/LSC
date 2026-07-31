<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Auth;
use LSC\Core\Database;
use LSC\Core\UploadStorage;
use LSC\Users\ProfileController;
use LSC\Users\UserRepository;

$database = Database::connection();
(new ProfileController(
    new Auth($database),
    new UserRepository($database),
    new UploadStorage(LSC_STORAGE . DIRECTORY_SEPARATOR . 'uploads')
))->show();
