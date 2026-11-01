<?php

declare(strict_types=1);

namespace LSC\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function verify(): void
    {
        $expected = $_SESSION['csrf_token'] ?? null;
        $submitted = $_POST['csrf_token'] ?? null;
        if (
            ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && (
                !is_string($expected)
                || $expected === ''
                || !is_string($submitted)
                || $submitted === ''
                || !hash_equals($expected, $submitted)
            )
        ) {
            http_response_code(403);
            exit('Invalid CSRF token');
        }
    }
}
