<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Auth;
use LSC\Core\Database;
use LSC\Dashboard\TeachingController;
use LSC\Groups\GroupRepository;
use LSC\Tasks\TaskRepository;

$database = Database::connection();
(new TeachingController(
    new Auth($database),
    new GroupRepository($database),
    new TaskRepository($database)
))->index();
