<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Auth;
use LSC\Core\Database;
use LSC\Core\RateLimiter;
use LSC\Core\UploadStorage;
use LSC\Files\FileController;
use LSC\Files\FileRepository;
use LSC\Files\FileService;
use LSC\Groups\GroupRepository;

$database = Database::connection();
$groups = new GroupRepository($database);
$files = new FileRepository($database);
$storage = new UploadStorage(LSC_STORAGE . DIRECTORY_SEPARATOR . 'uploads');
(new FileController(
    new Auth($database),
    $files,
    new FileService($files, $groups, $storage),
    $groups,
    new RateLimiter($database)
))->index();
