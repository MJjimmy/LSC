<?php // EmailVerificationController supplies escaped-on-output data. ?>
<div class="auth-card-title">Verify your email</div>
<div class="auth-card-sub">Confirm that this email belongs to you before accessing your workspace.</div>

<?php if ($error): ?><div class="auth-alert auth-alert-error" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><span><?= htmlspecialchars($error) ?></span></div><?php endif; ?>
<?php if ($success): ?><div class="auth-alert auth-alert-success" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i><span><?= htmlspecialchars($success) ?></span></div><?php endif; ?>

<?php if ($token !== ''): ?>
<form method="POST" action="verify-email.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <input type="hidden" name="action" value="confirm">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <button type="submit" class="auth-btn">Confirm email <i class="bi bi-check2" aria-hidden="true"></i></button>
</form>
<div class="auth-divider">or request a new link</div>
<?php endif; ?>

<form method="POST" action="verify-email.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <input type="hidden" name="action" value="resend">
    <div class="auth-field">
        <label for="verification-email">Email address</label>
        <input id="verification-email" type="email" name="email" placeholder="you@example.edu" class="auth-input" required autocomplete="email" maxlength="150">
    </div>
    <button type="submit" class="auth-btn">Send verification email <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
</form>

<div class="auth-footer"><a href="login.php">Back to sign in</a></div>
<?php
$auth_content = ob_get_clean();
$pageTitle = 'Verify Email | LSC';
$authStoryTitle = 'Trusted collaboration starts with a verified account.';
$authStoryCopy = 'Email verification protects group membership and helps ensure invitations reach the right person.';
$authStoryBadge = 'Verified academic identity';
require LSC_ROOT . '/views/layouts/auth.php';
