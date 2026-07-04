<?php
$pageTitle = $pageTitle ?? 'StudySync';


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

body {
    font-family: 'DM Sans', sans-serif;
    min-height: 100vh;
    display: flex;
    background: #161412;
    color: #1e1810;
}

/* Left decorative panel */
.auth-left {
    width: 420px;
    flex-shrink: 0;
    background: #161412;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 48px 44px;
    position: relative;
    overflow: hidden;
}

/* Copper radial glow */
.auth-left::before {
    content: '';
    position: absolute;
    top: -80px; left: -80px;
    width: 360px; height: 360px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(205,133,63,.14) 0%, transparent 65%);
    pointer-events: none;
}

.auth-left::after {
    content: '';
    position: absolute;
    bottom: -60px; right: -60px;
    width: 280px; height: 280px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(205,133,63,.08) 0%, transparent 65%);
    pointer-events: none;
}

.auth-brand {
    position: relative;
    z-index: 1;
}

.auth-wordmark {
    font-family: 'Playfair Display', serif;
    font-size: 28px;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.5px;
    text-decoration: none;
    display: block;
    margin-bottom: 4px;
}

.auth-wordmark span { color: #cd853f; }

.auth-tagline {
    font-size: 12px;
    color: rgba(255,255,255,.32);
    letter-spacing: 0.3px;
}

.auth-quote {
    position: relative;
    z-index: 1;
}

.auth-quote-text {
    font-family: 'Playfair Display', serif;
    font-size: 22px;
    font-weight: 700;
    color: rgba(255,255,255,.75);
    line-height: 1.4;
    margin-bottom: 14px;
}

.auth-quote-attr {
    font-size: 12px;
    color: rgba(255,255,255,.3);
    letter-spacing: 0.3px;
}

.auth-dots {
    position: relative;
    z-index: 1;
    display: flex;
    gap: 6px;
}

.auth-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: rgba(255,255,255,.15);
}

.auth-dot.active { background: #cd853f; }

/* Right form panel */
.auth-right {
    flex: 1;
    background: #faf8f6;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
}

.auth-card {
    background: #ffffff;
    border: 1px solid #ede8e2;
    border-radius: 18px;
    padding: 36px 40px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 4px 24px rgba(0,0,0,.05);
}

.auth-card-title {
    font-family: 'Playfair Display', serif;
    font-size: 26px;
    font-weight: 800;
    color: #1e1810;
    margin-bottom: 4px;
    letter-spacing: -0.3px;
}

.auth-card-sub {
    font-size: 13px;
    color: #a09080;
    margin-bottom: 28px;
}

/* Input styling */
.auth-field { margin-bottom: 14px; }

.auth-input {
    width: 100%;
    padding: 11px 14px;
    border-radius: 8px;
    border: 1px solid #ede8e2;
    background: #faf8f6;
    font-family: 'DM Sans', sans-serif;
    font-size: 13.5px;
    color: #1e1810;
    outline: none;
    transition: border-color .15s, background .15s;
}

.auth-input:focus {
    border-color: #cd853f;
    background: #fff;
}

.auth-input::placeholder { color: #b0a090; }

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
    color: #a09080;
    cursor: pointer;
    font-size: 15px;
    line-height: 1;
}

/* Submit button */
.auth-btn {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    background: #1e1810;
    color: #fff;
    border: none;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s;
    margin-top: 4px;
}

.auth-btn:hover { background: #2c211a; }

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
    color: #a09080;
}

.auth-footer a {
    color: #a8642a;
    font-weight: 600;
    text-decoration: none;
}

.auth-footer a:hover { color: #cd853f; }

@media (max-width: 760px) {
    .auth-left { display: none; }
}
</style>


<!-- ── PWA ──────────────────────────────────────────────────
     Paste this block inside <head> in layout.php AND auth-layout.php
     just before the closing </head> tag
─────────────────────────────────────────────────────────── -->

<!-- PWA manifest -->
<link rel="manifest" href="/manifest.json">

<!-- Theme colour (browser chrome on Android) -->
<meta name="theme-color" content="#161412">

<!-- iOS home screen support -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="StudySync">
<link rel="apple-touch-icon" href="/icons/icon-192.png">

<!-- Windows tile -->
<meta name="msapplication-TileImage" content="/icons/icon-144.png">
<meta name="msapplication-TileColor" content="#161412">

<!-- Favicon -->
<link rel="icon" type="image/png" sizes="32x32" href="/icons/icon-96.png">

<!-- Register service worker -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/service-worker.php')
            .then(reg => console.log('SW registered:', reg.scope))
            .catch(err => console.log('SW error:', err));
    });
}
</script>


</head>

<body>

    <!-- Left branding panel -->
    <div class="auth-left">
        <div class="auth-brand">
            <a href="login.php" class="auth-wordmark">Study<span>Sync</span></a>
            <div class="auth-tagline">Smart Student Collaboration</div>
        </div>

        <div class="auth-quote">
            <div class="auth-quote-text">"Education is not the filling of a pail, but the lighting of a fire."</div>
            <div class="auth-quote-attr">— W.B. Yeats</div>
        </div>

        <div class="auth-dots">
            <div class="auth-dot active"></div>
            <div class="auth-dot"></div>
            <div class="auth-dot"></div>
        </div>
    </div>

    <!-- Right form panel -->
    <div class="auth-right">
        <div class="auth-card">
            <?= $auth_content ?? '' ?>
        </div>
    </div>

</body>
</html>