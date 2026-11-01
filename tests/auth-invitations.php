<?php

declare(strict_types=1);

// Offline behavioral tests. No bootstrap/.env, network, external database or real mail.
// Optional --baseline=/path/to/original/source reproduces the defects against a backup.
$baseline = isset($argv[1]) && str_starts_with($argv[1], '--baseline=');
$source = $baseline ? substr($argv[1], strlen('--baseline=')) : dirname(__DIR__);
define('LSC_ROOT', $source);
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'LSC\\')) {
        require LSC_ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    }
});
ini_set('session.use_cookies', PHP_SAPI === 'cli' ? '0' : '1');
session_start();

use LSC\Core\Auth;
use LSC\Groups\GroupRepository;
use LSC\Groups\GroupService;
use LSC\Invitations\InvitationRepository;
use LSC\Invitations\InvitationService;
use LSC\Notifications\NotificationRepository;
use LSC\Notifications\NotificationDispatcher;
use LSC\Notifications\ResendMailer;
use LSC\Users\AccountService;
use LSC\Users\UserRepository;
use LSC\Users\PasswordPolicy;
use LSC\Users\PasswordResetService;
use LSC\Users\EmailVerificationService;

final class AuthFixtureDatabase extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        // SQL dialect translation only; production services perform real SQLite writes.
        // This does NOT establish MySQL locking/concurrency or migration behavior.
        if (str_contains($query, 'INSERT INTO rate_limits') && str_contains($query, ':key')) {
            $query = "INSERT INTO rate_limits(rl_key,attempts,expires_at) VALUES(:key,1,datetime('now','+' || :decay || ' seconds')) ON CONFLICT(rl_key) DO UPDATE SET attempts=CASE WHEN expires_at>CURRENT_TIMESTAMP THEN attempts+1 ELSE 1 END, expires_at=CASE WHEN expires_at>CURRENT_TIMESTAMP THEN expires_at ELSE datetime('now','+' || :decay2 || ' seconds') END";
        }
        $query = str_replace('TIMESTAMPDIFF(SECOND, NOW(), expires_at)', "(strftime('%s',expires_at)-strftime('%s','now'))", $query);
        $query = str_replace([' FOR UPDATE', 'INSERT IGNORE'], ['', 'INSERT OR IGNORE'], $query);
        $query = str_replace('DATE_ADD(NOW(), INTERVAL 7 DAY)', "datetime('now','+7 days')", $query);
        $query = str_replace('DATE_ADD(NOW(), INTERVAL 60 MINUTE)', "datetime('now','+60 minutes')", $query);
        $query = str_replace('NOW()', 'CURRENT_TIMESTAMP', $query);
        return parent::prepare($query, $options);
    }
    public function exec(string $statement): int|false
    {
        return parent::exec(str_replace('DATE_SUB(NOW(), INTERVAL 1 DAY)', "datetime('now','-1 day')", $statement));
    }
}
function fixture(string $dsn = 'sqlite::memory:'): AuthFixtureDatabase
{
    $db = new AuthFixtureDatabase($dsn, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA foreign_keys=ON');
    if ($db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn() !== false) return $db;
    $db->exec('CREATE TABLE users(user_id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT COLLATE NOCASE UNIQUE, password_hash TEXT, role TEXT, is_active INTEGER DEFAULT 1, avatar_path TEXT, email_verified_at TEXT DEFAULT NULL, notify_email INTEGER DEFAULT 1, notify_sms INTEGER DEFAULT 0, phone_number TEXT)');
    $db->exec('CREATE TABLE study_groups(group_id INTEGER PRIMARY KEY, group_name TEXT, description TEXT, created_by INTEGER REFERENCES users(user_id))');
    $db->exec('CREATE TABLE group_members(member_id INTEGER PRIMARY KEY, group_id INTEGER REFERENCES study_groups(group_id), user_id INTEGER REFERENCES users(user_id), UNIQUE(group_id,user_id))');
    $db->exec("CREATE TABLE group_invitations(invitation_id INTEGER PRIMARY KEY, group_id INTEGER REFERENCES study_groups(group_id), invited_by INTEGER REFERENCES users(user_id), invited_user_id INTEGER REFERENCES users(user_id), email TEXT, token TEXT UNIQUE, status TEXT DEFAULT 'pending', expires_at TEXT)");
    $db->exec('CREATE TABLE notifications(notification_id INTEGER PRIMARY KEY, user_id INTEGER REFERENCES users(user_id), type TEXT, title TEXT, body TEXT, link TEXT, is_read INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $db->exec('CREATE TABLE email_verification_tokens(token_hash TEXT PRIMARY KEY, user_id INTEGER REFERENCES users(user_id), email TEXT, credential_hash TEXT, expires_at INTEGER, next_path TEXT)');
    $db->exec('CREATE TABLE password_reset_tokens(reset_id INTEGER PRIMARY KEY, user_id INTEGER REFERENCES users(user_id), token_hash TEXT UNIQUE, expires_at TEXT, used_at TEXT)');
    $db->exec('CREATE TABLE app_auth_codes(code_hash TEXT PRIMARY KEY, user_id INTEGER REFERENCES users(user_id), challenge TEXT, expires_at INTEGER)');
    $db->exec('CREATE TABLE notification_queue(queue_id INTEGER PRIMARY KEY,user_id INTEGER,channel TEXT,recipient TEXT,subject TEXT,body TEXT)');
    $db->exec('CREATE TABLE rate_limits(rl_key TEXT PRIMARY KEY, attempts INTEGER, expires_at TEXT)');
    return $db;
}
function addUser(PDO $db, string $email, bool $verified = true, string $password = 'Blue lanterns cross quiet rivers7!'): int
{
    $db->prepare("INSERT INTO users(name,email,password_hash,role,email_verified_at) VALUES ('Fixture',?,?, 'student',?)")
        ->execute([$email, password_hash($password, PASSWORD_DEFAULT), $verified ? gmdate('Y-m-d H:i:s') : null]);
    return (int)$db->lastInsertId();
}
function invitations(PDO $db): InvitationService
{
    $groups = new GroupRepository($db);
    $notifications = new NotificationRepository($db);
    return new InvitationService($db, new InvitationRepository($db), $groups, new GroupService($db, $groups, $notifications), $notifications, new NotificationDispatcher($db));
}
function seedInvite(PDO $db, ?int $recipient, string $email, int $owner, string $status = 'pending', int $ttl = 3600): string
{
    $db->exec("INSERT OR IGNORE INTO study_groups VALUES(1,'Fixture group','Private fixture description',{$owner})");
    $token = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO group_invitations(group_id,invited_by,invited_user_id,email,token,status,expires_at) VALUES(1,?,?,?,?,?,?)')
        ->execute([$owner,$recipient,$email,$token,$status,gmdate('Y-m-d H:i:s',time()+$ttl)]);
    return $token;
}
if (defined('LSC_AUTH_FIXTURE_ONLY')) return;

$checks = 0;
$failures = [];
function expect(bool $ok, string $label): void
{
    global $checks, $failures;
    $checks++;
    if (!$ok) $failures[] = $label;
}

// These are meaningful RED/GREEN cases, not missing-method assertions.
$db = fixture();
$users = new UserRepository($db);
$accounts = new AccountService($users);
$result = $accounts->register('Fixture', 'weak@example.invalid', '12345678', '12345678', 'student');
expect(!$result['ok'], 'Registration rejects 12345678');
$id = addUser($db, 'unverified@example.invalid', false);
expect(!$accounts->authenticate('unverified@example.invalid', 'Blue lanterns cross quiet rivers7!')['ok'], 'Unverified mailbox cannot sign in with a correct password');
$_SESSION = ['user_id' => $id];
expect(!(new Auth($db))->isLoggedIn(), 'Old unverified session cannot bypass verification');
$_SESSION = [];
$owner = addUser($db, 'owner@example.invalid');
$recipient = addUser($db, 'recipient@example.invalid');
$token = seedInvite($db, $recipient, 'recipient@example.invalid', $owner);
$db->exec("CREATE TRIGGER reject_notice BEFORE INSERT ON notifications BEGIN SELECT RAISE(ABORT,'fixture notification failure'); END");
$result = invitations($db)->accept($token, $recipient);
expect($result['ok'], 'Notification failure cannot report a committed acceptance as failed');
expect((new GroupRepository($db))->isMember(1, $recipient), 'Accepted membership is saved despite notification failure');
expect(invitations($db)->accept($token, $recipient)['ok'], 'Repeated acceptance returns existing membership without duplicate writes');
$db->exec('DROP TRIGGER reject_notice');

if (!$baseline) {
    foreach (['12345678', 'password12345678', str_repeat('a', 16), str_repeat('1234', 5), str_repeat(' ', 15), str_repeat('a', 73), "long-passphrase\0test"] as $weak) {
        expect(PasswordPolicy::error($weak) !== null, 'Password policy rejects unsafe fixture ' . bin2hex($weak));
    }
    expect(PasswordPolicy::error('Blue lanterns cross quiet rivers7!') === null, 'Strong passphrase accepted');
    expect(PasswordPolicy::error('Rêves de cuivre et de lumière7!') === null, 'Unicode passphrase accepted within byte bound');
    $id = addUser($db, 'verified-weak@example.invalid', true, '12345678');
    expect(!$accounts->authenticate('verified-weak@example.invalid', '12345678')['ok'], 'Existing weak password must be reset even after verification');
    expect(!$accounts->authenticate('nobody@example.invalid', 'Blue lanterns cross quiet rivers7!')['ok'], 'Unknown email cannot authenticate');
    expect(!$accounts->authenticate('recipient@example.invalid', 'A completely wrong long password!')['ok'], 'Wrong password rejected');
    expect($accounts->authenticate('recipient@example.invalid', 'Blue lanterns cross quiet rivers7!')['ok'], 'Verified account with correct strong password accepted');
    $result = $accounts->register('New user', 'new@example.invalid', 'Copper owls fly over misty hills7!', 'Copper owls fly over misty hills7!', 'student');
    expect($result['ok'], 'Strong password registration succeeds');
    expect($users->findByEmail('new@example.invalid')['email_verified_at'] === null, 'Registration never marks its own email verified');
    expect(!$accounts->register('X', 'malformed', 'Copper owls fly over misty hills7!', 'Copper owls fly over misty hills7!', 'student')['ok'], 'Malformed email rejected');
    expect(!$accounts->register('X', 'role@example.invalid', 'Copper owls fly over misty hills7!', 'Copper owls fly over misty hills7!', 'admin')['ok'], 'Registration cannot create admin');
    expect(!$accounts->register('X', 'mismatch@example.invalid', 'Copper owls fly over misty hills7!', 'Different strong passphrase now!', 'student')['ok'], 'Password confirmation enforced');

    // Verification transport is injected; no actual HTTP call is possible in this fixture.
    putenv('APP_URL=https://lsc.example.invalid');
    putenv('APP_ENV=production');
    putenv('RESEND_API_KEY=fixture-not-a-real-key');
    putenv('RESEND_FROM_EMAIL=sender@example.invalid');
    putenv('EMAIL_DELIVERY_ENABLED=1');
    $mail = [];
    $mailer = new ResendMailer(function ($url, $payload, $headers) use (&$mail): array {
        $mail[] = json_decode($payload, true);
        return ['status'=>200, 'body'=>'{"id":"fixture-accepted"}', 'network_error'=>false];
    });
    $verification = new EmailVerificationService($db, $mailer);
    expect($verification->available(), 'Configured HTTPS verification available');
    putenv('APP_URL=http://attacker.invalid');
    expect(!$verification->available(), 'Insecure configured base URL rejected');
    putenv('APP_URL=https://lsc.example.invalid');
    putenv('EMAIL_DELIVERY_ENABLED=0');
    expect(!$verification->request('new@example.invalid'), 'Disabled delivery refuses request');
    expect(count($mail) === 0, 'Disabled delivery produces no message');
    putenv('EMAIL_DELIVERY_ENABLED=1');
    expect($verification->request('new@example.invalid', 'invite.php?token=' . $token), 'Unverified account can request verification');
    expect(count($mail) === 1, 'Exactly one verification transport call');
    preg_match('/token=([a-f0-9]{64})/', $mail[0]['text'], $match);
    $proof = $match[1];
    $row = $db->query('SELECT * FROM email_verification_tokens')->fetch();
    expect($row['token_hash'] === hash('sha256', $proof) && $row['token_hash'] !== $proof, 'Only token hash is persisted');
    expect($row['next_path'] === 'invite.php?token=' . $token, 'Invitation continuation survives verification email');
    expect($row['credential_hash'] === hash('sha256', $users->findByEmail('new@example.invalid')['password_hash']), 'Verification is bound to current password hash');
    expect(!$verification->verify($proof, '12345678', '12345678')['ok'], 'Verification cannot install weak password');
    foreach (['Copper owls fly over misty hills!', 'Copper owls fly over misty hills7', 'copper owls fly over misty hills7!', 'COPPER OWLS FLY OVER MISTY HILLS7!'] as $index => $incomplete) {
        expect(!$accounts->register('Policy fixture', 'policy' . $index . '@example.invalid', $incomplete, $incomplete, 'student')['ok'], 'Registration enforces every required character type, case ' . $index);
        expect(!$verification->verify($proof, $incomplete, $incomplete)['ok'], 'Verification enforces every required character type, case ' . $index);
    }
    expect(!$verification->verify($proof, 'Copper owls fly over misty hills7!', 'Wrong confirmation here!')['ok'], 'Verification requires matching confirmation');
    expect(!$verification->verify($proof . '-', 'Copper owls fly over misty hills7!', 'Copper owls fly over misty hills7!')['ok'], 'Malformed token rejected, not silently repaired');
    expect((int)$db->query('SELECT COUNT(*) FROM email_verification_tokens')->fetchColumn() === 1, 'Validation failure does not consume verification link');
    $newId = (int)$users->findByEmail('new@example.invalid')['user_id'];
    $db->prepare('INSERT INTO password_reset_tokens(user_id,token_hash,expires_at) VALUES(?,?,?)')->execute([$newId, str_repeat('d',64),gmdate('Y-m-d H:i:s',time()+3600)]);
    $db->prepare('INSERT INTO app_auth_codes VALUES(?,?,?,?)')->execute([str_repeat('e',64),$newId,str_repeat('f',64),time()+120]);
    $newPassword = 'Silver otters greet the morning7!';
    $result = $verification->verify($proof, $newPassword, $newPassword);
    expect($result['ok'], 'Valid mailbox token verifies account');
    expect($result['next'] === 'invite.php?token=' . $token, 'Verified user retains intended invitation');
    expect(!$accounts->authenticate('new@example.invalid', 'Copper owls fly over misty hills7!')['ok'], 'Verification replaces potentially pre-registered password');
    expect($accounts->authenticate('new@example.invalid', $newPassword)['ok'], 'Verified account can authenticate with newly chosen password');
    expect((int)$db->query('SELECT COUNT(*) FROM app_auth_codes')->fetchColumn() === 0, 'Verification revokes outstanding native codes');
    expect((int)$db->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn() === 0, 'Verification revokes old password reset tokens');
    expect(!$verification->verify($proof, $newPassword, $newPassword)['ok'], 'Verification token is single-use');
    $before = count($mail);
    expect($verification->request('unknown@example.invalid') && $verification->request('new@example.invalid'), 'Unknown and already-verified addresses get generic request outcome');
    expect(count($mail) === $before, 'Unknown and verified accounts are not sent verification');
    expect($verification->request('unverified@example.invalid', 'https://evil.invalid'), 'Existing unverified user can request verification');
    preg_match('/token=([a-f0-9]{64})/', $mail[array_key_last($mail)]['text'], $match);
    $expired = $match[1];
    expect($db->query('SELECT next_path FROM email_verification_tokens')->fetchColumn() === 'dashboard.php', 'External verification continuation is discarded');
    $db->exec('UPDATE email_verification_tokens SET expires_at = 1');
    expect(!$verification->verify($expired, $newPassword, $newPassword)['ok'], 'Expired verification link rejected');
    $verification->request('unverified@example.invalid');
    preg_match('/token=([a-f0-9]{64})/', $mail[array_key_last($mail)]['text'], $match);
    $replacement = $match[1];
    expect(!$verification->verify($expired, $newPassword, $newPassword)['ok'], 'Resend invalidates older link');
    $db->prepare('UPDATE users SET password_hash = ? WHERE email = ?')->execute([password_hash('Password was changed elsewhere!',PASSWORD_DEFAULT),'unverified@example.invalid']);
    expect(!$verification->verify($replacement, $newPassword, $newPassword)['ok'], 'Password change invalidates outstanding verification');
    $verification->request('unverified@example.invalid');
    preg_match('/token=([a-f0-9]{64})/', $mail[array_key_last($mail)]['text'], $match);
    $rollbackToken = $match[1];
    $db->exec("CREATE TRIGGER fail_revoke BEFORE DELETE ON email_verification_tokens BEGIN SELECT RAISE(ABORT,'fixture rollback'); END");
    expect(!$verification->verify($rollbackToken, $newPassword, $newPassword)['ok'], 'Verification storage failure is reported');
    expect($users->findByEmail('unverified@example.invalid')['email_verified_at'] === null, 'Verification storage failure rolls back account verification');
    expect(!$db->inTransaction(), 'Verification failure closes transaction');
    $db->exec('DROP TRIGGER fail_revoke');
    $badMailer = new ResendMailer(fn() => ['status'=>503,'body'=>'fixture rejection','network_error'=>false]);
    expect((new EmailVerificationService($db,$badMailer))->request('unverified@example.invalid'), 'Mail rejection does not disclose account existence');
    expect((int)$db->query('SELECT COUNT(*) FROM email_verification_tokens')->fetchColumn() === 0, 'Rejected mail leaves no usable verification token');

    $resets = new PasswordResetService($db);
    $reset = $resets->createToken('new@example.invalid');
    expect(is_string($reset), 'Password reset token created for active account');
    expect(!$resets->reset($reset, '12345678'), 'Reset service itself rejects weak password');
    foreach (['Copper owls fly over misty hills!', 'Copper owls fly over misty hills7', 'copper owls fly over misty hills7!', 'COPPER OWLS FLY OVER MISTY HILLS7!'] as $index => $incomplete) {
        expect(!$resets->reset($reset, $incomplete), 'Reset enforces every required character type, case ' . $index);
    }
    expect($resets->reset($reset, 'Crimson birds sail past the moon7!'), 'Strong password reset succeeds');
    expect(!$resets->reset($reset, 'Crimson birds sail past the moon7!'), 'Reset token cannot be reused');

    $other = addUser($db, 'other@example.invalid');
    $unverified = addUser($db, 'pending@example.invalid', false);
    $token2 = seedInvite($db, $other, 'other@example.invalid', $owner);
    expect(!invitations($db)->accept($token2, $recipient)['ok'], 'Wrong account cannot accept another account invitation');
    $token3 = seedInvite($db, $unverified, 'pending@example.invalid', $owner);
    expect(!invitations($db)->accept($token3, $unverified)['ok'], 'Unverified account cannot accept invitation by calling service directly');
    $emailOnly = seedInvite($db, null, 'OTHER@EXAMPLE.INVALID', $owner);
    expect(invitations($db)->accept($emailOnly, $other)['ok'], 'Invitation sent before registration matches verified email case-insensitively');
    $expiredInvite = seedInvite($db, $other, 'other@example.invalid', $owner, 'pending', -10);
    expect(!invitations($db)->accept($expiredInvite, $other)['ok'], 'Expired invitation cannot be accepted');
    $declinedInvite = seedInvite($db, $other, 'other@example.invalid', $owner, 'declined');
    expect(!invitations($db)->accept($declinedInvite, $other)['ok'], 'Declined invitation cannot be accepted');
    expect(!invitations($db)->accept($token2 . '-', $other)['ok'], 'Malformed invitation token rejected');
    $db->exec('DELETE FROM group_members WHERE user_id=' . $recipient);
    expect(!invitations($db)->accept($token, $recipient)['ok'], 'Used invitation cannot re-add a removed member');
    $bound = ['invited_user_id'=>$owner,'email'=>'other@example.invalid'];
    expect(!InvitationService::belongsTo($bound, $other, 'other@example.invalid'), 'Email match cannot override an existing recipient account binding');
    $failureInvite = seedInvite($db, $recipient, 'recipient@example.invalid', $owner);
    $db->exec("CREATE TRIGGER fail_accept BEFORE UPDATE ON group_invitations BEGIN SELECT RAISE(ABORT,'fixture acceptance failure'); END");
    expect(!invitations($db)->accept($failureInvite, $recipient)['ok'], 'Invitation status write failure is rejected');
    expect(!(new GroupRepository($db))->isMember(1,$recipient), 'Failed acceptance rolls back new membership');
    expect(!$db->inTransaction(), 'Invitation failure closes transaction');
    $db->exec('DROP TRIGGER fail_accept');
    date_default_timezone_set('Pacific/Kiritimati');
    $timezoneInvite = seedInvite($db, $recipient, 'recipient@example.invalid', $owner, 'pending', 60);
    expect(invitations($db)->accept($timezoneInvite, $recipient)['ok'], 'Invitation expiry uses database clock, not PHP timezone');
    date_default_timezone_set('UTC');
}
session_destroy();
echo json_encode(['mode'=>$baseline?'baseline':'fixed','checks'=>$checks,'failed'=>count($failures),'failures'=>$failures], JSON_PRETTY_PRINT) . "\n";
exit($failures ? 1 : 0);
