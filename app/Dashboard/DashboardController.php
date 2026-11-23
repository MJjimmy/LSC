<?php

declare(strict_types=1);

namespace LSC\Dashboard;

use LSC\Core\Auth;
use LSC\Groups\GroupRepository;
use LSC\Tasks\TaskRepository;

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
        $role = (string)$_SESSION['user_role'];
        $myTasks = $this->tasks->forUser($userId);
        $myGroups = $this->groups->forUser($userId);
        $pendingTasks = array_filter($myTasks, fn(array $task): bool => $task['status'] === 'pending');
        $overdueTasks = array_filter($myTasks, fn(array $task): bool =>
            $task['status'] === 'pending' && strtotime($task['due_date']) < strtotime('today')
        );
        $managedGroups = array_values(array_filter(
            $myGroups,
            static fn(array $group): bool => (int)$group['created_by'] === $userId
        ));
        $visibleTasks = $role === 'lecturer' ? $this->tasks->visibleToUser($userId) : $myTasks;
        $visiblePendingTasks = array_values(array_filter(
            $visibleTasks,
            static fn(array $task): bool => $task['status'] === 'pending'
        ));
        $pendingJoinRequestCount = 0;
        if ($role === 'lecturer') {
            foreach ($managedGroups as $group) {
                $pendingJoinRequestCount += count($this->groups->pendingRequests((int)$group['group_id']));
            }
        }
        $managedMemberCount = array_sum(array_map(
            static fn(array $group): int => (int)$group['member_count'],
            $managedGroups
        ));
        $stats = $role === 'admin' ? $this->dashboard->adminStats() : null;
        ob_start();
        require \LSC_ROOT . '/views/dashboard/index.php';
    }
}
