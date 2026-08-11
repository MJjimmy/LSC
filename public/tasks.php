<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Auth;
use LSC\Core\Database;
use LSC\Groups\GroupRepository;
use LSC\Tasks\TaskController;
use LSC\Tasks\TaskRepository;
use LSC\Tasks\TaskService;

$database = Database::connection();
$groups = new GroupRepository($database);
$tasks = new TaskRepository($database);
(new TaskController(
    new Auth($database),
    $tasks,
    new TaskService($tasks, $groups),
    $groups
))->index();
