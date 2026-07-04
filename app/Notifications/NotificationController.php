<?php

declare(strict_types=1);

namespace StudySync\Notifications;

use StudySync\Core\Auth;
use StudySync\Core\Csrf;

final class NotificationController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly NotificationRepository $notifications
    ) {
    }

    public function index(): void
    {
        $this->auth->requireLogin();
        $userId = (int)$_SESSION['user_id'];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            if (isset($_POST['mark_all_read'])) {
                $this->notifications->markAllRead($userId);
                header('Location: notifications.php');
                exit;
            }
            if (isset($_POST['open_notification_id'])) {
                $link = $this->notifications->markRead((int)$_POST['open_notification_id'], $userId);
                header('Location: ' . $this->auth->safeLocalRedirect($link, 'notifications.php'));
                exit;
            }
        }

        $notifications = $this->notifications->forUser($userId);
        $unreadCount = $this->notifications->unreadCount($userId);
        $iconMap = [
            'group_invite' => 'bi-person-plus-fill',
            'invite_accepted' => 'bi-person-check-fill',
            'join_request' => 'bi-person-raised-hand',
            'group_approved' => 'bi-check-circle-fill',
            'group_declined' => 'bi-info-circle-fill',
            'task' => 'bi-check2-square',
        ];
        ob_start();
        require STUDYSYNC_ROOT . '/views/notifications/index.php';
    }
}
