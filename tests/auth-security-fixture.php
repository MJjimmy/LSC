<?php
declare(strict_types=1);
// CI-only fixture/router. Never included by a production route.
if (getenv('AUTH_SECURITY_CI') !== 'isolated' || getenv('MYSQLHOST') !== '127.0.0.1'
    || getenv('MYSQLDATABASE') !== 'auth_security') {
    http_response_code(503);
    exit('Isolated auth-security fixture configuration required.');
}
require dirname(__DIR__) . '/config/bootstrap.php';
use LSC\Core\Auth;
use LSC\Core\Database;
use LSC\Users\AccountService;
use LSC\Users\UserRepository;
use LSC\Users\OAuthAccounts;
use LSC\Users\AppSignIn;
use LSC\Users\PasswordResetService;
$db = Database::connection();
if (PHP_SAPI === 'cli') {
    // Fresh dedicated CI database only; no source schema imports or existing data.
    $db->exec("CREATE TABLE users (user_id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(100) NOT NULL, email VARCHAR(150) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL DEFAULT 'student', avatar_path VARCHAR(500), is_active TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB");
    $db->exec('CREATE TABLE rate_limits (rl_key VARCHAR(255) PRIMARY KEY, attempts INT NOT NULL, expires_at DATETIME NOT NULL) ENGINE=InnoDB');
    $db->exec('CREATE TABLE password_reset_tokens (reset_id INT PRIMARY KEY AUTO_INCREMENT, user_id INT NOT NULL, token_hash CHAR(64) UNIQUE NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME) ENGINE=InnoDB');
    $db->exec('CREATE TABLE oauth_identities (identity_id INT PRIMARY KEY AUTO_INCREMENT, provider VARCHAR(20), subject VARCHAR(255), user_id INT NOT NULL, UNIQUE(provider, subject), UNIQUE(user_id, provider)) ENGINE=InnoDB');
    $db->exec('CREATE TABLE app_auth_codes (code_hash CHAR(64) PRIMARY KEY, challenge CHAR(64) NOT NULL, user_id INT NOT NULL, expires_at BIGINT NOT NULL) ENGINE=InnoDB');
    echo "Initialized isolated auth-security schema; production data imported: 0.\n";
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/fixture') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $email = $_POST['email'] ?? 'session@example.invalid';
    $accounts = new AccountService(new UserRepository($db));
    if ($action === 'create') {
        echo json_encode($accounts->register('CI fixture', $email, 'FixturePass123!', 'FixturePass123!', 'student'));
    } elseif ($action === 'quota') {
        echo json_encode($db->query("SELECT attempts FROM rate_limits WHERE rl_key = 'register:ip:127.0.0.1'")->fetchColumn());
    } elseif ($action === 'expire-quota') {
        $db->exec("UPDATE rate_limits SET expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE rl_key = 'register:ip:127.0.0.1'");
        echo '{}';
    } elseif ($action === 'login' || $action === 'oauth' || $action === 'native' || $action === 'native-ticket') {
        $user = (new UserRepository($db))->findByEmail($email);
        if ($action === 'oauth') {
            // Trusted local resolve path only; no provider transport or credentials.
            $db->prepare('INSERT IGNORE INTO oauth_identities(provider,subject,user_id) VALUES(?,?,?)')->execute(['google', 'auth-security-' . $user['user_id'], $user['user_id']]);
            $user = (new OAuthAccounts($db))->resolve('google', ['subject' => 'auth-security-' . $user['user_id']], null);
        }
        (new Auth($db))->login((int)$user['user_id'], $user['name'], $user['role']);
        if ($action === 'native' || $action === 'native-ticket') {
            // Same trusted post-provider hook used by oauth.php; no remote identity assertion.
            $service = new AppSignIn($db);
            $intent = $service->createIntent(hash('sha256', str_repeat('a', 64)), str_repeat('b', 64));
            $_SESSION['app_sign_in']['oauth_state'] = str_repeat('c', 64);
            $flow = ['provider'=>'google','link_user_id'=>null,'next'=>'app-auth.php?action=complete','app_intent_id'=>$intent['intent_id'],'state'=>str_repeat('c',64)];
            $service->completeGoogle($flow, (int)$user['user_id']);
            $ticket = $service->complete();
            if ($action === 'native-ticket') { echo json_encode($ticket); exit; }
            $_SESSION = [];
            $service->exchange($ticket['code'], str_repeat('a', 64));
        }
        echo '{"ok":true}';
    } elseif ($action === 'exchange') {
        try { (new AppSignIn($db))->exchange($_POST['code'], str_repeat('a',64)); echo '{"ok":true}'; }
        catch (RuntimeException $e) { http_response_code(400); echo '{"ok":false}'; }
    } elseif ($action === 'token') {
        echo json_encode(['token'=>(new PasswordResetService($db))->createToken($email)]);
    } elseif ($action === 'reset-direct') {
        echo json_encode(['ok'=>(new PasswordResetService($db))->reset($_POST['token'], $_POST['password'])]);
    } elseif ($action === 'legacy') {
        unset($_SESSION['auth_fingerprint']); echo '{}';
    } elseif ($action === 'deactivate') {
        $db->prepare('UPDATE users SET is_active=0 WHERE email=?')->execute([$email]); echo '{}';
    } elseif ($action === 'protected') {
        (new Auth($db))->requireLogin(); echo '{"ok":true}';
    } else {
        echo json_encode(['logged_in'=>(new Auth($db))->isLoggedIn(), 'csrf'=>\LSC\Core\Csrf::token()]);
    }
    exit;
}
$routes = ['/login.php','/register.php','/forgot-password.php','/reset-password.php','/logout.php'];
if (in_array($path, $routes, true)) { require dirname(__DIR__) . '/public' . $path; exit; }
http_response_code(404);
