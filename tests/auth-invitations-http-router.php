<?php

declare(strict_types=1);

// TEST ONLY: never place this router in public/ or use it on a deployed server.
$workspace = getenv('LSC_AUTH_TEST_WORKSPACE');
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    || !is_string($workspace) || !is_dir($workspace) || is_file(dirname(__DIR__) . '/.env')) {
    http_response_code(404);
    exit;
}
define('LSC_AUTH_FIXTURE_ONLY', true);
require __DIR__ . '/auth-invitations.php';
function csrfToken(): string { return \LSC\Core\Csrf::token(); }
header('Cache-Control: no-store');
$db = fixture('sqlite:' . $workspace . '/fixture.sqlite');
(new ReflectionProperty(\LSC\Core\Database::class, 'connection'))->setValue(null, $db);
if ((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
    $owner = addUser($db, 'owner@example.invalid');
    addUser($db, 'other@example.invalid');
    addUser($db, 'pending@example.invalid', false);
    $token = seedInvite($db, null, 'new@example.invalid', $owner);
    file_put_contents($workspace . '/invite-token.txt', $token);
}
$mailer = new \LSC\Notifications\ResendMailer(function ($url, $payload, $headers) use ($workspace): array {
    file_put_contents($workspace . '/mail.jsonl', $payload . "\n", FILE_APPEND | LOCK_EX);
    return ['status'=>200, 'body'=>'{"id":"http-fixture-accepted"}', 'network_error'=>false];
});
$auth = new \LSC\Core\Auth($db);
$users = new \LSC\Users\UserRepository($db);
$limiter = new \LSC\Core\RateLimiter($db);
$verification = new \LSC\Users\EmailVerificationService($db, $mailer);
$controller = new \LSC\Users\AccountController($auth, new \LSC\Users\AccountService($users), new \LSC\Users\PasswordResetService($db), $limiter, $mailer, $verification);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
switch ($path) {
    case '/__fixture/state':
        header('Content-Type: application/json');
        echo json_encode([
            'users'=>$db->query('SELECT user_id,email,email_verified_at FROM users')->fetchAll(),
            'members'=>$db->query('SELECT group_id,user_id FROM group_members')->fetchAll(),
            'invitations'=>$db->query('SELECT status FROM group_invitations')->fetchAll(),
            'verification_count'=>(int)$db->query('SELECT COUNT(*) FROM email_verification_tokens')->fetchColumn(),
            'invite_token'=>file_get_contents($workspace . '/invite-token.txt'),
        ]);
        break;
    case '/login.php': $controller->login(); break;
    case '/register.php': $controller->register(); break;
    case '/verify-email.php':
        (new \LSC\Users\EmailVerificationController($verification,$limiter))->show();
        break;
    case '/invite.php':
        (new \LSC\Invitations\InvitationController($auth,new \LSC\Invitations\InvitationRepository($db),invitations($db)))->show();
        break;
    default: http_response_code(404); echo 'Fixture route not found.';
}
