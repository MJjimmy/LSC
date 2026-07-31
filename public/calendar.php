<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Auth;
use LSC\Core\Database;
use LSC\Groups\GroupRepository;
use LSC\Tasks\CalendarController;
use LSC\Tasks\TaskRepository;

$database = Database::connection();
(new CalendarController(
    new Auth($database),
    new TaskRepository($database),
    new GroupRepository($database)
))->index();
