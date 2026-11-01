<?php
declare(strict_types=1);

// Isolated CLI regression: no database, network, session or credentials.
// Proves that emailed links are only ever built from a validated APP_URL.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'LSC\\')) {
        require_once dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    }
});

use LSC\Core\AppUrl;

$failures = [];
$checks = 0;
function appUrlCase(string $name, bool $passed): void
{
    global $failures, $checks;
    $checks++;
    if (!$passed) {
        $failures[] = $name;
        fwrite(STDOUT, "FAIL: {$name}\n");
        return;
    }
    fwrite(STDOUT, "PASS: {$name}\n");
}

function appUrlEnvironment(?string $appUrl, string $appEnv, ?string $host = null): void
{
    if ($appUrl === null) {
        putenv('APP_URL');
    } else {
        putenv('APP_URL=' . $appUrl);
    }
    putenv('APP_ENV=' . $appEnv);
    if ($host === null) {
        unset($_SERVER['HTTP_HOST'], $_SERVER['HTTPS']);
    } else {
        $_SERVER['HTTP_HOST'] = $host;
        unset($_SERVER['HTTPS']);
    }
}

// --- A valid configuration is normalised to a bare origin -------------------
appUrlEnvironment('https://lsc.example.edu', 'production');
appUrlCase('configured https origin is accepted', AppUrl::base() === 'https://lsc.example.edu');
appUrlCase('trailing slash is removed', AppUrl::validate('https://lsc.example.edu/') === 'https://lsc.example.edu');
appUrlCase('explicit port is preserved', AppUrl::validate('https://lsc.example.edu:8443') === 'https://lsc.example.edu:8443');
appUrlCase('link appends the path to the origin',
    AppUrl::link('invite.php?token=abc') === 'https://lsc.example.edu/invite.php?token=abc');

// --- Values that could redirect or leak a token are refused -----------------
foreach ([
    'http://lsc.example.edu' => 'plain http in production',
    'https://user:pass@lsc.example.edu' => 'embedded credentials',
    'https://lsc.example.edu/?next=evil' => 'query string',
    'https://lsc.example.edu/#fragment' => 'fragment',
    'https://lsc.example.edu/subdir' => 'subdirectory path',
    'https://lsc.example.edu:70000' => 'out-of-range port',
    "https://lsc.example.edu\n.evil" => 'control character',
    'javascript:alert(1)' => 'non-http scheme',
    'lsc.example.edu' => 'missing scheme',
    'https://' => 'missing host',
] as $candidate => $label) {
    appUrlCase('refused: ' . $label, AppUrl::validate($candidate) === null);
}

// --- A spoofed Host header cannot produce a public link --------------------
appUrlEnvironment(null, 'production', 'evil.example');
appUrlCase('missing APP_URL in production yields no base', AppUrl::base() === null);
appUrlCase('missing APP_URL in production yields no link', AppUrl::link('reset-password.php?token=abc') === null);

appUrlEnvironment('', 'production', 'evil.example');
appUrlCase('empty APP_URL in production yields no base', AppUrl::base() === null);

// --- Local development still works, but only over loopback -----------------
appUrlEnvironment(null, 'local', 'localhost:8000');
appUrlCase('loopback host is honoured outside production', AppUrl::base() === 'http://localhost:8000');
appUrlEnvironment(null, 'local', '127.0.0.1:8080');
appUrlCase('loopback IPv4 host is honoured outside production', AppUrl::base() === 'http://127.0.0.1:8080');
appUrlEnvironment(null, 'local', 'evil.example');
appUrlCase('non-loopback host is refused outside production', AppUrl::base() === null);
appUrlEnvironment(null, 'local', 'localhost:99999');
appUrlCase('out-of-range loopback port is refused', AppUrl::base() === null);
appUrlEnvironment(null, 'local', 'localhost:8000.evil.example');
appUrlCase('lookalike host is refused', AppUrl::base() === null);
appUrlEnvironment('http://localhost:8000', 'local');
appUrlCase('configured loopback http is accepted outside production', AppUrl::base() === 'http://localhost:8000');
appUrlEnvironment('http://localhost:8000', 'production');
appUrlCase('configured loopback http is refused in production', AppUrl::base() === null);

// --- Invitations fail closed instead of emailing a Host-derived link -------
final class AppUrlFixturePDO extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $query = str_replace(
            ['DATE_ADD(NOW(), INTERVAL 7 DAY)', ' FOR UPDATE', 'INSERT IGNORE INTO'],
            ["datetime('now', '+7 days')", '', 'INSERT OR IGNORE INTO'],
            $query
        );
        return parent::prepare($query, $options);
    }
}

$a = new AppUrlFixturePDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$a->exec('PRAGMA foreign_keys=ON');
$a->exec('CREATE TABLE users(user_id INTEGER PRIMARY KEY,name TEXT,email TEXT UNIQUE,password_hash TEXT,role TEXT,is_active INTEGER DEFAULT 1,avatar_path TEXT,email_verified_at TEXT DEFAULT CURRENT_TIMESTAMP,phone_number TEXT,notify_email INTEGER DEFAULT 1,notify_sms INTEGER DEFAULT 0)');
$a->exec('CREATE TABLE study_groups(group_id INTEGER PRIMARY KEY,group_name TEXT,description TEXT,created_by INTEGER,is_private INTEGER DEFAULT 0)');
$a->exec('CREATE TABLE group_members(member_id INTEGER PRIMARY KEY,group_id INTEGER,user_id INTEGER,UNIQUE(group_id,user_id))');
$a->exec("CREATE TABLE group_invitations(invitation_id INTEGER PRIMARY KEY,group_id INTEGER,invited_by INTEGER,invited_user_id INTEGER,email TEXT,token TEXT UNIQUE,status TEXT DEFAULT 'pending',created_at TEXT DEFAULT CURRENT_TIMESTAMP,expires_at TEXT)");
$a->exec('CREATE TABLE notifications(notification_id INTEGER PRIMARY KEY,user_id INTEGER,type TEXT,title TEXT,body TEXT,link TEXT,is_read INTEGER DEFAULT 0,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$a->exec("CREATE TABLE notification_queue(queue_id INTEGER PRIMARY KEY,user_id INTEGER,channel TEXT,recipient TEXT,subject TEXT,body TEXT,status TEXT DEFAULT 'pending',attempts INTEGER DEFAULT 0,created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
$a->exec("INSERT INTO users(user_id,name,email,password_hash,role) VALUES(1,'Synthetic owner','owner@example.invalid','x','student')");
$a->exec("INSERT INTO study_groups(group_id,group_name,description,created_by) VALUES(1,'Synthetic group','Offline fixture',1)");
$a->exec('INSERT INTO group_members(group_id,user_id) VALUES(1,1)');

$groups = new LSC\Groups\GroupRepository($a);
$invitations = new LSC\Invitations\InvitationRepository($a);
$notes = new LSC\Notifications\NotificationRepository($a);
$service = new LSC\Invitations\InvitationService(
    $a,
    $invitations,
    $groups,
    new LSC\Groups\GroupService($a, $groups, $notes),
    $notes,
    new LSC\Notifications\NotificationDispatcher($a)
);

appUrlEnvironment(null, 'production', 'evil.example');
$refused = $service->create(1, 1, 'student', 'someone@example.invalid');
$stored = (int)$a->query('SELECT COUNT(*) FROM group_invitations')->fetchColumn();
appUrlCase('invitation is refused without a trustworthy base URL', $refused['ok'] === false);
appUrlCase('refused invitation writes no row', $stored === 0);

appUrlEnvironment('https://lsc.example.edu', 'production');
$allowed = $service->create(1, 1, 'student', 'someone@example.invalid');
$queuedBody = (string)$a->query("SELECT body FROM notification_queue WHERE channel = 'email'")->fetchColumn();
$storedAfter = (int)$a->query('SELECT COUNT(*) FROM group_invitations')->fetchColumn();
appUrlCase('invitation is created once a base URL is configured', $allowed['ok'] === true && $storedAfter === 1);
appUrlCase('invitation email uses the configured origin',
    str_contains($queuedBody, 'https://lsc.example.edu/invite.php?token=')
    && !str_contains($queuedBody, 'evil.example'));

fwrite(STDOUT, json_encode(['checks' => $checks, 'failed' => count($failures), 'failures' => $failures]) . "\n");
exit($failures === [] ? 0 : 1);
