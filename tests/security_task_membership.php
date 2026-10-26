<?php
declare(strict_types=1);

// Disposable SQLite fixtures only. No bootstrap, network, mail or real database.
require dirname(__DIR__) . '/app/Tasks/TaskRepository.php';
require dirname(__DIR__) . '/app/Groups/GroupRepository.php';
require dirname(__DIR__) . '/app/Tasks/TaskService.php';

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE tasks (task_id INTEGER PRIMARY KEY, group_id INTEGER NOT NULL, assigned_to INTEGER NOT NULL, status TEXT NOT NULL)');
$db->exec('CREATE TABLE group_members (group_id INTEGER NOT NULL, user_id INTEGER NOT NULL, PRIMARY KEY (group_id, user_id))');
$db->exec("INSERT INTO tasks VALUES (1, 10, 100, 'pending')");
$service = new LSC\Tasks\TaskService(new LSC\Tasks\TaskRepository($db), new LSC\Groups\GroupRepository($db));

if ($service->markDone(1, 100) !== false || $db->query('SELECT status FROM tasks WHERE task_id = 1')->fetchColumn() !== 'pending') {
    throw new RuntimeException('REGRESSION: removed group member can mutate a formerly assigned task.');
}
fwrite(STDOUT, "PASS: removed member cannot change an assigned task.\n");
$db->exec('INSERT INTO group_members VALUES (10, 100)');
if (!$service->markDone(1, 100) || $db->query('SELECT status FROM tasks WHERE task_id = 1')->fetchColumn() !== 'done') {
    throw new RuntimeException('Current assigned group member must be able to mark a task done.');
}
if (!$service->markDone(1, 100)) { throw new RuntimeException('Authorized repeated completion must remain idempotent.'); }
$db->exec("UPDATE tasks SET status = 'pending'");
if ($service->markDone(1, 101) || $service->markDone(999, 100)) { throw new RuntimeException('Unrelated users and unknown tasks must be denied.'); }
$db->exec('DELETE FROM group_members');
if ($service->markDone(1, 100) || $db->query('SELECT status FROM tasks WHERE task_id = 1')->fetchColumn() !== 'pending') {
    throw new RuntimeException('Membership removal must revoke mutation rights.');
}
fwrite(STDOUT, "PASS: current membership, assignment, repeated completion and revocation checks.\n");
