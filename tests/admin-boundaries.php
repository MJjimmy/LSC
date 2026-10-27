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

function profileFixture(array $config, string $fixture = 'admin-profile-fixture.php'): array
{
    if (!in_array($fixture, ['admin-profile-fixture.php', 'admin-avatar-fixture.php'], true)) {
        throw new RuntimeException('Unknown fixture');
    }
    $pipes = [];
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', __DIR__ . '/' . $fixture,
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

$adminView = profileFixture(['actor' => 1, 'target' => 2]);
adminCheck(str_contains($adminView['html'], 'target@example.invalid')
    && str_contains($adminView['html'], '+27820000002'), 'Administrator sees the target email and phone');
adminCheck(str_contains($adminView['html'], 'name="update_profile"'), 'Administrator receives working profile edit controls');
adminCheck(!str_contains($adminView['html'], 'name="update_notifications"'), 'Administrator cannot edit another user notification consent');
adminCheck(!str_contains($adminView['html'], 'synthetic-not-a-password-hash'), 'Credential material never enters the profile HTML');
$inactive = profileFixture(['actor' => 1, 'target' => 4]);
adminCheck($inactive['status'] === 200 && str_contains($inactive['html'], 'Inactive learner'),
    'Administrator can inspect an inactive account without reactivating it');

foreach ([2, 3] as $actor) {
    $target = $actor === 2 ? 3 : 2;
    $read = profileFixture(['actor' => $actor, 'target' => $target, 'session_role' => 'admin']);
    adminCheck($read['status'] === 200 && !str_contains($read['html'], 'name="update_profile"')
        && !str_contains($read['html'], 'target@example.invalid') && !str_contains($read['html'], 'creator@example.invalid')
        && !str_contains($read['html'], '+27820000002'),
        'Ordinary user/group creator cannot obtain admin controls or contact details by forging a session role: ' . $actor);
    $denied = profileFixture(['actor' => $actor, 'target' => $target, 'method' => 'POST', 'post' => [
        'csrf_token' => 'synthetic-admin-csrf', 'name' => 'Unauthorized change', 'update_profile' => '',
    ]]);
    adminCheck($denied['status'] === 403 && $denied['users'][$target - 1]['name'] !== 'Unauthorized change',
        'Cross-account profile write is forbidden for non-administrator: ' . $actor);
}
$badCsrf = profileFixture(['actor' => 1, 'target' => 2, 'method' => 'POST', 'post' => [
    'csrf_token' => 'wrong-token', 'name' => 'Unauthorized change', 'update_profile' => '',
]]);
adminCheck($badCsrf['status'] === 403 && $badCsrf['users'][1]['name'] === 'Target learner', 'Admin profile editing still requires valid CSRF');
$consent = profileFixture(['actor' => 1, 'target' => 2, 'method' => 'POST', 'post' => [
    'csrf_token' => 'synthetic-admin-csrf', 'update_notifications' => '', 'phone_number' => '+27829999999', 'notify_sms' => '1',
]]);
adminCheck($consent['status'] === 403 && $consent['protected_fields_unchanged'], 'Direct admin POST cannot change target contact or notification preferences');
$injected = profileFixture(['actor' => 1, 'target' => 2, 'method' => 'POST', 'post' => [
    'csrf_token' => 'synthetic-admin-csrf', 'update_profile' => '', 'name' => 'Allowed profile change',
    'role' => 'admin', 'email' => 'hijack@example.invalid', 'password_hash' => 'injected', 'is_active' => '0', 'notify_sms' => '1',
]]);
adminCheck($injected['status'] === 200 && $injected['protected_fields_unchanged'], 'Profile edit ignores forged role, identity, password, active-state and consent fields');
$own = profileFixture(['actor' => 2, 'target' => 2, 'method' => 'POST', 'post' => [
    'csrf_token' => 'synthetic-admin-csrf', 'update_profile' => '', 'name' => 'Learner own edit',
]]);
adminCheck($own['status'] === 200 && $own['users'][1]['name'] === 'Learner own edit'
    && $own['session']['user_name'] === 'Learner own edit', 'Learner retains normal own-profile editing and session refresh');
$missing = profileFixture(['actor' => 1, 'target' => 999, 'method' => 'POST', 'post' => [
    'csrf_token' => 'synthetic-admin-csrf', 'name' => 'Ghost edit', 'update_profile' => '',
]]);
adminCheck($missing['status'] === 404, 'Missing target profile fails closed');

$repeatDb = adminFixture();
$repeatGroups = new LSC\Groups\GroupRepository($repeatDb);
$repeatService = new LSC\Groups\GroupService($repeatDb, $repeatGroups, new LSC\Notifications\NotificationRepository($repeatDb));
$repeatGroups->addJoinRequest(10, 1);
adminCheck($repeatService->requestJoin(10, 1) && $repeatService->requestJoin(10, 1)
    && (int)$repeatDb->query('SELECT COUNT(*) FROM group_members WHERE user_id = 1')->fetchColumn() === 1,
    'Repeated admin joins remain idempotent');
adminCheck((int)$repeatDb->query('SELECT COUNT(*) FROM group_join_requests WHERE user_id = 1')->fetchColumn() === 0,
    'Admin direct join removes an earlier pending request');
adminCheck(!$repeatService->requestJoin(999, 1) && !$repeatService->requestJoin(10, 4),
    'Nonexistent group and inactive administrator cannot join');
$_POST = ['user_role' => 'admin'];
adminCheck($repeatService->requestJoin(11, 2) && !$repeatGroups->isMember(11, 2),
    'Group creator remains subject to approval and cannot forge the site-admin role');

$temp = getenv('RUNNER_TEMP') ?: getenv('TMPDIR');
if (!$temp || !is_dir($temp)) { throw new RuntimeException('Designated fixture temp directory missing'); }
$viewRoot = $temp . '/lsc-admin-views-' . bin2hex(random_bytes(12));
mkdir($viewRoot . '/views/layouts', 0700, true);
file_put_contents($viewRoot . '/views/layouts/app.php', '<?php echo $page_content;');
define('LSC_ROOT', $viewRoot);
function csrfToken(): string { return 'synthetic-admin-view-csrf'; }
function adminRender(string $view, array $variables): string
{
    extract($variables, EXTR_SKIP);
    ob_start(); ob_start();
    require dirname(__DIR__) . '/views/' . $view . '.php';
    return ob_get_clean();
}
try {
    $discoveryDb = adminFixture();
    $discoveryGroups = new LSC\Groups\GroupRepository($discoveryDb);
    $available = $discoveryGroups->publicGroupsAvailableTo(1);
    adminCheck(array_column($available, 'group_id') === [10, 11], 'Administrator can discover both private and public unjoined groups');
    adminCheck(array_column($discoveryGroups->publicGroupsAvailableTo(3), 'group_id') === [11], 'Ordinary discovery still excludes private groups');
    $_SESSION = ['user_id' => 1, 'user_role' => 'admin'];
    $html = adminRender('groups/index', ['myGroups' => [], 'publicGroups' => $available]);
    adminCheck(str_contains($html, 'Join directly') && str_contains($html, 'Synthetic private group')
        && str_contains($html, 'name="csrf_token"'), 'Administrator group discovery exposes a deliberate CSRF-protected direct-join form');
    adminCheck((int)$discoveryDb->query('SELECT COUNT(*) FROM group_members')->fetchColumn() === 0,
        'Reading discovery and rendering forms does not join groups');
    $stats = ['total_users' => 1, 'total_groups' => 0, 'total_tasks' => 0, 'total_files' => 0];
    $html = adminRender('users/admin', ['stats' => $stats, 'users' => [[
        'user_id' => 2, 'name' => 'Target learner', 'email' => 'target@example.invalid', 'role' => 'student', 'is_active' => 1,
    ]]]);
    adminCheck(str_contains($html, 'href="profile.php?user_id=2"'), 'Admin user management links directly to the correct learner profile');
} finally {
    unlink($viewRoot . '/views/layouts/app.php'); rmdir($viewRoot . '/views/layouts');
    rmdir($viewRoot . '/views'); rmdir($viewRoot);
}

$adminPhoto = profileFixture(['actor' => 1, 'target' => 3], 'admin-avatar-fixture.php');
adminCheck($adminPhoto['status'] === 200 && $adminPhoto['served_fixture_bytes']
    && $adminPhoto['inactive_target_still_inactive'], 'Site administrator can read an inactive profile avatar without reactivating it');
foreach ([2, 4] as $actor) {
    $photo = profileFixture(['actor' => $actor, 'target' => 3], 'admin-avatar-fixture.php');
    adminCheck($photo['status'] === 404 && !$photo['served_fixture_bytes'],
        'Ordinary or demoted actor cannot read inactive avatars despite a stale admin session role: ' . $actor);
}
$disabledPhoto = profileFixture(['actor' => 5, 'target' => 3], 'admin-avatar-fixture.php');
adminCheck(!$disabledPhoto['served_fixture_bytes'], 'Inactive administrator cannot read an inactive avatar');
$publicPhoto = profileFixture(['actor' => 2, 'target' => 1], 'admin-avatar-fixture.php');
adminCheck($publicPhoto['status'] === 200 && $publicPhoto['served_fixture_bytes'], 'Active-profile avatar access remains compatible for authenticated learners');
$missingPhoto = profileFixture(['actor' => 1, 'target' => 999], 'admin-avatar-fixture.php');
adminCheck($missingPhoto['status'] === 404 && !$missingPhoto['served_fixture_bytes'], 'Missing avatar target fails closed');

echo "RESULT: {$checks} checks; " . count($failures) . " failures; synthetic SQLite, no MySQL claim.\n";
exit($failures === [] ? 0 : 1);
