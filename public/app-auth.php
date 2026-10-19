<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Csrf;
use LSC\Core\Database;
use LSC\Core\RateLimiter;
use LSC\Users\AppSignIn;
use LSC\Users\OAuthProvider;

header('Cache-Control: no-store, max-age=0');
header('Referrer-Policy: no-referrer');
header('Content-Type: text/html; charset=UTF-8');
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$error = 'This app sign-in request is invalid or expired. Please start again.';
$status = 400;
try {
    $database = Database::connection();
    $service = new AppSignIn($database);
    $action = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';
    $method = $_SERVER['REQUEST_METHOD'];
    $limiter = new RateLimiter($database);
    $key = 'app-auth:' . ($action === 'exchange' ? 'exchange:' : 'browser:') . RateLimiter::clientIp();
    if ($limiter->tooManyAttempts($key, $action === 'exchange' ? 30 : 120)) {
        $status = 429;
        throw new RuntimeException('Rate limited');
    }
    // Count all attempts, including successful exchanges; never trust forwarded IP headers by default.
    $limiter->hit($key, 600);
    if ($action === 'start' && $method === 'GET') {
        // The originating form document must allow the authorization redirect too.
        OAuthProvider::allowAuthorizationRedirect();
        $intent = $service->createIntent($_GET['challenge'] ?? null, $_GET['state'] ?? null);
        ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="no-referrer"><title>Continue to Google — LSC</title></head>
<body><main><h1>Sign in to the LSC Android app</h1>
<p>Continue to Google to choose the account you want to use in LSC. You will return to the app after signing in.</p>
<form method="post" action="app-auth.php?action=start">
<input type="hidden" name="csrf_token" value="<?= $escape(Csrf::token()) ?>">
<input type="hidden" name="intent_id" value="<?= $escape($intent['intent_id']) ?>">
<button type="submit">Continue with Google</button>
</form></main></body></html>
        <?php
        exit;
    }
    if ($action === 'start' && $method === 'POST') {
        // Explicit confirmation must belong to both this session and its current private intent.
        $token = $_POST['csrf_token'] ?? null;
        $expected = $_SESSION['csrf_token'] ?? null;
        if (!is_string($token) || !is_string($expected) || $expected === '' || !hash_equals($expected, $token)) {
            $status = 403;
            throw new RuntimeException('Invalid confirmation');
        }
        $url = $service->beginGoogle($_POST['intent_id'] ?? null);
        OAuthProvider::allowAuthorizationRedirect();
        header('Location: ' . $url, true, 303);
        exit;
    }
    if ($action === 'complete' && $method === 'GET') {
        $return = $service->complete();
        $link = 'lscapp://auth?' . http_build_query($return, '', '&', PHP_QUERY_RFC3986);
        ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="no-referrer"><title>Return to LSC</title></head>
<body><main><h1>Google sign-in complete</h1><p>Return to the app to finish signing in. This link expires in two minutes and works once.</p>
<a href="<?= $escape($link) ?>" rel="noreferrer">Return to LSC</a></main></body></html>
        <?php
        exit;
    }
    if ($action === 'exchange' && $method === 'POST') {
        // Android WebView.postUrl submits application/x-www-form-urlencoded data.
        // No browser-session CSRF: this fresh WebView authenticates using code + private verifier.
        $service->exchange($_POST['code'] ?? null, $_POST['verifier'] ?? null);
        header('Location: dashboard.php', true, 303);
        exit;
    }
    if (in_array($action, ['start', 'complete', 'exchange'], true)) {
        $status = 405;
        header('Allow: ' . ($action === 'start' ? 'GET, POST' : ($action === 'complete' ? 'GET' : 'POST')));
    }
} catch (Throwable $exception) {
    // Never reflect exception messages, SQL, provider responses, codes or verifiers.
    if (!$exception instanceof RuntimeException || $exception instanceof PDOException) {
        error_log('LSC app sign-in failed: ' . get_class($exception));
    }
}
http_response_code($status);
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="no-referrer"><title>App sign-in help — LSC</title></head>
<body><main><h1>Unable to finish app sign-in</h1><p role="alert"><?= $escape($error) ?></p><p>Return to the LSC app and try Google sign-in again.</p></main></body></html>
