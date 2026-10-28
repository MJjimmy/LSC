<?php

declare(strict_types=1);

// Offline only: no bootstrap/.env, production database, provider requests or accounts.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'LSC\\')) {
        $file = dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});
if (!class_exists(LSC\Users\AppSignIn::class)) {
    fwrite(STDERR, "RED: AppSignIn class is missing.\n");
    exit(1);
}
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "BLOCKED: pdo_sqlite is required for offline tests.\n");
    exit(1);
}
ini_set('session.use_cookies', '0');
session_start();
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA foreign_keys = ON');
$db->exec('CREATE TABLE users (user_id INTEGER PRIMARY KEY, name TEXT NOT NULL, role TEXT NOT NULL, avatar_path TEXT, is_active INTEGER NOT NULL, password_hash TEXT NOT NULL)');
$proof = password_hash('SyntheticOnly123!', PASSWORD_DEFAULT);
$db->prepare("INSERT INTO users VALUES (1, 'Teacher', 'teacher', NULL, 1, ?), (2, 'Inactive', 'admin', NULL, 0, ?)")->execute([$proof, $proof]);
$db->exec('CREATE TABLE app_auth_codes (code_hash CHAR(64) PRIMARY KEY, challenge CHAR(64) NOT NULL, user_id INTEGER NOT NULL REFERENCES users(user_id), expires_at BIGINT NOT NULL)');
$service = new LSC\Users\AppSignIn($db);
$checks = 0;
$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    ++$checks;
};
$reject = static function (callable $operation, string $label) use ($assert): void {
    try {
        $operation();
    } catch (RuntimeException $exception) {
        $assert($exception->getMessage() === 'This app sign-in request is invalid or expired. Please start again.', $label . ' (sanitized)');
        return;
    }
    throw new RuntimeException('FAIL: accepted ' . $label);
};
$verifier = str_repeat('a', 64);
$challenge = hash('sha256', $verifier);
$state = str_repeat('b', 64);
$reject(fn() => $service->createIntent(str_repeat('A', 64), $state), 'uppercase challenge');
$reject(fn() => $service->createIntent($challenge, ['bad']), 'array state');
$reject(fn() => $service->createIntent($challenge, $state . "\n"), 'trailing newline');

// Only fake local configuration; begin builds an authorization URL, never fetches it.
putenv('APP_URL=https://offline.invalid');
putenv('GOOGLE_CLIENT_ID=offline-test-not-a-credential');
putenv('GOOGLE_CLIENT_SECRET=offline-test-not-a-credential');
$_SESSION = [];
$assert((new LSC\Core\Auth($db))->login(1, 'Forged', 'admin', null, $proof) === true, 'trusted credential-bound browser login');
$intent = $service->createIntent($challenge, $state);
$assert(strlen($intent['intent_id']) === 64 && $intent['expires'] >= time() + 599, 'private random intent with ten-minute TTL');
$reject(fn() => $service->complete(), 'existing browser login cannot issue ticket');
$reject(fn() => $service->beginGoogle(str_repeat('0', 64)), 'unknown confirmation intent');
$url = $service->beginGoogle($intent['intent_id']);
$flow = $_SESSION['oauth_flow'];
$assert(str_starts_with($url, 'https://accounts.google.com/'), 'existing Google provider begin used');
$assert($flow['app_intent_id'] === $intent['intent_id'] && $flow['link_user_id'] === null, 'tagged sign-in, never implicit linking');
$reject(fn() => $service->complete(), 'begin alone does not complete');
$bad = $flow;
$bad['app_intent_id'] = str_repeat('0', 64);
$reject(fn() => $service->validateCallback($bad), 'unknown callback intent');
$bad = $flow;
$bad['state'] = str_repeat('0', 64);
$reject(fn() => $service->validateCallback($bad), 'wrong Google flow state');
$saved = $_SESSION;
$_SESSION = [];
$reject(fn() => $service->validateCallback($flow), 'cross-session callback');
$_SESSION = $saved;
$_SESSION['app_sign_in']['expires'] = time() - 1;
$reject(fn() => $service->validateCallback($flow), 'expired intent');
$_SESSION = $saved;
$flow = LSC\Users\OAuthProvider::consume('google', $flow['state']);
$assert(!isset($_SESSION['oauth_flow']), 'real provider state consumption is single-use');
$service->validateCallback($flow);
// Simulates only the trusted hook AFTER a real provider exchange and identity resolve.
$service->completeGoogle($flow, 1);
$completed = $_SESSION;
$_SESSION['app_sign_in']['expires'] = time() - 1;
$reject(fn() => $service->complete(), 'completed intent still expires');
$_SESSION = $completed;
$db->exec('UPDATE users SET is_active = 0 WHERE user_id = 1');
$reject(fn() => $service->complete(), 'inactive completed user cannot issue ticket');
$db->exec('UPDATE users SET is_active = 1 WHERE user_id = 1');
$reject(fn() => $service->completeGoogle($flow, 1), 'completion hook replay');
$_SESSION['user_id'] = 2;
$reject(fn() => $service->complete(), 'completed user must match authenticated user');
$_SESSION['user_id'] = 1;
$return = $service->complete();
$assert($return['state'] === $state && preg_match('/^[a-f0-9]{64}$/D', $return['code']) === 1, 'opaque code and native state');
$reject(fn() => $service->complete(), 'intent consumed once');
$row = $db->query('SELECT * FROM app_auth_codes')->fetch(PDO::FETCH_ASSOC);
$assert($row['code_hash'] === hash('sha256', $return['code']) && $row['code_hash'] !== $return['code'], 'only hashed code persisted');
$assert((int)$row['expires_at'] <= time() + 120, 'ticket expires in two minutes');
$_SESSION = [];
$reject(fn() => $service->exchange($return['code'], str_repeat('c', 64)), 'wrong verifier');
$assert((int)$db->query('SELECT COUNT(*) FROM app_auth_codes')->fetchColumn() === 1, 'wrong verifier does not consume ticket');
$service->exchange($return['code'], $verifier);
$assert($_SESSION['user_id'] === 1 && $_SESSION['user_role'] === 'teacher' && $_SESSION['android_client'] === true, 'fresh WebView session receives authoritative role');
$reject(fn() => $service->exchange($return['code'], $verifier), 'ticket replay');
$reject(fn() => $service->exchange(['bad'], $verifier), 'array code');
$reject(fn() => $service->exchange(str_repeat('0', 64), ['bad']), 'array verifier');
$reject(fn() => $service->exchange(str_repeat('0', 64), str_repeat('A', 64)), 'uppercase verifier');
$reject(fn() => $service->exchange(str_repeat('A', 64), $verifier), 'uppercase code');
$reject(fn() => $service->exchange(str_repeat('0', 64), $verifier), 'unknown code');
$insert = $db->prepare('INSERT INTO app_auth_codes VALUES (?, ?, ?, ?)');
$expired = str_repeat('d', 64);
$insert->execute([hash('sha256', $expired), $challenge, 1, time() - 1]);
$reject(fn() => $service->exchange($expired, $verifier), 'expired code');
$inactive = str_repeat('e', 64);
$insert->execute([hash('sha256', $inactive), $challenge, 2, time() + 120]);
$reject(fn() => $service->exchange($inactive, $verifier), 'inactive user');
$assert(!$db->inTransaction(), 'failed exchanges roll back');

// Source/template checks are supplementary: no HTTP/MySQL behavior is inferred.
function csrfToken(): string { return LSC\Core\Csrf::token(); }
$_SESSION = [];
$_GET = [];
ob_start();
require dirname(__DIR__) . '/views/auth/google.php';
$html = ob_get_clean();
$assert(str_contains($html, '<form method="POST" action="oauth.php?provider=google">') && !str_contains($html, 'lscapp://google'), 'desktop Google form unchanged');
$_GET = ['app' => 'android'];
ob_start();
require dirname(__DIR__) . '/views/auth/google.php';
$html = ob_get_clean();
$assert(str_contains($html, 'href="lscapp://google"') && !str_contains($html, '<form'), 'native trigger replaces embedded form');
$_GET = [];
$_SESSION['android_client'] = true;
ob_start();
require dirname(__DIR__) . '/views/auth/google.php';
$html = ob_get_clean();
$assert(str_contains($html, 'href="lscapp://google"'), 'session marker retains native trigger');
$route = file_get_contents(dirname(__DIR__) . '/public/app-auth.php');
$callback = file_get_contents(dirname(__DIR__) . '/public/oauth.php');
$assert(strpos($route, 'hash_equals($expected, $token)') < strpos($route, '$service->beginGoogle('), 'confirmation verifies CSRF before provider start');
$assert(strpos($callback, '$appSignIn->validateCallback($flow)') < strpos($callback, 'OAuthProvider::exchange(')
    && strpos($callback, 'OAuthProvider::exchange(') < strpos($callback, 'OAuthAccounts($database))->resolve(')
    && strpos($callback, 'OAuthAccounts($database))->resolve(') < strpos($callback, '$appSignIn->completeGoogle('), 'completion hook follows verified exchange and identity resolve');
$assert(str_contains($route, "header('Location: dashboard.php', true, 303)") && str_contains($route, "header('Referrer-Policy: no-referrer')"), 'fixed redirect and non-referrer response wired');
session_destroy();
fwrite(STDOUT, "PASS: {$checks} app sign-in checks (offline SQLite/session; no provider network).\n");
