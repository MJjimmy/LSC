<?php // Variables are provided by AccountController::forgotPassword(). ?>

<div class="auth-card-title">Forgot Password</div>
<div class="auth-card-sub">Request a secure reset link</div>

<?php if ($submitted): ?>
    <div class="auth-alert auth-alert-success"><i class="bi bi-envelope-check" style="margin-right:6px;"></i>If an active account matches that email, a reset link has been created.</div>
    <?php if ($developmentResetUrl): ?>
        <div class="auth-alert" style="background:#fdf6ec;border:1px solid #f5d9b0;color:#8a4d0a;word-break:break-all;">
            Development preview: <a href="<?= htmlspecialchars($developmentResetUrl) ?>" style="color:inherit;font-weight:600;">open reset link</a>
        </div>
    <?php endif; ?>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <div class="auth-field"><input type="email" name="email" placeholder="Email address" class="auth-input" required autocomplete="email"></div>
    <button type="submit" class="auth-btn">Send Reset Link</button>
</form>

<div class="auth-footer">Remembered it? <a href="login.php">Back to sign in</a></div>

<?php
$auth_content = ob_get_clean();
$pageTitle = 'Forgot Password | LSC';
require LSC_ROOT . '/views/layouts/auth.php';
?>
