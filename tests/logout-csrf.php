<?php
declare(strict_types=1);
/**
 * Sign-out must be a CSRF-protected POST.
 *
 * A GET/`<img src>`/link must never end a session, so the only sign-out
 * controls the interface renders are POST forms carrying the session token.
 * This renders the real shared layout (no database needed for a signed-out
 * render) and inspects the emitted markup, then checks the controller's guard
 * ordering. The 405 branch itself exits, so it is asserted structurally.
 */

use LSC\Core\Csrf;

define('LSC_ROOT', dirname(__DIR__));
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'LSC\\')) {
        require_once LSC_ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    }
});

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$checks = 0;
$failures = [];
function check(string $label, bool $passed): void
{
    global $checks, $failures;
    $checks++;
    if ($passed) {
        echo "PASS: {$label}\n";
        return;
    }
    $failures[] = $label;
    echo "FAIL: {$label}\n";
}

// --- Render the real layout -------------------------------------------------
$_SERVER['PHP_SELF'] = '/profile.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
unset($_SESSION['user_id']);
$pageTitle = 'Logout CSRF fixture';
$page_content = '';

ob_start();
require LSC_ROOT . '/views/layouts/app.php';
$html = (string) ob_get_clean();

preg_match_all('#<form\b[^>]*>#i', $html, $formTags);
preg_match_all('#</form>#i', $html, $formEnds);
$logoutForms = array_values(array_filter(
    $formTags[0] ?? [],
    static fn (string $tag): bool => (bool) preg_match('#action\s*=\s*["\']logout\.php["\']#i', $tag)
));

check('the layout renders a sign-out form', count($logoutForms) >= 1);
check('every sign-out control is a POST form', count($logoutForms) === 2);
// PHP 8.1-compatible: the release runner only guarantees 8.1.
$allPost = $logoutForms !== [];
foreach ($logoutForms as $tag) {
    if (!preg_match('#method\s*=\s*["\']post["\']#i', $tag)) {
        $allPost = false;
    }
}
check('no sign-out form uses GET', $allPost);

// No anchor/img may point at logout.php: that is the bug this guards against.
check('no link points at logout.php', !preg_match('#<(?:a|img|area)\b[^>]*(?:href|src)\s*=\s*["\'][^"\']*logout\.php#i', $html));

// Each form must carry a hidden token equal to the live session token.
$sessionToken = Csrf::token();
preg_match_all('#<input\b[^>]*name\s*=\s*["\']csrf_token["\'][^>]*>#i', $html, $tokenInputs);
$values = [];
foreach ($tokenInputs[0] ?? [] as $input) {
    if (preg_match('#value\s*=\s*["\']([^"\']*)["\']#i', $input, $m)) {
        $values[] = $m[1];
    }
}
check('each sign-out form carries a CSRF token', count($values) >= 2);
check('tokens are 64 hex characters', count($values) === 2
    && count(array_filter($values, static fn (string $v): bool => (bool) preg_match('/^[0-9a-f]{64}$/', $v))) === 2);
check('tokens match the live session token', $values === [$sessionToken, $sessionToken]);

// The token must be generated, not a static literal baked into the template.
$_SESSION['csrf_token'] = null;
unset($_SESSION['csrf_token']);
$fresh = Csrf::token();
check('the token is per-session and not a template constant', $fresh !== $sessionToken && strlen($fresh) === 64);

// --- Controller ordering ----------------------------------------------------
$controller = (string) file_get_contents(LSC_ROOT . '/app/Users/AccountController.php');
$methodStart = strpos($controller, 'public function logout()');
check('the controller has a logout method', $methodStart !== false);
$body = $methodStart === false ? '' : substr($controller, $methodStart);

$postGuard = strpos($body, "!== 'POST'");
$reject = strpos($body, 'http_response_code(405)');
$verify = strpos($body, 'Csrf::verify()');
$destroy = strpos($body, '$this->auth->logout()');

check('logout rejects anything that is not a POST', $postGuard !== false);
check('the non-POST branch answers 405 and advertises Allow: POST',
    $reject !== false && strpos($body, "header('Allow: POST')") !== false);
check('the token is verified before the session is destroyed',
    $verify !== false && $destroy !== false && $verify < $destroy);
check('the POST guard runs before the token check',
    $postGuard !== false && $verify !== false && $postGuard < $verify);

echo json_encode(['checks' => $checks, 'failed' => count($failures), 'failures' => $failures]), "\n";
exit($failures === [] ? 0 : 1);
