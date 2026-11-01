<?php

declare(strict_types=1);

namespace LSC\Users;

final class PasswordPolicy
{
    public const HELP = 'Use at least 15 characters, including an uppercase letter, a lowercase letter, a number and a special character (for example !, @ or #). Avoid common passwords and repeated sequences.';

    public static function error(string $password): ?string
    {
        // PASSWORD_DEFAULT currently uses bcrypt: reject rather than truncate at 72 bytes.
        if (!mb_check_encoding($password, 'UTF-8') || str_contains($password, "\0")
            || strlen($password) > 72 || mb_strlen($password, 'UTF-8') < 15) {
            return 'Use 15 or more characters, up to 72 UTF-8 bytes.';
        }
        $compact = strtolower(preg_replace('/[\s\p{P}\p{S}]+/u', '', $password) ?? '');
        if ($compact === '' || preg_match('/^\d+$/D', $compact) || preg_match('/^(.{1,8})\1+$/D', $compact)
            || in_array($compact, ['password123456789', 'password12345678', 'qwerty1234567890',
                'letmein123456789', 'iloveyou123456789', '123456789abcdef'], true)) {
            return self::HELP;
        }
        if (!preg_match('/\p{Lu}/u', $password) || !preg_match('/\p{Ll}/u', $password)
            || !preg_match('/[0-9]/', $password) || !preg_match('/[\p{P}\p{S}]/u', $password)) {
            return self::HELP;
        }
        return null;
    }
}
