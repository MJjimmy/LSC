<?php

declare(strict_types=1);

/**
 * Real-Postgres integration test for the parts of LSC that were written for
 * MySQL. It is the guard against the exact regression this repository shipped:
 * an infrastructure layer pointed at Postgres and an application layer that
 * still spoke MySQL.
 *
 * Requires a throwaway database with database/init-postgres.sql imported and
 * the PG* environment variables set. It refuses to run without
 * LSC_POSTGRES_TEST=1 because it deletes every row it finds.
 *
 *   LSC_POSTGRES_TEST=1 PGHOST=... php tests/postgres-integration.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (getenv('LSC_POSTGRES_TEST') !== '1') {
    fwrite(STDERR, "Refusing to run: set LSC_POSTGRES_TEST=1 and point PG* at a throwaway database.\n");
    exit(1);
}

require dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Database;
use LSC\Core\RateLimiter;
use LSC\Core\SqlDialect;
use LSC\Dashboard\DashboardRepository;
use LSC\Groups\GroupRepository;
use LSC\Groups\GroupService;
use LSC\Invitations\InvitationRepository;
use LSC\Notifications\NotificationDispatcher;
use LSC\Notifications\NotificationRepository;
use LSC\Users\EmailVerificationSchema;
use LSC\Users\PasswordResetService;
use LSC\Users\ProviderSchema;
use LSC\Users\UserRepository;

$checks = 0;
$pass = static function (string $message) use (&$checks): void {
    $checks++;
    fwrite(STDOUT, 'PASS: ' . $message . "\n");
};
$fail = static function (string $message) use ($checks): never {
    fwrite(STDERR, 'FAIL after ' . $checks . " checks: " . $message . "\n");
    exit(1);
};

$db = Database::connection();
if (SqlDialect::driver($db) !== SqlDialect::PGSQL) {
    $fail('expected a Postgres connection, got ' . SqlDialect::driver($db));
}
$pass('connected to Postgres');

// Empty the schema so the run is repeatable and ids are deterministic.
$db->exec('TRUNCATE app_auth_codes, oauth_identities, email_verification_tokens,
    password_reset_tokens, notification_queue, notifications, rate_limits,
    group_invitations, group_join_requests, group_members, messages, files,
    tasks, study_groups, users RESTART IDENTITY CASCADE');
$pass('fixture tables cleared');

$statement = $db->prepare(
    'INSERT INTO users (name, email, password_hash, role, is_active, email_verified_at)
     VALUES (?, ?, ?, ?, 1, CURRENT_TIMESTAMP) RETURNING user_id'
);
$statement->execute(['Postgres Fixture', 'pg-fixture@example.invalid', password_hash('fixture-only', PASSWORD_DEFAULT), 'admin']);
$adminId = (int)$statement->fetchColumn();
if ($adminId < 1) {
    $fail('could not create the fixture user');
}
$pass('user insert with 1/0 flags');

// --- Rate limiting ---------------------------------------------------------
$limiter = new RateLimiter($db);
$limiter->hit('pg:hit', 120);
$limiter->hit('pg:hit', 120);
if ($limiter->tooManyAttempts('pg:hit', 3)) {
    $fail('rate limiter counted too many attempts for two hits');
}
$limiter->hit('pg:hit', 120);
if (!$limiter->tooManyAttempts('pg:hit', 3)) {
    $fail('rate limiter did not reach the third hit');
}
if ($limiter->availableInSeconds('pg:hit') <= 0) {
    $fail('rate limiter reported an expired window');
}
$limiter->clear('pg:hit');
if ($limiter->tooManyAttempts('pg:hit', 3)) {
    $fail('rate limiter kept counting after clear');
}
if (!$limiter->consume('pg:consume', 2, 120) || !$limiter->consume('pg:consume', 2, 120)) {
    $fail('rate limiter refused attempts inside the quota');
}
if ($limiter->consume('pg:consume', 2, 120)) {
    $fail('rate limiter admitted an attempt over the quota');
}
$pass('rate limiter hit/consume/available/clear on Postgres');

// --- Groups ----------------------------------------------------------------
$groups = new GroupRepository($db);
$groups->ensureJoinRequestsTable();
$groups->ensureJoinRequestsTable();
$groupId = $groups->create('Postgres study group', 'Fixture', 0, $adminId);
if (!is_int($groupId) || $groupId < 1) {
    $fail('group creation failed');
}
$secondGroupId = $groups->create('Postgres second group', 'Fixture', 1, $adminId);
if (!is_int($secondGroupId) || $secondGroupId < 1) {
    $fail('private group creation failed');
}
if (!$groups->isMember($groupId, $adminId)) {
    $fail('group creator was not added as a member');
}
$groups->join($groupId, $adminId); // duplicate insert must be a no-op on Postgres
if ((int)$db->query('SELECT COUNT(*) FROM group_members WHERE group_id = ' . $groupId)->fetchColumn() !== 1) {
    $fail('duplicate join created a second membership row');
}
$request = $groups->addJoinRequest($groupId, $adminId);
if (!($request['ok'] ?? false)) {
    $fail('join request insert failed');
}
$second = $groups->addJoinRequest($groupId, $adminId);
if (($second['created'] ?? true) !== false) {
    $fail('duplicate join request was reported as created');
}
if (count($groups->pendingRequests($groupId)) !== 1) {
    $fail('pending join request was not returned');
}
$requestId = (int)$groups->pendingRequests($groupId)[0]['request_id'];
if (($groups->findJoinRequest($requestId, true)['group_id'] ?? 0) !== $groupId) {
    $fail('join request could not be locked and read');
}
if (!$groups->deleteJoinRequest($requestId)) {
    $fail('join request could not be deleted');
}
$publicGroups = $groups->publicGroupsAvailableTo($adminId);
if (!is_array($publicGroups)) {
    $fail('public group listing failed');
}
$pass('group repositories, join requests and row locks on Postgres');

// --- Administrator self-join path (row-locked role check) -------------------
$service = new GroupService($db, $groups, new NotificationRepository($db));
if (!$service->requestJoin($secondGroupId, $adminId)) {
    $fail('administrator could not request to join a group');
}
if (!$groups->isMember($secondGroupId, $adminId)) {
    $fail('administrator join did not create a membership');
}
$pass('administrator join path with FOR UPDATE on Postgres');

// --- Invitations -----------------------------------------------------------
$invitations = new InvitationRepository($db);
$token = bin2hex(random_bytes(32));
if (!$invitations->create($groupId, $adminId, null, 'invitee@example.invalid', $token)) {
    $fail('invitation insert failed');
}
$invitation = $invitations->findByToken($token);
if (!$invitation) {
    $fail('invitation could not be read back');
}
$expiresIn = (int)floor((strtotime((string)$invitation['expires_at']) - time()) / 86400);
if ($expiresIn < 6 || $expiresIn > 7) {
    $fail('invitation expiry is not seven days out (got ' . $expiresIn . ' days)');
}
$pending = $invitations->findPending($groupId, 'invitee@example.invalid');
if (($pending['token'] ?? '') !== $token) {
    $fail('pending invitation was not found under the group lock');
}
$pass('invitation creation and pending lookup on Postgres');

// --- Password reset --------------------------------------------------------
$reset = new PasswordResetService($db);
$resetToken = $reset->createToken('pg-fixture@example.invalid');
if (!is_string($resetToken) || $resetToken === '') {
    $fail('password reset token was not created');
}
$stored = $db->prepare('SELECT expires_at FROM password_reset_tokens WHERE token_hash = ?');
$stored->execute([hash('sha256', $resetToken)]);
$expiry = $stored->fetchColumn();
if ($expiry === false) {
    $fail('password reset token was not stored');
}
$minutes = (int)round((strtotime((string)$expiry) - time()) / 60);
if ($minutes < 55 || $minutes > 60) {
    $fail('password reset expiry is not 60 minutes (got ' . $minutes . ')');
}
if (!$reset->reset($resetToken, 'Postgres-Fixture!1')) {
    $fail('password reset failed on Postgres');
}
if ($reset->reset($resetToken, 'Postgres-Fixture!2')) {
    $fail('a consumed reset token was accepted twice');
}
$pass('password reset issue, consume and single-use rejection on Postgres');

// --- User repository -------------------------------------------------------
$users = new UserRepository($db);
$profile = $users->findProfile($adminId);
if (!$profile || (int)$profile['is_active'] !== 1) {
    $fail('user profile did not return an active account');
}
$current = (string)$db->query('SELECT password_hash FROM users WHERE user_id = ' . $adminId)->fetchColumn();
$replacement = password_hash('Postgres-Fixture!3', PASSWORD_DEFAULT);
if ($users->updatePasswordHash($adminId, $replacement, 'not-the-current-hash')) {
    $fail('password change accepted a mismatched current hash');
}
if (!$users->updatePasswordHash($adminId, $replacement, $current)) {
    $fail('password change rejected the correct current hash on Postgres');
}
if (!$users->updateNotificationSettings($adminId, '+27820000000', 1, 0)) {
    $fail('notification preferences could not be saved');
}
if ((int)$db->query('SELECT notify_sms FROM users WHERE user_id = ' . $adminId)->fetchColumn() !== 0) {
    $fail('notification preference flag was not stored as an integer');
}
$pass('user repository reads, password compare and preferences on Postgres');

// --- Queue ----------------------------------------------------------------
$dispatcher = new NotificationDispatcher($db, null);
if (!$dispatcher->queueEmail('queue@example.invalid', 'Postgres queue', 'Body', $adminId)) {
    $fail('queue insert failed');
}
if (!$dispatcher->queueForUser($adminId, 'Postgres queue', 'Body')) {
    $fail('per-user queueing failed');
}
$queued = (int)$db->query("SELECT COUNT(*) FROM notification_queue WHERE status = 'pending'")->fetchColumn();
if ($queued !== 2) {
    $fail('expected two queued notifications, found ' . $queued);
}
$pass('notification queue writes on Postgres');

// --- Files and calendar (lastInsertId, month/year predicates) -------------
$files = new \LSC\Files\FileRepository($db);
$fileId = $files->create($groupId, $adminId, 'fixture.pdf', '/uploads/group-files/fixture.pdf', 'pdf', 1024);
if (!is_int($fileId) || $fileId < 1) {
    $fail('file insert did not return a generated id (lastInsertId on Postgres)');
}
$tasks = new \LSC\Tasks\TaskRepository($db);
if (!$tasks->create($groupId, $adminId, 'Calendar fixture', 'Fixture', date('Y-m-d'))) {
    $fail('task insert failed');
}
$calendar = $tasks->forCalendar([$groupId], (int)date('n'), (int)date('Y'));
if (count($calendar) < 1) {
    $fail('calendar query returned nothing (month/year predicates)');
}
$pass('file and task inserts plus the calendar month/year query');

// --- Dashboard stats (CURRENT_DATE) ---------------------------------------
$task = $db->prepare('INSERT INTO tasks (group_id, assigned_to, title, due_date, status) VALUES (?, ?, ?, CURRENT_DATE - INTERVAL \'2 days\', \'pending\')');
$task->execute([$groupId, $adminId, 'Overdue fixture']);
$stats = (new DashboardRepository($db))->adminStats();
if (($stats['overdue_tasks'] ?? 0) < 1) {
    $fail('dashboard did not count an overdue task');
}
if (($stats['total_users'] ?? 0) !== 1) {
    $fail('dashboard did not count the active user');
}
$pass('dashboard aggregate queries on Postgres');

// --- Schema preparation is idempotent -------------------------------------
EmailVerificationSchema::prepare($db);
EmailVerificationSchema::prepare($db);
ProviderSchema::prepare($db);
ProviderSchema::prepare($db);
$columnCheck = $db->query('SELECT provider_message_id FROM notification_queue LIMIT 1');
if ($columnCheck === false) {
    $fail('notification_queue.provider_message_id is missing');
}
$pass('mailbox-proof and provider schema preparation is idempotent');

// --- Worker lock ----------------------------------------------------------
$lock = SqlDialect::workerLock($db, 'lsc_mail_fixture');
$acquire = $db->prepare($lock['acquire'][0]);
$acquire->execute($lock['acquire'][1]);
if (!SqlDialect::isTrue($acquire->fetchColumn())) {
    $fail('worker advisory lock was not acquired');
}
$release = $db->prepare($lock['release'][0]);
$release->execute($lock['release'][1]);
if (SqlDialect::isTrue($release->fetchColumn()) !== true) {
    $fail('worker advisory lock was not released');
}
$pass('worker advisory lock on Postgres');

fwrite(STDOUT, 'Postgres integration: ' . $checks . " checks passed.\n");
exit(0);
