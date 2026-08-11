<?php

declare(strict_types=1);

namespace LSC\Files;

use PDO;

final class FileRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function forGroup(int $groupId): array
    {
        $statement = $this->database->prepare(<<<'SQL'
            SELECT f.*, u.name AS uploaded_by_name
            FROM files f
            JOIN users u ON f.uploaded_by = u.user_id
            WHERE f.group_id = ?
            ORDER BY f.uploaded_at DESC
            SQL);
        $statement->execute([$groupId]);
        return $statement->fetchAll();
    }

    public function create(
        int $groupId,
        int $uploadedBy,
        string $fileName,
        string $filePath,
        string $fileType,
        int $fileSize
    ): int|false {
        $statement = $this->database->prepare(<<<'SQL'
            INSERT INTO files (group_id, uploaded_by, file_name, file_path, file_type, file_size)
            VALUES (?, ?, ?, ?, ?, ?)
            SQL);
        if (!$statement->execute([$groupId, $uploadedBy, $fileName, $filePath, $fileType, $fileSize])) {
            return false;
        }
        return (int)$this->database->lastInsertId();
    }

    public function find(int $fileId): array|false
    {
        $statement = $this->database->prepare(<<<'SQL'
            SELECT f.*, u.name AS uploaded_by_name
            FROM files f
            JOIN users u ON u.user_id = f.uploaded_by
            WHERE f.file_id = ?
            LIMIT 1
            SQL);
        $statement->execute([$fileId]);
        return $statement->fetch();
    }

    public function deleteOwned(int $fileId, int $userId): bool
    {
        return $this->database->prepare(
            'DELETE FROM files WHERE file_id = ? AND uploaded_by = ?'
        )->execute([$fileId, $userId]);
    }
}
