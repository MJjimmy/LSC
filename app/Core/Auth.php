<?php

declare(strict_types=1);

namespace LSC\Core;

use PDO;

final class Auth
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function isLoggedIn(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $user = $this->activeUser((int)$_SESSION['user_id']);
        $proof = $_SESSION['auth_fingerprint'] ?? null;
        if (!$user || !is_string($proof) || !hash_equals(self::fingerprint($user['password_hash']), $proof)) {
            $this->invalidate();
            return false;
        }
        $this->identity($user);
        return true;
    }

    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }


    }

    public function requireAdmin(): void
    {
        $this->requireLogin();
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            header('Location: dashboard.php');
            exit;
        }
    }

    public function login(int $userId, string $name, string $role, ?string $avatarPath = null, ?string $expectedPasswordHash = null): bool
    {
        $user = $this->activeUser($userId);
        if (!$user || $expectedPasswordHash === null || $expectedPasswordHash === ''
            || !hash_equals($user['password_hash'], $expectedPasswordHash)) {
            $this->invalidate();
            return false;
        }
        session_regenerate_id(true);
        $this->identity($user);
        $_SESSION['auth_fingerprint'] = self::fingerprint($user['password_hash']);
        $_SESSION['session_started'] = $_SESSION['last_activity'] = time();
        return true;
    }

    public static function fingerprint(string $passwordHash): string
    {
        return hash('sha256', $passwordHash);
    }

    private function activeUser(int $userId): array|false
    {
        $statement = $this->database->prepare('SELECT user_id, name, role, avatar_path, password_hash FROM users WHERE user_id = ? AND is_active = 1');
        $statement->execute([$userId]);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    private function identity(array $user): void
    {
        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_avatar'] = $user['avatar_path'];
    }

    private function invalidate(): void
    {
        // Keep an anonymous active session usable for a new CSRF token.
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function safeLocalRedirect(?string $target, string $fallback = 'dashboard.php'): string
    {
        if (!$target || preg_match('/^[a-z][a-z0-9+.-]*:/i', $target) || str_starts_with($target, '//')) {
            return $fallback;
        }

        $target = ltrim($target, '/');
        return preg_match('/^[a-zA-Z0-9._?=&%-]+$/', $target) ? $target : $fallback;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'],
                'domain' => $parameters['domain'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }
}
