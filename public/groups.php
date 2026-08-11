<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Auth;
use LSC\Core\Database;
use LSC\Core\UploadStorage;
use LSC\Chat\MessageRepository;
use LSC\Files\FileRepository;
use LSC\Groups\GroupController;
use LSC\Groups\GroupRepository;
use LSC\Groups\GroupService;
use LSC\Invitations\InvitationRepository;
use LSC\Invitations\InvitationService;
use LSC\Notifications\NotificationDispatcher;
use LSC\Notifications\NotificationRepository;
use LSC\Tasks\TaskRepository;

$database = Database::connection();
$groups = new GroupRepository($database);
$notifications = new NotificationRepository($database);
$dispatcher = new NotificationDispatcher($database);
$groupService = new GroupService($database, $groups, $notifications);
$invitations = new InvitationRepository($database);
$controller = new GroupController(
    new Auth($database),
    $groups,
    $groupService,
    new UploadStorage(LSC_STORAGE . DIRECTORY_SEPARATOR . 'uploads'),
    new TaskRepository($database),
    new FileRepository($database),
    new MessageRepository($database),
    new InvitationService($database, $invitations, $groups, $groupService, $notifications, $dispatcher)
);
$controller->index();
