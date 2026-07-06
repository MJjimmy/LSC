<?php

declare(strict_types=1);

define('STUDYSYNC_ROOT', dirname(__DIR__));
define('STUDYSYNC_STORAGE', STUDYSYNC_ROOT . DIRECTORY_SEPARATOR . 'storage');

spl_autoload_register(static function (string $class): void {
    $prefix = 'StudySync\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $path = STUDYSYNC_ROOT . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

function csrfToken(): string
{
    return \StudySync\Core\Csrf::token();
}

$isProduction = strtolower((string)getenv('APP_ENV')) === 'production';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', (!$isProduction && getenv('APP_DEBUG') === '1') ? '1' : '0');
ini_set('session.use_strict_mode', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_name('studysync_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isProduction || $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    if ($isProduction && $isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
