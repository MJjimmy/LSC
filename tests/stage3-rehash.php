<?php
declare(strict_types=1);
// In-memory fixture only, no bootstrap, sessions, provider, filesystem DB or email.
require dirname(__DIR__) . '/app/Users/UserRepository.php';
require dirname(__DIR__) . '/app/Users/AccountService.php';
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE users (user_id INTEGER PRIMARY KEY, name TEXT, email TEXT UNIQUE, password_hash TEXT, role TEXT, is_active INTEGER, avatar_path TEXT)');
$original = password_hash('SyntheticBefore123!', PASSWORD_BCRYPT, ['cost' => 4]);
$current = password_hash('SyntheticAfter123!', PASSWORD_DEFAULT);
$rehash = password_hash('SyntheticBefore123!', PASSWORD_DEFAULT);
$db->prepare('INSERT INTO users VALUES (1, ?, ?, ?, ?, 1, NULL)')->execute(['Synthetic', 'rehash@example.invalid', $original, 'student']);
$repository = new LSC\Users\UserRepository($db);
$failures = [];
$db->prepare('UPDATE users SET password_hash = ? WHERE user_id = 1')->execute([$current]);
$accepted = $repository->updatePasswordHash(1, $rehash, $original);
if ($accepted !== false || $db->query('SELECT password_hash FROM users WHERE user_id = 1')->fetchColumn() !== $current) {
    $failures[] = 'stale automatic rehash overwrites a concurrently reset password';
} else {
    echo "PASS: stale rehash cannot overwrite a reset password.\n";
}
$db->prepare('UPDATE users SET password_hash = ? WHERE user_id = 1')->execute([$original]);
$result = (new LSC\Users\AccountService($repository))->authenticate('rehash@example.invalid', 'SyntheticBefore123!');
$stored = $db->query('SELECT password_hash FROM users WHERE user_id = 1')->fetchColumn();
if (!$result['ok'] || $result['user']['password_hash'] !== $stored || $stored === $original
    || password_needs_rehash($stored, PASSWORD_DEFAULT) || !password_verify('SyntheticBefore123!', $stored)) {
    $failures[] = 'successful automatic rehash returns a stale authentication snapshot';
} else {
    echo "PASS: successful rehash returns the stored authentication snapshot.\n";
}
foreach ($failures as $failure) { fwrite(STDERR, "FAIL: {$failure}\n"); }
exit($failures === [] ? 0 : 1);
