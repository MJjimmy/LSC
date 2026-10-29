<?php
declare(strict_types=1);

// CLI-only: real GroupController/Auth/repositories over owned synthetic SQLite.
if (PHP_SAPI !== 'cli' || !isset($argv[1])) { exit(2); }
$config = json_decode(base64_decode($argv[1], true), true, 32, JSON_THROW_ON_ERROR);
$source = dirname(__DIR__);
$base = realpath((string)(getenv('RUNNER_TEMP') ?: getenv('TMPDIR')));
if (!$base || str_starts_with($base . '/', $source . '/')) {
    throw new RuntimeException('Owned external fixture directory required.');
}
$fixture = $base . '/lsc-ui-group-' . bin2hex(random_bytes(12));
foreach (['views/groups', 'views/layouts', 'sessions', 'storage/group-files'] as $dir) {
    mkdir($fixture . '/' . $dir, 0700, true);
}
file_put_contents($fixture . '/views/groups/show.php', '<?php require ' . var_export($source . '/views/groups/show.php', true) . ';');
file_put_contents($fixture . '/views/groups/index.php', '<?php require ' . var_export($source . '/views/groups/index.php', true) . ';');
file_put_contents($fixture . '/views/layouts/app.php', '<?php echo $page_content;');
file_put_contents($fixture . '/storage/group-files/kept.txt', 'Synthetic shared file: must survive leaving.');
define('LSC_ROOT', $fixture);
spl_autoload_register(static function (string $class) use ($source): void {
    if (str_starts_with($class, 'LSC\\')) {
        $path = $source . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) { require $path; }
    }
});
function csrfToken(): string { return LSC\Core\Csrf::token(); }
// Only the existing MySQL ensure-table DDL is substituted. All membership,
// authority, task reads and mutations run their original SQL against SQLite.
final class UiFixturePDO extends PDO
{
    public function exec(string $statement): int|false
    {
        if (preg_match('/^\s*CREATE TABLE IF NOT EXISTS group_join_requests\s*\(/', $statement)) {
            if ((int)$this->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='group_join_requests'")->fetchColumn() !== 1) {
                throw new RuntimeException('Synthetic join-request table was not initialized.');
            }
            return 0;
        }
        return parent::exec($statement);
    }
}
$db = new UiFixturePDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE users (user_id INTEGER PRIMARY KEY, name TEXT, email TEXT, role TEXT, is_active INTEGER, avatar_path TEXT, password_hash TEXT);
    CREATE TABLE study_groups (group_id INTEGER PRIMARY KEY, group_name TEXT, description TEXT, created_by INTEGER, is_private INTEGER);
    CREATE TABLE group_members (group_id INTEGER, user_id INTEGER, joined_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(group_id,user_id));
    CREATE TABLE group_join_requests (request_id INTEGER PRIMARY KEY, group_id INTEGER, user_id INTEGER, requested_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE tasks (task_id INTEGER PRIMARY KEY, group_id INTEGER, assigned_to INTEGER, title TEXT, description TEXT, due_date TEXT, status TEXT);
    CREATE TABLE files (file_id INTEGER PRIMARY KEY, group_id INTEGER, uploaded_by INTEGER, file_name TEXT, file_path TEXT, file_type TEXT, file_size INTEGER, uploaded_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE messages (id INTEGER PRIMARY KEY, group_id INTEGER, user_id INTEGER, message TEXT, file_id INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE notifications (notification_id INTEGER PRIMARY KEY, user_id INTEGER, type TEXT, title TEXT, body TEXT, link TEXT);
    CREATE TABLE rate_limits (rl_key TEXT PRIMARY KEY, attempts INTEGER, expires_at TEXT);
    INSERT INTO users VALUES (1,'Creator','creator@example.invalid','student',1,NULL,'synthetic-proof-only'),
        (2,'Leaving learner','learner@example.invalid','student',1,NULL,'synthetic-proof-only'),
        (3,'Other member','member@example.invalid','lecturer',1,NULL,'synthetic-proof-only'),
        (4,'Site administrator','admin@example.invalid','admin',1,NULL,'synthetic-proof-only'),
        (5,'Outsider','outside@example.invalid','student',1,NULL,'synthetic-proof-only');
    INSERT INTO study_groups VALUES (10,'Synthetic group','Owned fixture only',1,1),(11,'Other group','Keep membership',1,0);
    INSERT INTO group_members (group_id,user_id) VALUES (10,1),(10,2),(10,3),(10,4),(11,1),(11,2);
    INSERT INTO group_join_requests (request_id,group_id,user_id) VALUES (1,10,2),(2,10,3),(3,11,2);
    INSERT INTO tasks VALUES (1,10,2,'Preserved assignment','Keep task data','2030-01-01','pending'),(2,11,2,'Other assignment','Keep other access','2030-01-02','pending');
    INSERT INTO files (file_id,group_id,uploaded_by,file_name,file_path,file_type,file_size) VALUES (1,10,2,'kept.txt','group-files/kept.txt','txt',43);
    INSERT INTO messages (id,group_id,user_id,message,file_id) VALUES (1,10,2,'Preserve prior messages',1);");
$before = [];
foreach (['study_groups', 'tasks', 'files', 'messages'] as $table) {
    $before[$table] = $db->query('SELECT * FROM ' . $table . ' ORDER BY 1')->fetchAll();
}
ini_set('session.use_cookies', '0');
ini_set('session.save_path', $fixture . '/sessions');
session_start();
$actorId = (int)($config['actor'] ?? 2);
$auth = new LSC\Core\Auth($db);
if (empty($config['anonymous'])) {
    $row = $db->query('SELECT * FROM users WHERE user_id = ' . $actorId)->fetch();
    if (!$row || !$auth->login($actorId, $row['name'], $row['role'], null, $row['password_hash'])) {
        throw new RuntimeException('Synthetic trusted login failed.');
    }
}
if (!empty($config['pre_left'])) {
    $db->prepare('DELETE FROM group_members WHERE group_id = 10 AND user_id = ?')->execute([$actorId]);
}
if (!empty($config['inactive'])) {
    $db->prepare('UPDATE users SET is_active = 0 WHERE user_id = ?')->execute([$actorId]);
}
if (!empty($config['tamper_role'])) { $_SESSION['user_role'] = 'admin'; }
if (isset($config['flash'])) { $_SESSION['group_flash'] = $config['flash']; }
if (!empty($config['fail_cleanup'])) {
    // A deliberate, isolated fault must roll back membership removal.
    ini_set('error_log', $fixture . '/expected-failure.log');
    $db->exec("CREATE TRIGGER fail_owned_cleanup BEFORE DELETE ON group_join_requests BEGIN SELECT RAISE(ABORT, 'Owned synthetic cleanup fault'); END;");
}
$_SESSION['csrf_token'] = 'owned-fixture-csrf';
$_SERVER['REQUEST_METHOD'] = $config['method'] ?? 'POST';
$_SERVER['PHP_SELF'] = '/group.php';
$_GET = ['id' => (string)($config['group'] ?? 10), 'tab' => 'tasks'];
$_POST = $config['post'] ?? ['leave_group' => '1', 'csrf_token' => 'owned-fixture-csrf'];
$_FILES = [];
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$groups = new LSC\Groups\GroupRepository($db);
$notifications = new LSC\Notifications\NotificationRepository($db);
$service = new LSC\Groups\GroupService($db, $groups, $notifications);
$tasks = new LSC\Tasks\TaskRepository($db);
$invitations = new LSC\Invitations\InvitationService($db, new LSC\Invitations\InvitationRepository($db), $groups, $service, $notifications, new LSC\Notifications\NotificationDispatcher($db));
ob_start();
register_shutdown_function(static function () use ($db, $groups, $tasks, $before, $fixture, $actorId): void {
    $html = '';
    while (ob_get_level() > 0) { $html = ob_get_clean() . $html; }
    $unchanged = true;
    foreach ($before as $table => $rows) {
        $unchanged = $unchanged && $db->query('SELECT * FROM ' . $table . ' ORDER BY 1')->fetchAll() === $rows;
    }
    $record = [
        'status' => http_response_code() ?: 200,
        'html' => $html,
        'member_10' => $groups->isMember(10, $actorId),
        'member_11' => $groups->isMember(11, $actorId),
        'members' => $db->query('SELECT group_id,user_id FROM group_members ORDER BY group_id,user_id')->fetchAll(),
        'requests' => $db->query('SELECT group_id,user_id FROM group_join_requests ORDER BY group_id,user_id')->fetchAll(),
        'shared_content_unchanged' => $unchanged,
        'shared_file_preserved' => is_file($fixture . '/storage/group-files/kept.txt'),
        'visible_task_ids' => array_map(static fn(array $task): int => (int)$task['task_id'], $tasks->forUser($actorId)),
        'flash' => $_SESSION['group_flash'] ?? null,
        'expected_failure_log' => is_file($fixture . '/expected-failure.log')
            && count(file($fixture . '/expected-failure.log', FILE_IGNORE_NEW_LINES)) === 1
            && preg_match('/^\[[^\r\n]+\] GroupService::leaveGroup failed\.\r?\n?$/', file_get_contents($fixture . '/expected-failure.log')) === 1,
    ];
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    foreach (glob($fixture . '/sessions/sess_*') ?: [] as $file) { unlink($file); }
    if (is_file($fixture . '/storage/group-files/kept.txt')) { unlink($fixture . '/storage/group-files/kept.txt'); }
    if (is_file($fixture . '/expected-failure.log')) { unlink($fixture . '/expected-failure.log'); }
    foreach (['views/groups/show.php', 'views/groups/index.php', 'views/layouts/app.php'] as $file) { unlink($fixture . '/' . $file); }
    foreach (['views/groups', 'views/layouts', 'views', 'sessions', 'storage/group-files', 'storage'] as $dir) { rmdir($fixture . '/' . $dir); }
    rmdir($fixture);
    echo json_encode($record, JSON_THROW_ON_ERROR);
});
$controller = new LSC\Groups\GroupController($auth, $groups, $service, new LSC\Core\UploadStorage($fixture . '/storage'), $tasks,
    new LSC\Files\FileRepository($db), new LSC\Chat\MessageRepository($db), $invitations, new LSC\Core\RateLimiter($db));
if (($config['route'] ?? 'show') === 'index') { $controller->index(); }
else { $controller->show(); }
