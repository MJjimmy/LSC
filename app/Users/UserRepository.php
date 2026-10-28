<?php

declare(strict_types=1);

namespace LSC\Users;

use PDO;
use PDOException;

final class UserRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function findActive(int $userId): array|false
    {
        return $this->findProfile($userId);
    }

    public function findProfile(int $userId, bool $includeInactive = false): array|false
    {
        $statement = $this->database->prepare(<<<'SQL'
            SELECT u.user_id, u.name, u.email, u.role, u.bio, u.institution,
                   u.course, u.avatar_path, u.created_at,
                   u.phone_number, u.notify_email, u.notify_sms, u.is_active,
                   (SELECT COUNT(*) FROM group_members gm WHERE gm.user_id = u.user_id) AS group_count,
                   (SELECT COUNT(*) FROM files f WHERE f.uploaded_by = u.user_id) AS file_count,
                   (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.status = 'done') AS completed_task_count
            FROM users u
            WHERE u.user_id = ? AND (u.is_active = 1 OR ? = 'all')
            LIMIT 1
            SQL);
        $statement->execute([$userId, $includeInactive ? 'all' : 'active']);
        return $statement->fetch();
    }

    public function findByEmail(string $email): array|false
    {
        $statement = $this->database->prepare(<<<'SQL'
            SELECT user_id, name, email, password_hash, role, is_active, avatar_path
            FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1
            SQL);
        $statement->execute([trim($email)]);
        return $statement->fetch();
    }

    public function create(string $name, string $email, string $passwordHash, string $role = 'student'): bool
    {
        try {
            return $this->database->prepare(
                'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
            )->execute([$name, $email, $passwordHash, $role]);
        } catch (PDOException $exception) {
            error_log('UserRepository::create failed: ' . $exception->getMessage());
            return false;
        }
    }

    public function updateProfile(int $userId, string $name, string $institution, string $course, string $bio): bool
    {
        return $this->database->prepare(<<<'SQL'
            UPDATE users SET name = ?, institution = ?, course = ?, bio = ? WHERE user_id = ?
            SQL)->execute([$name, $institution ?: null, $course ?: null, $bio ?: null, $userId]);
    }

    public function updateAvatar(int $userId, ?string $avatarPath): bool
    {
        return $this->database->prepare('UPDATE users SET avatar_path = ? WHERE user_id = ?')
            ->execute([$avatarPath, $userId]);
    }

    public function updateNotificationSettings(int $userId, ?string $phoneNumber, int $notifyEmail, int $notifySms): bool
    {
        return $this->database->prepare(<<<'SQL'
            UPDATE users SET phone_number = ?, notify_email = ?, notify_sms = ? WHERE user_id = ?
            SQL)->execute([$phoneNumber, $notifyEmail, $notifySms, $userId]);
    }

    public function updatePasswordHash(int $userId, string $passwordHash, string $expectedPasswordHash): bool
    {
        $exact = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'password_hash = ? COLLATE BINARY' : 'BINARY password_hash = BINARY ?';
        $statement = $this->database->prepare('UPDATE users SET password_hash = ? WHERE user_id = ? AND is_active = 1 AND ' . $exact);
        $statement->execute([$passwordHash, $userId, $expectedPasswordHash]);
        return $statement->rowCount() === 1;
    }

    public function all(): array
    {
        return $this->database->query(
            'SELECT user_id, name, email, role, is_active, created_at FROM users ORDER BY user_id'
        )->fetchAll();
    }

    public function setActive(int $userId, bool $active): bool
    {
        return $this->database->prepare('UPDATE users SET is_active = ? WHERE user_id = ?')
            ->execute([$active ? 1 : 0, $userId]);
    }
}
