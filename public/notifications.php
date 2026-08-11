<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Auth;
use LSC\Core\Database;
use LSC\Notifications\NotificationController;
use LSC\Notifications\NotificationRepository;

$database = Database::connection();
(new NotificationController(
    new Auth($database),
    new NotificationRepository($database)
))->index();
