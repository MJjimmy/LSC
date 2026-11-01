<?php

declare(strict_types=1);

namespace LSC\Users;

use LSC\Notifications\ResendMailer;
use PDO;
use RuntimeException;
use Throwable;

final class EmailVerificationService
{
    public function __construct(private readonly PDO $database, private readonly ResendMailer $mailer)
    {
    }

    private function baseUrl(): ?string
    {
        $base = rtrim((string)getenv('APP_URL'), '/');
        $parts = parse_url($base);
        if (!is_array($parts) || empty($parts['host']) || ($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }
        return $base;
    }

    public function available(): bool
    {
        return $this->mailer->enabled() && $this->baseUrl() !== null;
    }

    /** No account-existence disclosure and no token returned to the caller. */
    public function request(string $email, string $next = 'dashboard.php'): bool
    {
        if (!$this->available()) {
            return false;
        }
        $email = trim($email);
        if (strlen($next) > 500) $next = 'dashboard.php';
        if (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        try {
            $this->database->beginTransaction();
            $query = $this->database->prepare('SELECT user_id, email, password_hash, email_verified_at, is_active FROM users WHERE LOWER(email) = LOWER(?) FOR UPDATE');
            $query->execute([$email]);
            $user = $query->fetch(PDO::FETCH_ASSOC);
            if (!$user || (int)$user['is_active'] !== 1 || $user['email_verified_at'] !== null) {
                $this->database->commit();
                return true;
            }
            $this->database->prepare('DELETE FROM email_verification_tokens WHERE user_id = ? OR expires_at <= ?')
                ->execute([(int)$user['user_id'], time()]);
            $this->database->prepare('INSERT INTO email_verification_tokens (token_hash, user_id, email, credential_hash, expires_at, next_path) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$tokenHash, (int)$user['user_id'], $user['email'], hash('sha256', $user['password_hash']), time() + 3600, OAuthProvider::safeNext($next)]);
            $this->database->commit();
            $url = $this->baseUrl() . '/verify-email.php?token=' . $token;
            $sent = $this->mailer->send($user['email'], 'Verify your LSC email address',
                "To verify your email and set a secure password, open this link within one hour:\n\n{$url}\n\nIf you did not request this, ignore this message.",
                'lsc/verify/' . $tokenHash);
            if (!$sent['ok']) {
                $this->database->prepare('DELETE FROM email_verification_tokens WHERE token_hash = ?')->execute([$tokenHash]);
                error_log('LSC verification email was not accepted by the provider.');
            }
            // Provider rejection must not disclose whether this address has an account.
            return true;
        } catch (Throwable) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            error_log('LSC email verification request failed.');
            return false;
        }
    }

    /** A GET never consumes a link. Mailbox proof also replaces any pre-registered password. */
    public function verify(string $token, string $password, string $confirmation): array
    {
        $invalid = ['ok' => false, 'error' => 'This verification link is invalid, expired, or already used. Request a new link.'];
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return $invalid;
        }
        if (($error = PasswordPolicy::error($password)) !== null) {
            return ['ok' => false, 'error' => $error];
        }
        if ($password !== $confirmation) {
            return ['ok' => false, 'error' => 'Passwords do not match.'];
        }
        try {
            // Discover the owner, then lock user before token (same order as request).
            $lookup = $this->database->prepare('SELECT user_id FROM email_verification_tokens WHERE token_hash = ?');
            $lookup->execute([hash('sha256', $token)]);
            $userId = (int)$lookup->fetchColumn();
            if ($userId < 1) {
                return $invalid;
            }
            $this->database->beginTransaction();
            $lookup = $this->database->prepare('SELECT email, password_hash, is_active, email_verified_at FROM users WHERE user_id = ? FOR UPDATE');
            $lookup->execute([$userId]);
            $user = $lookup->fetch(PDO::FETCH_ASSOC);
            $lookup = $this->database->prepare('SELECT * FROM email_verification_tokens WHERE token_hash = ? AND user_id = ? FOR UPDATE');
            $lookup->execute([hash('sha256', $token), $userId]);
            $proof = $lookup->fetch(PDO::FETCH_ASSOC);
            if (!$user || !$proof || (int)$user['is_active'] !== 1 || $user['email_verified_at'] !== null
                || (int)$proof['expires_at'] <= time() || strcasecmp($proof['email'], $user['email']) !== 0
                || !hash_equals($proof['credential_hash'], hash('sha256', $user['password_hash']))) {
                $this->database->rollBack();
                return $invalid;
            }
            $update = $this->database->prepare('UPDATE users SET email_verified_at = ?, password_hash = ? WHERE user_id = ? AND email_verified_at IS NULL');
            $update->execute([gmdate('Y-m-d H:i:s'), password_hash($password, PASSWORD_DEFAULT), $userId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Verification changed concurrently.');
            }
            $this->database->prepare('DELETE FROM email_verification_tokens WHERE user_id = ?')->execute([$userId]);
            $this->database->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$userId]);
            $this->database->prepare('DELETE FROM app_auth_codes WHERE user_id = ?')->execute([$userId]);
            $this->database->commit();
            return ['ok' => true, 'next' => OAuthProvider::safeNext($proof['next_path'])];
        } catch (Throwable) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            error_log('LSC email verification completion failed.');
            return ['ok' => false, 'error' => 'Verification is temporarily unavailable. Please try again later.'];
        }
    }
}
