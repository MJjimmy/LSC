<?php

declare(strict_types=1);

namespace StudySync\Notifications;

use PDO;

final class NotificationRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function create(int $userId, string $type, string $title, ?string $body = null, ?string $link = null): bool
    {
        $statement = $this->database->prepare(
            'INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)'
        );
        return $statement->execute([$userId, $type, $title, $body, $link]);
    }

    public function forUser(int $userId, int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));
        $statement = $this->database->prepare(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT {$limit}"
        );
        $statement->execute([$userId]);
        return $statement->fetchAll();
    }

    public function unreadCount(int $userId): int
    {
        $statement = $this->database->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0'
        );
        $statement->execute([$userId]);
        return (int)$statement->fetchColumn();
    }

    public function markRead(int $notificationId, int $userId): ?string
    {
        $statement = $this->database->prepare(
            'SELECT link FROM notifications WHERE notification_id = ? AND user_id = ?'
        );
        $statement->execute([$notificationId, $userId]);
        $link = $statement->fetchColumn();
        if ($link === false) {
            return null;
        }

        $this->database->prepare(
            'UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?'
        )->execute([$notificationId, $userId]);

        return $link ?: 'notifications.php';
    }

    public function markAllRead(int $userId): bool
    {
        $statement = $this->database->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        return $statement->execute([$userId]);
    }
}
