<?php
declare(strict_types=1);
// Adapted from auth-security-fixture.php at 678c818b. CI-only router, never a production route.
// This gate precedes bootstrap, environment loading, sessions and database access.
if (getenv('AUTH_SECURITY_CI') !== 'isolated' || getenv('MYSQLHOST') !== '127.0.0.1'
    || getenv('MYSQLDATABASE') !== 'auth_security'
    || !preg_match('/^[0-9]{1,5}$/D', (string)getenv('MYSQLPORT'))
    || !getenv('MYSQLUSER') || !getenv('MYSQLPASSWORD')
    || is_file(dirname(__DIR__) . '/.env')) {
    http_response_code(503);
    exit('Isolated stage3 configuration required.');
}
if (PHP_SAPI !== 'cli' && (PHP_SAPI !== 'cli-server'
    || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1'
    || ($_SERVER['HTTP_HOST'] ?? '') !== '127.0.0.1:18971'
    || !getenv('STAGE3_FIXTURE_KEY')
    || !hash_equals((string)getenv('STAGE3_FIXTURE_KEY'), (string)($_SERVER['HTTP_X_STAGE3_FIXTURE'] ?? '')))) {
    http_response_code(403);
    exit('Isolated stage3 request required.');
}
// No route below sends mail or performs OAuth transport. Disable PHP mail/URL streams too.
putenv('APP_ENV=testing');
putenv('APP_DEBUG=0');
putenv('APP_URL=http://127.0.0.1:18971');
putenv('RESEND_API_KEY=');
putenv('GOOGLE_CLIENT_ID=');
putenv('GOOGLE_CLIENT_SECRET=');
require dirname(__DIR__) . '/config/bootstrap.php';

use LSC\Core\Auth;
use LSC\Core\Csrf;
use LSC\Core\Database;
use LSC\Users\AccountService;
use LSC\Users\UserRepository;
use LSC\Users\OAuthAccounts;
use LSC\Users\AppSignIn;
use LSC\Users\PasswordResetService;

$db = Database::connection();
if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
    || $db->query('SELECT DATABASE()')->fetchColumn() !== 'auth_security') {
    throw new RuntimeException('Isolated MySQL database required.');
}
if (PHP_SAPI === 'cli') {
    // Never drop, migrate, import, or reuse existing tables. Refuse a nonempty database.
    if ((int)$db->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn() !== 0) {
        throw new RuntimeException('Fresh empty disposable database required.');
    }
    // Exactly the original five-table schema: no auth_fingerprint column or migration.
    $db->exec("CREATE TABLE users (user_id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(100) NOT NULL, email VARCHAR(150) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL DEFAULT 'student', avatar_path VARCHAR(500), is_active TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB");
    $db->exec('CREATE TABLE rate_limits (rl_key VARCHAR(255) PRIMARY KEY, attempts INT NOT NULL, expires_at DATETIME NOT NULL) ENGINE=InnoDB');
    $db->exec('CREATE TABLE password_reset_tokens (reset_id INT PRIMARY KEY AUTO_INCREMENT, user_id INT NOT NULL, token_hash CHAR(64) UNIQUE NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME) ENGINE=InnoDB');
    $db->exec('CREATE TABLE oauth_identities (identity_id INT PRIMARY KEY AUTO_INCREMENT, provider VARCHAR(20), subject VARCHAR(255), user_id INT NOT NULL, UNIQUE(provider, subject), UNIQUE(user_id, provider)) ENGINE=InnoDB');
    $db->exec('CREATE TABLE app_auth_codes (code_hash CHAR(64) PRIMARY KEY, challenge CHAR(64) NOT NULL, user_id INT NOT NULL, expires_at BIGINT NOT NULL) ENGINE=InnoDB');
    echo "Initialized isolated stage3 schema; production data imported: 0.\n";
    exit;
}
header('X-Stage3-Fixture: isolated');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/fixture') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $email = $_POST['email'] ?? 'session@example.invalid';
    $accounts = new AccountService(new UserRepository($db));
    $auth = new Auth($db);
    if ($action === 'create') {
        echo json_encode($accounts->register('Stage3 fixture', $email, 'FixturePass123!', 'FixturePass123!', 'student'));
    } elseif ($action === 'quota') {
        echo json_encode(['attempts' => (int)$db->query("SELECT attempts FROM rate_limits WHERE rl_key = 'register:ip:127.0.0.1'")->fetchColumn(),
            'users' => (int)$db->query("SELECT COUNT(*) FROM users WHERE email LIKE 'quota%@example.invalid'")->fetchColumn()]);
    } elseif ($action === 'quota-window') {
        echo json_encode(['seconds' => (int)$db->query("SELECT TIMESTAMPDIFF(SECOND, NOW(), expires_at) FROM rate_limits WHERE rl_key = 'register:ip:127.0.0.1'")->fetchColumn()]);
    } elseif ($action === 'user-count') {
        $query = $db->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $query->execute([$email]);
        echo json_encode(['count' => (int)$query->fetchColumn()]);
    } elseif ($action === 'code-count') {
        echo json_encode(['count' => (int)$db->query('SELECT COUNT(*) FROM app_auth_codes')->fetchColumn()]);
    } elseif ($action === 'expire-quota') {
        $db->exec("UPDATE rate_limits SET expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE rl_key = 'register:ip:127.0.0.1'");
        echo '{}';
    } elseif ($action === 'capture-proof') {
        // Trusted authenticated proof is retained server-side, not returned or refreshed on replay.
        $result = $accounts->authenticate($email, 'FixturePass123!');
        if (!$result['ok']) { throw new RuntimeException('Proof setup failed.'); }
        $_SESSION['stage3_old_proof'] = $result['user'];
        echo '{"ok":true}';
    } elseif ($action === 'replay-proof') {
        $user = $_SESSION['stage3_old_proof'] ?? null;
        if (!is_array($user)) { throw new RuntimeException('Proof setup missing.'); }
        // Fifth argument is deliberately the PRE-reset password-hash snapshot.
        $result = $auth->login((int)$user['user_id'], $user['name'], $user['role'], $user['avatar_path'], $user['password_hash']);
        echo json_encode(['strict_false' => $result === false, 'logged_in' => $auth->isLoggedIn()]);
    } elseif (in_array($action, ['oauth', 'native', 'native-ticket', 'native-ready'], true)) {
        $user = (new UserRepository($db))->findByEmail($email);
        if (!$user) { throw new RuntimeException('Synthetic account missing.'); }
        // Trusted local identity resolution; never OAuthProvider::exchange or a provider endpoint.
        $db->prepare('INSERT IGNORE INTO oauth_identities(provider,subject,user_id) VALUES(?,?,?)')
            ->execute(['google', 'stage3-' . $user['user_id'], $user['user_id']]);
        try {
            $user = (new OAuthAccounts($db))->resolve('google', ['subject' => 'stage3-' . $user['user_id']], null);
            $auth->login((int)$user['user_id'], $user['name'], $user['role'], $user['avatar_path'], $user['password_hash']);
            if ($action !== 'oauth') {
                $service = new AppSignIn($db);
                $intent = $service->createIntent(hash('sha256', str_repeat('a', 64)), str_repeat('b', 64));
                $_SESSION['app_sign_in']['oauth_state'] = str_repeat('c', 64);
                $flow = ['provider' => 'google', 'link_user_id' => null, 'next' => 'app-auth.php?action=complete',
                    'app_intent_id' => $intent['intent_id'], 'state' => str_repeat('c', 64)];
                $service->completeGoogle($flow, (int)$user['user_id']);
                // Real completeGoogle has run, but complete() must wait until AFTER the reset.
                if ($action === 'native-ready') { echo '{"ok":true}'; exit; }
                $ticket = $service->complete();
                if ($action === 'native-ticket') { echo json_encode($ticket); exit; }
                $_SESSION = [];
                $service->exchange($ticket['code'], str_repeat('a', 64));
            }
            echo json_encode(['ok' => $auth->isLoggedIn()]);
        } catch (RuntimeException $exception) {
            http_response_code(400); echo '{"ok":false}';
        }
    } elseif ($action === 'exchange' || $action === 'complete') {
        try {
            $service = new AppSignIn($db);
            if ($action === 'exchange') {
                $service->exchange($_POST['code'], str_repeat('a', 64));
            } else {
                $service->complete();
            }
            echo '{"ok":true}';
        } catch (RuntimeException $exception) {
            http_response_code(400); echo '{"ok":false}';
        }
    } elseif ($action === 'token') {
        echo json_encode(['token' => (new PasswordResetService($db))->createToken($email)]);
    } elseif ($action === 'legacy') {
        unset($_SESSION['auth_fingerprint']); echo '{}';
    } elseif ($action === 'deactivate') {
        $db->prepare('UPDATE users SET is_active=0 WHERE email=?')->execute([$email]); echo '{}';
    } elseif ($action === 'protected') {
        $auth->requireLogin(); echo '{"ok":true}';
    } elseif ($action === '') {
        echo json_encode(['logged_in' => $auth->isLoggedIn(), 'csrf' => Csrf::token()]);
    } else {
        http_response_code(404); echo '{"ok":false}';
    }
    exit;
}
// Allowlist only real controllers required by stage3. No logout/forgot/provider/live routes.
if (in_array($path, ['/login.php', '/register.php', '/reset-password.php'], true)) {
    require dirname(__DIR__) . '/public' . $path;
    exit;
}
http_response_code(404);
