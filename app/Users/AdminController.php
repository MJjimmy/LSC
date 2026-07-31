<?php

declare(strict_types=1);

namespace LSC\Users;

use LSC\Core\Auth;
use LSC\Core\Csrf;
use LSC\Dashboard\DashboardRepository;

final class AdminController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly UserRepository $users,
        private readonly DashboardRepository $dashboard
    ) {
    }

    public function index(): void
    {
        $this->auth->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $target = (int)($_POST['deactivate'] ?? $_POST['activate'] ?? 0);
            if ($target > 0 && $target !== (int)$_SESSION['user_id']) {
                $this->users->setActive($target, isset($_POST['activate']));
            }
            header('Location: admin.php');
            exit;
        }
        $stats = $this->dashboard->adminStats();
        $users = $this->users->all();
        ob_start();
        require \LSC_ROOT . '/views/users/admin.php';
    }
}
