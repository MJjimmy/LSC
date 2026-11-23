<?php
// Standalone form: never nest inside the email/password form.
\LSC\Users\OAuthProvider::allowAuthorizationRedirect();
$googleAvailable = \LSC\Users\OAuthProvider::enabled('google');
$googleNext = \LSC\Users\OAuthProvider::safeNext($_GET['next'] ?? null);
$googleAndroid = ($_GET['app'] ?? null) === 'android' || !empty($_SESSION['android_client']);
if (($_GET['app'] ?? null) === 'android') $_SESSION['android_client'] = true;
?>
<div class="auth-provider">
    <div class="auth-divider">or continue with</div>
    <?php if ($googleAndroid): ?>
        <a class="auth-btn auth-btn-google" href="lscapp://google"><i class="bi bi-google" aria-hidden="true"></i> Continue with Google</a>
    <?php else: ?>
    <form method="POST" action="oauth.php?provider=google">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="next" value="<?= htmlspecialchars($googleNext, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <button type="submit" class="auth-btn auth-btn-google" <?= $googleAvailable ? '' : 'disabled' ?>><i class="bi bi-google" aria-hidden="true"></i> Continue with Google</button>
    </form>
    <?php endif; ?>
    <?php if (!$googleAvailable): ?><p class="auth-provider-note">Google sign-in is currently unavailable. Use your email and password instead.</p><?php endif; ?>
</div>
