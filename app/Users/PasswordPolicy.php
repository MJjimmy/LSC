<?php
declare(strict_types=1);
namespace LSC\Users;

/** User-approved policy shared by signup and password reset; never logs input. */
final class PasswordPolicy
{
    public static function error(string $password): ?string
    {
        if (!mb_check_encoding($password, 'UTF-8') || str_contains($password, "\0")) {
            return 'Use a valid password without null characters.';
        }
        if (mb_strlen($password, 'UTF-8') < 8) {
            return 'Password must contain at least 8 characters and a special character.';
        }
        // PASSWORD_DEFAULT currently uses bcrypt; do not silently truncate input.
        if (strlen($password) > 72) {
            return 'Password is too long. Use no more than 72 UTF-8 bytes.';
        }
        if (preg_match('/[^\p{L}\p{N}\s]/u', $password) !== 1) {
            return 'Password must include a special character, such as !, @, # or ?.';
        }
        $base = strtolower((string)preg_replace('/[^a-z0-9]/i', '', $password));
        $common = ['password','password1','password12','password123','password1234','password12345',
            '12345678','123456789','1234567890','87654321','qwerty123','qwerty1234','qwertyuiop',
            'abcdefgh','abcdefgh123','letmein123','welcome123','admin123','administrator','student123','changeme123'];
        if (in_array($base, $common, true) || preg_match('/^(.)\1{7,}$/D', $base) === 1) {
            return 'Choose a less predictable password; common words and sequences are not allowed.';
        }
        return null;
    }
}
