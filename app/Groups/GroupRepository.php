<?php

declare(strict_types=1);

namespace LSC\Groups;

use PDO;
use Throwable;

final class GroupRepository
{
    private bool $joinRequestsTableChecked = false;

    public function __construct(private readonly PDO $database)
    {
    }

    public function ensureJoinRequestsTable(): void
    {
        if ($this->joinRequestsTableChecked) {
            return;
        }

        $this->database->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS group_join_requests (
                request_id int(11) NOT NULL AUTO_INCREMENT,
                group_id int(11) NOT NULL,
                user_id int(11) NOT NULL,
                requested_at datetime NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (request_id),
                UNIQUE KEY uq_join_request_group_user (group_id, user_id),
                KEY user_id (user_id),
                CONSTRAINT group_join_requests_ibfk_1 FOREIGN KEY (group_id) REFERENCES study_groups (group_id) ON DELETE CASCADE,
                CONSTRAINT group_join_requests_ibfk_2 FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL);

        $this->joinRequestsTableChecked = true;
    }

    public function find(int $groupId): array|false
    {
        $statement = $this->database->prepare('SELECT * FROM study_groups WHERE group_id = ?');
        $statement->execute([$groupId]);
        return $statement->fetch();
    }

    public function forUser(int $userId): array
    {
        $statement = $this->database->prepare(<<<'SQL'
            SELECT sg.*,
                   (SELECT COUNT(*) FROM group_members WHERE group_id = sg.group_id) AS member_count
            FROM study_groups sg
            JOIN group_members gm ON sg.group_id = gm.group_id
            WHERE gm.user_id = ?
            ORDER BY sg.group_name
            SQL);
        $statement->execute([$userId]);
        return $statement->fetchAll();
    }

    public function publicGroupsAvailableTo(int $userId): array
    {
        $this->ensureJoinRequestsTable();
        $statement = $this->database->prepare(<<<'SQL'
            SELECT sg.*,
                   (SELECT COUNT(*) FROM group_members WHERE group_id = sg.group_id) AS member_count,
                   gjr.request_id AS pending_request_id
            FROM study_groups sg
            LEFT JOIN group_join_requests gjr
                   ON gjr.group_id = sg.group_id AND gjr.user_id = ?
            WHERE sg.is_private = 0
              AND sg.group_id NOT IN (SELECT group_id FROM group_members WHERE user_id = ?)
            ORDER BY sg.group_name
            SQL);
        $statement->execute([$userId, $userId]);
        return $statement->fetchAll();
    }

    public function create(string $name, string $description, int $isPrivate, int $createdBy): int|false
    {
        try {
            $this->database->beginTransaction();
            $statement = $this->database->prepare(
                'INSERT INTO study_groups (group_name, description, is_private, created_by) VALUES (?, ?, ?, ?)'
            );
            $statement->execute([$name, $description, $isPrivate, $createdBy]);
            $groupId = (int)$this->database->lastInsertId();
            $this->database->prepare('INSERT INTO group_members (group_id, user_id) VALUES (?, ?)')
                ->execute([$groupId, $createdBy]);
            $this->database->commit();
            return $groupId;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            error_log('GroupRepository::create failed: ' . $exception->getMessage());
            return false;
        }
    }

    public function delete(int $groupId): bool
    {
        return $this->database->prepare('DELETE FROM study_groups WHERE group_id = ?')->execute([$groupId]);
    }

    public function isMember(int $groupId, int $userId): bool
    {
        $statement = $this->database->prepare(
            'SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?'
        );
        $statement->execute([$groupId, $userId]);
        return (bool)$statement->fetch();
    }

    public function isCreator(int $groupId, int $userId): bool
    {
        $statement = $this->database->prepare(
            'SELECT 1 FROM study_groups WHERE group_id = ? AND created_by = ?'
        );
        $statement->execute([$groupId, $userId]);
        return (bool)$statement->fetch();
    }

    public function join(int $groupId, int $userId): bool
    {
        return $this->database->prepare(
            'INSERT IGNORE INTO group_members (group_id, user_id) VALUES (?, ?)'
        )->execute([$groupId, $userId]);
    }

    public function addJoinRequest(int $groupId, int $userId): array
    {
        $this->ensureJoinRequestsTable();
        $statement = $this->database->prepare(
            'INSERT IGNORE INTO group_join_requests (group_id, user_id) VALUES (?, ?)'
        );
        $ok = $statement->execute([$groupId, $userId]);
        return ['ok' => $ok, 'created' => $ok && $statement->rowCount() > 0];
    }

    public function requestNotificationDetails(int $groupId, int $userId): array|false
    {
        $statement = $this->database->prepare(<<<'SQL'
            SELECT sg.group_name, sg.created_by, u.name AS requester_name
            FROM study_groups sg
            JOIN users u ON u.user_id = ?
            WHERE sg.group_id = ?
            SQL);
        $statement->execute([$userId, $groupId]);
        return $statement->fetch();
    }

    public function pendingRequests(int $groupId): array
    {
        $this->ensureJoinRequestsTable();
        $statement = $this->database->prepare(<<<'SQL'
            SELECT gjr.request_id, gjr.group_id, gjr.user_id, gjr.requested_at,
                   u.name, u.email, u.role
            FROM group_join_requests gjr
            JOIN users u ON gjr.user_id = u.user_id
            WHERE gjr.group_id = ?
            ORDER BY gjr.requested_at ASC
            SQL);
        $statement->execute([$groupId]);
        return $statement->fetchAll();
    }

    public function findJoinRequest(int $requestId, bool $forUpdate = false): array|false
    {
        $this->ensureJoinRequestsTable();
        $sql = 'SELECT group_id, user_id FROM group_join_requests WHERE request_id = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $statement = $this->database->prepare($sql);
        $statement->execute([$requestId]);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteJoinRequest(int $requestId): bool
    {
        return $this->database->prepare(
            'DELETE FROM group_join_requests WHERE request_id = ?'
        )->execute([$requestId]);
    }

    public function groupName(int $groupId): string
    {
        $statement = $this->database->prepare('SELECT group_name FROM study_groups WHERE group_id = ?');
        $statement->execute([$groupId]);
        return (string)$statement->fetchColumn();
    }

    public function removeMember(int $groupId, int $userId): bool
    {
        return $this->database->prepare(
            'DELETE FROM group_members WHERE group_id = ? AND user_id = ?'
        )->execute([$groupId, $userId]);
    }

    public function members(int $groupId): array
    {
        $statement = $this->database->prepare(<<<'SQL'
            SELECT u.user_id, u.name, u.email, u.role, gm.joined_at
            FROM group_members gm
            JOIN users u ON gm.user_id = u.user_id
            WHERE gm.group_id = ?
            ORDER BY gm.joined_at ASC
            SQL);
        $statement->execute([$groupId]);
        return $statement->fetchAll();
    }
}
