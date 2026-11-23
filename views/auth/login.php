<?php // Variables are provided by AccountController::login(). ?>

<div class="auth-card-title">Welcome back</div>
<div class="auth-card-sub">Sign in to continue to your academic workspace.</div>

<?php if (isset($_GET['registered'])): ?>
    <div class="auth-alert auth-alert-success" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i><span>Account created. Verify your email before signing in.</span></div>
<?php endif; ?>

<?php if (isset($_GET['deactivated'])): ?>
    <div class="auth-alert auth-alert-error" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><span>Your account is inactive. Contact an administrator.</span></div>
<?php endif; ?>

<?php if (isset($_GET['expired'])): ?>
    <div class="auth-alert auth-alert-error" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><span>Your session expired. Please sign in again.</span></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="auth-alert auth-alert-error" role="alert">
        <i class="bi bi-exclamation-circle" aria-hidden="true"></i><span><?= htmlspecialchars($error) ?></span>
    </div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <input type="hidden" name="next" value="<?= htmlspecialchars($_GET['next'] ?? '') ?>">

    <div class="auth-field">
        <label for="login-email">Email address</label>
        <input type="email" name="email" placeholder="Email address"
               id="login-email" class="auth-input" required autocomplete="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>

    <div class="auth-field auth-pw-wrap">
        <label for="password">Password</label>
        <input type="password" name="password" id="password"
               placeholder="Enter your password" class="auth-input" required autocomplete="current-password">
        <button type="button" class="auth-pw-toggle" onclick="togglePassword()" aria-label="Show password" aria-pressed="false">
            <i id="eyeIcon" class="bi bi-eye" aria-hidden="true"></i>
        </button>
    </div>

    <div class="auth-form-meta">
        <a href="forgot-password.php">Forgot your password?</a>
    </div>

    <button type="submit" class="auth-btn">Sign in <i class="bi bi-arrow-right" aria-hidden="true"></i></button>

</form>

<?php require LSC_ROOT . '/views/auth/google.php'; ?>

<div class="auth-footer">
    New to LSC? <a href="register.php<?= !empty($_GET['next']) ? '?next=' . rawurlencode($_GET['next']) : '' ?>">Create an account</a>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    const button = icon.closest('button');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
        button.setAttribute('aria-label', 'Hide password');
        button.setAttribute('aria-pressed', 'true');
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
        button.setAttribute('aria-label', 'Show password');
        button.setAttribute('aria-pressed', 'false');
    }
}

// After logout, the previous session's service worker may still hold cached
// pages. If this login page was reached with ?clear-sw-cache=1, ask the
// service worker to purge every lsc-* cache immediately.
(function clearSWCached() {
    if (new URLSearchParams(window.location.search).has('clear-sw-cache')) {
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({ type: 'lsc:clear-cache' });
        }
    }
})();
</script>

<?php
$auth_content = ob_get_clean();
$pageTitle = 'Login | LSC';
require LSC_ROOT . '/views/layouts/auth.php';
?>
