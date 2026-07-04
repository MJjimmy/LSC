<?php

declare(strict_types=1);

namespace StudySync\Tasks;

use StudySync\Core\Auth;
use StudySync\Core\Csrf;
use StudySync\Groups\GroupRepository;

final class TaskController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly TaskRepository $tasks,
        private readonly TaskService $service,
        private readonly GroupRepository $groups
    ) {
    }

    public function index(): void
    {
        $this->auth->requireLogin();
        $userId = (int)$_SESSION['user_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            if (isset($_POST['delete_task'])) {
                $this->service->deleteIfAllowed((int)$_POST['delete_task'], $userId, (string)$_SESSION['user_role']);
            } elseif (isset($_POST['mark_done_id'])) {
                $this->service->markDone((int)$_POST['mark_done_id'], $userId);
            } elseif (isset($_POST['create_task'])) {
                $this->service->createForMember(
                    (int)($_POST['group_id'] ?? 0),
                    $userId,
                    trim((string)($_POST['title'] ?? '')),
                    trim((string)($_POST['description'] ?? '')),
                    (string)($_POST['due_date'] ?? '')
                );
            }
            $this->redirectAfterPost();
        }

        $myGroups = $this->groups->forUser($userId);
        $selected_group = isset($_GET['group_id']) && $_GET['group_id'] !== 'all'
            ? (int)$_GET['group_id']
            : 'all';
        $groupIds = array_map(static fn(array $group): int => (int)$group['group_id'], $myGroups);
        if ($selected_group !== 'all' && !in_array($selected_group, $groupIds, true)) {
            $selected_group = 'all';
        }
        $selected_status = (string)($_GET['status'] ?? 'all');
        $tasks = $selected_group === 'all'
            ? $this->tasks->visibleToUser($userId)
            : $this->tasks->forGroup($selected_group);

        if ($selected_status !== 'all') {
            $tasks = array_values(array_filter($tasks, fn(array $task): bool => $task['status'] === $selected_status));
        }

        $pendingCount = count(array_filter($tasks, fn(array $task): bool => $task['status'] === 'pending'));
        $doneCount = count(array_filter($tasks, fn(array $task): bool => $task['status'] === 'done'));
        $overdueCount = count(array_filter(
            $tasks,
            fn(array $task): bool => $task['status'] === 'pending' && strtotime($task['due_date']) < strtotime('today')
        ));

        ob_start();
        require STUDYSYNC_ROOT . '/views/tasks/index.php';
    }

    private function redirectAfterPost(): never
    {
        $group = (string)($_POST['group_id'] ?? 'all');
        $status = (string)($_POST['status'] ?? 'all');
        $target = isset($_POST['mark_done_id'])
            ? 'tasks.php?group_id=' . rawurlencode($group) . '&status=' . rawurlencode($status)
            : 'tasks.php';
        header('Location: ' . $target);
        exit;
    }
}
