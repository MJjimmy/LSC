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
            'overdue_tasks' => (int)$this->database->query("SELECT COUNT(*) FROM tasks WHERE due_date < CURDATE() AND status = 'pending'")->fetchColumn(),
            'total_files' => (int)$this->database->query('SELECT COUNT(*) FROM files')->fetchColumn(),
        ];
    }
}
