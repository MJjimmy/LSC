<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Auth;
use LSC\Core\Database;
use LSC\Dashboard\DashboardRepository;
use LSC\Users\AdminController;
use LSC\Users\UserRepository;

$database = Database::connection();
(new AdminController(
    new Auth($database),
    new UserRepository($database),
    new DashboardRepository($database)
))->index();
