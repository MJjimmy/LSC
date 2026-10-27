<?php

declare(strict_types=1);

namespace LSC\Users;

use LSC\Core\Auth;
use LSC\Core\Csrf;
use LSC\Core\RateLimiter;
use LSC\Notifications\ResendMailer;

final class AccountController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly AccountService $accounts,
        private readonly PasswordResetService $passwordResets,
        private readonly RateLimiter $limiter,
        private readonly ?ResendMailer $mailer = null
    ) {
    }

    public function login(): void
    {
        if ($this->auth->isLoggedIn()) {
            header('Location: dashboard.php');
            exit;
        }
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $email = (string)($_POST['email'] ?? '');
            $ipKey = 'login:ip:' . RateLimiter::clientIp();
            $emailKey = 'login:email:' . strtolower(trim($email));

            if ($this->limiter->tooManyAttempts($ipKey, 8) || $this->limiter->tooManyAttempts($emailKey, 5)) {
                $wait = max($this->limiter->availableInSeconds($ipKey), $this->limiter->availableInSeconds($emailKey));
                $error = 'Too many login attempts. Try again in ' . RateLimiter::humanDuration($wait) . '.';
            } else {
                $result = $this->accounts->authenticate($email, (string)($_POST['password'] ?? ''));
                if ($result['ok']) {
                    $user = $result['user'];
                    if ($this->auth->login((int)$user['user_id'], $user['name'], $user['role'], $user['avatar_path'], $user['password_hash']) === true) {
                        $this->limiter->clear($ipKey);
                        $this->limiter->clear($emailKey);
                        header('Location: ' . $this->auth->safeLocalRedirect($_POST['next'] ?? $_GET['next'] ?? null));
                        exit;
                    }
                    $result = ['ok' => false, 'error' => 'Invalid email or password.'];
                }
                $this->limiter->hit($ipKey, 900);
                $this->limiter->hit($emailKey, 900);
                $error = $result['error'];
            }
        }
        ob_start();
        require \LSC_ROOT . '/views/auth/login.php';
    }

    public function register(): void
    {
        if ($this->auth->isLoggedIn()) {
            header('Location: dashboard.php');
            exit;
        }
        $error = '';
        $success = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $ipKey = 'register:ip:' . RateLimiter::clientIp();

            if ($this->limiter->tooManyAttempts($ipKey, 6)) {
                $wait = $this->limiter->availableInSeconds($ipKey);
                $error = 'Too many signup attempts from this connection. Try again in ' . RateLimiter::humanDuration($wait) . '.';
            } else {
                $this->limiter->hit($ipKey, 3600);
                $result = $this->accounts->register(
                    (string)($_POST['name'] ?? ''),
                    (string)($_POST['email'] ?? ''),
                    (string)($_POST['password'] ?? ''),
                    (string)($_POST['confirm_password'] ?? ''),
                    (string)($_POST['role'] ?? '')
                );
                if ($result['ok']) {
                    $this->limiter->clear($ipKey);
                    $next = $this->auth->safeLocalRedirect($_POST['next'] ?? $_GET['next'] ?? null);
                    header('Location: login.php?registered=1&next=' . rawurlencode($next));
                    exit;
                }
                $error = $result['error'];
            }
        }
        ob_start();
        require \LSC_ROOT . '/views/auth/register.php';
    }

    public function forgotPassword(): void
    {
        if ($this->auth->isLoggedIn()) {
            header('Location: dashboard.php');
            exit;
        }
        $submitted = false;
        $developmentResetUrl = null;
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $email = trim((string)($_POST['email'] ?? ''));
            $ipKey = 'forgot:ip:' . RateLimiter::clientIp();
            $emailKey = 'forgot:email:' . strtolower($email);

            if ($this->limiter->tooManyAttempts($ipKey, 6) || $this->limiter->tooManyAttempts($emailKey, 3)) {
                $wait = max($this->limiter->availableInSeconds($ipKey), $this->limiter->availableInSeconds($emailKey));
                $error = 'Too many reset requests. Try again in ' . RateLimiter::humanDuration($wait) . '.';
            } else {
                $this->limiter->hit($ipKey, 3600);
                $this->limiter->hit($emailKey, 3600);
                $mailer = $this->mailer ?? new ResendMailer();
                $showDevelopmentLink = getenv('APP_SHOW_RESET_LINKS') === '1'
                    && strtolower((string)getenv('APP_ENV')) !== 'production';
                if (!$mailer->enabled() && !$showDevelopmentLink) {
                    $error = 'Password reset email is temporarily unavailable. Please try again later.';
                } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) && ($token = $this->passwordResets->createToken($email))) {
                    $resetUrl = $this->resetUrl($token);
                    $accepted = false;
                    if ($mailer->enabled()) {
                        $result = $mailer->send($email, 'Reset your LSC password',
                            "Use this link within 60 minutes to reset your password:\n\n{$resetUrl}\n",
                            'lsc/reset/' . hash('sha256', $token));
                        $accepted = $result['ok'];
                        if (!$accepted) error_log('LSC reset email not accepted: ' . $result['error']);
                    }
                    if (!$accepted && !$showDevelopmentLink) $this->passwordResets->revokeToken($token);
                    if ($showDevelopmentLink) {
                        $developmentResetUrl = $resetUrl;
                    }
                }
                $submitted = $error === '';
            }
        }
        ob_start();
        require \LSC_ROOT . '/views/auth/forgot-password.php';
    }

    public function resetPassword(): void
    {
        $token = preg_replace('/[^a-f0-9]/i', '', (string)($_GET['token'] ?? $_POST['token'] ?? ''));
        $error = strlen($token) === 64 ? '' : 'This reset link is invalid.';
        $success = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
            Csrf::verify();
            $ipKey = 'reset:ip:' . RateLimiter::clientIp();

            if ($this->limiter->tooManyAttempts($ipKey, 10)) {
                $wait = $this->limiter->availableInSeconds($ipKey);
                $error = 'Too many attempts. Try again in ' . RateLimiter::humanDuration($wait) . '.';
            } else {
                $password = (string)($_POST['password'] ?? '');
                $confirmation = (string)($_POST['confirm_password'] ?? '');
                if (strlen($password) < 8) {
                    $error = 'Password must be at least 8 characters.';
                } elseif ($password !== $confirmation) {
                    $error = 'Passwords do not match.';
                } elseif ($this->passwordResets->reset($token, $password)) {
                    $this->limiter->clear($ipKey);
                    $success = 'Password updated. You can now sign in.';
                } else {
                    $this->limiter->hit($ipKey, 3600);
                    $error = 'This reset link has expired or has already been used.';
                }
            }
        }
        ob_start();
        require \LSC_ROOT . '/views/auth/reset-password.php';
    }

    public function logout(): never
    {
        $this->auth->logout();
        header('Location: login.php');
        exit;
    }

    private function resetUrl(string $token): string
    {
        $baseUrl = rtrim((string)getenv('APP_URL'), '/');
        if ($baseUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = preg_replace('/[^a-zA-Z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $baseUrl = $scheme . '://' . $host;
        }
        return $baseUrl . '/reset-password.php?token=' . rawurlencode($token);
    }
}
