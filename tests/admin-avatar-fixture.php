<?php
declare(strict_types=1);
// Actual avatar route bytes, real Auth/UserRepository/UploadStorage and synthetic SQLite.
// Only bootstrap is replaced: owned storage/session paths and injected in-memory PDO.
if (PHP_SAPI !== 'cli' || !isset($argv[1])) { exit(2); }
$config = json_decode(base64_decode($argv[1], true), true, 32, JSON_THROW_ON_ERROR);
$root = dirname(__DIR__);
if (file_exists($root . '/.env') || is_link($root . '/.env')) {
    throw new RuntimeException('Refusing source environment file');
}
$temp = getenv('RUNNER_TEMP') ?: getenv('TMPDIR');
if (!$temp || !is_dir($temp)) { throw new RuntimeException('Designated fixture temp directory missing'); }
$fixture = $temp . '/lsc-admin-avatar-' . bin2hex(random_bytes(12));
foreach (['public', 'config', 'storage/uploads/avatars', 'sessions'] as $directory) {
    mkdir($fixture . '/' . $directory, 0700, true);
}
copy($root . '/public/avatar.php', $fixture . '/public/avatar.php');
if (hash_file('sha256', $fixture . '/public/avatar.php') !== hash_file('sha256', $root . '/public/avatar.php')) {
    throw new RuntimeException('Avatar route copy differs from the candidate source');
}
file_put_contents($fixture . '/config/bootstrap.php', '<?php /* SQLite fixture bootstrap already initialized. */');
define('LSC_ROOT', $root);
define('LSC_STORAGE', $fixture . '/storage');
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'LSC\\')) {
        require_once $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    }
});
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE users (user_id INTEGER PRIMARY KEY, name TEXT, email TEXT, role TEXT,
    password_hash TEXT NOT NULL DEFAULT 'synthetic-avatar-proof',
    is_active INTEGER, bio TEXT, institution TEXT, course TEXT, avatar_path TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP, phone_number TEXT, notify_email INTEGER, notify_sms INTEGER);
    CREATE TABLE group_members (group_id INTEGER,user_id INTEGER);
    CREATE TABLE files (uploaded_by INTEGER);
    CREATE TABLE tasks (assigned_to INTEGER,status TEXT);
    INSERT INTO users (user_id,name,email,role,is_active,avatar_path) VALUES
        (1,'Administrator','admin@example.invalid','admin',1,'/uploads/avatars/fixture.png'),
        (2,'Learner','learner@example.invalid','student',1,'/uploads/avatars/fixture.png'),
        (3,'Inactive learner','inactive@example.invalid','student',0,'/uploads/avatars/fixture.png'),
        (4,'Demoted administrator','demoted@example.invalid','lecturer',1,NULL),
        (5,'Inactive administrator','disabled@example.invalid','admin',0,NULL);");
(new ReflectionProperty(LSC\Core\Database::class, 'connection'))->setValue(null, $db);
$image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true);
file_put_contents($fixture . '/storage/uploads/avatars/fixture.png', $image);
ini_set('session.use_cookies', '0');
ini_set('session.save_path', $fixture . '/sessions');
session_start();
$actorId = (int)($config['actor'] ?? 1);
$statement = $db->prepare('SELECT * FROM users WHERE user_id = ?');
$statement->execute([$actorId]);
$actor = $statement->fetch();
if (!$actor) { throw new RuntimeException('Unknown synthetic avatar actor'); }
if (!$actor['is_active']) {
    $db->prepare('UPDATE users SET is_active = 1 WHERE user_id = ?')->execute([$actorId]);
}
if ((new LSC\Core\Auth($db))->login($actorId, $actor['name'], $actor['role'], $actor['avatar_path'], $actor['password_hash']) !== true) {
    throw new RuntimeException('Synthetic trusted avatar login failed');
}
if (!$actor['is_active']) {
    $db->prepare('UPDATE users SET is_active = 0 WHERE user_id = ?')->execute([$actorId]);
}
$_SESSION['user_role'] = 'admin'; // Real request authorization must refresh this stale value.
$_GET = ['user_id' => (string)($config['target'] ?? 3)];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
register_shutdown_function(static function () use ($db, $fixture, $image): void {
    $bytes = '';
    while (ob_get_level() > 0) { $bytes = ob_get_clean() . $bytes; }
    $result = ['status' => http_response_code() ?: 200,
        'served_fixture_bytes' => $bytes === $image,
        'inactive_target_still_inactive' => (int)$db->query('SELECT is_active FROM users WHERE user_id = 3')->fetchColumn() === 0];
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    foreach (glob($fixture . '/sessions/sess_*') ?: [] as $session) { unlink($session); }
    foreach (['public/avatar.php', 'config/bootstrap.php', 'storage/uploads/avatars/fixture.png'] as $file) { unlink($fixture . '/' . $file); }
    foreach (['public', 'config', 'storage/uploads/avatars', 'storage/uploads', 'storage', 'sessions'] as $directory) { rmdir($fixture . '/' . $directory); }
    rmdir($fixture);
    echo json_encode($result, JSON_THROW_ON_ERROR);
});
require $fixture . '/public/avatar.php';
