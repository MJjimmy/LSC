<?php
$pageTitle = $pageTitle ?? 'LSC — Learn. Share. Connect.';


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

body {
    font-family: 'Inter', system-ui, sans-serif;
    min-height: 100vh;
    display: flex;
    background: #123b63;
    color: #172033;
}

/* Left decorative panel */
.auth-left {
    width: 420px;
    flex-shrink: 0;
    background: linear-gradient(155deg, #123b63 0%, #175488 100%);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 48px 44px;
    position: relative;
    overflow: hidden;
}

/* Subtle educational blue and green accents */
.auth-left::before {
    content: '';
    position: absolute;
    top: -80px; left: -80px;
    width: 360px; height: 360px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(74,222,128,.16) 0%, transparent 65%);
    pointer-events: none;
}

.auth-left::after {
    content: '';
    position: absolute;
    bottom: -60px; right: -60px;
    width: 280px; height: 280px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(96,165,250,.16) 0%, transparent 65%);
    pointer-events: none;
}

.auth-brand {
    position: relative;
    z-index: 1;
}

.auth-wordmark {
    font-family: 'Inter', system-ui, sans-serif;
    font-size: 28px;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.5px;
    text-decoration: none;
    display: block;
    margin-bottom: 4px;
}

.auth-wordmark span { color: #4ade80; }

.auth-tagline {
    font-size: 12px;
    color: rgba(255,255,255,.68);
    letter-spacing: 0.3px;
}

.auth-highlights {
    position: relative;
    z-index: 1;
}

.auth-highlights h2 {
    max-width: 290px;
    margin-bottom: 20px;
    color: #fff;
    font-size: 24px;
    line-height: 1.3;
}

.auth-highlight {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 12px;
    color: rgba(255,255,255,.78);
    font-size: 13px;
}

.auth-highlight i {
    width: 28px;
    height: 28px;
    border-radius: 7px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #86efac;
    background: rgba(255,255,255,.09);
}

.auth-brand-note {
    position: relative;
    z-index: 1;
    color: rgba(255,255,255,.48);
    font-size: 11px;
}

/* Right form panel */
.auth-right {
    flex: 1;
    background: #f4f8fc;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
}

.auth-card {
    background: #ffffff;
    border: 1px solid #dce6f0;
    border-radius: 12px;
    padding: 36px 40px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 12px 32px rgba(15,47,82,.08);
}

.auth-card-title {
    font-family: 'Inter', system-ui, sans-serif;
    font-size: 26px;
    font-weight: 800;
    color: #172033;
    margin-bottom: 4px;
    letter-spacing: -0.3px;
}

.auth-card-sub {
    font-size: 13px;
    color: #7b8ba0;
    margin-bottom: 28px;
}

/* Input styling */
.auth-field { margin-bottom: 14px; }

.auth-input {
    width: 100%;
    padding: 11px 14px;
    border-radius: 8px;
    border: 1px solid #dce6f0;
    background: #f8fbfe;
    font-family: 'Inter', system-ui, sans-serif;
    font-size: 13.5px;
    color: #172033;
    outline: none;
    transition: border-color .15s, background .15s;
}

.auth-input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,.1);
    background: #fff;
}

.auth-input::placeholder { color: #94a3b8; }

/* Password wrapper */
.auth-pw-wrap { position: relative; }

.auth-pw-wrap .auth-input { padding-right: 44px; }

.auth-pw-toggle {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #7b8ba0;
    cursor: pointer;
    font-size: 15px;
    line-height: 1;
}

/* Submit button */
.auth-btn {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    background: #2563eb;
    color: #fff;
    border: none;
    font-family: 'Inter', system-ui, sans-serif;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s;
    margin-top: 4px;
}

.auth-btn:hover { background: #1d4ed8; }

/* Error / success alerts */
.auth-alert {
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 16px;
}

.auth-alert-error {
    background: #fdf1f0;
    border: 1px solid #f5c6c0;
    color: #c0392b;
}

.auth-alert-success {
    background: #edf8f2;
    border: 1px solid #b8e8cd;
    color: #1a7a40;
}

/* Footer link */
.auth-footer {
    text-align: center;
    margin-top: 20px;
    font-size: 13px;
    color: #7b8ba0;
}

.auth-footer a {
    color: #1d4ed8;
    font-weight: 600;
    text-decoration: none;
}

.auth-footer a:hover { color: #15803d; }

@media (max-width: 760px) {
    .auth-left { display: none; }
}
</style>


<?php require STUDYSYNC_ROOT . '/views/layouts/pwa.php'; ?>


</head>

<body>

    <!-- Left branding panel -->
    <div class="auth-left">
        <div class="auth-brand">
            <a href="login.php" class="auth-wordmark">LS<span>C</span></a>
            <div class="auth-tagline">Learn · Share · Connect</div>
        </div>

        <div class="auth-highlights">
            <h2>Keep learning organised and connected.</h2>
            <div class="auth-highlight"><i class="bi bi-people"></i><span>Work together in focused groups</span></div>
            <div class="auth-highlight"><i class="bi bi-check2-square"></i><span>Track tasks and deadlines clearly</span></div>
            <div class="auth-highlight"><i class="bi bi-folder2-open"></i><span>Keep resources easy to find</span></div>
        </div>

        <div class="auth-brand-note">Built for students and lifelong learners.</div>
    </div>

    <!-- Right form panel -->
    <div class="auth-right">
        <div class="auth-card">
            <?= $auth_content ?? '' ?>
        </div>
    </div>

</body>
</html>
