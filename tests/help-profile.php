<?php
declare(strict_types=1);
// Hosted fixture: render real PHP views without a DB, provider calls or real users.
require_once dirname(__DIR__) . '/config/bootstrap.php';
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$n = 0;
function checkUi(bool $ok, string $label): void {
    global $n;
    if (!$ok) { throw new RuntimeException($label); }
    $n++;
}
$_SESSION = [];
$_SERVER['PHP_SELF'] = '/profile.php';
$profile = ['user_id'=>1, 'name'=>'Fixture <learner>', 'role'=>'student',
    'avatar_path'=>null, 'course'=>'', 'institution'=>'', 'bio'=>'Fixture <bio>',
    'group_count'=>0, 'file_count'=>0, 'completed_task_count'=>0,
    'created_at'=>'2026-01-01 00:00:00', 'phone_number'=>null, 'notify_email'=>1, 'notify_sms'=>0];
$error = $success = '';
foreach ([true, false] as $isOwnProfile) {
    ob_start();
    ob_start(); // Profile view consumes the inner buffer and renders its real layout.
    require LSC_ROOT . '/views/users/profile.php';
    $html = ob_get_clean();
    checkUi(!str_contains($html, 'oauth.php?provider=google'), 'Profile has no Google form');
    checkUi(!str_contains($html, 'Connect Google') && !str_contains($html, 'Continue with Google'), 'Profile has no extra Google button');
    checkUi(str_contains($html, 'href="help.php"'), 'Profile layout includes Help');
    checkUi(str_contains($html, 'Fixture &lt;learner&gt;') && !str_contains($html, 'Fixture <learner>'), 'Profile name stays escaped');
    checkUi(str_contains($html, 'name="update_profile"') === $isOwnProfile, 'Profile edit controls preserve ownership');
    checkUi(str_contains($html, 'name="update_notifications"') === $isOwnProfile, 'Notification settings preserve ownership');
}
foreach (['', 'android'] as $app) {
    $_GET = ['app'=>$app];
    $_SERVER['PHP_SELF'] = '/help.php';
    ob_start();
    require LSC_ROOT . '/public/help.php';
    $html = ob_get_clean();
    checkUi(str_contains($html, '<h1>Help &amp; getting started</h1>'), 'Guest Help renders');
    foreach (['account','groups','invitations','tasks','files','settings','android','troubleshooting'] as $topic) {
        checkUi(str_contains($html, 'id="'.$topic.'"') && str_contains($html, 'href="#'.$topic.'"'), 'Help topic has a matching anchor: '.$topic);
    }
    $suffix = $app === 'android' ? '?app=android' : '';
    checkUi(str_contains($html, 'href="login.php'.$suffix.'"'), 'Help keeps platform-specific sign-in route');
    checkUi(str_contains($html, 'href="register.php'.$suffix.'"'), 'Help keeps platform-specific registration route');
    checkUi(!str_contains($html, 'Connect Google'), 'Help does not direct users to the removed Profile control');
    checkUi(str_contains($html, 'Request to Join') && !str_contains($html, 'Join a public group directly'), 'Help matches actual group approval workflow');
}
foreach (['login','register'] as $authPage) {
    checkUi(str_contains(file_get_contents(LSC_ROOT.'/views/auth/'.$authPage.'.php'), '/views/auth/google.php'), 'Google remains on '.$authPage);
}
echo "PASS: {$n} real Help/Profile view checks; no database, Google exchange or email.\n";
