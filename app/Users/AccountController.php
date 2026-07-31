<?php

declare(strict_types=1);

namespace LSC\Users;

use LSC\Core\Auth;
use LSC\Core\Csrf;

final class AccountController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly AccountService $accounts,
        private readonly PasswordResetService $passwordResets
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
            $result = $this->accounts->authenticate(
                (string)($_POST['email'] ?? ''),
                (string)($_POST['password'] ?? '')
            );
            if ($result['ok']) {
                $user = $result['user'];
                $this->auth->login((int)$user['user_id'], $user['name'], $user['role'], $user['avatar_path']);
                header('Location: ' . $this->auth->safeLocalRedirect($_POST['next'] ?? $_GET['next'] ?? null));
                exit;
            }
            $error = $result['error'];
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
            $result = $this->accounts->register(
                (string)($_POST['name'] ?? ''),
                (string)($_POST['email'] ?? ''),
                (string)($_POST['password'] ?? ''),
                (string)($_POST['confirm_password'] ?? ''),
                (string)($_POST['role'] ?? '')
            );
            if ($result['ok']) {
                $next = $this->auth->safeLocalRedirect($_POST['next'] ?? $_GET['next'] ?? null);
                header('Location: login.php?registered=1&next=' . rawurlencode($next));
                exit;
            }
            $error = $result['error'];
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $email = trim((string)($_POST['email'] ?? ''));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && ($token = $this->passwordResets->createToken($email))) {
                $resetUrl = $this->resetUrl($token);
                $from = (string)getenv('MAIL_FROM');
                if ($from !== '') {
                    $headers = "From: {$from}\r\nContent-Type: text/plain; charset=UTF-8";
                    @mail($email, 'Reset your LSC password', "Use this link within 60 minutes to reset your password:\n\n{$resetUrl}\n", $headers);
                }
                if (strtolower((string)getenv('APP_ENV')) !== 'production') {
                    $developmentResetUrl = $resetUrl;
                }
            }
            $submitted = true;
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
            $password = (string)($_POST['password'] ?? '');
            $confirmation = (string)($_POST['confirm_password'] ?? '');
            if (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters.';
            } elseif ($password !== $confirmation) {
                $error = 'Passwords do not match.';
            } elseif ($this->passwordResets->reset($token, $password)) {
                $success = 'Password updated. You can now sign in.';
            } else {
                $error = 'This reset link has expired or has already been used.';
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
