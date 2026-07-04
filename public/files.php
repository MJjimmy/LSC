<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use StudySync\Core\Auth;
use StudySync\Core\Database;
use StudySync\Core\UploadStorage;
use StudySync\Files\FileController;
use StudySync\Files\FileRepository;
use StudySync\Files\FileService;
use StudySync\Groups\GroupRepository;

$database = Database::connection();
$groups = new GroupRepository($database);
$files = new FileRepository($database);
$storage = new UploadStorage(STUDYSYNC_STORAGE . DIRECTORY_SEPARATOR . 'uploads');
(new FileController(
    new Auth($database),
    $files,
    new FileService($files, $groups, $storage),
    $groups
))->index();
