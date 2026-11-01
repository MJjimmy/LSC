<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/**
 * Break-glass account recovery. Run this on the server; never expose it.
 *
 *   php bin/account-recovery.php status
 *   php bin/account-recovery.php create-admin --email=admin@example.com --name="Site Admin" --password-stdin
 *   php bin/account-recovery.php promote-admin --email=admin@example.com
 *   php bin/account-recovery.php verify-email  --email=student@example.com
 *   php bin/account-recovery.php set-password  --email=student@example.com --password-stdin
 *
 * Passwords are only accepted on stdin (pipe or interactive prompt) so they
 * never appear in shell history, process listings or CI logs.
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Database;
use LSC\Users\AccountRecovery;

const LSC_RECOVERY_USAGE = <<<'TXT'
Usage: php bin/account-recovery.php <command> [options]

Commands:
  status                                  Show account, verification and administrator counts.
  create-admin  --email= --name=          Create a verified, active administrator.
  promote-admin --email=                  Grant administrator rights and reactivate an account.
  verify-email  --email=                  Mark a mailbox as proven (grandfathering step).
  set-password  --email=                  Replace a password, verify the mailbox and revoke reset links.

Options:
  --password-stdin    Read the new password from stdin instead of prompting.
  --dry-run           Report what would change without writing anything.

Passwords must satisfy the shared policy in LSC\Users\PasswordPolicy: at
least 8 characters including a symbol, at most 72 bytes, and no common or
sequential passwords. Exit codes: 0 success, 1 refused or failed, 2 usage
error.
TXT;

/** @return array{0: string, 1: array<string, string>, 2: bool} */
function lscRecoveryArgs(array $argv): array
{
    $command = (string)($argv[1] ?? '');
    $options = [];
    $dryRun = false;
    foreach (array_slice($argv, 2) as $argument) {
        if ($argument === '--dry-run') { $dryRun = true; continue; }
        if ($argument === '--password-stdin') { $options['password_stdin'] = '1'; continue; }
        if (preg_match('/^--([a-z-]+)=(.*)$/sD', $argument, $match) === 1) {
            $options[$match[1]] = $match[2];
            continue;
        }
        $options['invalid'] = $argument;
    }
    return [$command, $options, $dryRun];
}

function lscRecoveryPrompt(string $prompt): string
{
    if (!function_exists('stream_isatty') || !stream_isatty(STDIN)) {
        fwrite(STDERR, "No password on stdin. Use --password-stdin and pipe the password in.\n");
        exit(2);
    }
    fwrite(STDOUT, $prompt);
    $muted = function_exists('shell_exec') ? @shell_exec('stty -echo 2>/dev/null') : null;
    $line = fgets(STDIN);
    if ($muted !== null && function_exists('shell_exec')) { @shell_exec('stty echo 2>/dev/null'); }
    fwrite(STDOUT, "\n");
    return $line === false ? '' : rtrim($line, "\r\n");
}

function lscRecoveryPassword(array $options): string
{
    if (isset($options['password'])) {
        fwrite(STDERR, "Refused: pass the password on stdin with --password-stdin, not in an argument.\n");
        exit(2);
    }
    if (isset($options['password_stdin'])) {
        $line = fgets(STDIN);
        $password = $line === false ? '' : rtrim($line, "\r\n");
        if ($password === '') {
            fwrite(STDERR, "Refused: no password was provided on stdin.\n");
            exit(2);
        }
        return $password;
    }
    $first = lscRecoveryPrompt('New password: ');
    $second = lscRecoveryPrompt('Repeat password: ');
    if ($first !== $second) {
        fwrite(STDERR, "Refused: the passwords did not match.\n");
        exit(2);
    }
    return $first;
}

[$command, $options, $dryRun] = lscRecoveryArgs($argv);

if ($command === '' || $command === 'help' || isset($options['invalid'])) {
    fwrite(isset($options['invalid']) ? STDERR : STDOUT, LSC_RECOVERY_USAGE . "\n");
    exit($command === '' || isset($options['invalid']) ? 2 : 0);
}
if (!in_array($command, ['status', 'create-admin', 'promote-admin', 'verify-email', 'set-password'], true)) {
    fwrite(STDERR, "Unknown command: {$command}\n\n" . LSC_RECOVERY_USAGE . "\n");
    exit(2);
}

$recovery = new AccountRecovery(Database::connection());

if ($command === 'status') {
    $status = $recovery->status();
    if (!$status['schema_ready']) {
        fwrite(STDOUT, "Schema: verification columns are missing. Run bin/prepare-email-verification.php first.\n");
    }
    fwrite(STDOUT, sprintf(
        "Accounts: %d\nUnverified: %d\nAdministrators: %d\nVerified administrators: %d\n",
        $status['accounts'], $status['unverified'], $status['admins'], $status['verified_admins']
    ));
    if ($status['schema_ready'] && $status['verified_admins'] === 0) {
        fwrite(STDOUT, "WARNING: no verified administrator can sign in. Create one with create-admin.\n");
    }
    if ($status['schema_ready'] && $status['unverified'] > 0) {
        fwrite(STDOUT, "Note: unverified accounts cannot sign in until an email is proven or verify-email is run.\n");
    }
    exit(0);
}

$email = (string)($options['email'] ?? '');
if ($command === 'create-admin') {
    $name = (string)($options['name'] ?? '');
    $result = $recovery->createAdmin($email, $name, lscRecoveryPassword($options), $dryRun);
} elseif ($command === 'set-password') {
    $result = $recovery->setPassword($email, lscRecoveryPassword($options), $dryRun);
} elseif ($command === 'promote-admin') {
    $result = $recovery->promoteAdmin($email, $dryRun);
} else {
    $result = $recovery->verifyEmail($email, $dryRun);
}

if (!$result['ok']) {
    fwrite(STDERR, 'Refused: ' . $result['error'] . "\n");
    exit(1);
}
fwrite(STDOUT, $result['detail'] . "\n");
exit(0);
