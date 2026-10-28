<?php

declare(strict_types=1);

namespace LSC\Users;

use PDO;
use RuntimeException;
use Throwable;

final class OAuthAccounts
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function resolve(string $provider, array $identity, ?int $linkUserId, ?string $linkFingerprint = null): array
    {
        if ($provider !== 'google') {
            throw new RuntimeException('Unsupported sign-in provider.');
        }
        $this->database->beginTransaction();
        try {
            $linkedUser = null;
            if ($linkUserId !== null) {
                if ($linkFingerprint === null || preg_match('/^[a-f0-9]{64}$/D', $linkFingerprint) !== 1) {
                    throw new RuntimeException('Sign in again before connecting Google.');
                }
                // Serialize attachment with password reset; checks before provider I/O are insufficient.
                $sqlite = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
                if ($sqlite) {
                    $this->database->prepare('UPDATE users SET user_id = user_id WHERE user_id = ?')->execute([$linkUserId]);
                }
                $statement = $this->database->prepare('SELECT * FROM users WHERE user_id = ?' . ($sqlite ? '' : ' FOR UPDATE'));
                $statement->execute([$linkUserId]);
                $linkedUser = $statement->fetch(PDO::FETCH_ASSOC);
                if (!$linkedUser || (int)$linkedUser['is_active'] !== 1
                    || !hash_equals(hash('sha256', $linkedUser['password_hash']), $linkFingerprint)) {
                    throw new RuntimeException('Sign in again before connecting Google.');
                }
            }
            $statement = $this->database->prepare(
                'SELECT u.* FROM oauth_identities o JOIN users u ON u.user_id = o.user_id WHERE o.provider = ? AND o.subject = ? FOR UPDATE'
            );
            $statement->execute([$provider, $identity['subject']]);
            $user = $statement->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                if ($linkUserId !== null && (int)$user['user_id'] !== $linkUserId) {
                    throw new RuntimeException('This Google account is already connected to another LSC account.');
                }
            } elseif ($linkUserId !== null) {
                $user = $linkedUser;
                $statement = $this->database->prepare('SELECT subject FROM oauth_identities WHERE user_id = ? AND provider = ? FOR UPDATE');
                $statement->execute([$linkUserId, $provider]);
                if ($statement->fetchColumn() !== false) {
                    throw new RuntimeException('Your LSC account already has a Google account connected.');
                }
                $this->attach($provider, $identity['subject'], $linkUserId);
            } else {
                // Email is contact information, not evidence of local account ownership.
                if ((new UserRepository($this->database))->findByEmail($identity['email'])) {
                    throw new RuntimeException('An LSC account already uses this email. Sign in with your LSC email and password. If needed, use Forgot password on the sign-in page.');
                }
                $statement = $this->database->prepare(
                    "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'student')"
                );
                $statement->execute([$identity['name'], $identity['email'], password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)]);
                $user = (new UserRepository($this->database))->findByEmail($identity['email']);
                $this->attach($provider, $identity['subject'], (int)$user['user_id']);
            }
            if ((int)$user['is_active'] !== 1) {
                throw new RuntimeException('Your account has been deactivated. Contact an administrator.');
            }
            $this->database->commit();
            return $user;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    private function attach(string $provider, string $subject, int $userId): void
    {
        $this->database->prepare('INSERT INTO oauth_identities (provider, subject, user_id) VALUES (?, ?, ?)')
            ->execute([$provider, $subject, $userId]);
    }
}
