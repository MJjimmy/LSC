<?php
// Standalone form: never nest inside the email/password form.
\LSC\Users\OAuthProvider::allowAuthorizationRedirect();
$googleAvailable = \LSC\Users\OAuthProvider::enabled('google');
$googleNext = \LSC\Users\OAuthProvider::safeNext($_GET['next'] ?? null);
?>
<div style="margin-top:18px;text-align:center;">
    <div style="margin-bottom:12px;">or</div>
    <form method="POST" action="oauth.php?provider=google">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="next" value="<?= htmlspecialchars($googleNext, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <button type="submit" class="auth-btn" <?= $googleAvailable ? '' : 'disabled' ?>>Continue with Google</button>
    </form>
    <?php if (!$googleAvailable): ?><p>Google sign-in is currently unavailable. Use email and password.</p><?php endif; ?>
</div>
