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
        $operations = $this->dashboard->adminOperations();
        $recentGroups = $this->dashboard->recentGroups();
        $users = $this->users->all();
        $query = trim((string)($_GET['q'] ?? ''));
        $roleFilter = (string)($_GET['role'] ?? 'all');
        $statusFilter = (string)($_GET['status'] ?? 'all');
        $filteredUsers = array_values(array_filter($users, static function (array $user) use ($query, $roleFilter, $statusFilter): bool {
            $matchesQuery = $query === ''
                || stripos((string)$user['name'], $query) !== false
                || stripos((string)$user['email'], $query) !== false;
            $matchesRole = $roleFilter === 'all' || $user['role'] === $roleFilter;
            $matchesStatus = $statusFilter === 'all'
                || ($statusFilter === 'active' && (int)$user['is_active'] === 1)
                || ($statusFilter === 'inactive' && (int)$user['is_active'] === 0);
            return $matchesQuery && $matchesRole && $matchesStatus;
        }));
        ob_start();
        require \LSC_ROOT . '/views/users/admin.php';
    }
}
