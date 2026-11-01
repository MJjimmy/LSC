<?php

declare(strict_types=1);

namespace LSC\Users;

use LSC\Core\Csrf;
use LSC\Core\RateLimiter;

final class EmailVerificationController
{
    public function __construct(private readonly EmailVerificationService $service, private readonly RateLimiter $limiter)
    {
    }

    public function show(): void
    {
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        $token = is_string($_POST['token'] ?? $_GET['token'] ?? null) ? ($_POST['token'] ?? $_GET['token']) : '';
        $next = OAuthProvider::safeNext($_POST['next'] ?? $_GET['next'] ?? null);
        $error = '';
        $success = '';
        if ($token !== '' && !preg_match('/^[a-f0-9]{64}$/D', $token)) {
            $error = 'This verification link is invalid. Request a new link.';
            $token = '';
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $key = 'verify:ip:' . RateLimiter::clientIp();
            $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
            $emailKey = 'verify:email:' . hash('sha256', strtolower($email));
            if ($this->limiter->tooManyAttempts($key, 10)
                || ($token === '' && $this->limiter->tooManyAttempts($emailKey, 3))) {
                $error = 'Too many verification attempts. Please try again in an hour.';
            } elseif ($error === '') {
                $this->limiter->hit($key, 3600);
                if ($token !== '') {
                    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
                    $confirmation = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
                    $result = $this->service->verify($token, $password, $confirmation);
                    if ($result['ok']) {
                        // No automatic login; start a fresh authenticated session with the new password.
                        $success = 'Email verified and password saved. Sign in to continue.';
                        $next = $result['next'];
                    } else {
                        $error = $result['error'];
                    }
                } else {
                    $this->limiter->hit($emailKey, 3600);
                    if ($this->service->request($email, $next)) {
                        $success = 'If this address belongs to an active, unverified account, a verification email has been requested. Check your inbox and spam folder.';
                    } else {
                        $error = 'Verification email is temporarily unavailable. Please try again later.';
                    }
                }
            }
        }
        ob_start();
        require \LSC_ROOT . '/views/auth/verify-email.php';
    }
}
