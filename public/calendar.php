<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use StudySync\Core\Auth;
use StudySync\Core\Database;
use StudySync\Groups\GroupRepository;
use StudySync\Tasks\CalendarController;
use StudySync\Tasks\TaskRepository;

$database = Database::connection();
(new CalendarController(
    new Auth($database),
    new TaskRepository($database),
    new GroupRepository($database)
))->index();
