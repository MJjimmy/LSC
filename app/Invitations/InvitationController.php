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
        header('Referrer-Policy: no-referrer');
        $token = is_string($_POST['token'] ?? $_GET['token'] ?? null) ? ($_POST['token'] ?? $_GET['token']) : '';
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
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
            if (($_POST['action'] ?? '') === 'switch_account') {
                $this->auth->logout();
                header('Location: login.php?next=' . rawurlencode('invite.php?token=' . $token), true, 303);
                exit;
            }
            $result = $this->service->accept($token, (int)$_SESSION['user_id']);
            if ($result['ok']) {
                header('Location: group.php?id=' . $result['group_id'], true, 303);
                exit;
            }
            $error = $result['error'];
        }

        $invite = $this->invitations->findByToken($token);
        $currentEmail = $this->invitations->userEmail((int)$_SESSION['user_id']);
        $correctAccount = $invite && InvitationService::belongsTo($invite, (int)$_SESSION['user_id'], $currentEmail);
        $isAvailable = $invite && $invite['status'] === 'pending' && (bool)$invite['not_expired'];
        ob_start();
        require \LSC_ROOT . '/views/invitations/show.php';
    }
}
