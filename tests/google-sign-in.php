<?php

declare(strict_types=1);

define('LSC_ROOT', dirname(__DIR__));
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'LSC\\')) {
        require LSC_ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    }
});
function csrfToken(): string { return 'test-csrf'; }

use LSC\Users\OAuthAccounts;
use LSC\Users\OAuthProvider;

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function rejects(callable $action, string $message): void
{
    try {
        $action();
    } catch (RuntimeException) {
        check(true, $message);
        return;
    }
    throw new RuntimeException($message);
}

putenv('APP_URL=https://lsc.example');
putenv('GOOGLE_CLIENT_ID=test-google');
putenv('GOOGLE_CLIENT_SECRET=test-secret');
putenv('MICROSOFT_CLIENT_ID=test-microsoft');
putenv('MICROSOFT_CLIENT_SECRET=test-secret');
putenv('MICROSOFT_TENANT_ID=common');
$_SESSION = [];
check(OAuthProvider::enabled('google'), 'Google should be configured.');
putenv('APP_URL=http://lsc.example');
check(!OAuthProvider::enabled('google'), 'Remote HTTP must not enable sign-in.');
putenv('APP_URL=https://lsc.example');
putenv('GOOGLE_CLIENT_SECRET=');
check(!OAuthProvider::enabled('google'), 'Missing credentials must disable sign-in.');
putenv('GOOGLE_CLIENT_SECRET=test-secret');

$url = OAuthProvider::begin('google', 'group.php?id=7');
parse_str(parse_url($url, PHP_URL_QUERY), $query);
$flow = $_SESSION['oauth_flow'];
check($query['code_challenge_method'] === 'S256', 'PKCE must use SHA-256.');
check($query['code_challenge'] === rtrim(strtr(base64_encode(hash('sha256', $flow['verifier'], true)), '+/', '-_'), '='), 'PKCE must match its verifier.');
check($query['redirect_uri'] === 'https://lsc.example/oauth.php?provider=google', 'Callback must use configured URL.');
check(OAuthProvider::consume('google', $query['state'])['next'] === 'group.php?id=7', 'Keep local continuation.');
rejects(fn() => OAuthProvider::consume('google', $query['state']), 'State must be single-use.');
OAuthProvider::begin('google', 'dashboard.php');
rejects(fn() => OAuthProvider::consume('google', 'wrong-state'), 'Reject wrong state.');
OAuthProvider::begin('google', 'dashboard.php');
$state = $_SESSION['oauth_flow']['state'];
rejects(fn() => OAuthProvider::consume('microsoft', $state), 'Reject provider mix-up.');
OAuthProvider::begin('google', 'dashboard.php');
$_SESSION['oauth_flow']['expires'] = time() - 1;
rejects(fn() => OAuthProvider::consume('google', $_SESSION['oauth_flow']['state']), 'Reject expired requests.');
$_SESSION['user_id'] = 1;
$_SESSION['auth_fingerprint'] = hash('sha256', 'synthetic-session-proof');
OAuthProvider::begin('google', 'profile.php', 1);
$_SESSION['user_id'] = 2;
rejects(fn() => OAuthProvider::consume('google', $_SESSION['oauth_flow']['state']), 'Linking must stay in the same account.');

$identity = OAuthProvider::identity('google', ['sub' => 'google-1', 'email' => 'student@example.com', 'email_verified' => true, 'name' => 'Student']);
rejects(fn() => OAuthProvider::identity('google', ['sub' => 'google-1', 'email' => 'student@example.com', 'email_verified' => false]), 'Reject unverified Google email.');
rejects(fn() => OAuthProvider::identity('google', ['email' => 'student@example.com', 'email_verified' => true]), 'Reject missing subject.');
rejects(fn() => OAuthProvider::identity('microsoft', []), 'Unsupported identity provider is denied.');
check(!OAuthProvider::enabled('microsoft'), 'Microsoft must stay disabled.');

// Real SQLite persistence for account behavior; only MySQL's row-lock suffix is
// removed. These tests do not establish MySQL concurrency behavior.
final class TestDatabase extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
$db = new TestDatabase('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys = ON');
$db->exec("CREATE TABLE users (user_id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT UNIQUE, password_hash TEXT, role TEXT, is_active INTEGER DEFAULT 1, avatar_path TEXT)");
$db->exec('CREATE TABLE oauth_identities (user_id INTEGER REFERENCES users(user_id), provider TEXT, subject TEXT, UNIQUE(provider, subject), UNIQUE(user_id, provider))');
$accounts = new OAuthAccounts($db);
$student = $accounts->resolve('google', $identity, null);
check($student['role'] === 'student', 'Provider sign-up cannot create admins or lecturers.');
check(!password_verify('', $student['password_hash']), 'Provider accounts must not have an empty password.');
check($accounts->resolve('google', $identity, null)['user_id'] === $student['user_id'], 'Repeat sign-in must resolve existing identity.');
$second = ['subject' => 'google-second', 'email' => $identity['email'], 'name' => 'Student'];
rejects(fn() => $accounts->resolve('google', $second, null), 'Email alone must not link existing accounts.');
check((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1, 'Failed linking must not create accounts.');
rejects(fn() => $accounts->resolve('google', $second, (int)$student['user_id'], hash('sha256', $student['password_hash'])), 'One Google identity per local account.');
$db->exec("INSERT INTO users (name,email,password_hash,role) VALUES ('Admin','admin@example.com','local-password','admin')");
$adminId = (int)$db->lastInsertId();
$linked = $accounts->resolve('google', ['subject' => 'admin-google', 'email' => 'admin@example.com', 'name' => 'Admin'], $adminId, hash('sha256', 'local-password'));
check($linked['role'] === 'admin', 'Explicit linking preserves admin role.');
$other = $accounts->resolve('google', ['subject' => 'google-2', 'email' => 'other@example.com', 'name' => 'Other'], null);
rejects(fn() => $accounts->resolve('google', $identity, (int)$other['user_id'], hash('sha256', $other['password_hash'])), 'Cannot claim another account’s provider identity.');
$db->exec('UPDATE users SET is_active = 0 WHERE user_id = ' . (int)$student['user_id']);
rejects(fn() => $accounts->resolve('google', $identity, null), 'Deactivated users must not sign in.');
$db->exec("UPDATE users SET role = 'lecturer' WHERE user_id = " . (int)$other['user_id']);
check($accounts->resolve('google', ['subject' => 'google-2', 'email' => 'changed@example.com', 'name' => 'Changed'], null)['role'] === 'lecturer', 'Existing identity retains local account and role when provider email changes.');


$auth = new \LSC\Core\Auth($db);
foreach (['https://evil.example', '//evil.example', "\r\nLocation: x", 'javascript:alert(1)', '../evil', '%2f%2fevil.example'] as $unsafe) {
    check(OAuthProvider::safeNext($unsafe) === 'dashboard.php', 'Reject unsafe continuation: ' . $unsafe);
}
check(OAuthProvider::safeNext('group.php?id=7') === 'group.php?id=7', 'Allow local PHP route.');
$_SESSION = [];
OAuthProvider::begin('google', 'dashboard.php');
$_SESSION['user_id'] = 99;
rejects(fn() => OAuthProvider::consume('google', $_SESSION['oauth_flow']['state']), 'Anonymous flow cannot switch a signed-in session.');
$_GET = ['next' => '" onfocus="alert(1)'];
ob_start();
require LSC_ROOT . '/views/auth/google.php';
$html = ob_get_clean();
check(str_contains($html, 'Continue with Google') && !str_contains($html, 'Microsoft'), 'Google-only button renders.');
check(!str_contains($html, 'value="" onfocus='), 'Continuation is escaped.');
putenv('GOOGLE_CLIENT_SECRET=');
ob_start(); require LSC_ROOT . '/views/auth/google.php'; $html = ob_get_clean();
check(str_contains($html, 'disabled'), 'Unavailable Google button is disabled.');
putenv('GOOGLE_CLIENT_SECRET=test-secret');
rejects(fn() => OAuthProvider::identity('google', ['sub' => 'x', 'email' => 'bad', 'email_verified' => true]), 'Malformed email is rejected.');
rejects(fn() => OAuthProvider::identity('google', ['sub' => 'x', 'email' => 'x@example.com', 'email_verified' => 'true']), 'Verified flag must be a boolean.');
$db->exec('UPDATE users SET is_active = 0 WHERE user_id = ' . $adminId);
rejects(fn() => $accounts->resolve('google', ['subject' => 'inactive-new', 'email' => 'unused@example.com', 'name' => 'X'], $adminId, hash('sha256', 'local-password')), 'Inactive users cannot attach another identity.');
check((int)$db->query("SELECT COUNT(*) FROM oauth_identities WHERE subject = 'inactive-new'")->fetchColumn() === 0, 'Refused linking leaves no identity.');
$_SESSION = ['user_id' => 1, 'auth_fingerprint' => hash('sha256', 'synthetic-session-proof')];
OAuthProvider::begin('google', 'profile.php', 1);
$state = $_SESSION['oauth_flow']['state'];
unset($_SESSION['user_id']);
rejects(fn() => OAuthProvider::consume('google', $state), 'Logout invalidates account linking.');

// Source-wiring regression checks supplement, not replace, HTTP/MySQL tests.
$route = file_get_contents(LSC_ROOT . '/public/oauth.php');
check(str_contains($route, 'Csrf::verify()') && str_contains($route, "['intent']") && str_contains($route, "'link'"), 'Link start is CSRF and intent protected.');
check(str_contains($route, 'profile.php?connected=1') && !str_contains($route, 'help.php'), 'Link callback returns to Profile.');
foreach (['login', 'register'] as $page) {
    $view = file_get_contents(LSC_ROOT . '/views/auth/' . $page . '.php');
    check(strpos($view, '</form>') < strpos($view, "/views/auth/google.php"), 'Google button is outside ' . $page . ' email form.');
}
$schemaScript = file_get_contents(LSC_ROOT . '/bin/prepare-provider-schema.php');
check(str_contains($schemaScript, "PHP_SAPI !== 'cli'") && str_contains($schemaScript, 'information_schema.COLUMNS'), 'Schema preparation is CLI-only and inspects columns.');
check(!preg_match('/\\b(DROP|TRUNCATE|DELETE|UPDATE)\\s+(TABLE\\s+)?(users|notification_queue|oauth_identities)\\b/i', $schemaScript), 'Schema script has no destructive data mutation.');
echo "Passed {$checks} Google website sign-in checks.\n";
