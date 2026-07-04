<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use StudySync\Core\Auth;
use StudySync\Core\Database;
use StudySync\Dashboard\DashboardController;
use StudySync\Dashboard\DashboardRepository;
use StudySync\Groups\GroupRepository;
use StudySync\Tasks\TaskRepository;

$database = Database::connection();
(new DashboardController(
    new Auth($database),
    new TaskRepository($database),
    new GroupRepository($database),
    new DashboardRepository($database)
))->index();
