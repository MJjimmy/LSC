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
$failures = [];
function uiFixCheck(bool $ok, string $label): void
{
    global $checks, $failures;
    $checks++;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . "\n";
    if (!$ok) { $failures[] = $label; }
}
function uiLeaveForms(string $html): array
{
    preg_match_all('/<form\b[^>]*>.*?<\/form>/si', $html, $matches);
    return array_values(array_filter($matches[0], static fn(string $form): bool => str_contains($form, 'name="leave_group"')));
}
$left = uiGroupFixture();
uiFixCheck(!$left['member_10'], 'A member can leave their own group through the real POST controller');
uiFixCheck($left['member_11'], 'Leaving one group preserves other memberships');
uiFixCheck($left['shared_content_unchanged'] && $left['shared_file_preserved'], 'Leaving preserves shared tasks, files, file bytes and chat history');
uiFixCheck($left['requests'] === [['group_id' => 10, 'user_id' => 3], ['group_id' => 11, 'user_id' => 2]], 'Only the leaving member\'s old request for this group is cleared');
uiFixCheck(($left['flash']['type'] ?? '') === 'success', 'Successful leave sets clear feedback for the groups page');
uiFixCheck($left['html'] === '', 'Successful POST redirects instead of rendering former group content');
uiFixCheck($left['visible_task_ids'] === [2], 'Dashboard task queries stop exposing assignments from a group the user left');
$get = uiGroupFixture(['method' => 'GET']);
$forms = uiLeaveForms($get['html']);
$form = $forms[0] ?? '';
uiFixCheck(count($forms) === 1 && (bool)preg_match('/method="POST"/i', $form) && str_contains($form, 'name="csrf_token"'), 'Members receive one POST-only Leave group form with CSRF protection');
uiFixCheck((bool)preg_match('/onsubmit="return confirm\(/', $form) && !(bool)preg_match('/<button\b[^>]*\bdisabled\b/i', $form), 'Member leave button is enabled and asks for confirmation');
uiFixCheck($get['member_10'] && $get['shared_content_unchanged'], 'GET requests cannot leave or mutate the group');
$creatorGet = uiGroupFixture(['actor' => 1, 'method' => 'GET']);
$creatorForm = uiLeaveForms($creatorGet['html'])[0] ?? '';
uiFixCheck((bool)preg_match('/<button\b[^>]*\bdisabled\b/i', $creatorForm) && str_contains($creatorGet['html'], 'Group creators cannot leave their own group.'), 'Creators see a disabled Leave group button with an explanation');
$creatorPost = uiGroupFixture(['actor' => 1]);
uiFixCheck($creatorPost['member_10'] && $creatorPost['shared_content_unchanged'] && ($creatorPost['flash']['type'] ?? '') === 'error', 'Forged creator POST is rejected server-side without deleting content');
$forged = uiGroupFixture(['tamper_role' => true, 'post' => ['leave_group' => '1', 'csrf_token' => 'owned-fixture-csrf', 'user_id' => '3', 'member_id' => '3', 'remove_member_user_id' => '3', 'delete_group' => '1']]);
uiFixCheck(!$forged['member_10'] && $forged['members'] === $left['members'] && $forged['shared_content_unchanged'] && $forged['shared_file_preserved'], 'Forged target IDs, stale role and extra manager fields cannot remove another member or delete the group');
$admin = uiGroupFixture(['actor' => 4, 'post' => ['leave_group' => '1', 'csrf_token' => 'owned-fixture-csrf', 'delete_group' => '1']]);
uiFixCheck(!$admin['member_10'] && count($admin['members']) === 5 && $admin['shared_content_unchanged'] && $admin['shared_file_preserved'], 'A noncreator administrator can leave their membership without triggering group deletion');
foreach ([[], ['csrf_token' => 'wrong'], ['csrf_token' => ['wrong']]] as $token) {
    $bad = uiGroupFixture(['post' => ['leave_group' => '1'] + $token]);
    uiFixCheck($bad['status'] === 403 && $bad['member_10'] && $bad['shared_content_unchanged'], 'Missing, invalid or nonscalar CSRF is rejected without mutation');
}
$outside = uiGroupFixture(['actor' => 5]);
uiFixCheck(!$outside['member_10'] && count($outside['members']) === 6 && $outside['shared_content_unchanged'], 'A nonmember cannot remove memberships or shared content');
foreach ([['anonymous' => true], ['inactive' => true]] as $identity) {
    $denied = uiGroupFixture($identity);
    uiFixCheck($denied['member_10'] && $denied['shared_content_unchanged'] && $denied['html'] === '', 'Anonymous or inactive accounts cannot execute the leave action');
}
$repeat = uiGroupFixture(['pre_left' => true]);
uiFixCheck(!$repeat['member_10'] && count($repeat['members']) === 5 && $repeat['shared_content_unchanged'] && ($repeat['flash']['type'] ?? '') !== 'success', 'Repeating a leave request cannot mutate other memberships or report a second removal');
$adminOutside = uiGroupFixture(['actor' => 4, 'pre_left' => true, 'method' => 'GET']);
uiFixCheck(uiLeaveForms($adminOutside['html']) === [], 'Administrator oversight without membership does not expose a Leave group form');
$rollback = uiGroupFixture(['fail_cleanup' => true]);
uiFixCheck($rollback['member_10'] && count($rollback['requests']) === 3 && $rollback['shared_content_unchanged'] && $rollback['shared_file_preserved'] && $rollback['expected_failure_log'], 'A deliberate cleanup failure rolls back membership and logs only its sanitized expected message');
foreach ([0, 999] as $id) {
    $missing = uiGroupFixture(['group' => $id]);
    uiFixCheck($missing['member_10'] && count($missing['members']) === 6 && $missing['shared_content_unchanged'], 'Invalid or missing groups cannot cause membership changes');
}
$feedback = uiGroupFixture(['method' => 'GET', 'route' => 'index', 'flash' => ['type' => 'success', 'message' => 'You have left the group. <script>owned-fixture</script>']]);
uiFixCheck(str_contains($feedback['html'], 'You have left the group.') && str_contains($feedback['html'], '&lt;script&gt;owned-fixture&lt;/script&gt;') && $feedback['flash'] === null, 'Groups page displays escaped leave feedback once and clears it');
if ($failures) { throw new RuntimeException(implode('; ', $failures)); }
echo 'PASS: ' . $checks . " UI fix checks.\n";
