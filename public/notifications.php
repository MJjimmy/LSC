<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use StudySync\Core\Auth;
use StudySync\Core\Database;
use StudySync\Notifications\NotificationController;
use StudySync\Notifications\NotificationRepository;

$database = Database::connection();
(new NotificationController(
    new Auth($database),
    new NotificationRepository($database)
))->index();
