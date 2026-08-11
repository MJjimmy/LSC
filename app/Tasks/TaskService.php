<?php

declare(strict_types=1);

namespace LSC\Tasks;

use LSC\Groups\GroupRepository;

final class TaskService
{
    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly GroupRepository $groups
    ) {
    }

    public function createForMember(
        int $groupId,
        int $userId,
        string $title,
        string $description,
        string $dueDate
    ): bool {
        if (
            $title === ''
            || $groupId <= 0
            || strtotime($dueDate) === false
            || strtotime($dueDate) < strtotime('today')
            || !$this->groups->isMember($groupId, $userId)
        ) {
            return false;
        }

        return $this->tasks->create($groupId, $userId, $title, $description, $dueDate);
    }

    public function markDone(int $taskId, int $userId): bool
    {
        $task = $this->tasks->find($taskId);
        return $task && (int)$task['assigned_to'] === $userId
            ? $this->tasks->updateStatus($taskId, 'done')
            : false;
    }

    public function deleteIfAllowed(int $taskId, int $userId, string $role): bool
    {
        $task = $this->tasks->find($taskId);
        if (!$task || ($role !== 'admin' && !$this->groups->isCreator((int)$task['group_id'], $userId))) {
            return false;
        }
        return $this->tasks->delete($taskId);
    }
}
