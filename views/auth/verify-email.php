<?php // EmailVerificationController supplies escaped-on-output data. ?>
<div class="auth-card-title">Verify your email</div>
<div class="auth-card-sub">Your account cannot sign in until you confirm ownership of its email address.</div>
<?php if ($error): ?><div class="auth-alert auth-alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="auth-alert auth-alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($token !== ''): ?>
<form method="POST" action="verify-email.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <input type="hidden" name="action" value="confirm">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <button type="submit" class="auth-btn">Confirm email</button>
</form>
<?php endif; ?>
<form method="POST" action="verify-email.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <input type="hidden" name="action" value="resend">
    <div class="auth-field"><label for="verification-email">Email address</label><input id="verification-email" type="email" name="email" class="auth-input" required autocomplete="email" maxlength="150"></div>
    <button type="submit" class="auth-btn">Request another verification email</button>
</form>
<div class="auth-footer"><a href="login.php">Back to sign in</a></div>
<?php
$auth_content=ob_get_clean();
$pageTitle='Verify email | LSC';
require LSC_ROOT.'/views/layouts/auth.php';
