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
        if ($userId < 1 || $userId !== (int)($_SESSION['user_id'] ?? 0)
            || !(new Auth($this->database))->isLoggedIn()) {
            throw new RuntimeException(self::ERROR);
        }
        $_SESSION['app_sign_in']['completed_user_id'] = $userId;
        $_SESSION['app_sign_in']['completed_auth_fingerprint'] = $_SESSION['auth_fingerprint'];
    }

    private function sqlite(): bool
    {
        return $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    /** Same user-first lock order as password reset, before reading any proof. */
    private function lockUser(int $userId): array|false
    {
        if ($this->sqlite()) {
            $this->database->prepare('UPDATE users SET user_id = user_id WHERE user_id = ?')->execute([$userId]);
        }
        $statement = $this->database->prepare('SELECT user_id, name, role, avatar_path, is_active, password_hash FROM users WHERE user_id = ?' . ($this->sqlite() ? '' : ' FOR UPDATE'));
        $statement->execute([$userId]);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function complete(): array
    {
        $intent = $this->intent();
        $userId = (int)($intent['completed_user_id'] ?? 0);
        $proof = $intent['completed_auth_fingerprint'] ?? null;
        if ($userId < 1 || $userId !== (int)($_SESSION['user_id'] ?? 0)
            || !is_string($proof) || !is_string($_SESSION['auth_fingerprint'] ?? null)
            || !hash_equals($proof, $_SESSION['auth_fingerprint']) || $this->database->inTransaction()) {
            throw new RuntimeException(self::ERROR);
        }
        try {
            $this->database->beginTransaction();
            $user = $this->lockUser($userId);
            if (!$user || (int)$user['is_active'] !== 1 || !hash_equals(Auth::fingerprint($user['password_hash']), $proof)) {
                throw new RuntimeException(self::ERROR);
            }
            $code = bin2hex(random_bytes(32));
            // Credential-bound PKCE, same existing 64-character field; legacy
            // unbound codes cannot be redeemed after deployment or a reset.
            $challenge = hash_hmac('sha256', $intent['challenge'], $user['password_hash']);
            $this->database->prepare('INSERT INTO app_auth_codes (code_hash, challenge, user_id, expires_at) VALUES (?, ?, ?, ?)')
                ->execute([hash('sha256', $code), $challenge, $userId, time() + 120]);
            $this->database->commit();
        } catch (Throwable) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw new RuntimeException(self::ERROR);
        }
        unset($_SESSION['app_sign_in']);
        return ['code' => $code, 'state' => $intent['state']];
    }

    /** No browser CSRF token: possession of both the opaque code and native verifier authenticates. */
    public function exchange(mixed $code, mixed $verifier): void
    {
        if (!self::hex($code) || !self::hex($verifier)) {
            throw new RuntimeException(self::ERROR);
        }
        $hash = hash('sha256', $code);
        if ($this->database->inTransaction()) throw new RuntimeException(self::ERROR);
        try {
            $lookup = $this->database->prepare('SELECT user_id FROM app_auth_codes WHERE code_hash = ?');
            $lookup->execute([$hash]);
            $userId = (int)$lookup->fetchColumn();
            if ($userId < 1) throw new RuntimeException(self::ERROR);
            $this->database->beginTransaction();
            $user = $this->lockUser($userId);
            $statement = $this->database->prepare('SELECT challenge, expires_at FROM app_auth_codes WHERE code_hash = ? AND user_id = ?' . ($this->sqlite() ? '' : ' FOR UPDATE'));
            $statement->execute([$hash, $userId]);
            $ticket = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$user || !$ticket || (int)$ticket['expires_at'] <= time() || (int)$user['is_active'] !== 1
                || !hash_equals($ticket['challenge'], hash_hmac('sha256', hash('sha256', $verifier), $user['password_hash']))) {
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
            throw new RuntimeException(self::ERROR);
        }
        if ((new Auth($this->database))->login((int)$user['user_id'], $user['name'], $user['role'], $user['avatar_path'], $user['password_hash']) !== true) {
            throw new RuntimeException(self::ERROR);
        }
        unset($_SESSION['app_sign_in'], $_SESSION['oauth_flow']);
        $_SESSION['android_client'] = true;
        $_SESSION['session_started'] = $_SESSION['last_activity'] = time();
    }
}
