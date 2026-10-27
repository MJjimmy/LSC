<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Auth;
use LSC\Core\Csrf;
use LSC\Core\Database;
use LSC\Core\RateLimiter;
use LSC\Users\AppSignIn;
use LSC\Users\OAuthAccounts;
use LSC\Users\OAuthProvider;

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$error = '';
try {
    $database = Database::connection();
    $auth = new Auth($database);
    $provider = is_string($_GET['provider'] ?? null) ? $_GET['provider'] : '';
    if ($provider !== 'google') {
        throw new RuntimeException('Please choose Google on the sign-in page.');
    }
    $limiter = new RateLimiter($database);
    $key = 'oauth:ip:' . RateLimiter::clientIp();
    if ($limiter->tooManyAttempts($key, 120)) {
        throw new RuntimeException('Too many sign-in requests. Please try again in a few minutes.');
    }
    $limiter->hit($key, 600);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Csrf::verify();
        $linkUserId = null;
        if (($_POST['intent'] ?? '') === 'link' && !$auth->isLoggedIn()) {
            throw new RuntimeException('Sign in with your LSC password, then open your own Profile and choose Connect Google.');
        }
        if ($auth->isLoggedIn()) {
            if (($_POST['intent'] ?? '') !== 'link') {
                throw new RuntimeException('To connect Google, open your own Profile and choose Connect Google.');
            }
            $auth->requireLogin();
            $linkUserId = (int)$_SESSION['user_id'];
        }
        $next = $linkUserId !== null ? 'profile.php?connected=1' : OAuthProvider::safeNext($_POST['next'] ?? null);
        header('Location: ' . OAuthProvider::begin($provider, $next, $linkUserId), true, 303);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new RuntimeException('Unsupported sign-in request.');
    }
    $flow = OAuthProvider::consume($provider, is_string($_GET['state'] ?? null) ? $_GET['state'] : '');
    if (isset($_GET['error'])) {
        throw new RuntimeException('Sign-in was cancelled or permission was not granted. You can try again or use email and password.');
    }
    $code = is_string($_GET['code'] ?? null) ? $_GET['code'] : '';
    if ($code === '' || !OAuthProvider::enabled($provider)) {
        throw new RuntimeException('Unable to complete this sign-in. Please try again.');
    }
    $appSignIn = array_key_exists('app_intent_id', $flow) ? new AppSignIn($database) : null;
    if ($appSignIn !== null) {
        $appSignIn->validateCallback($flow);
    }
    $identity = OAuthProvider::exchange($provider, $code, $flow['verifier']);
    $user = (new OAuthAccounts($database))->resolve($provider, $identity, $flow['link_user_id']);
    if ($auth->login((int)$user['user_id'], $user['name'], $user['role'], $user['avatar_path'], $user['password_hash']) !== true) {
        throw new RuntimeException('Unable to complete sign-in. Please try again.');
    }
    if ($appSignIn !== null) {
        // Never complete from an existing browser login or an unverified provider response.
        $appSignIn->completeGoogle($flow, (int)$user['user_id']);
    }
    header('Location: ' . OAuthProvider::safeNext($flow['next']), true, 303);
    exit;
} catch (PDOException $exception) {
    error_log('LSC provider sign-in database error: ' . $exception->getCode());
    $error = 'Provider sign-in is temporarily unavailable. Please use email and password or contact the platform administrator.';
} catch (RuntimeException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('LSC provider sign-in failed: ' . get_class($exception));
    $error = 'Unable to complete sign-in. Please try again or use email and password.';
}
http_response_code(400);
ob_start();
?>
<div class="auth-card-title">Let’s try that again</div>
<p class="auth-alert auth-alert-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
<a class="auth-btn" style="display:block;text-align:center;text-decoration:none;" href="login.php">Back to sign in</a>
<?php
$auth_content = ob_get_clean();
$pageTitle = 'Sign-in help — LSC';
require LSC_ROOT . '/views/layouts/auth.php';
