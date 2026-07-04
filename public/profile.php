<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use StudySync\Core\Auth;
use StudySync\Core\Database;
use StudySync\Core\UploadStorage;
use StudySync\Users\ProfileController;
use StudySync\Users\UserRepository;

$database = Database::connection();
(new ProfileController(
    new Auth($database),
    new UserRepository($database),
    new UploadStorage(STUDYSYNC_STORAGE . DIRECTORY_SEPARATOR . 'uploads')
))->show();
