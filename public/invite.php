<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use StudySync\Core\Auth;
use StudySync\Core\Database;
use StudySync\Groups\GroupRepository;
use StudySync\Groups\GroupService;
use StudySync\Invitations\InvitationController;
use StudySync\Invitations\InvitationRepository;
use StudySync\Invitations\InvitationService;
use StudySync\Notifications\NotificationRepository;

$database = Database::connection();
$groups = new GroupRepository($database);
$notifications = new NotificationRepository($database);
$groupService = new GroupService($database, $groups, $notifications);
$invitations = new InvitationRepository($database);
(new InvitationController(
    new Auth($database),
    $invitations,
    new InvitationService($database, $invitations, $groups, $groupService, $notifications)
))->show();
