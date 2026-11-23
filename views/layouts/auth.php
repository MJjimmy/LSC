<?php
$pageTitle = $pageTitle ?? 'LSC | Learn. Share. Connect.';
$isAndroid = ($_GET['app'] ?? '') === 'android';
$authStoryTitle = $authStoryTitle ?? 'A clearer place for academic work to move forward.';
$authStoryCopy = $authStoryCopy ?? 'Bring tasks, deadlines, files and group conversation into one focused workspace.';
$authStoryBadge = $authStoryBadge ?? 'Your work stays connected';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>

<?php require LSC_ROOT . '/views/partials/theme-init.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="icon" type="image/svg+xml" href="/icons/lsc-icon.svg">
<meta name="theme-color" content="#0b2b4b">

<style>
:root {
    --navy-950: #071d32;
    --navy-900: #0b2b4b;
    --blue-700: #1756a9;
    --blue-600: #2468c5;
    --blue-50: #eef6ff;
    --green-700: #16734b;
    --green-100: #d7f2e4;
    --ink: #142233;
    --slate: #546579;
    --muted: #78889a;
    --line: #dce4ec;
    --surface: #ffffff;
    --canvas: #f7f9fb;
    --danger: #a4362e;
    --danger-soft: #fff3f1;
    --success: #146b45;
    --success-soft: #edf9f3;
}

*, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    background: var(--surface);
    color: var(--ink);
    font-family: 'DM Sans', system-ui, sans-serif;
    min-height: 100vh;
    -webkit-font-smoothing: antialiased;
}

button,
input,
select,
textarea {
    font: inherit;
}

.auth-page {
    display: grid;
    grid-template-columns: minmax(390px, .92fr) minmax(520px, 1.08fr);
    min-height: 100vh;
}

.auth-story {
    background: var(--navy-900);
    color: #fff;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    overflow: hidden;
    padding: 38px 44px 42px;
    position: relative;
}

.auth-story::after {
    border: 34px solid rgba(255, 255, 255, .045);
    border-radius: 50%;
    content: '';
    height: 180px;
    position: absolute;
    right: -60px;
    top: -45px;
    width: 180px;
}

.auth-brand {
    align-items: center;
    display: inline-flex;
    gap: 11px;
    position: relative;
    text-decoration: none;
    width: fit-content;
    z-index: 1;
}

.auth-brand-mark {
    align-items: center;
    background: #fff;
    border-radius: 10px;
    color: var(--navy-900);
    display: inline-flex;
    font-size: 20px;
    height: 42px;
    justify-content: center;
    width: 42px;
}

.auth-brand-copy {
    display: grid;
    line-height: 1;
}

.auth-brand-name {
    color: #fff;
    font-family: 'Manrope', sans-serif;
    font-size: 21px;
    font-weight: 800;
    letter-spacing: -.04em;
}

.auth-brand-line {
    color: #9fb5c8;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .13em;
    margin-top: 6px;
    text-transform: uppercase;
}

.auth-story-content {
    margin: auto 0;
    max-width: 590px;
    padding: 54px 0 46px;
    position: relative;
    z-index: 1;
}

.auth-story-image {
    border: 1px solid rgba(255, 255, 255, .18);
    margin-bottom: 30px;
    padding: 8px;
    position: relative;
}

.auth-story-image img {
    aspect-ratio: 16 / 9;
    display: block;
    object-fit: cover;
    width: 100%;
}

.auth-story-badge {
    align-items: center;
    background: #fff;
    bottom: -16px;
    color: var(--navy-900);
    display: flex;
    font-size: 12px;
    font-weight: 700;
    gap: 9px;
    left: 24px;
    padding: 11px 14px;
    position: absolute;
}

.auth-story-badge i {
    color: var(--green-700);
    font-size: 16px;
}

.auth-story h1 {
    font-family: 'Manrope', sans-serif;
    font-size: clamp(30px, 3.5vw, 46px);
    font-weight: 800;
    letter-spacing: -.045em;
    line-height: 1.1;
    margin-top: 38px;
    max-width: 570px;
}

.auth-story-copy {
    color: #b9cad8;
    font-size: 15px;
    line-height: 1.7;
    margin-top: 15px;
    max-width: 500px;
}

.auth-story-footer {
    align-items: center;
    color: #8da4b9;
    display: flex;
    font-size: 11px;
    gap: 9px;
    position: relative;
    z-index: 1;
}

.auth-story-footer i {
    color: #76d4a5;
}

.auth-main {
    background: var(--canvas);
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding: 34px 52px;
}

.auth-main-header {
    align-items: center;
    display: flex;
    justify-content: flex-end;
    min-height: 42px;
}

.auth-home-link {
    align-items: center;
    color: var(--slate);
    display: inline-flex;
    font-size: 13px;
    font-weight: 600;
    gap: 7px;
    text-decoration: none;
}

.auth-home-link:hover {
    color: var(--navy-900);
}

.auth-panel-wrap {
    align-items: center;
    display: flex;
    flex: 1;
    justify-content: center;
    padding: 42px 0;
}

.auth-card {
    width: min(100%, 460px);
}

.auth-card-title {
    color: var(--navy-950);
    font-family: 'Manrope', sans-serif;
    font-size: 34px;
    font-weight: 800;
    letter-spacing: -.045em;
    line-height: 1.15;
    margin-bottom: 9px;
}

.auth-card-sub {
    color: var(--slate);
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 30px;
}

.auth-field {
    margin-bottom: 18px;
}

.auth-field label,
.auth-label {
    color: var(--ink);
    display: block;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 8px;
}

.auth-field-note {
    color: var(--muted);
    font-size: 11px;
    line-height: 1.5;
    margin-top: 7px;
}

.auth-input {
    background: var(--surface);
    border: 1px solid #cfd9e3;
    border-radius: 8px;
    color: var(--ink);
    font-size: 14px;
    min-height: 50px;
    outline: none;
    padding: 0 15px;
    transition: border-color .15s ease, box-shadow .15s ease;
    width: 100%;
}

select.auth-input {
    cursor: pointer;
}

.auth-input::placeholder {
    color: #93a0ae;
}

.auth-input:focus {
    border-color: var(--blue-600);
    box-shadow: 0 0 0 3px rgba(36, 104, 197, .12);
}

.auth-pw-wrap {
    position: relative;
}

.auth-pw-wrap .auth-input {
    padding-right: 50px;
}

.auth-pw-toggle {
    align-items: center;
    background: transparent;
    border: 0;
    color: var(--muted);
    cursor: pointer;
    display: flex;
    font-size: 17px;
    height: 42px;
    justify-content: center;
    position: absolute;
    right: 5px;
    top: 34px;
    width: 42px;
}

.auth-pw-toggle:hover {
    color: var(--navy-900);
}

.auth-form-meta {
    align-items: center;
    display: flex;
    justify-content: flex-end;
    margin: -5px 0 18px;
}

.auth-form-meta a,
.auth-footer a,
.auth-support a {
    color: var(--blue-700);
    font-weight: 700;
    text-decoration: none;
}

.auth-form-meta a {
    font-size: 12px;
}

.auth-form-meta a:hover,
.auth-footer a:hover,
.auth-support a:hover {
    color: var(--green-700);
}

.auth-btn {
    align-items: center;
    background: var(--blue-700);
    border: 1px solid var(--blue-700);
    border-radius: 8px;
    color: #fff;
    cursor: pointer;
    display: flex;
    font-size: 14px;
    font-weight: 700;
    gap: 9px;
    justify-content: center;
    margin-top: 4px;
    min-height: 50px;
    padding: 0 18px;
    text-align: center;
    text-decoration: none;
    transition: background-color .15s ease, border-color .15s ease, transform .15s ease;
    width: 100%;
}

.auth-btn:hover {
    background: #10488e;
    border-color: #10488e;
    transform: translateY(-1px);
}

.auth-btn:focus-visible,
.auth-pw-toggle:focus-visible,
.auth-home-link:focus-visible,
.auth-footer a:focus-visible,
.auth-support a:focus-visible {
    outline: 3px solid rgba(36, 104, 197, .24);
    outline-offset: 3px;
}

.auth-btn:disabled {
    background: #dce4ec;
    border-color: #dce4ec;
    color: #718195;
    cursor: not-allowed;
    transform: none;
}

.auth-btn-google {
    background: var(--surface);
    border-color: #cfd9e3;
    color: var(--ink);
}

.auth-btn-google:hover {
    background: var(--blue-50);
    border-color: #b8d4f2;
}

.auth-divider {
    align-items: center;
    color: var(--muted);
    display: flex;
    font-size: 11px;
    gap: 14px;
    letter-spacing: .05em;
    margin: 24px 0 18px;
    text-transform: uppercase;
}

.auth-divider::before,
.auth-divider::after {
    background: var(--line);
    content: '';
    flex: 1;
    height: 1px;
}

.auth-provider-note {
    color: var(--muted);
    font-size: 11px;
    line-height: 1.5;
    margin-top: 10px;
    text-align: center;
}

.auth-alert {
    align-items: flex-start;
    border: 1px solid;
    display: flex;
    font-size: 13px;
    font-weight: 500;
    gap: 9px;
    line-height: 1.5;
    margin-bottom: 20px;
    padding: 12px 14px;
}

.auth-alert i {
    flex: 0 0 auto;
    margin-top: 1px;
}

.auth-alert-error {
    background: var(--danger-soft);
    border-color: #efc7c3;
    color: var(--danger);
}

.auth-alert-success {
    background: var(--success-soft);
    border-color: #b9e2ce;
    color: var(--success);
}

.auth-footer {
    color: var(--slate);
    font-size: 13px;
    margin-top: 24px;
    text-align: center;
}

.auth-legal {
    color: var(--muted);
    font-size: 11px;
    line-height: 1.55;
    margin-top: 16px;
    text-align: center;
}

.auth-legal a,
.auth-verification-link a {
    color: var(--blue-700);
    font-weight: 700;
    text-decoration: none;
}

.auth-legal a:hover,
.auth-verification-link a:hover {
    color: var(--green-700);
}

.auth-verification-link {
    color: var(--slate);
    font-size: 12px;
    margin-top: 18px;
    text-align: center;
}

.auth-support {
    border-top: 1px solid var(--line);
    color: var(--muted);
    font-size: 12px;
    margin-top: 30px;
    padding-top: 20px;
    text-align: center;
}

.auth-main-footer {
    color: var(--muted);
    font-size: 11px;
    padding-top: 20px;
    text-align: center;
}

@media (max-width: 980px) {
    .auth-page {
        grid-template-columns: minmax(320px, .75fr) minmax(480px, 1.25fr);
    }

    .auth-story {
        padding: 32px;
    }

    .auth-story-image {
        display: none;
    }

    .auth-main {
        padding: 30px 38px;
    }
}

@media (max-width: 760px) {
    .auth-page {
        display: block;
    }

    .auth-story {
        display: none;
    }

    .auth-main {
        min-height: 100vh;
        padding: 24px;
    }

    .auth-main-header {
        justify-content: flex-start;
    }

    .auth-panel-wrap {
        align-items: flex-start;
        padding: 42px 0 30px;
    }
}

@media (max-width: 460px) {
    .auth-main {
        padding: 20px;
    }

    .auth-card-title {
        font-size: 30px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .auth-btn {
        transition: none;
    }
}
</style>

<?php require LSC_ROOT . '/views/layouts/pwa.php'; ?>
</head>
<body>
<div class="auth-page">
    <aside class="auth-story">
        <a class="auth-brand" href="index.php" aria-label="LSC home">
            <span class="auth-brand-mark"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
            <span class="auth-brand-copy">
                <span class="auth-brand-name">LSC</span>
                <span class="auth-brand-line">Learn · Share · Connect</span>
            </span>
        </a>

        <div class="auth-story-content">
            <div class="auth-story-image">
                <img src="/images/home-students.png" alt="University students collaborating around a laptop">
                <div class="auth-story-badge"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= htmlspecialchars($authStoryBadge) ?></div>
            </div>
            <h1><?= htmlspecialchars($authStoryTitle) ?></h1>
            <p class="auth-story-copy"><?= htmlspecialchars($authStoryCopy) ?></p>
        </div>

        <p class="auth-story-footer"><i class="bi bi-shield-check" aria-hidden="true"></i> Secure access for focused academic collaboration</p>
    </aside>

    <main class="auth-main">
        <div class="auth-main-header">
            <a class="auth-home-link" href="<?= $isAndroid ? 'help.php?app=android' : 'index.php' ?>">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                <?= $isAndroid ? 'Back to help' : 'Back to homepage' ?>
            </a>
        </div>

        <div class="auth-panel-wrap">
            <div class="auth-card">
                <?= $auth_content ?? '' ?>
                <p class="auth-support">Need help accessing your account? <a href="help.php<?= $isAndroid ? '?app=android' : '' ?>">Visit the help centre</a></p>
            </div>
        </div>

        <p class="auth-main-footer">&copy; <?= date('Y') ?> LSC · Learn · Share · Connect</p>
    </main>
</div>

<?php require LSC_ROOT . '/views/partials/consent.php'; ?>
</body>
</html>
