<?php
declare(strict_types=1);
// Real controller and in-memory SQLite; no providers, live users or live databases.
function uiGroupFixture(array $config = []): array
{
    $pipes = [];
    $process = proc_open([PHP_BINARY, __DIR__ . '/ui-group-fixture.php', base64_encode(json_encode($config, JSON_THROW_ON_ERROR))],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Owned group fixture unavailable.'); }
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $code = proc_close($process);
    if ($code !== 0 || $error !== '') { throw new RuntimeException('Group fixture setup/execution failed: ' . $error); }
    return json_decode($output, true, 32, JSON_THROW_ON_ERROR);
}
$checks = 0;
function uiFixCheck(bool $ok, string $label): void
{
    global $checks;
    if (!$ok) { throw new RuntimeException($label); }
    $checks++;
    echo 'PASS: ' . $label . "\n";
}
$left = uiGroupFixture();
uiFixCheck(!$left['member_10'], 'A member can leave their own group through the real POST controller');
echo 'PASS: ' . $checks . " UI fix checks.\n";
