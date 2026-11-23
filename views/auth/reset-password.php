<?php // Variables are provided by AccountController::resetPassword(). ?>

<div class="auth-card-title">Choose a new password</div>
<div class="auth-card-sub">Create a secure password you do not use for another account.</div>

<?php if ($error): ?><div class="auth-alert auth-alert-error" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><span><?= htmlspecialchars($error) ?></span></div><?php endif; ?>
<?php if ($success): ?><div class="auth-alert auth-alert-success" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i><span><?= htmlspecialchars($success) ?></span></div><?php endif; ?>

<?php if (!$success && strlen($token) === 64): ?>
<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <div class="auth-field auth-pw-wrap">
        <label for="new-password">New password</label>
        <input id="new-password" type="password" name="password" placeholder="Create a secure password" class="auth-input" required minlength="8" autocomplete="new-password">
        <button type="button" class="auth-pw-toggle" data-password-toggle="new-password" aria-label="Show new password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
        <p class="auth-field-note">Use 8–72 characters and include at least one symbol.</p>
    </div>
    <div class="auth-field auth-pw-wrap">
        <label for="confirm-new-password">Confirm new password</label>
        <input id="confirm-new-password" type="password" name="confirm_password" placeholder="Enter the password again" class="auth-input" required minlength="8" autocomplete="new-password">
        <button type="button" class="auth-pw-toggle" data-password-toggle="confirm-new-password" aria-label="Show password confirmation" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
    </div>
    <button type="submit" class="auth-btn">Update password <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
</form>
<?php endif; ?>

<div class="auth-footer"><a href="login.php">Back to sign in</a></div>

<script>
document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
        const input = document.getElementById(button.dataset.passwordToggle);
        const icon = button.querySelector('i');
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        icon.className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
        button.setAttribute('aria-pressed', showing ? 'false' : 'true');
        button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
    });
});
</script>

<?php
$auth_content = ob_get_clean();
$pageTitle = 'Choose New Password | LSC';
$authStoryTitle = 'Protect the work your group depends on.';
$authStoryCopy = 'A strong, unique password helps keep your shared files, plans and conversations in the right hands.';
$authStoryBadge = 'Single-use reset link';
require LSC_ROOT . '/views/layouts/auth.php';
?>
