<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use StudySync\Core\Auth;
use StudySync\Core\Database;
use StudySync\Dashboard\DashboardRepository;
use StudySync\Users\AdminController;
use StudySync\Users\UserRepository;

$database = Database::connection();
(new AdminController(
    new Auth($database),
    new UserRepository($database),
    new DashboardRepository($database)
))->index();
