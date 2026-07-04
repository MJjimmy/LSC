<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use StudySync\Core\Auth;
use StudySync\Core\Database;
use StudySync\Groups\GroupRepository;
use StudySync\Tasks\TaskController;
use StudySync\Tasks\TaskRepository;
use StudySync\Tasks\TaskService;

$database = Database::connection();
$groups = new GroupRepository($database);
$tasks = new TaskRepository($database);
(new TaskController(
    new Auth($database),
    $tasks,
    new TaskService($tasks, $groups),
    $groups
))->index();
