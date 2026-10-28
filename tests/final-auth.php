<?php
declare(strict_types=1);
// Offline real application classes, in-memory SQLite, no bootstrap/.env/transport.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'LSC\\')) require_once dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
ini_set('session.use_cookies', '0');
session_start();
ob_start(); // session_regenerate_id must remain usable throughout this CLI suite.
// Deterministic exchange-commit/login race, real PDO operations, no production hook.
class AuthCommitFixture extends PDO {
    public ?Closure $afterCommit = null;
    public function commit(): bool {
        $result = parent::commit(); $hook = $this->afterCommit; $this->afterCommit = null;
        if ($hook) $hook();
        return $result;
    }
}
$db = new AuthCommitFixture('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE users (user_id INTEGER PRIMARY KEY, name TEXT, email TEXT, password_hash TEXT, role TEXT, is_active INTEGER, avatar_path TEXT)');
$db->exec('CREATE TABLE app_auth_codes (code_hash CHAR(64) PRIMARY KEY, challenge CHAR(64), user_id INTEGER, expires_at INTEGER)');
$db->exec('CREATE TABLE rate_limits (rl_key TEXT PRIMARY KEY, attempts INTEGER, expires_at TEXT)');
$old = password_hash('SyntheticBefore123!', PASSWORD_DEFAULT);
$new = password_hash('SyntheticAfter123!', PASSWORD_DEFAULT);
$db->prepare('INSERT INTO users VALUES(1, ?, ?, ?, ?, 1, NULL)')->execute(['Synthetic', 'offline@example.invalid', $old, 'student']);
$auth = new LSC\Core\Auth($db);
$app = new LSC\Users\AppSignIn($db);
$passed = $failed = 0;
function check(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); }
function reject(callable $call): void {
    try { $call(); } catch (RuntimeException) { return; }
    throw new RuntimeException('operation unexpectedly accepted');
}
function test(string $label, callable $call): void {
    global $passed, $failed;
    try { $call(); ++$passed; echo "PASS: $label\n"; }
    catch (Throwable $e) { ++$failed; echo "FAIL: $label (" . get_class($e) . ': ' . $e->getMessage() . ")\n"; }
}
function resetUser(): void {
    global $db, $old;
    $db->prepare('UPDATE users SET password_hash=?, is_active=1 WHERE user_id=1')->execute([$old]);
    $_SESSION = [];
}
function readyIntent(): array {
    global $app, $auth, $old;
    check($auth->login(1, 'Synthetic', 'student', null, $old) === true, 'trusted login');
    $intent = $app->createIntent(hash('sha256', str_repeat('a', 64)), str_repeat('b', 64));
    $_SESSION['app_sign_in']['oauth_state'] = str_repeat('c', 64);
    $flow = ['provider'=>'google', 'link_user_id'=>null, 'next'=>'app-auth.php?action=complete', 'app_intent_id'=>$intent['intent_id'], 'state'=>str_repeat('c',64)];
    $app->completeGoogle($flow, 1);
    return $_SESSION;
}
test('explicit stale proof returns strict false without authentication', function () use ($auth, $old, $new, $db) {
    resetUser(); $db->prepare('UPDATE users SET password_hash=?')->execute([$new]);
    check($auth->login(1, 'Forged', 'admin', null, $old) === false, 'stale proof accepted');
    check(!$auth->isLoggedIn(), 'stale proof authenticated');
});
test('missing trusted credential proof is strictly rejected', function () use ($auth) {
    resetUser();
    check($auth->login(1, 'Forged', 'admin') === false && !$auth->isLoggedIn(), 'missing proof authenticated');
});
test('four argument fallback cannot refresh an existing stale session', function () use ($auth, $db, $old, $new) {
    resetUser(); $auth->login(1, 'Synthetic', 'student', null, $old);
    $db->prepare('UPDATE users SET password_hash=?')->execute([$new]);
    check($auth->login(1, 'Synthetic', 'student', null) === false && !$auth->isLoggedIn(), 'fallback resurrected stale session');
});
test('password change revokes independent sessions and private intents', function () use ($auth, $db, $old, $new) {
    resetUser(); $auth->login(1, 'Synthetic', 'student', null, $old); $saved = $_SESSION;
    $_SESSION['app_sign_in'] = ['private'=>true]; $_SESSION['oauth_flow'] = ['private'=>true];
    $db->prepare('UPDATE users SET password_hash=?')->execute([$new]);
    check(!$auth->isLoggedIn() && !isset($_SESSION['app_sign_in'], $_SESSION['oauth_flow']), 'revocation must erase pending flows');
    $_SESSION = $saved; check(!$auth->isLoggedIn(), 'independent stale session survived');
});
test('missing fingerprint and inactive accounts fail closed', function () use ($auth, $db, $old) {
    resetUser(); $auth->login(1, 'Synthetic', 'student', null, $old); unset($_SESSION['auth_fingerprint']);
    check(!$auth->isLoggedIn(), 'legacy session survived');
    $db->exec('UPDATE users SET is_active=0');
    check($auth->login(1,'Synthetic','student',null,$old) === false, 'inactive login accepted');
});
test('completed pre-reset intent cannot issue a fresh ticket', function () use ($app, $db, $new) {
    resetUser(); readyIntent(); $before = $db->query('SELECT COUNT(*) FROM app_auth_codes')->fetchColumn();
    $db->prepare('UPDATE users SET password_hash=?')->execute([$new]);
    reject(fn() => $app->complete());
    check($db->query('SELECT COUNT(*) FROM app_auth_codes')->fetchColumn() === $before, 'stale intent wrote ticket');
});
test('native ticket remains verifier-bound one-use and authoritative', function () use ($app, $auth, $db) {
    resetUser(); readyIntent(); $ticket = $app->complete(); $_SESSION=[];
    $row = $db->query('SELECT * FROM app_auth_codes ORDER BY rowid DESC LIMIT 1')->fetch();
    check($row['code_hash'] === hash('sha256',$ticket['code']) && $row['code_hash'] !== $ticket['code'], 'raw ticket stored');
    reject(fn() => $app->exchange($ticket['code'], str_repeat('d',64)));
    $app->exchange($ticket['code'], str_repeat('a',64));
    check($auth->isLoggedIn() && $_SESSION['android_client'] === true, 'native login failed');
    reject(fn() => $app->exchange($ticket['code'], str_repeat('a',64)));
});
test('issued ticket remains revoked even after fresh browser login', function () use ($app, $auth, $db, $new) {
    resetUser(); readyIntent(); $ticket = $app->complete();
    $db->prepare('UPDATE users SET password_hash=?')->execute([$new]);
    $_SESSION=[]; $auth->login(1,'Synthetic','student',null,$new);
    reject(fn() => $app->exchange($ticket['code'], str_repeat('a',64)));
});
test('legacy unbound native tickets fail closed', function () use ($app, $db) {
    resetUser(); $code=bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO app_auth_codes VALUES(?,?,1,?)')->execute([hash('sha256',$code),hash('sha256',str_repeat('a',64)),time()+120]);
    reject(fn() => $app->exchange($code,str_repeat('a',64)));
});
test('atomic signup reservation preserves six-hit fixed hour contract', function () use ($db) {
    $limiter = new LSC\Core\RateLimiter($db);
    for ($i=0;$i<6;$i++) check($limiter->consume('register:ip:fixture',6,3600), 'allowed reservation rejected');
    $row=$db->query('SELECT * FROM rate_limits')->fetch();
    check(!$limiter->consume('register:ip:fixture',6,3600), 'seventh reservation accepted');
    check($row === $db->query('SELECT * FROM rate_limits')->fetch(), 'denial changed quota/window');
    $remaining=strtotime($row['expires_at'].' UTC')-time(); check($remaining>=3590 && $remaining<=3600,'not a one hour window');
    $db->exec("UPDATE rate_limits SET expires_at='2000-01-01 00:00:00'");
    check($limiter->consume('register:ip:fixture',6,3600),'expiry not renewed');
    check((int)$db->query('SELECT attempts FROM rate_limits')->fetchColumn()===1,'expired count not reset');
});
test('completed proof is immutable across fresh same-user relogin', function () use ($app, $auth, $db, $new) {
    resetUser(); readyIntent(); $intent = $_SESSION['app_sign_in'];
    $db->prepare('UPDATE users SET password_hash=?')->execute([$new]);
    check($auth->login(1,'Synthetic','student',null,$new) === true,'fresh relogin');
    check($_SESSION['app_sign_in'] === $intent,'login refreshed completed intent proof');
    $before=(int)$db->query('SELECT COUNT(*) FROM app_auth_codes')->fetchColumn();
    reject(fn()=>$app->complete());
    check((int)$db->query('SELECT COUNT(*) FROM app_auth_codes')->fetchColumn()===$before,'old intent minted ticket');
});
test('legacy completed intent missing proof cannot issue code', function () use ($app) {
    resetUser(); readyIntent(); unset($_SESSION['app_sign_in']['completed_auth_fingerprint']);
    reject(fn()=>$app->complete());
});
test('exchange commit followed by password reset cannot establish native session', function () use ($app,$auth,$db,$new) {
    resetUser(); readyIntent(); $ticket=$app->complete(); $_SESSION=[];
    $db->afterCommit=static function() use($db,$new): void { $db->prepare('UPDATE users SET password_hash=?')->execute([$new]); };
    reject(fn()=>$app->exchange($ticket['code'],str_repeat('a',64)));
    check(!$auth->isLoggedIn() && !isset($_SESSION['android_client']),'stale exchange established native session');
    $query=$db->prepare('SELECT COUNT(*) FROM app_auth_codes WHERE code_hash=?'); $query->execute([hash('sha256',$ticket['code'])]);
    check((int)$query->fetchColumn()===0,'committed exchange must remain single-use');
});
test('fresh login and authorization use current authoritative metadata', function () use ($auth,$db,$old) {
    resetUser();
    check($auth->login(1,'Forged','admin',null,$old)===true,'trusted login');
    check($_SESSION['user_name']==='Synthetic' && $_SESSION['user_role']==='student','caller metadata trusted');
    $db->exec("UPDATE users SET role='lecturer' WHERE user_id=1");
    check($auth->isLoggedIn() && $_SESSION['user_role']==='lecturer','role not refreshed');
    $db->exec("UPDATE users SET role='student' WHERE user_id=1");
});
test('malformed fingerprints invalidate anonymously without warnings', function () use ($auth,$old) {
    foreach ([null,[],123,'',str_repeat('a',64)] as $fingerprint) {
        resetUser(); $auth->login(1,'Synthetic','student',null,$old); $_SESSION['auth_fingerprint']=$fingerprint;
        check(!$auth->isLoggedIn() && session_status()===PHP_SESSION_ACTIVE,'malformed fingerprint accepted or session destroyed');
        check(strlen(LSC\Core\Csrf::token())===64,'anonymous CSRF unavailable');
    }
});
test('expired native ticket does not authenticate or consume', function () use ($app,$db,$auth) {
    resetUser(); readyIntent(); $ticket=$app->complete(); $_SESSION=[];
    $db->prepare('UPDATE app_auth_codes SET expires_at=? WHERE code_hash=?')->execute([time(),hash('sha256',$ticket['code'])]);
    reject(fn()=>$app->exchange($ticket['code'],str_repeat('a',64)));
    check(!$auth->isLoggedIn(),'expired code authenticated');
});
echo "Final auth: $passed passed, $failed failed; external database/provider/mail calls: 0.\n";
ob_end_flush(); session_destroy(); exit($failed ? 1 : 0);
