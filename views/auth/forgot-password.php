<?php // Variables are provided by AccountController::forgotPassword(). ?>

<div class="auth-card-title">Reset your password</div>
<div class="auth-card-sub">Enter your account email and we will create a secure reset link.</div>

<?php if ($error): ?>
    <div class="auth-alert auth-alert-error" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><span><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
<?php endif; ?>

<?php if ($submitted): ?>
    <div class="auth-alert auth-alert-success" role="status"><i class="bi bi-envelope-check" aria-hidden="true"></i><span>If an active account matches that email, a reset link has been created.</span></div>
    <?php if ($developmentResetUrl): ?>
        <div class="auth-alert" style="background:#fff8eb;border-color:#eed7ae;color:#8a4d0a;word-break:break-all;">
            <i class="bi bi-tools" aria-hidden="true"></i>
            <span>Development preview: <a href="<?= htmlspecialchars($developmentResetUrl) ?>" style="color:inherit;font-weight:700;">open reset link</a></span>
        </div>
    <?php endif; ?>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <div class="auth-field">
        <label for="recovery-email">Email address</label>
        <input id="recovery-email" type="email" name="email" placeholder="you@example.edu" class="auth-input" required autocomplete="email">
        <p class="auth-field-note">For privacy, the response is the same whether or not an account exists.</p>
    </div>
    <button type="submit" class="auth-btn">Send reset link <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
</form>

<div class="auth-footer">Remembered your password? <a href="login.php">Back to sign in</a></div>

<?php
$auth_content = ob_get_clean();
$pageTitle = 'Reset Password | LSC';
$authStoryTitle = 'A secure route back to your academic workspace.';
$authStoryCopy = 'Reset links are time-limited and single-use, helping keep your group work and account protected.';
$authStoryBadge = 'Secure account recovery';
require LSC_ROOT . '/views/layouts/auth.php';
?>
