<?php

declare(strict_types=1);

namespace LSC\Users;

use PDO;
use Throwable;

final class PasswordResetService
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function createToken(string $email): ?string
    {
        $statement = $this->database->prepare(
            'SELECT user_id FROM users WHERE LOWER(email) = LOWER(?) AND is_active = 1 LIMIT 1'
        );
        $statement->execute([trim($email)]);
        $userId = $statement->fetchColumn();
        if (!$userId) {
            return null;
        }

        if ($this->database->inTransaction()) return null;
        try {
            $this->database->beginTransaction();
            if (!$this->lockActiveUser((int)$userId)) {
                $this->database->rollBack();
                return null;
            }
            $token = bin2hex(random_bytes(32));
            $this->database->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([(int)$userId]);
            $expires = $this->sqlite() ? "datetime('now', '+60 minutes')" : 'DATE_ADD(NOW(), INTERVAL 60 MINUTE)';
            $this->database->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ' . $expires . ')')
                ->execute([(int)$userId, hash('sha256', $token)]);
            $this->database->commit();
            return $token;
        } catch (Throwable) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            return null;
        }
    }

    public function revokeToken(string $token): bool
    {
        return $this->database->prepare(
            'DELETE FROM password_reset_tokens WHERE token_hash = ? AND used_at IS NULL'
        )->execute([hash('sha256', $token)]);
    }

    private function sqlite(): bool
    {
        return $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    private function lockActiveUser(int $userId): bool
    {
        if ($this->sqlite()) {
            $this->database->prepare('UPDATE users SET user_id = user_id WHERE user_id = ?')->execute([$userId]);
        }
        $statement = $this->database->prepare('SELECT user_id FROM users WHERE user_id = ? AND is_active = 1' . ($this->sqlite() ? '' : ' FOR UPDATE'));
        $statement->execute([$userId]);
        return $statement->fetchColumn() !== false;
    }

    public function reset(string $token, string $newPassword): bool
    {
        if ($this->database->inTransaction()) return false;
        try {
            // Discovery only. Acceptance is re-read under user-first locks.
            $hash = hash('sha256', $token);
            $lookup = $this->database->prepare('SELECT user_id FROM password_reset_tokens WHERE token_hash = ?');
            $lookup->execute([$hash]);
            $userId = (int)$lookup->fetchColumn();
            if ($userId < 1) return false;
            $replacement = password_hash($newPassword, PASSWORD_DEFAULT);
            $this->database->beginTransaction();
            if (!$this->lockActiveUser($userId)) {
                $this->database->rollBack();
                return false;
            }
            $statement = $this->database->prepare('SELECT reset_id FROM password_reset_tokens WHERE token_hash = ? AND user_id = ? AND used_at IS NULL AND expires_at > CURRENT_TIMESTAMP' . ($this->sqlite() ? '' : ' FOR UPDATE'));
            $statement->execute([$hash, $userId]);
            $resetId = $statement->fetchColumn();
            if ($resetId === false) {
                $this->database->rollBack();
                return false;
            }
            $consume = $this->database->prepare('UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE reset_id = ? AND used_at IS NULL AND expires_at > CURRENT_TIMESTAMP');
            $consume->execute([(int)$resetId]);
            $update = $this->database->prepare('UPDATE users SET password_hash = ? WHERE user_id = ? AND is_active = 1');
            $update->execute([$replacement, $userId]);
            if ($consume->rowCount() !== 1 || $update->rowCount() !== 1) {
                throw new \RuntimeException('Reset refused.');
            }
            $this->database->prepare('DELETE FROM password_reset_tokens WHERE user_id = ? AND reset_id <> ?')->execute([$userId, (int)$resetId]);
            $this->database->prepare('DELETE FROM app_auth_codes WHERE user_id = ?')->execute([$userId]);
            $this->database->commit();
            return true;
        } catch (Throwable) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            error_log('PasswordResetService::reset failed.');
            return false;
        }
    }
}
