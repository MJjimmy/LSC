<?php
// config/auth.php
require_once __DIR__ . '/db_connect.php';
// FIX #1: db_connect already starts session with a guard — no second session_start needed here

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }

    global $pdo;
    $stmt = $pdo->prepare('SELECT name, role, avatar_path FROM users WHERE user_id = ? AND is_active = 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        logoutUser();
        header('Location: login.php?deactivated=1');
        exit;
    }

    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_avatar'] = $user['avatar_path'];
}

function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        header('Location: dashboard.php');
        exit;
    }
}

function loginUser(int $userId, string $name, string $role, ?string $avatarPath = null): void {
    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);
    $_SESSION['user_id']   = $userId;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_role'] = $role;
    $_SESSION['user_avatar'] = $avatarPath;
}

function safeLocalRedirect(?string $target, string $fallback = 'dashboard.php'): string {
    if (!$target || preg_match('/^[a-z][a-z0-9+.-]*:/i', $target) || str_starts_with($target, '//')) {
        return $fallback;
    }

    $target = ltrim($target, '/');
    return preg_match('/^[a-zA-Z0-9._?=&%-]+$/', $target) ? $target : $fallback;
}

function logoutUser(): void {
    $_SESSION = [];
    // Clear the session cookie properly
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $p['path'],
            'domain' => $p['domain'],
            'secure' => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}
