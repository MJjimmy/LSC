<?php
declare(strict_types=1);

// Isolated CLI regression: real recovery service and real account service on a
// synthetic SQLite database. No network, credentials or production data.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'LSC\\')) {
        require_once dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    }
});

use LSC\Users\AccountRecovery;
use LSC\Users\AccountService;
use LSC\Users\UserRepository;

const ORIGINAL_PASSWORD = 'Original-fixture-passphrase-1!';
const REPLACEMENT_PASSWORD = 'Replacement-fixture-passphrase-2!';

function recoveryDb(bool $withVerification = true): PDO
{
    $db = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $verified = $withVerification ? 'email_verified_at TEXT' : "legacy_unverified TEXT";
    $db->exec("CREATE TABLE users(user_id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,email TEXT UNIQUE,"
        . "password_hash TEXT,role TEXT DEFAULT 'student',is_active INTEGER DEFAULT 1,avatar_path TEXT," . $verified . ")");
    if ($withVerification) {
        $db->exec('CREATE TABLE password_reset_tokens(reset_id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER,'
            . 'token_hash TEXT,expires_at TEXT,used_at TEXT)');
        $insert = $db->prepare('INSERT INTO users(name,email,password_hash,role,is_active,email_verified_at) VALUES(?,?,?,?,?,?)');
        $insert->execute(['Synthetic student', 'student@example.invalid', password_hash(ORIGINAL_PASSWORD, PASSWORD_DEFAULT), 'student', 1, null]);
        $insert->execute(['Synthetic lecturer', 'lecturer@example.invalid', password_hash(ORIGINAL_PASSWORD, PASSWORD_DEFAULT), 'lecturer', 1, '2026-01-01 00:00:00']);
        $insert->execute(['Synthetic admin', 'admin@example.invalid', password_hash(ORIGINAL_PASSWORD, PASSWORD_DEFAULT), 'admin', 1, null]);
        $db->exec("INSERT INTO password_reset_tokens(user_id,token_hash,expires_at,used_at) VALUES(1,'" . str_repeat('a', 64) . "','2026-12-01 00:00:00',NULL)");
    }
    return $db;
}

function recoveryAccounts(PDO $db): AccountService
{
    return new AccountService(new UserRepository($db));
}

$failures = [];
$checks = 0;
function recoveryCase(string $name, bool $passed, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    if (!$passed) {
        $failures[] = $name;
        fwrite(STDOUT, "FAIL: {$name}" . ($detail !== '' ? ': ' . $detail : '') . "\n");
        return;
    }
    fwrite(STDOUT, "PASS: {$name}\n");
}

$db = recoveryDb();
$recovery = new AccountRecovery($db);
$accounts = recoveryAccounts($db);

// --- The state this tool exists to repair ---------------------------------
$status = $recovery->status();
recoveryCase('status reports the schema as ready', $status['schema_ready'] === true);
recoveryCase('status counts unverified accounts', $status['unverified'] === 2, (string)$status['unverified']);
recoveryCase('status counts administrators', $status['admins'] === 1, (string)$status['admins']);
recoveryCase('status reports no verified administrator', $status['verified_admins'] === 0, (string)$status['verified_admins']);
recoveryCase('an unverified administrator cannot sign in',
    $accounts->authenticate('admin@example.invalid', ORIGINAL_PASSWORD)['ok'] === false);
recoveryCase('an unverified student cannot sign in',
    $accounts->authenticate('student@example.invalid', ORIGINAL_PASSWORD)['ok'] === false);
recoveryCase('a verified account can still sign in',
    $accounts->authenticate('lecturer@example.invalid', ORIGINAL_PASSWORD)['ok'] === true);

// --- Recovery without email delivery --------------------------------------
$verified = $recovery->verifyEmail('student@example.invalid');
recoveryCase('verify-email succeeds', $verified['ok'] === true, (string)$verified['error']);
recoveryCase('verify-email restores sign-in', $accounts->authenticate('student@example.invalid', ORIGINAL_PASSWORD)['ok'] === true);
$again = $recovery->verifyEmail('STUDENT@example.invalid');
recoveryCase('verify-email is idempotent and case-insensitive',
    $again['ok'] === true && str_contains($again['detail'], 'already verified'), $again['detail']);
recoveryCase('verify-email refuses an unknown address',
    $recovery->verifyEmail('nobody@example.invalid')['ok'] === false);

// --- Creating a verified administrator ------------------------------------
$created = $recovery->createAdmin('owner@example.invalid', 'Site owner', REPLACEMENT_PASSWORD);
recoveryCase('create-admin succeeds', $created['ok'] === true, (string)$created['error']);
recoveryCase('created administrator can sign in',
    $accounts->authenticate('owner@example.invalid', REPLACEMENT_PASSWORD)['ok'] === true);
$row = $db->query("SELECT role, is_active, email_verified_at FROM users WHERE email = 'owner@example.invalid'")->fetch();
recoveryCase('created account is an active verified administrator',
    $row !== false && $row['role'] === 'admin' && (int)$row['is_active'] === 1 && !empty($row['email_verified_at']));
recoveryCase('status now reports a verified administrator', $recovery->status()['verified_admins'] === 1);

$duplicate = $recovery->createAdmin('owner@example.invalid', 'Second owner', REPLACEMENT_PASSWORD);
recoveryCase('create-admin refuses an existing address', $duplicate['ok'] === false);
recoveryCase('existing address error points at promote-admin',
    str_contains((string)$duplicate['error'], 'promote-admin'), (string)$duplicate['error']);

$before = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$weak = $recovery->createAdmin('weak@example.invalid', 'Weak owner', 'password123');
recoveryCase('create-admin refuses a predictable password', $weak['ok'] === false);
recoveryCase('refused password writes no account', (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() === $before);

$short = $recovery->createAdmin('short@example.invalid', 'Short owner', 'short1!');
recoveryCase('create-admin refuses a too-short password', $short['ok'] === false);

$dryRun = $recovery->createAdmin('dry@example.invalid', 'Dry owner', REPLACEMENT_PASSWORD, true);
recoveryCase('create-admin dry run reports the intended change',
    $dryRun['ok'] === true && str_contains($dryRun['detail'], 'Dry run'), $dryRun['detail']);
recoveryCase('create-admin dry run writes nothing', (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() === $before);

recoveryCase('create-admin refuses a malformed email',
    $recovery->createAdmin('not-an-email', 'Nobody', REPLACEMENT_PASSWORD)['ok'] === false);

// --- Promotion keeps the unverified warning visible -----------------------
$promoted = $recovery->promoteAdmin('admin@example.invalid');
recoveryCase('promote-admin succeeds for an existing account', $promoted['ok'] === true, (string)$promoted['error']);
recoveryCase('promote-admin warns that the mailbox is still unverified',
    str_contains($promoted['detail'], 'still unverified'), $promoted['detail']);
recoveryCase('promote-admin alone does not grant sign-in',
    $accounts->authenticate('admin@example.invalid', ORIGINAL_PASSWORD)['ok'] === false);
recoveryCase('promote-admin refuses an unknown address',
    $recovery->promoteAdmin('nobody@example.invalid')['ok'] === false);

// --- Password replacement revokes outstanding reset links ------------------
$replaced = $recovery->setPassword('student@example.invalid', REPLACEMENT_PASSWORD);
recoveryCase('set-password succeeds', $replaced['ok'] === true, (string)$replaced['error']);
recoveryCase('set-password invalidates the old password',
    $accounts->authenticate('student@example.invalid', ORIGINAL_PASSWORD)['ok'] === false);
recoveryCase('set-password enables the new password',
    $accounts->authenticate('student@example.invalid', REPLACEMENT_PASSWORD)['ok'] === true);
$token = $db->query('SELECT used_at FROM password_reset_tokens WHERE user_id = 1')->fetchColumn();
recoveryCase('set-password revokes outstanding reset tokens', $token !== null && $token !== false, var_export($token, true));
recoveryCase('set-password refuses reusing the current password',
    $recovery->setPassword('student@example.invalid', REPLACEMENT_PASSWORD)['ok'] === false);
recoveryCase('set-password refuses a policy-breaking password',
    $recovery->setPassword('student@example.invalid', 'password123')['ok'] === false);
recoveryCase('set-password refuses an unknown address',
    $recovery->setPassword('nobody@example.invalid', REPLACEMENT_PASSWORD)['ok'] === false);

// --- Pre-migration databases degrade instead of crashing ------------------
$legacy = recoveryDb(false);
$legacyStatus = (new AccountRecovery($legacy))->status();
recoveryCase('status tolerates a pre-migration schema', $legacyStatus['schema_ready'] === false);
recoveryCase('pre-migration status still counts accounts', $legacyStatus['accounts'] === 0, (string)$legacyStatus['accounts']);

fwrite(STDOUT, json_encode(['checks' => $checks, 'failed' => count($failures), 'failures' => $failures]) . "\n");
exit($failures === [] ? 0 : 1);
