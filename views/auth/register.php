<?php // Variables are provided by AccountController::register(). ?>

<div class="auth-card-title">Create your account</div>
<div class="auth-card-sub">Join your academic workspace and confirm your email to get started.</div>

<?php if ($error): ?>
    <div class="auth-alert auth-alert-error" role="alert">
        <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="auth-alert auth-alert-success" role="status">
        <i class="bi bi-envelope-check" aria-hidden="true"></i>
        <span><?= htmlspecialchars($success) ?></span>
    </div>
    <p class="auth-verification-link">Did not receive it? <a href="verify-email.php">Request another verification email</a></p>
<?php endif; ?>

<?php if (!$success): ?>
<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <input type="hidden" name="next" value="<?= htmlspecialchars($_GET['next'] ?? '') ?>">

    <div class="auth-field">
        <label for="register-name">Full name</label>
        <input type="text" id="register-name" name="name" placeholder="Your full name"
               class="auth-input" required autocomplete="name"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
    </div>

    <div class="auth-field">
        <label for="register-email">Email address</label>
        <input type="email" id="register-email" name="email" placeholder="you@example.edu"
               class="auth-input" required autocomplete="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        <p class="auth-field-note">We will send a verification link to this address.</p>
    </div>

    <div class="auth-field">
        <label for="role">Your role</label>
        <select id="role" name="role" class="auth-input" required>
            <option value="">Select your role</option>
            <option value="student" <?= ($_POST['role'] ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
            <option value="lecturer" <?= ($_POST['role'] ?? '') === 'lecturer' ? 'selected' : '' ?>>Lecturer</option>
        </select>
        <p class="auth-field-note">Administrator access is assigned separately and cannot be self-selected.</p>
    </div>

    <div class="auth-field auth-pw-wrap">
        <label for="register-password">Password</label>
        <input type="password" id="register-password" name="password"
               placeholder="Create a secure password" class="auth-input"
               required minlength="8" autocomplete="new-password">
        <button type="button" class="auth-pw-toggle" data-password-toggle="register-password"
                aria-label="Show password" aria-pressed="false">
            <i class="bi bi-eye" aria-hidden="true"></i>
        </button>
        <p class="auth-field-note">Use 8–72 characters and include at least one symbol.</p>
    </div>

    <div class="auth-field auth-pw-wrap">
        <label for="register-confirm-password">Confirm password</label>
        <input type="password" id="register-confirm-password" name="confirm_password"
               placeholder="Enter your password again" class="auth-input"
               required minlength="8" autocomplete="new-password">
        <button type="button" class="auth-pw-toggle" data-password-toggle="register-confirm-password"
                aria-label="Show password confirmation" aria-pressed="false">
            <i class="bi bi-eye" aria-hidden="true"></i>
        </button>
    </div>

    <button type="submit" class="auth-btn">Create account <i class="bi bi-arrow-right" aria-hidden="true"></i></button>

    <p class="auth-legal">By creating an account, you agree to the <a href="terms.php">terms and conditions</a> and acknowledge the <a href="privacy.php">privacy policy</a>.</p>
</form>
<?php endif; ?>

<?php if (!$success): ?>
    <?php require LSC_ROOT . '/views/auth/google.php'; ?>
<?php endif; ?>

<div class="auth-footer">
    Already have an account? <a href="login.php<?= !empty($_GET['next']) ? '?next=' . rawurlencode($_GET['next']) : '' ?>">Sign in</a>
</div>

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
$pageTitle = 'Create Account | LSC';
$authStoryTitle = 'Build a better place for your group to work together.';
$authStoryCopy = 'Start a focused workspace for your course, project or study group, then bring everyone into the same clear view.';
$authStoryBadge = 'Free for students';
require LSC_ROOT . '/views/layouts/auth.php';
?>
