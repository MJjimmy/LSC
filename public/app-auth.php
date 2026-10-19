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
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="no-referrer"><title>Google sign-in — LSC</title></head>
<body>
<form id="google-start" method="post" action="app-auth.php?action=start">
<input type="hidden" name="csrf_token" value="<?= $escape(Csrf::token()) ?>">
<input type="hidden" name="intent_id" value="<?= $escape($intent['intent_id']) ?>">
<noscript><p>Enable JavaScript to continue automatically, or use the button below.</p><button type="submit">Continue with Google</button></noscript>
</form>
<script>document.getElementById('google-start').submit();</script>
</body></html>
        <?php
        exit;
    }
    if ($action === 'start' && $method === 'POST') {
        // Explicit confirmation must belong to both this session and its current private intent.
        $token = $_POST['csrf_token'] ?? null;
        $expected = $_SESSION['csrf_token'] ?? null;
        if (!is_string($token) || !is_string($expected) || $expected === '' || !hash_equals($expected, $token)) {
            // Failure-only diagnostics: never log cookies, session IDs, tokens, intent values or identities.
            $cookieName = session_name();
            $cookie = $_COOKIE[$cookieName] ?? null;
            $cookiePresent = is_string($cookie) && $cookie !== '';
            $cookieHeader = $_SERVER['HTTP_COOKIE'] ?? '';
            $cookieCount = is_string($cookieHeader)
                ? preg_match_all('/(?:^|;)\s*' . preg_quote($cookieName, '/') . '=/', $cookieHeader) : 0;
            error_log('LSC app sign-in confirmation rejected: ' . json_encode([
                'cookie_present' => $cookiePresent,
                // A matching ID does not prove that the session's stored data survived.
                'cookie_matches_session' => $cookiePresent && session_id() !== '' && hash_equals(session_id(), $cookie),
                'csrf_expected_present' => is_string($expected) && $expected !== '',
                'csrf_received_present' => is_string($token) && $token !== '',
                'current_intent_present' => is_array($_SESSION['app_sign_in'] ?? null),
                'duplicate_session_cookie' => $cookieCount > 1,
            ]));
            $error = 'The browser could not verify this sign-in confirmation. Your browser session may have changed or cookies may be unavailable.';
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
        // Fixed native target; code/state are validated hex values, never an arbitrary redirect.
        header('Location: ' . $link, true, 303);
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
