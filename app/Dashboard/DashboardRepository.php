<?php

declare(strict_types=1);

namespace LSC\Dashboard;

use PDO;

final class DashboardRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function adminStats(): array
    {
        return [
            'total_users' => (int)$this->database->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn(),
            'total_groups' => (int)$this->database->query('SELECT COUNT(*) FROM study_groups')->fetchColumn(),
            'total_tasks' => (int)$this->database->query('SELECT COUNT(*) FROM tasks')->fetchColumn(),
            'pending_tasks' => (int)$this->database->query("SELECT COUNT(*) FROM tasks WHERE status = 'pending'")->fetchColumn(),
            'overdue_tasks' => (int)$this->database->query("SELECT COUNT(*) FROM tasks WHERE due_date < CURRENT_DATE AND status = 'pending'")->fetchColumn(),
            'total_files' => (int)$this->database->query('SELECT COUNT(*) FROM files')->fetchColumn(),
        ];
    }

    public function adminOperations(): array
    {
        return [
            'students' => (int)$this->database->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_active = 1")->fetchColumn(),
            'lecturers' => (int)$this->database->query("SELECT COUNT(*) FROM users WHERE role = 'lecturer' AND is_active = 1")->fetchColumn(),
            'inactive_users' => (int)$this->database->query('SELECT COUNT(*) FROM users WHERE is_active = 0')->fetchColumn(),
            'unverified_users' => (int)$this->database->query('SELECT COUNT(*) FROM users WHERE email_verified_at IS NULL')->fetchColumn(),
            'public_groups' => (int)$this->database->query('SELECT COUNT(*) FROM study_groups WHERE is_private = 0')->fetchColumn(),
            'private_groups' => (int)$this->database->query('SELECT COUNT(*) FROM study_groups WHERE is_private = 1')->fetchColumn(),
            'queue_pending' => (int)$this->database->query("SELECT COUNT(*) FROM notification_queue WHERE status = 'pending'")->fetchColumn(),
            'queue_failed' => (int)$this->database->query("SELECT COUNT(*) FROM notification_queue WHERE status = 'failed'")->fetchColumn(),
        ];
    }

    public function recentGroups(int $limit = 12): array
    {
        $limit = max(1, min($limit, 50));
        $statement = $this->database->prepare(<<<'SQL'
            SELECT sg.group_id, sg.group_name, sg.is_private, sg.created_at,
                   u.name AS creator_name,
                   (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = sg.group_id) AS member_count,
                   (SELECT COUNT(*) FROM tasks t WHERE t.group_id = sg.group_id) AS task_count,
                   (SELECT COUNT(*) FROM files f WHERE f.group_id = sg.group_id) AS file_count
            FROM study_groups sg
            JOIN users u ON u.user_id = sg.created_by
            ORDER BY sg.created_at DESC
            LIMIT ?
            SQL);
        $statement->bindValue(1, $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
