<?php

declare(strict_types=1);

define('LSC_ROOT', dirname(__DIR__));
define('LSC_STORAGE', LSC_ROOT . DIRECTORY_SEPARATOR . 'storage');

/**
 * Minimal, dependency-free .env loader for local development.
 * Production hosts set environment variables natively (Railway, hPanel,
 * systemd), so a missing .env file is not an error. Variables that are
 * already set in the environment always take precedence over .env values.
 */
(static function (string $path): void {
    if (!is_file($path) || !is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name === '' || !preg_match('/^[A-Z][A-Z0-9_]*$/', $name)) {
            continue;
        }
        if (
            strlen($value) >= 2
            && (str_starts_with($value, '"') || str_starts_with($value, "'"))
            && substr($value, -1) === $value[0]
        ) {
            $value = substr($value, 1, -1);
        }
        if (getenv($name) === false) {
            putenv("{$name}={$value}");
        }
    }
})(LSC_ROOT . DIRECTORY_SEPARATOR . '.env');

spl_autoload_register(static function (string $class): void {
    $prefix = 'LSC\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $path = LSC_ROOT . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

function csrfToken(): string
{
    return \LSC\Core\Csrf::token();
}

$isProduction = strtolower((string)getenv('APP_ENV')) === 'production';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', (!$isProduction && getenv('APP_DEBUG') === '1') ? '1' : '0');
ini_set('session.use_strict_mode', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_name('LSC_session');
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
