<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Auth;
use LSC\Core\Database;
use LSC\Groups\GroupRepository;
use LSC\Groups\GroupService;
use LSC\Invitations\InvitationController;
use LSC\Invitations\InvitationRepository;
use LSC\Invitations\InvitationService;
use LSC\Notifications\NotificationRepository;

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
