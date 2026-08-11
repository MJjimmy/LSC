<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Auth;
use LSC\Core\Database;
use LSC\Dashboard\DashboardController;
use LSC\Dashboard\DashboardRepository;
use LSC\Groups\GroupRepository;
use LSC\Tasks\TaskRepository;

$database = Database::connection();
(new DashboardController(
    new Auth($database),
    new TaskRepository($database),
    new GroupRepository($database),
    new DashboardRepository($database)
))->index();
