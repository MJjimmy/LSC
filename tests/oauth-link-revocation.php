<?php

declare(strict_types=1);

define('LSC_ROOT', dirname(__DIR__));
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'LSC\\')) require LSC_ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
use LSC\Core\Auth;
use LSC\Users\OAuthAccounts;
use LSC\Users\OAuthProvider;
use LSC\Users\PasswordResetService;

ob_start();
session_start();
putenv('APP_URL=https://offline.example.invalid');
putenv('GOOGLE_CLIENT_ID=synthetic-client');
putenv('GOOGLE_CLIENT_SECRET=synthetic-secret');

class LinkFixturePDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
function fixture(): LinkFixturePDO {
    $db = new LinkFixturePDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec("CREATE TABLE users(user_id INTEGER PRIMARY KEY, name TEXT, email TEXT UNIQUE, password_hash TEXT NOT NULL, role TEXT, is_active INTEGER DEFAULT 1, avatar_path TEXT)");
    $db->exec("INSERT INTO users(user_id,name,email,password_hash,role) VALUES(1,'Synthetic user','victim@example.invalid','synthetic-old-hash','student')");
    $db->exec('CREATE TABLE oauth_identities(provider TEXT,subject TEXT,user_id INTEGER REFERENCES users(user_id), UNIQUE(provider,subject), UNIQUE(user_id,provider))');
    $db->exec('CREATE TABLE password_reset_tokens(reset_id INTEGER PRIMARY KEY,user_id INTEGER REFERENCES users(user_id),token_hash TEXT UNIQUE,expires_at TEXT,used_at TEXT)');
    $db->exec('CREATE TABLE app_auth_codes(code_hash TEXT PRIMARY KEY,user_id INTEGER REFERENCES users(user_id),challenge TEXT,expires_at INTEGER)');
    $_SESSION = ['user_id' => 1, 'auth_fingerprint' => hash('sha256', 'synthetic-old-hash')];
    return $db;
}
function insist(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$passed = $failed = 0;
function scenario(string $label, callable $test): void {
    global $passed, $failed;
    try { $test(); ++$passed; echo 'PASS: ' . $label . "\n"; }
    catch (Throwable $error) { ++$failed; echo 'FAIL: ' . $label . ' (' . get_class($error) . ': ' . $error->getMessage() . ")\n"; }
}
function identity(string $subject = 'synthetic-google-subject'): array {
    return ['subject' => $subject, 'email' => 'provider@example.invalid', 'name' => 'Synthetic provider'];
}
function resetUser(LinkFixturePDO $db): void {
    $service = new PasswordResetService($db);
    $token = $service->createToken('victim@example.invalid');
    insist(is_string($token) && $service->reset($token, 'SyntheticReset123!'), 'reset fixture prerequisite failed');
}

scenario('Google linking started before real reset cannot attach or restore login', function (): void {
    $db = fixture();
    $capturedProof = $_SESSION['auth_fingerprint'];
    OAuthProvider::begin('google', 'profile.php', 1);
    $flow = OAuthProvider::consume('google', $_SESSION['oauth_flow']['state']);
    resetUser($db); // The reset completes while a verified provider response is pending.
    $rejected = false; $authenticated = false;
    try {
        $user = (new OAuthAccounts($db))->resolve('google', identity(), 1, $capturedProof);
        $authenticated = (new Auth($db))->login((int)$user['user_id'], $user['name'], $user['role'], $user['avatar_path'], $user['password_hash']);
    } catch (RuntimeException) { $rejected = true; }
    $attached = (int)$db->query('SELECT COUNT(*) FROM oauth_identities')->fetchColumn();
    insist($rejected && $attached === 0 && !$authenticated,
        'callback accepted=' . ($rejected ? 'no' : 'yes') . ', attached=' . $attached . ', restored_login=' . ($authenticated ? 'yes' : 'no'));
});
echo "OAuth link scenarios: $passed passed, $failed failed (offline SQLite; no HTTP/provider/MySQL proof).\n";
exit($failed ? 1 : 0);
