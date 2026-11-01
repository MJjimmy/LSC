<?php

declare(strict_types=1);

namespace LSC\Core;

/**
 * Single source of truth for the public base URL used in emailed links
 * (password resets, group invitations, email-ownership proof).
 *
 * The previous behaviour derived the base URL from $_SERVER['HTTP_HOST']
 * whenever APP_URL was unset. That turns a missing variable into an account
 * takeover primitive: a request carrying `Host: attacker.example` makes the
 * server email a valid reset token that points at the attacker's domain.
 *
 * This class therefore fails closed. It never trusts the Host header for a
 * public deployment; the only Host-derived value is a loopback URL, and only
 * outside production, so `php -S localhost:8000` keeps working locally.
 */
final class AppUrl
{
    /** Validated base URL, or null when no trustworthy value exists. */
    public static function base(): ?string
    {
        $configured = trim((string)getenv('APP_URL'));
        if ($configured !== '') {
            return self::validate($configured);
        }

        return self::production() ? null : self::loopbackFallback();
    }

    /** Absolute link for $path, or null when there is no trustworthy base. */
    public static function link(string $path): ?string
    {
        $base = self::base();

        return $base === null ? null : $base . '/' . ltrim($path, '/');
    }

    /**
     * Accept only an absolute origin: https anywhere, http only for loopback
     * outside production. Rejects userinfo, query strings, fragments, a path
     * (LSC must be served at the domain root), control characters and
     * out-of-range ports, so a configured value cannot smuggle a redirect.
     */
    public static function validate(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }
        if (isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = (string)($parts['host'] ?? '');
        if ($host === '') {
            return null;
        }

        $loopback = self::loopback($host);
        $https = $scheme === 'https';
        if (!$https && !($scheme === 'http' && $loopback && !self::production())) {
            return null;
        }

        $path = (string)($parts['path'] ?? '');
        if ($path !== '' && $path !== '/') {
            return null;
        }

        $port = '';
        if (isset($parts['port'])) {
            $number = (int)$parts['port'];
            if ($number < 1 || $number > 65535) {
                return null;
            }
            $port = ':' . $number;
        }

        // Re-validate the reassembled origin so nothing parsed but unexpected
        // (for example an IPv6 zone id) can reach an email body.
        $origin = $scheme . '://' . $host . $port;
        if (filter_var($origin, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $origin;
    }

    public static function production(): bool
    {
        return strtolower(trim((string)getenv('APP_ENV'))) === 'production';
    }

    private static function loopback(string $host): bool
    {
        return in_array(strtolower(trim($host, '[]')), ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * Local development only. Accepts the Host header solely when it names a
     * loopback address, so a spoofed Host still cannot produce a public link.
     */
    private static function loopbackFallback(): ?string
    {
        $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
        if (preg_match('/^(\[[0-9a-f:.]+\]|[a-z0-9.-]+)(?::(\d{1,5}))?$/D', $host, $match) !== 1) {
            return null;
        }

        $name = $match[1];
        $port = isset($match[2]) && $match[2] !== '' ? (int)$match[2] : 0;
        if ($port > 65535 || !self::loopback($name)) {
            return null;
        }

        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        return ($secure ? 'https' : 'http') . '://' . strtolower($name) . ($port > 0 ? ':' . $port : '');
    }
}
