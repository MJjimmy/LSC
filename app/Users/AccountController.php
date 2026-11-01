<?php

declare(strict_types=1);

namespace LSC\Users;

use LSC\Core\AppUrl;
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
        private readonly ?ResendMailer $mailer = null,
        private readonly ?EmailVerificationService $emailVerification = null
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

            if (!$this->limiter->consume($ipKey, 6, 3600)) {
                $wait = $this->limiter->availableInSeconds($ipKey);
                $error = 'Too many signup attempts from this connection. Try again in ' . RateLimiter::humanDuration($wait) . '.';
            } elseif ($this->emailVerification === null || !$this->emailVerification->available()) {
                $error = 'Email verification is temporarily unavailable. Please try again later.';
            } else {
                $result = $this->accounts->register(
                    (string)($_POST['name'] ?? ''),
                    (string)($_POST['email'] ?? ''),
                    (string)($_POST['password'] ?? ''),
                    (string)($_POST['confirm_password'] ?? ''),
                    (string)($_POST['role'] ?? '')
                );
                if ($result['ok']) {
                    if ($this->emailVerification->request((string)($_POST['email'] ?? ''))) {
                        $success = 'Check your inbox and spam folder. Confirm your email using the verification link before signing in. Your account does not have access yet.';
                    } else {
                        $error = 'Your account is pending verification, but the email could not be sent. Request another verification email using the link below, or try again later.';
                    }
                } else {
                    $error = $result['error'];
                }
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
                $resetBase = AppUrl::base();
                if ($resetBase === null) {
                    // Never build a reset link from the Host header: a missing
                    // or invalid APP_URL would otherwise mail a valid token to
                    // whatever host the request claimed to be.
                    $error = 'Password reset links are unavailable because this site has no configured public URL. Contact an administrator.';
                } elseif (!$mailer->enabled() && !$showDevelopmentLink) {
                    $error = 'Password reset email is temporarily unavailable. Please try again later.';
                } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) && ($token = $this->passwordResets->createToken($email))) {
                    $resetUrl = $resetBase . '/reset-password.php?token=' . rawurlencode($token);
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
                if (($passwordError = PasswordPolicy::error($password)) !== null) {
                    $error = $passwordError;
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

    /**
     * Sign-out is a state change, so it requires a POST carrying a CSRF token.
     * A bare link (or an <img src="logout.php"> on any other site) must not be
     * able to end a session.
     */
    public function logout(): never
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            header('Content-Type: text/html; charset=UTF-8');
            $loginUrl = htmlspecialchars(AppUrl::link('/login.php') ?? 'login.php', ENT_QUOTES, 'UTF-8');
            exit(
                '<!doctype html><meta charset="utf-8"><title>Sign out</title>'
                . '<p>Signing out requires the sign-out button inside LSC.</p>'
                . '<p><a href="' . $loginUrl . '">Go to sign in</a></p>'
            );
        }

        Csrf::verify();
        $this->auth->logout();
        header('Location: login.php');
        exit;
    }

}
