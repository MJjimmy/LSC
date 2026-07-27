<?php

declare(strict_types=1);

namespace LSC\Invitations;

use LSC\Core\Auth;
use LSC\Core\Csrf;

final class InvitationController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly InvitationRepository $invitations,
        private readonly InvitationService $service
    ) {
    }

    public function show(): void
    {
        $token = preg_replace('/[^a-f0-9]/i', '', (string)($_GET['token'] ?? $_POST['token'] ?? ''));
        if (strlen($token) !== 64) {
            http_response_code(404);
            exit('Invitation not found.');
        }
        if (!$this->auth->isLoggedIn()) {
            header('Location: login.php?next=' . rawurlencode('invite.php?token=' . $token));
            exit;
        }
        $this->auth->requireLogin();

        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $result = $this->service->accept($token, (int)$_SESSION['user_id']);
            if ($result['ok']) {
                header('Location: group.php?id=' . $result['group_id']);
                exit;
            }
            $error = $result['error'];
        }

        $invite = $this->invitations->findByToken($token);
        $isAvailable = $invite && $invite['status'] === 'pending' && strtotime($invite['expires_at']) >= time();
        ob_start();
        require LSC_ROOT . '/views/invitations/show.php';
    }
}
