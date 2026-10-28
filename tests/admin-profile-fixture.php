<?php
declare(strict_types=1);
// CLI child fixture: actual Auth/ProfileController/UserRepository, synthetic SQLite only.
if (PHP_SAPI !== 'cli' || !isset($argv[1])) { exit(2); }
$config = json_decode(base64_decode($argv[1], true), true, 32, JSON_THROW_ON_ERROR);
$root = dirname(__DIR__);
foreach (['Core/Auth', 'Core/Csrf', 'Core/UploadStorage', 'Users/UserRepository', 'Users/ProfileController'] as $class) {
    require $root . '/app/' . $class . '.php';
}
$temp = getenv('RUNNER_TEMP') ?: getenv('TMPDIR');
if (!$temp || !is_dir($temp)) { throw new RuntimeException('Designated fixture temp directory missing'); }
$fixture = $temp . '/lsc-admin-profile-' . bin2hex(random_bytes(12));
mkdir($fixture . '/views/users', 0700, true);
mkdir($fixture . '/views/layouts', 0700, true);
mkdir($fixture . '/sessions', 0700, true);
file_put_contents($fixture . '/views/users/profile.php', '<?php require ' . var_export($root . '/views/users/profile.php', true) . ';');
file_put_contents($fixture . '/views/layouts/app.php', '<?php echo $page_content;');
define('LSC_ROOT', $fixture);
function csrfToken(): string { return LSC\Core\Csrf::token(); }
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE users (user_id INTEGER PRIMARY KEY, name TEXT, email TEXT, password_hash TEXT,
    role TEXT, is_active INTEGER, bio TEXT, institution TEXT, course TEXT, avatar_path TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP, phone_number TEXT, notify_email INTEGER, notify_sms INTEGER);
    CREATE TABLE group_members (group_id INTEGER,user_id INTEGER);
    CREATE TABLE files (uploaded_by INTEGER);
    CREATE TABLE tasks (assigned_to INTEGER,status TEXT);
    INSERT INTO users (user_id,name,email,password_hash,role,is_active,bio,institution,course,avatar_path,phone_number,notify_email,notify_sms)
    VALUES (1,'Site administrator','admin@example.invalid','synthetic-not-a-password-hash','admin',1,'Admin bio','Fixture University','CS','/uploads/avatars/admin-owned.png','+27820000001',1,0),
    (2,'Target learner','target@example.invalid','synthetic-not-a-password-hash','student',1,'Fixture <bio>','Fixture College','Biology',NULL,'+27820000002',1,0),
    (3,'Group creator','creator@example.invalid','synthetic-not-a-password-hash','lecturer',1,'Creator bio','Fixture College','Math',NULL,NULL,1,0),
    (4,'Inactive learner','inactive@example.invalid','synthetic-not-a-password-hash','student',0,'Inactive bio','Fixture College','Physics',NULL,NULL,1,0),
    (5,'Inactive admin','disabled@example.invalid','synthetic-not-a-password-hash','admin',0,'Disabled bio','Fixture College','CS',NULL,NULL,1,0);");
$before = $db->query('SELECT user_id,email,password_hash,role,is_active,phone_number,notify_email,notify_sms FROM users ORDER BY user_id')->fetchAll();
ini_set('session.use_cookies', '0');
ini_set('session.save_path', $fixture . '/sessions');
session_start();
$actorId = (int)($config['actor'] ?? 1);
$statement = $db->prepare('SELECT * FROM users WHERE user_id = ?');
$statement->execute([$actorId]);
$actor = $statement->fetch();
$auth = new LSC\Core\Auth($db);
if (!$actor || $auth->login($actorId, $actor['name'], $actor['role'], $actor['avatar_path'], $actor['password_hash']) !== true) {
    throw new RuntimeException('Synthetic trusted fixture login failed');
}
// Deliberately stale role metadata must still be refreshed by real authorization.
$_SESSION['user_role'] = $config['session_role'] ?? 'admin';
$_SESSION['csrf_token'] = 'synthetic-admin-csrf';
$_SERVER['REQUEST_METHOD'] = $config['method'] ?? 'GET';
$_SERVER['PHP_SELF'] = '/profile.php';
$_GET = ['user_id' => (string)($config['target'] ?? 2)];
$_POST = $config['post'] ?? [];
$_FILES = [];
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
ob_start();
register_shutdown_function(static function () use ($db, $before, $fixture): void {
    $html = '';
    while (ob_get_level() > 0) { $html = ob_get_clean() . $html; }
    $after = $db->query('SELECT user_id,email,password_hash,role,is_active,phone_number,notify_email,notify_sms FROM users ORDER BY user_id')->fetchAll();
    $record = ['status' => http_response_code() ?: 200, 'html' => $html,
        'session' => array_intersect_key($_SESSION, array_flip(['user_id','user_name','user_avatar','user_role'])),
        'protected_fields_unchanged' => $after === $before,
        'users' => $db->query('SELECT user_id,name,institution,course,bio FROM users ORDER BY user_id')->fetchAll()];
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    foreach (glob($fixture . '/sessions/sess_*') ?: [] as $session) { unlink($session); }
    rmdir($fixture . '/sessions');
    unlink($fixture . '/views/users/profile.php');
    unlink($fixture . '/views/layouts/app.php');
    rmdir($fixture . '/views/users'); rmdir($fixture . '/views/layouts');
    rmdir($fixture . '/views'); rmdir($fixture);
    echo json_encode($record, JSON_THROW_ON_ERROR);
});
$controller = new LSC\Users\ProfileController($auth, new LSC\Users\UserRepository($db), new LSC\Core\UploadStorage($fixture . '/storage'));
$controller->show();
