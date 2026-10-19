<?php

declare(strict_types=1);

namespace LSC\Users;

use LSC\Core\Auth;
use PDO;
use RuntimeException;
use Throwable;

/** Browser intent is session-private; only a one-time PKCE-bound code crosses into Android. */
final class AppSignIn
{
    private const ERROR = 'This app sign-in request is invalid or expired. Please start again.';

    public function __construct(private readonly PDO $database)
    {
    }

    private static function hex(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) === 1;
    }

    public function createIntent(mixed $challenge, mixed $state): array
    {
        if (!self::hex($challenge) || !self::hex($state)) {
            throw new RuntimeException(self::ERROR);
        }
        // Replacing an intent invalidates any older callback in this browser session.
        $_SESSION['app_sign_in'] = [
            'intent_id' => bin2hex(random_bytes(32)), 'challenge' => $challenge,
            'state' => $state, 'expires' => time() + 600,
        ];
        return $_SESSION['app_sign_in'];
    }

    private function intent(): array
    {
        $intent = $_SESSION['app_sign_in'] ?? null;
        if (!is_array($intent) || !self::hex($intent['intent_id'] ?? null)
            || !self::hex($intent['challenge'] ?? null) || !self::hex($intent['state'] ?? null)
            || !is_int($intent['expires'] ?? null) || $intent['expires'] <= time()) {
            throw new RuntimeException(self::ERROR);
        }
        return $intent;
    }

    /** Called only after the route verifies the browser-session CSRF token. */
    public function beginGoogle(mixed $intentId): string
    {
        $intent = $this->intent();
        if (!self::hex($intentId) || !hash_equals($intent['intent_id'], $intentId)
            || isset($intent['oauth_state']) || isset($intent['completed_user_id'])) {
            throw new RuntimeException(self::ERROR);
        }
        // Always perform Google authorization, even with an already authenticated browser.
        // Null link id deliberately forbids implicit account linking.
        $url = OAuthProvider::begin('google', 'app-auth.php?action=complete');
        $_SESSION['oauth_flow']['app_intent_id'] = $intent['intent_id'];
        $_SESSION['app_sign_in']['oauth_state'] = $_SESSION['oauth_flow']['state'];
        return $url;
    }

    /** Validate the consumed provider flow BEFORE any provider exchange/account resolution. */
    public function validateCallback(array $flow): void
    {
        $intent = $this->intent();
        if (($flow['provider'] ?? null) !== 'google' || ($flow['link_user_id'] ?? null) !== null
            || ($flow['next'] ?? null) !== 'app-auth.php?action=complete'
            || !self::hex($flow['app_intent_id'] ?? null)
            || !hash_equals($intent['intent_id'], $flow['app_intent_id'])
            || !self::hex($intent['oauth_state'] ?? null) || !self::hex($flow['state'] ?? null)
            || !hash_equals($intent['oauth_state'], $flow['state'])
            || isset($intent['completed_user_id'])) {
            throw new RuntimeException(self::ERROR);
        }
    }

    /** Trusted callback hook: only after Google exchange + OAuthAccounts::resolve + Auth::login. */
    public function completeGoogle(array $flow, int $userId): void
    {
        $this->validateCallback($flow);
        if ($userId < 1 || $userId !== (int)($_SESSION['user_id'] ?? 0)) {
            throw new RuntimeException(self::ERROR);
        }
        $_SESSION['app_sign_in']['completed_user_id'] = $userId;
    }

    public function complete(): array
    {
        $intent = $this->intent();
        $userId = (int)($intent['completed_user_id'] ?? 0);
        if ($userId < 1 || $userId !== (int)($_SESSION['user_id'] ?? 0)) {
            throw new RuntimeException(self::ERROR);
        }
        $statement = $this->database->prepare('SELECT user_id FROM users WHERE user_id = ? AND is_active = 1');
        $statement->execute([$userId]);
        if ($statement->fetchColumn() === false) {
            throw new RuntimeException(self::ERROR);
        }
        unset($_SESSION['app_sign_in']);
        $code = bin2hex(random_bytes(32));
        $this->database->prepare('INSERT INTO app_auth_codes (code_hash, challenge, user_id, expires_at) VALUES (?, ?, ?, ?)')
            ->execute([hash('sha256', $code), $intent['challenge'], $userId, time() + 120]);
        return ['code' => $code, 'state' => $intent['state']];
    }

    /** No browser CSRF token: possession of both the opaque code and native verifier authenticates. */
    public function exchange(mixed $code, mixed $verifier): void
    {
        if (!self::hex($code) || !self::hex($verifier)) {
            throw new RuntimeException(self::ERROR);
        }
        $hash = hash('sha256', $code);
        $sqlite = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $this->database->beginTransaction();
        try {
            if ($sqlite) {
                // SQLite has no FOR UPDATE: acquire its write lock before the lookup.
                $this->database->prepare('UPDATE app_auth_codes SET expires_at = expires_at WHERE code_hash = ?')->execute([$hash]);
            }
            $statement = $this->database->prepare(
                'SELECT c.challenge, c.expires_at, u.user_id, u.name, u.role, u.avatar_path, u.is_active
                 FROM app_auth_codes c JOIN users u ON u.user_id = c.user_id WHERE c.code_hash = ?'
                 . ($sqlite ? '' : ' FOR UPDATE')
            );
            $statement->execute([$hash]);
            $user = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$user || (int)$user['expires_at'] <= time() || (int)$user['is_active'] !== 1
                || !hash_equals($user['challenge'], hash('sha256', $verifier))) {
                throw new RuntimeException(self::ERROR);
            }
            $consume = $this->database->prepare('DELETE FROM app_auth_codes WHERE code_hash = ?');
            $consume->execute([$hash]);
            if ($consume->rowCount() !== 1) {
                throw new RuntimeException(self::ERROR);
            }
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        }
        (new Auth($this->database))->login((int)$user['user_id'], $user['name'], $user['role'], $user['avatar_path']);
        unset($_SESSION['app_sign_in'], $_SESSION['oauth_flow']);
        $_SESSION['android_client'] = true;
        $_SESSION['session_started'] = $_SESSION['last_activity'] = time();
    }
}
