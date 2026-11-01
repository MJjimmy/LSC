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

    /** Reserve one admitted local signup attempt, including successful signups. */
    public function consume(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        if ($maxAttempts < 1 || $decaySeconds < 1 || $this->database->inTransaction()) {
            return false;
        }
        $sqlite = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $expires = $sqlite ? "datetime('now', '+' || ? || ' seconds')" : 'DATE_ADD(NOW(), INTERVAL ? SECOND)';
        try {
            $this->database->beginTransaction();
            // The unique-key write serializes contenders even for a missing row.
            $this->database->prepare('INSERT INTO rate_limits (rl_key, attempts, expires_at) VALUES (?, 0, ' . $expires . ')'
                . ($sqlite ? ' ON CONFLICT(rl_key) DO NOTHING' : ' ON DUPLICATE KEY UPDATE rl_key = VALUES(rl_key)'))
                ->execute([$key, $decaySeconds]);
            $statement = $this->database->prepare('SELECT attempts, (expires_at > CURRENT_TIMESTAMP) AS active FROM rate_limits WHERE rl_key = ?' . ($sqlite ? '' : ' FOR UPDATE'));
            $statement->execute([$key]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$row || ((int)$row['active'] === 1 && (int)$row['attempts'] >= $maxAttempts)) {
                $this->database->rollBack();
                return false;
            }
            if ((int)$row['active'] === 1) {
                $update = $this->database->prepare('UPDATE rate_limits SET attempts = attempts + 1 WHERE rl_key = ?');
                $update->execute([$key]);
            } else {
                $update = $this->database->prepare('UPDATE rate_limits SET attempts = 1, expires_at = ' . $expires . ' WHERE rl_key = ?');
                $update->execute([$decaySeconds, $key]);
            }
            if ($update->rowCount() !== 1) {
                throw new \RuntimeException('Quota reservation failed.');
            }
            $this->database->commit();
            return true;
        } catch (\Throwable) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            return false;
        }
    }

    /** True if the key has hit or exceeded $maxAttempts within its current window. */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $statement = $this->database->prepare(
            'SELECT attempts FROM rate_limits WHERE rl_key = ? AND expires_at > CURRENT_TIMESTAMP'
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
        if ($this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->database->prepare("INSERT INTO rate_limits (rl_key, attempts, expires_at)
                VALUES (:key, 1, datetime('now', '+' || :decay || ' seconds'))
                ON CONFLICT(rl_key) DO UPDATE SET
                attempts = CASE WHEN rate_limits.expires_at > datetime('now') THEN rate_limits.attempts + 1 ELSE 1 END,
                expires_at = CASE WHEN rate_limits.expires_at > datetime('now') THEN rate_limits.expires_at ELSE datetime('now', '+' || :decay2 || ' seconds') END")
                ->execute(['key' => $key, 'decay' => $decaySeconds, 'decay2' => $decaySeconds]);
            return;
        }
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
            $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? "SELECT CAST(strftime('%s', expires_at) AS INTEGER) - CAST(strftime('%s', 'now') AS INTEGER) FROM rate_limits WHERE rl_key = ? AND expires_at > CURRENT_TIMESTAMP"
                : 'SELECT TIMESTAMPDIFF(SECOND, NOW(), expires_at) FROM rate_limits WHERE rl_key = ? AND expires_at > NOW()'
        );
        $statement->execute([$key]);
        $seconds = $statement->fetchColumn();
        return $seconds !== false ? max(0, (int)$seconds) : 0;
    }

    /**
     * Best-effort client IP for rate-limit keys.
     *
     * The shipped runtime puts nginx in front of php-fpm, and Railway adds
     * another proxy hop, so REMOTE_ADDR alone is the *proxy* for every visitor.
     * That turned per-IP limits into one shared bucket: eight failed logins
     * from anyone locked out every user. Forwarded headers are therefore read
     * only when the direct peer is a configured proxy, and the client is the
     * right-most address in the chain that is not itself trusted - the only
     * hop a client cannot forge.
     *
     * TRUSTED_PROXIES accepts a comma-separated list of IPs, CIDR ranges and
     * the keyword `cloudflare`; `loopback` is always trusted. TRUSTED_PROXY=
     * cloudflare remains supported for existing deployments.
     */
    public static function clientIp(): string
    {
        $remote = self::normalize((string)($_SERVER['REMOTE_ADDR'] ?? '')) ?: '0.0.0.0';
        $configured = self::trustedProxyList();

        if (in_array('cloudflare', $configured, true)
            && ($cloudflare = self::normalize((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''))) !== null) {
            return $cloudflare;
        }

        if (!self::isTrustedProxy($remote, $configured)) {
            return $remote;
        }

        $forwarded = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwarded === '') {
            return $remote;
        }

        $hops = array_reverse(explode(',', $forwarded));
        foreach ($hops as $hop) {
            $candidate = self::normalize(trim($hop));
            if ($candidate === null) {
                continue;
            }
            if (!self::isTrustedProxy($candidate, $configured)) {
                return $candidate;
            }
        }

        return $remote;
    }

    /** @return list<string> */
    private static function trustedProxyList(): array
    {
        $raw = (string)getenv('TRUSTED_PROXIES');
        if (trim($raw) === '') {
            $raw = (string)getenv('TRUSTED_PROXY');
        }
        $entries = [];
        foreach (explode(',', strtolower($raw)) as $entry) {
            $entry = trim($entry);
            if ($entry !== '') {
                $entries[] = $entry;
            }
        }
        return $entries;
    }

    /** @param list<string> $configured */
    private static function isTrustedProxy(string $ip, array $configured): bool
    {
        if ($ip === '0.0.0.0') {
            return false;
        }
        foreach ($configured as $entry) {
            if ($entry === 'loopback' && self::inRange($ip, '127.0.0.0/8')) {
                return true;
            }
            if ($entry === 'loopback' && $ip === '::1') {
                return true;
            }
            if ($entry === 'cloudflare') {
                continue;
            }
            if (self::inRange($ip, $entry)) {
                return true;
            }
        }
        return false;
    }

    private static function normalize(string $ip): ?string
    {
        $ip = trim($ip);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        $packed = @inet_pton($ip);
        return $packed === false ? null : (string)inet_ntop($packed);
    }

    /** True when $ip falls inside $range ("10.0.0.0/8", "fd00::/8" or a bare IP). */
    private static function inRange(string $ip, string $range): bool
    {
        $parts = explode('/', $range, 2);
        $network = self::normalize($parts[0]);
        if ($network === null) {
            return false;
        }
        $packedIp = inet_pton($ip);
        $packedNetwork = inet_pton($network);
        if ($packedIp === false || $packedNetwork === false || strlen($packedIp) !== strlen($packedNetwork)) {
            return false;
        }

        $bits = strlen($packedIp) * 8;
        $prefix = $bits;
        if (isset($parts[1])) {
            if (preg_match('/^\d{1,3}$/D', $parts[1]) !== 1) {
                return false;
            }
            $prefix = (int)$parts[1];
        }
        if ($prefix < 0 || $prefix > $bits) {
            return false;
        }

        $fullBytes = intdiv($prefix, 8);
        if ($fullBytes > 0 && substr($packedIp, 0, $fullBytes) !== substr($packedNetwork, 0, $fullBytes)) {
            return false;
        }
        $remainder = $prefix % 8;
        if ($remainder === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remainder)) & 0xFF;
        return (ord($packedIp[$fullBytes]) & $mask) === (ord($packedNetwork[$fullBytes]) & $mask);
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
