<?php

declare(strict_types=1);

namespace StudySync\Core;

use PDO;

final class Auth
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }

        $statement = $this->database->prepare(
            'SELECT name, role, avatar_path FROM users WHERE user_id = ? AND is_active = 1'
        );
        $statement->execute([(int)$_SESSION['user_id']]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->logout();
            header('Location: login.php?deactivated=1');
            exit;
        }

        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_avatar'] = $user['avatar_path'];
    }

    public function requireAdmin(): void
    {
        $this->requireLogin();
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            header('Location: dashboard.php');
            exit;
        }
    }

    public function login(int $userId, string $name, string $role, ?string $avatarPath = null): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_role'] = $role;
        $_SESSION['user_avatar'] = $avatarPath;
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
