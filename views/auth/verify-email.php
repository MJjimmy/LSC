<?php // Variables are supplied by EmailVerificationController. ?>
<div class="auth-card-title">Verify your email</div>
<p class="auth-card-sub">Verify your mailbox before accessing LSC. Existing password accounts must also verify.</p>
<?php if (isset($_GET['registered'])): ?><div class="auth-alert auth-alert-success">Account created, but access is locked until you verify your email. Check your inbox and spam folder, or request a new link below.</div><?php endif; ?>
<?php if ($error): ?><div class="auth-alert auth-alert-error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="auth-alert auth-alert-success" role="status"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if (!$success): ?>
<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
    <?php if ($token !== ''): ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <p>Choose a new password to finish verification. This replaces any password previously set for this email.</p>
        <p><?= htmlspecialchars(\LSC\Users\PasswordPolicy::HELP) ?></p>
        <div class="auth-field"><input type="password" name="password" class="auth-input" placeholder="New passphrase (15+ characters)" minlength="15" required autocomplete="new-password"></div>
        <div class="auth-field"><input type="password" name="confirm_password" class="auth-input" placeholder="Confirm new passphrase" minlength="15" required autocomplete="new-password"></div>
        <button class="auth-btn" type="submit">Verify email and save password</button>
    <?php else: ?>
        <div class="auth-field"><input type="email" name="email" class="auth-input" placeholder="Your email address" maxlength="150" required autocomplete="email"></div>
        <button class="auth-btn" type="submit">Send verification email</button>
    <?php endif; ?>
</form>
<?php endif; ?>
<div class="auth-footer">
    <a href="login.php?next=<?= rawurlencode($next) ?>">Sign in</a>
    &middot; <a href="verify-email.php?next=<?= rawurlencode($next) ?>">Request another verification link</a>
</div>
<?php
$auth_content = ob_get_clean();
$pageTitle = 'Verify Email | LSC';
require LSC_ROOT . '/views/layouts/auth.php';
?>
