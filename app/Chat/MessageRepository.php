<?php

declare(strict_types=1);

namespace LSC\Chat;

use PDO;

final class MessageRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function forGroup(int $groupId, int $sinceId = 0): array
    {
        $statement = $this->database->prepare(<<<'SQL'
            SELECT m.id, m.user_id, m.message, m.created_at,
                   u.name AS user_name, u.avatar_path AS user_avatar,
                   f.file_id, f.file_name, f.file_type, f.file_size
            FROM messages m
            JOIN users u ON m.user_id = u.user_id
            LEFT JOIN files f ON m.file_id = f.file_id
            WHERE m.group_id = ? AND m.id > ?
            ORDER BY m.created_at ASC
            SQL);
        $statement->execute([$groupId, $sinceId]);
        return $statement->fetchAll();
    }

    public function create(int $groupId, int $userId, string $message, ?int $fileId = null): bool
    {
        $message = trim($message);
        if ($message === '' && !$fileId) {
            return false;
        }
        return $this->database->prepare(
            'INSERT INTO messages (group_id, user_id, message, file_id) VALUES (?, ?, ?, ?)'
        )->execute([$groupId, $userId, $message, $fileId]);
    }

}
