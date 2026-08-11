<?php

declare(strict_types=1);

namespace LSC\Core;

use PDO;

/**
 * Simple DB-backed rate limiter (fixed window). No Redis needed - fine for
 * Hostinger shared hosting, and the table is tiny (one row per active
 * key, self-cleaning as windows expire).
 *
 * Usage pattern for a login-style endpoint:
 *
 *   $limiter = new RateLimiter($database);
 *   $ipKey = 'login:ip:' . RateLimiter::clientIp();
 *   $emailKey = 'login:email:' . strtolower($email);
 *
 *   if ($limiter->tooManyAttempts($ipKey, 5, 900) || $limiter->tooManyAttempts($emailKey, 5, 900)) {
 *       // show "too many attempts, try again in N minutes"
 *   }
 *   ... on failed attempt: $limiter->hit($ipKey, 900); $limiter->hit($emailKey, 900);
 *   ... on success: $limiter->clear($ipKey); $limiter->clear($emailKey);
 */
final class RateLimiter
{
    public function __construct(private readonly PDO $database)
    {
    }

    /** True if the key has hit or exceeded $maxAttempts within its current window. */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $statement = $this->database->prepare(
            'SELECT attempts FROM rate_limits WHERE rl_key = ? AND expires_at > NOW()'
        );
        $statement->execute([$key]);
        $attempts = $statement->fetchColumn();
        return $attempts !== false && (int)$attempts >= $maxAttempts;
    }

    /**
     * Record an attempt. Starts a fresh window if none is active or the
     * previous one expired. Single atomic statement, safe under concurrent
     * requests (relies on the primary key + ON DUPLICATE KEY UPDATE).
     */
    public function hit(string $key, int $decaySeconds): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO rate_limits (rl_key, attempts, expires_at)
             VALUES (:key, 1, DATE_ADD(NOW(), INTERVAL :decay SECOND))
             ON DUPLICATE KEY UPDATE
                attempts = IF(expires_at > NOW(), attempts + 1, 1),
                expires_at = IF(expires_at > NOW(), expires_at, DATE_ADD(NOW(), INTERVAL :decay2 SECOND))'
        );
        $statement->execute([
            'key' => $key,
            'decay' => $decaySeconds,
            'decay2' => $decaySeconds,
        ]);

        // Opportunistic cleanup - no separate cron needed. ~1 in 200 hits
        // sweeps rows that expired over a day ago, keeping the table small.
        if (random_int(1, 200) === 1) {
            $this->database->exec(
                'DELETE FROM rate_limits WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)'
            );
        }
    }

    /** Call after a successful login/reset so a good attempt doesn't stay counted. */
    public function clear(string $key): void
    {
        $this->database->prepare('DELETE FROM rate_limits WHERE rl_key = ?')->execute([$key]);
    }

    /** Seconds until the key's current window resets. 0 if no active window. */
    public function availableInSeconds(string $key): int
    {
        $statement = $this->database->prepare(
            'SELECT TIMESTAMPDIFF(SECOND, NOW(), expires_at) FROM rate_limits WHERE rl_key = ? AND expires_at > NOW()'
        );
        $statement->execute([$key]);
        $seconds = $statement->fetchColumn();
        return $seconds !== false ? max(0, (int)$seconds) : 0;
    }

    /**
     * Best-effort client IP. Only trusts the Cloudflare header when
     * TRUSTED_PROXY=cloudflare is set in the environment - otherwise
     * anyone can fake that header and bypass IP-based rate limiting
     * entirely. Set TRUSTED_PROXY=cloudflare once you actually put LSC
     * behind Cloudflare (see config/bootstrap.php for where env vars live).
     */
    public static function clientIp(): string
    {
        if (strtolower((string)getenv('TRUSTED_PROXY')) === 'cloudflare' && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';
    }

    /** "2 minutes" / "45 seconds", for user-facing messages. */
    public static function humanDuration(int $seconds): string
    {
        if ($seconds <= 60) {
            return $seconds . ' second' . ($seconds === 1 ? '' : 's');
        }
        $minutes = (int)ceil($seconds / 60);
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    }
}
