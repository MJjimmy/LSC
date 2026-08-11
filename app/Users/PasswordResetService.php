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

        $token = bin2hex(random_bytes(32));
        $this->database->prepare(
            'DELETE FROM password_reset_tokens WHERE user_id = ? OR expires_at < NOW()'
        )->execute([(int)$userId]);
        $insert = $this->database->prepare(<<<'SQL'
            INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 60 MINUTE))
            SQL);
        return $insert->execute([(int)$userId, hash('sha256', $token)]) ? $token : null;
    }

    public function reset(string $token, string $newPassword): bool
    {
        try {
            $this->database->beginTransaction();
            $statement = $this->database->prepare(<<<'SQL'
                SELECT reset_id, user_id FROM password_reset_tokens
                WHERE token_hash = ? AND used_at IS NULL AND expires_at >= NOW() FOR UPDATE
                SQL);
            $statement->execute([hash('sha256', $token)]);
            $reset = $statement->fetch();
            if (!$reset) {
                $this->database->rollBack();
                return false;
            }

            $this->database->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
                ->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int)$reset['user_id']]);
            $this->database->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE reset_id = ?')
                ->execute([(int)$reset['reset_id']]);
            $this->database->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            error_log('PasswordResetService::reset failed: ' . $exception->getMessage());
            return false;
        }
    }
}
