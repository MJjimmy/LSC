<?php
declare(strict_types=1);

// Synthetic CLI fixtures only: no bootstrap, real sessions, DB, provider or mail.
$root = dirname(__DIR__);
require $root . '/app/Tasks/TaskController.php';
$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) { throw new RuntimeException($message); }
    $checks++;
}

$normalizer = new ReflectionMethod(LSC\Tasks\TaskController::class, 'normalizeStatus');
foreach (['all', 'pending', 'done'] as $valid) {
    check($normalizer->invoke(null, $valid) === $valid, 'Valid status changed.');
}
$invalid = [null, false, 1, [], ['pending'], '', 'PENDING', "';globalThis.stage2Marker=1;//", '<img src=x>', 'pending&group_id=999'];
foreach ($invalid as $value) {
    check($normalizer->invoke(null, $value) === 'all', 'Invalid status did not normalize to all.');
}

// Render the actual task view with a tiny layout seam, not a substituted view.
// The view's existing final layout include only echoes the captured page here.
$fixtureRoot = sys_get_temp_dir() . '/lsc-stage2-' . bin2hex(random_bytes(10));
if (!mkdir($fixtureRoot . '/views/layouts', 0700, true)) { throw new RuntimeException('Fixture directory unavailable.'); }
$layout = $fixtureRoot . '/views/layouts/app.php';
file_put_contents($layout, '<?php echo $page_content;');
define('LSC_ROOT', $fixtureRoot);
function csrfToken(): string { return 'synthetic-stage2-csrf'; }
$_SESSION = ['user_id' => 1];
$myGroups = [['group_id' => 10, 'group_name' => 'Synthetic study group']];
$selected_group = 10;
$tasks = [];
$pendingCount = $doneCount = $overdueCount = 0;
try {
    foreach (['all', 'pending', 'done', ...$invalid] as $input) {
        $selected_status = $normalizer->invoke(null, $input);
        ob_start();
        ob_start();
        require $root . '/views/tasks/index.php';
        $html = ob_get_clean();
        check(str_contains($html, 'id="task-group-filter"'), 'Actual group filter missing.');
        check(str_contains($html, 'id="task-status-filter"'), 'Actual status filter missing.');
        check(preg_match('/<select\s+onchange=/i', $html) !== 1, 'Actual view retains inline filter code.');
        check(!str_contains($html, 'globalThis.stage2Marker'), 'Untrusted status entered the rendered page.');
        check(str_contains($html, 'name="csrf_token"'), 'Task form lost CSRF field.');
    }
} finally {
    unlink($layout);
    rmdir($fixtureRoot . '/views/layouts');
    rmdir($fixtureRoot . '/views');
    rmdir($fixtureRoot);
}

// Exercise actual shared CSRF verifier in isolated child processes because it exits.
$cases = [
    [[], [], 403],
    [[], ['csrf_token' => ''], 403],
    [[], ['csrf_token' => 'synthetic'], 403],
    [['csrf_token' => ''], ['csrf_token' => ''], 403],
    [['csrf_token' => ['bad']], ['csrf_token' => 'bad'], 403],
    [['csrf_token' => 'synthetic'], ['csrf_token' => []], 403],
    [['csrf_token' => 'synthetic'], ['csrf_token' => 'wrong'], 403],
    [['csrf_token' => 'synthetic'], ['csrf_token' => 'synthetic'], 200],
];
foreach ($cases as [$session, $post, $expected]) {
    $code = 'register_shutdown_function(static function (): void { echo "\\nSTATUS=" . (http_response_code() ?: 200); });'
        . 'require ' . var_export($root . '/app/Core/Csrf.php', true) . ';'
        . '$_SERVER["REQUEST_METHOD"]="POST"; $_SESSION=' . var_export($session, true) . '; $_POST=' . var_export($post, true) . ';'
        . '\\LSC\\Core\\Csrf::verify();';
    $pipes = [];
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Child process unavailable.'); }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    check(proc_close($process) === 0 && $stderr === '', 'CSRF fixture failed to execute.');
    check(str_contains($stdout, 'STATUS=' . $expected), 'Shared CSRF verifier returned unexpected status.');
}
echo "PASS: {$checks} status, actual-view and CSRF boundary assertions.\n";
