<?php

declare(strict_types=1);

namespace StudySync\Core;

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
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && (
                !isset($_POST['csrf_token'])
                || !hash_equals($_SESSION['csrf_token'] ?? '', (string)$_POST['csrf_token'])
            )
        ) {
            http_response_code(403);
            exit('Invalid CSRF token');
        }
    }
}
