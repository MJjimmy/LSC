<?php
declare(strict_types=1);

// In-memory synthetic fixtures only. No bootstrap/.env, providers, uploads or live DB.
$root = dirname(__DIR__);
require $root . '/app/Groups/GroupRepository.php';
require $root . '/app/Groups/GroupService.php';
require $root . '/app/Notifications/NotificationRepository.php';

// Execute real repository queries with only explicit MySQL dialect adaptations.
// This verifies authorization/state behavior, NOT MySQL engine/concurrency behavior.
final class AdminFixturePDO extends PDO
{
    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public function exec(string $statement): int|false
    {
        if (str_contains($statement, 'CREATE TABLE IF NOT EXISTS group_join_requests')) {
            return parent::exec('CREATE TABLE IF NOT EXISTS group_join_requests (
                request_id INTEGER PRIMARY KEY, group_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL, requested_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(group_id, user_id))');
        }
        return parent::exec($statement);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace('INSERT IGNORE INTO', 'INSERT OR IGNORE INTO', $query), $options);
    }
}

function adminFixture(): AdminFixturePDO
{
    $db = new AdminFixturePDO();
    $db->exec("CREATE TABLE users (user_id INTEGER PRIMARY KEY, name TEXT, role TEXT, is_active INTEGER);
        CREATE TABLE study_groups (group_id INTEGER PRIMARY KEY, group_name TEXT, description TEXT,
            created_by INTEGER, is_private INTEGER);
        CREATE TABLE group_members (group_id INTEGER, user_id INTEGER, UNIQUE(group_id,user_id));
        CREATE TABLE notifications (notification_id INTEGER PRIMARY KEY, user_id INTEGER,
            type TEXT, title TEXT, body TEXT, link TEXT);
        INSERT INTO users VALUES (1,'Site administrator','admin',1),
            (2,'Group creator','student',1),(3,'Learner','student',1),(4,'Inactive admin','admin',0);
        INSERT INTO study_groups VALUES (10,'Synthetic private group','Fixture only',2,1),
            (11,'Synthetic public group','Fixture only',2,0);");
    return $db;
}

$failures = [];
$checks = 0;
function adminCheck(bool $ok, string $label): void
{
    global $failures, $checks;
    $checks++;
    if ($ok) {
        echo "PASS: {$label}\n";
    } else {
        $failures[] = $label;
        fwrite(STDERR, "FAIL: {$label}\n");
    }
}

$db = adminFixture();
$groups = new LSC\Groups\GroupRepository($db);
$service = new LSC\Groups\GroupService($db, $groups, new LSC\Notifications\NotificationRepository($db));
adminCheck($service->requestJoin(10, 1), 'Site administrator join action succeeds');
adminCheck($groups->isMember(10, 1), 'Site administrator joins immediately without group-owner approval');
adminCheck((int)$db->query('SELECT COUNT(*) FROM group_join_requests WHERE user_id = 1')->fetchColumn() === 0,
    'Site administrator has no pending approval request');

function profileFixture(array $config): array
{
    $pipes = [];
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', __DIR__ . '/admin-profile-fixture.php',
        base64_encode(json_encode($config, JSON_THROW_ON_ERROR))], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Profile fixture child unavailable'); }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $code = proc_close($process);
    if ($code !== 0 || $stderr !== '') { throw new RuntimeException('Profile fixture setup/execution failed: ' . $stderr); }
    return json_decode($stdout, true, 32, JSON_THROW_ON_ERROR);
}

$edited = profileFixture(['actor' => 1, 'target' => 2, 'method' => 'POST', 'post' => [
    'csrf_token' => 'synthetic-admin-csrf', 'update_profile' => '', 'name' => 'Admin-corrected learner',
    'institution' => 'Updated College', 'course' => 'Updated Course', 'bio' => 'Corrected bio',
]]);
adminCheck($edited['status'] === 200 && $edited['users'][1]['name'] === 'Admin-corrected learner',
    'Site administrator can edit another active learner profile');
adminCheck($edited['session']['user_id'] === 1 && $edited['session']['user_name'] === 'Site administrator'
    && $edited['session']['user_avatar'] === '/uploads/avatars/admin-owned.png',
    'Editing a learner preserves the acting administrator session identity');
adminCheck($edited['protected_fields_unchanged'], 'Profile edit cannot change authentication, role, contact or consent fields');

echo "RESULT: {$checks} checks; " . count($failures) . " failures; synthetic SQLite, no MySQL claim.\n";
exit($failures === [] ? 0 : 1);
