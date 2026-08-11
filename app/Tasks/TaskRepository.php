<?php

declare(strict_types=1);

namespace LSC\Tasks;

use PDO;

final class TaskRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function find(int $taskId): array|false
    {
        $statement = $this->database->prepare('SELECT * FROM tasks WHERE task_id = ?');
        $statement->execute([$taskId]);
        return $statement->fetch();
    }

    public function forUser(int $userId): array
    {
        $statement = $this->database->prepare(<<<'SQL'
            SELECT t.*, sg.group_name
            FROM tasks t
            JOIN study_groups sg ON t.group_id = sg.group_id
            WHERE t.assigned_to = ?
            ORDER BY t.due_date ASC
            SQL);
        $statement->execute([$userId]);
        return $statement->fetchAll();
    }

    public function forGroup(int $groupId): array
    {
        $statement = $this->database->prepare(<<<'SQL'
            SELECT t.*, u.name AS assigned_to_name, sg.group_name
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.user_id
            LEFT JOIN study_groups sg ON t.group_id = sg.group_id
            WHERE t.group_id = ?
            ORDER BY t.due_date ASC
            SQL);
        $statement->execute([$groupId]);
        return $statement->fetchAll();
    }

    public function visibleToUser(int $userId): array
    {
        $statement = $this->database->prepare(<<<'SQL'
            SELECT t.*, sg.group_name, u.name AS assigned_to_name
            FROM tasks t
            INNER JOIN group_members gm ON gm.group_id = t.group_id
            LEFT JOIN study_groups sg ON sg.group_id = t.group_id
            LEFT JOIN users u ON u.user_id = t.assigned_to
            WHERE gm.user_id = ?
            ORDER BY t.due_date ASC
            SQL);
        $statement->execute([$userId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(int $groupId, int $assignedTo, string $title, string $description, string $dueDate): bool
    {
        $statement = $this->database->prepare(<<<'SQL'
            INSERT INTO tasks (group_id, assigned_to, title, description, due_date, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
            SQL);
        return $statement->execute([$groupId, $assignedTo, $title, $description, $dueDate]);
    }

    public function delete(int $taskId): bool
    {
        return $this->database->prepare('DELETE FROM tasks WHERE task_id = ?')->execute([$taskId]);
    }

    public function updateStatus(int $taskId, string $status): bool
    {
        if (!in_array($status, ['pending', 'done'], true)) {
            return false;
        }
        return $this->database->prepare('UPDATE tasks SET status = ? WHERE task_id = ?')
            ->execute([$status, $taskId]);
    }

    public function forCalendar(array $groupIds, int $month, int $year): array
    {
        if (!$groupIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $statement = $this->database->prepare(<<<SQL
            SELECT t.task_id, t.title, t.due_date, t.status, sg.group_name
            FROM tasks t
            JOIN study_groups sg ON t.group_id = sg.group_id
            WHERE t.group_id IN ({$placeholders})
              AND MONTH(t.due_date) = ?
              AND YEAR(t.due_date) = ?
            ORDER BY t.due_date ASC
            SQL);
        $statement->execute(array_merge($groupIds, [$month, $year]));
        return $statement->fetchAll();
    }
}
