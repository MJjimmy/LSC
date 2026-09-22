<?php // Variables are provided by AccountController::register(). ?>

<div class="auth-card-title">Create Account</div>
<div class="auth-card-sub">Join LSC today</div>

<?php if ($error): ?>
    <div class="auth-alert auth-alert-error">
        <i class="bi bi-exclamation-circle" style="margin-right:6px;"></i><?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="auth-alert auth-alert-success">
        <i class="bi bi-check-circle" style="margin-right:6px;"></i><?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <input type="hidden" name="next" value="<?= htmlspecialchars($_GET['next'] ?? '') ?>">

    <div class="auth-field">
        <input type="text" name="name" placeholder="Full name"
               class="auth-input" required
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
    </div>

    <div class="auth-field">
        <input type="email" name="email" placeholder="Email address"
               class="auth-input" required autocomplete="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>

    <div class="auth-field">
        <label for="role" style="display:block;font-size:12px;font-weight:600;color:#526277;margin-bottom:6px;">Register as</label>
        <select id="role" name="role" class="auth-input" required>
            <option value="">Select your role</option>
            <option value="student" <?= ($_POST['role'] ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
            <option value="lecturer" <?= ($_POST['role'] ?? '') === 'lecturer' ? 'selected' : '' ?>>Lecturer</option>
        </select>
    </div>

    <div class="auth-field">
        <input type="password" name="password" placeholder="Password (min 8 chars)"
               class="auth-input" required>
    </div>

    <div class="auth-field">
        <input type="password" name="confirm_password" placeholder="Confirm password"
               class="auth-input" required>
    </div>

    <button type="submit" class="auth-btn">Create Account</button>

</form>

<div class="auth-footer">
    Already have an account? <a href="login.php">Sign In</a>
</div>

<?php
$auth_content = ob_get_clean();
$pageTitle = 'Register | LSC';
require LSC_ROOT . '/views/layouts/auth.php';
?>
