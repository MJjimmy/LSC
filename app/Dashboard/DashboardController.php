<?php

declare(strict_types=1);

namespace StudySync\Dashboard;

use StudySync\Core\Auth;
use StudySync\Groups\GroupRepository;
use StudySync\Tasks\TaskRepository;

final class DashboardController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly TaskRepository $tasks,
        private readonly GroupRepository $groups,
        private readonly DashboardRepository $dashboard
    ) {
    }

    public function index(): void
    {
        $this->auth->requireLogin();
        $userId = (int)$_SESSION['user_id'];
        $myTasks = $this->tasks->forUser($userId);
        $myGroups = $this->groups->forUser($userId);
        $pendingTasks = array_filter($myTasks, fn(array $task): bool => $task['status'] === 'pending');
        $overdueTasks = array_filter($myTasks, fn(array $task): bool =>
            $task['status'] === 'pending' && strtotime($task['due_date']) < strtotime('today')
        );
        $stats = $_SESSION['user_role'] === 'admin' ? $this->dashboard->adminStats() : null;
        ob_start();
        require STUDYSYNC_ROOT . '/views/dashboard/index.php';
    }
}
