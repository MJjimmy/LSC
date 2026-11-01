<?php // Variables are provided by AccountController::resetPassword(). ?>

<div class="auth-card-title">Create New Password</div>
<div class="auth-card-sub"><?= htmlspecialchars(\LSC\Users\PasswordPolicy::HELP) ?></div>

<?php if ($error): ?><div class="auth-alert auth-alert-error"><i class="bi bi-exclamation-circle" style="margin-right:6px;"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="auth-alert auth-alert-success"><i class="bi bi-check-circle" style="margin-right:6px;"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>

<?php if (!$success && strlen($token) === 64): ?>
<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <div class="auth-field"><input type="password" name="password" placeholder="New passphrase" minlength="15" class="auth-input" required autocomplete="new-password"></div>
    <div class="auth-field"><input type="password" name="confirm_password" placeholder="Confirm new password" class="auth-input" required autocomplete="new-password"></div>
    <button type="submit" class="auth-btn">Update Password</button>
</form>
<?php endif; ?>

<div class="auth-footer"><a href="login.php">Back to sign in</a> &middot; <a href="verify-email.php">Verify email</a></div>

<?php
$auth_content = ob_get_clean();
$pageTitle = 'Reset Password | LSC';
require LSC_ROOT . '/views/layouts/auth.php';
?>
