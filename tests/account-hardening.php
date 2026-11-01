<?php
declare(strict_types=1);
// CLI-only real-service regressions; synthetic SQLite, no bootstrap/provider/network.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'LSC\\')) require_once dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
function hardeningDb(): PDO {
    $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA foreign_keys=ON');
    $db->exec('CREATE TABLE users(user_id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,email TEXT UNIQUE,password_hash TEXT,role TEXT,is_active INTEGER DEFAULT 1,avatar_path TEXT,email_verified_at TEXT DEFAULT NULL)');
    $db->exec('CREATE TABLE password_reset_tokens(reset_id INTEGER PRIMARY KEY,user_id INTEGER REFERENCES users(user_id),token_hash TEXT UNIQUE,expires_at TEXT,used_at TEXT)');
    $db->exec('CREATE TABLE app_auth_codes(code_hash TEXT PRIMARY KEY,user_id INTEGER REFERENCES users(user_id),challenge TEXT,expires_at INTEGER)');
    return $db;
}
$passed = $failed = 0;
function hardeningCheck(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function hardeningTest(string $name, callable $test): void {
    global $passed, $failed;
    try { $test(); $passed++; echo "PASS: {$name}\n"; }
    catch (Throwable $error) { $failed++; echo "FAIL: {$name}: " . get_class($error) . ': ' . $error->getMessage() . "\n"; }
}
hardeningTest('signup rejects an eight-character password without a special character', function (): void {
    $db = hardeningDb();
    $accounts = new LSC\Users\AccountService(new LSC\Users\UserRepository($db));
    $result = $accounts->register('Synthetic learner', 'learner@example.invalid', '12345678', '12345678', 'student');
    hardeningCheck($result['ok'] === false, 'password without a special character was accepted');
    hardeningCheck((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0, 'rejected signup persisted an account');
});
hardeningTest('real reset service refuses a password without a special character', function (): void {
    $db = hardeningDb(); $before = password_hash('Synthetic-old-password!', PASSWORD_DEFAULT);
    $db->prepare('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)')->execute(['Synthetic learner','learner@example.invalid',$before,'student']);
    $resets = new LSC\Users\PasswordResetService($db);
    $token = $resets->createToken('learner@example.invalid');
    hardeningCheck(is_string($token), 'fixture reset token was not created');
    hardeningCheck($resets->reset($token, '12345678') === false, 'reset service accepted a password without a special character');
    hardeningCheck($db->query('SELECT password_hash FROM users')->fetchColumn() === $before, 'rejected password changed the account');
});
echo json_encode(['passed'=>$passed,'failed'=>$failed,'production_changed'=>false,'real_email_sent'=>false], JSON_THROW_ON_ERROR) . "\n";
exit($failed ? 1 : 0);
