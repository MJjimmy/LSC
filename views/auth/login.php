<?php // Variables are provided by AccountController::login(). ?>

<div class="auth-card-title">Welcome Back</div>
<div class="auth-card-sub">Sign in to your account</div>

<?php if (isset($_GET['registered'])): ?>
    <div class="auth-alert auth-alert-success"><i class="bi bi-check-circle" style="margin-right:6px;"></i>Account created. Sign in to continue.</div>
<?php endif; ?>

<?php if (isset($_GET['deactivated'])): ?>
    <div class="auth-alert auth-alert-error"><i class="bi bi-exclamation-circle" style="margin-right:6px;"></i>Your account is inactive. Contact an administrator.</div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="auth-alert auth-alert-error">
        <i class="bi bi-exclamation-circle" style="margin-right:6px;"></i><?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <input type="hidden" name="next" value="<?= htmlspecialchars($_GET['next'] ?? '') ?>">

    <div class="auth-field">
        <input type="email" name="email" placeholder="Email address"
               class="auth-input" required autocomplete="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>

    <div class="auth-field auth-pw-wrap">
        <input type="password" name="password" id="password"
               placeholder="Password" class="auth-input" required>
        <button type="button" class="auth-pw-toggle" onclick="togglePassword()">
            <i id="eyeIcon" class="bi bi-eye"></i>
        </button>
    </div>

    <div style="text-align:right;margin:-4px 0 14px;font-size:12px;">
        <a href="forgot-password.php" style="color:var(--copper-dark);text-decoration:none;">Forgot password?</a>
    </div>

    <button type="submit" class="auth-btn">Sign In</button>

</form>

<div class="auth-footer">
    Don't have an account? <a href="register.php<?= !empty($_GET['next']) ? '?next=' . rawurlencode($_GET['next']) : '' ?>">Register</a>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>

<?php
$auth_content = ob_get_clean();
$pageTitle = 'Sign in — StudySync';
require STUDYSYNC_ROOT . '/views/layouts/auth.php';
?>
