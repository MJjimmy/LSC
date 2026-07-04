<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use StudySync\Core\Auth;
use StudySync\Core\Database;
use StudySync\Core\UploadStorage;
use StudySync\Chat\MessageRepository;
use StudySync\Files\FileRepository;
use StudySync\Groups\GroupController;
use StudySync\Groups\GroupRepository;
use StudySync\Groups\GroupService;
use StudySync\Invitations\InvitationRepository;
use StudySync\Invitations\InvitationService;
use StudySync\Notifications\NotificationRepository;
use StudySync\Tasks\TaskRepository;

$database = Database::connection();
$groups = new GroupRepository($database);
$notifications = new NotificationRepository($database);
$groupService = new GroupService($database, $groups, $notifications);
$invitations = new InvitationRepository($database);
$controller = new GroupController(
    new Auth($database),
    $groups,
    $groupService,
    new UploadStorage(STUDYSYNC_STORAGE . DIRECTORY_SEPARATOR . 'uploads'),
    new TaskRepository($database),
    new FileRepository($database),
    new MessageRepository($database),
    new InvitationService($database, $invitations, $groups, $groupService, $notifications)
);
$controller->index();
