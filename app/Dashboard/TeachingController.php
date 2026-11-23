<?php

declare(strict_types=1);

namespace LSC\Dashboard;

use LSC\Core\Auth;
use LSC\Groups\GroupRepository;
use LSC\Tasks\TaskRepository;

final class TeachingController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly GroupRepository $groups,
        private readonly TaskRepository $tasks
    ) {
    }

    public function index(): void
    {
        $this->auth->requireLogin();
        if (($_SESSION['user_role'] ?? '') !== 'lecturer') {
            http_response_code(403);
            exit('The teaching workspace is available to lecturer accounts.');
        }

        $userId = (int)$_SESSION['user_id'];
        $memberships = $this->groups->forUser($userId);
        $managedGroups = array_values(array_filter(
            $memberships,
            static fn(array $group): bool => (int)$group['created_by'] === $userId
        ));
        $managedGroupIds = array_map(static fn(array $group): int => (int)$group['group_id'], $managedGroups);
        $visibleTasks = $this->tasks->visibleToUser($userId);
        $managedTasks = array_values(array_filter(
            $visibleTasks,
            static fn(array $task): bool => in_array((int)$task['group_id'], $managedGroupIds, true)
        ));

        $pendingRequestCount = 0;
        foreach ($managedGroups as &$group) {
            $group['pending_request_count'] = count($this->groups->pendingRequests((int)$group['group_id']));
            $pendingRequestCount += $group['pending_request_count'];
        }
        unset($group);

        $openTasks = array_values(array_filter($managedTasks, static fn(array $task): bool => $task['status'] === 'pending'));
        $completedTasks = array_values(array_filter($managedTasks, static fn(array $task): bool => $task['status'] === 'done'));
        $overdueTasks = array_values(array_filter(
            $openTasks,
            static fn(array $task): bool => strtotime($task['due_date']) < strtotime('today')
        ));
        $memberCount = array_sum(array_map(
            static fn(array $group): int => (int)$group['member_count'],
            $managedGroups
        ));

        ob_start();
        require \LSC_ROOT . '/views/dashboard/teaching.php';
    }
}
