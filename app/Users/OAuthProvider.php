<?php

declare(strict_types=1);

namespace LSC\Users;

use RuntimeException;

final class OAuthProvider
{
    public static function configuration(string $provider): array
    {
        $base = rtrim((string)getenv('APP_URL'), '/');
        $settings = match ($provider) {
            'google' => [
                'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token' => 'https://oauth2.googleapis.com/token',
                'profile' => 'https://openidconnect.googleapis.com/v1/userinfo',
                'scope' => 'openid email profile',
            ],
            default => throw new RuntimeException('Unsupported sign-in provider.'),
        };
        $prefix = strtoupper($provider);
        return $settings + [
            'client_id' => (string)getenv($prefix . '_CLIENT_ID'),
            'client_secret' => (string)getenv($prefix . '_CLIENT_SECRET'),
            'redirect_uri' => $base . '/oauth.php?provider=' . $provider,
        ];
    }

    public static function enabled(string $provider): bool
    {
        $base = (string)getenv('APP_URL');
        $parts = parse_url($base);
        $local = is_array($parts) && in_array($parts['host'] ?? '', ['localhost', '127.0.0.1'], true)
            && strtolower((string)getenv('APP_ENV')) !== 'production';
        if (!is_array($parts) || empty($parts['host']) || isset($parts['query']) || isset($parts['fragment'])
            || isset($parts['user']) || !in_array($parts['scheme'] ?? '', $local ? ['http', 'https'] : ['https'], true)) {
            return false;
        }
        try {
            $config = self::configuration($provider);
            return $config['client_id'] !== '' && $config['client_secret'] !== '';
        } catch (RuntimeException) {
            return false;
        }
    }

    public static function allowAuthorizationRedirect(): void
    {
        // Chromium applies form-action to POST redirect targets, too. Keep the
        // existing policy and permit only Google's authorization origin.
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Security-Policy:') === 0) {
                header(str_replace("form-action 'self'", "form-action 'self' https://accounts.google.com", $header));
                return;
            }
        }
    }

    public static function safeNext(mixed $target): string
    {
        // Only a same-directory PHP route, never schemes, slashes, dot paths or encoded paths.
        return is_string($target) && preg_match('/^[a-zA-Z0-9_-]+\.php(?:\?[a-zA-Z0-9._~=&%+\-]*)?$/D', $target)
            ? $target : 'dashboard.php';
    }

    public static function begin(string $provider, string $next, ?int $linkUserId = null): string
    {
        if (!self::enabled($provider)) {
            throw new RuntimeException('This sign-in option is not available yet. Please use email and password.');
        }
        $config = self::configuration($provider);
        $verifier = bin2hex(random_bytes(32));
        $state = bin2hex(random_bytes(32));
        $_SESSION['oauth_flow'] = [
            'provider' => $provider, 'state' => $state, 'verifier' => $verifier,
            'expires' => time() + 600, 'next' => self::safeNext($next), 'link_user_id' => $linkUserId,
            'session_user_id' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
        ];
        return $config['authorize'] . '?' . http_build_query([
            'client_id' => $config['client_id'], 'redirect_uri' => $config['redirect_uri'],
            'response_type' => 'code', 'scope' => $config['scope'], 'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256', 'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public static function consume(string $provider, string $state): array
    {
        $flow = $_SESSION['oauth_flow'] ?? null;
        unset($_SESSION['oauth_flow']);
        if (!is_array($flow) || $state === '' || !hash_equals($flow['state'], $state)
            || $flow['provider'] !== $provider || $flow['expires'] < time()
            || ($flow['session_user_id'] ?? null) !== (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null)
            || ($flow['link_user_id'] !== null && $flow['link_user_id'] !== (int)($_SESSION['user_id'] ?? 0))) {
            throw new RuntimeException('This sign-in request expired or is invalid. Please try again.');
        }
        return $flow;
    }

    public static function identity(string $provider, array $profile): array
    {
        if ($provider === 'google') {
            if (($profile['email_verified'] ?? false) !== true) {
                throw new RuntimeException('Please verify your Google email address before continuing.');
            }
            $subject = $profile['sub'] ?? '';
            $email = $profile['email'] ?? '';
            $name = $profile['name'] ?? '';
        } else {
            throw new RuntimeException('Unsupported sign-in provider.');
        }
        if (!is_string($subject) || $subject === '' || strlen($subject) > 255
            || !is_string($email) || strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Your provider did not return a usable email address. Please register with email instead.');
        }
        return ['subject' => $subject, 'email' => strtolower(trim($email)),
            'name' => mb_substr(trim(is_string($name) ? $name : '') ?: $email, 0, 100)];
    }

    public static function exchange(string $provider, string $code, string $verifier): array
    {
        $config = self::configuration($provider);
        $token = self::request($config['token'], [
            'client_id' => $config['client_id'], 'client_secret' => $config['client_secret'],
            'code' => $code, 'code_verifier' => $verifier, 'grant_type' => 'authorization_code',
            'redirect_uri' => $config['redirect_uri'],
        ]);
        if (!is_string($token['access_token'] ?? null) || $token['access_token'] === '') {
            throw new RuntimeException('Your provider could not complete sign-in. Please try again.');
        }
        // Retrieve identity directly over TLS with the server-exchanged access token.
        // Never trust email/subject fields supplied by the browser or decode an unverified ID token.
        return self::identity($provider, self::request($config['profile'], null, $token['access_token']));
    }

    private static function request(string $url, ?array $fields = null, ?string $token = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Provider sign-in is temporarily unavailable. Please use email and password.');
        }
        $handle = curl_init($url);
        $headers = ['Accept: application/json'];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($fields !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($fields));
        }
        $response = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        $data = is_string($response) ? json_decode($response, true) : null;
        if ($status !== 200 || !is_array($data)) {
            throw new RuntimeException('Unable to connect to your sign-in provider. Please try again or use email and password.');
        }
        return $data;
    }
}
