<?php

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
