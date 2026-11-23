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
        :root{--canvas:#f7f9fb;--card:#fff;--line:#dce4ec;--ink:#142233;--slate:#546579;--muted:#78889a;--blue:#1756a9;--navy:#0b2b4b;--green:#16734b;--cream:var(--canvas);--border:var(--line);--text-primary:var(--ink);--text-secondary:var(--slate);--brand:var(--blue)}
        *{box-sizing:border-box}
        body{margin:0;background:var(--canvas);color:var(--ink);font:15px/1.7 'DM Sans',system-ui,sans-serif;-webkit-font-smoothing:antialiased}
        .help-header{background:#fff;border-bottom:1px solid var(--line)}
        .help-nav,.help-shell,.help-footer-inner{max-width:1080px;margin:auto;padding-left:28px;padding-right:28px}
        .help-nav{align-items:center;display:flex;justify-content:space-between;min-height:76px}
        .brand{align-items:center;color:var(--ink);display:inline-flex;gap:11px;text-decoration:none}
        .brand-mark{align-items:center;background:var(--navy);border-radius:9px;color:#fff;display:flex;height:40px;justify-content:center;width:40px}
        .brand strong{font:800 20px/1 'Manrope',sans-serif;letter-spacing:-.04em}
        .brand small{color:var(--muted);display:block;font-size:9px;font-weight:700;letter-spacing:.12em;margin-top:6px;text-transform:uppercase}
        .account-nav{align-items:center;display:flex;gap:10px}
        .account-nav a{border:1px solid var(--line);border-radius:8px;color:var(--navy);font-size:13px;font-weight:700;padding:9px 14px;text-decoration:none}
        .account-nav a:last-of-type{background:var(--blue);border-color:var(--blue);color:#fff}
        .help-shell{padding-bottom:80px;padding-top:62px}
        .help-shell main>h1{font:800 clamp(38px,6vw,58px)/1.05 'Manrope',sans-serif;letter-spacing:-.05em;margin:0 0 15px}
        .help-shell main>p:first-of-type{color:var(--slate);font-size:17px;max-width:700px}
        .help-shell section{background:var(--card);border:1px solid var(--line);margin-top:22px;padding:28px 30px}
        .help-shell h2{font:800 20px/1.25 'Manrope',sans-serif;letter-spacing:-.025em;margin:0 0 14px}
        .help-shell h3{font-size:15px;margin:22px 0 7px}
        .help-shell p,.help-shell li{color:var(--slate)}
        .help-shell a{color:var(--blue);font-weight:700}
        .help-shell details{border-top:1px solid var(--line);padding:16px 0}
        .help-shell details:last-child{padding-bottom:0}
        .help-shell summary{color:var(--ink);cursor:pointer;font-weight:700}
        .help-shell summary+p{margin-bottom:0}
        .help-footer{background:var(--navy);color:#b9cad8}
        .help-footer-inner{align-items:center;display:flex;font-size:12px;justify-content:space-between;min-height:92px}
        .help-footer nav{display:flex;gap:22px}
        .help-footer a{color:#d6e1ea;text-decoration:none}
        a:focus-visible,summary:focus-visible{outline:3px solid rgba(36,104,197,.3);outline-offset:4px}
        @media(max-width:640px){.help-nav,.help-shell,.help-footer-inner{padding-left:20px;padding-right:20px}.help-nav{align-items:flex-start;gap:18px;padding-bottom:16px;padding-top:16px}.brand small{display:none}.account-nav{flex-wrap:wrap;justify-content:flex-end}.help-shell{padding-bottom:56px;padding-top:44px}.help-shell section{padding:22px 20px}.help-footer-inner{align-items:flex-start;flex-direction:column;gap:12px;justify-content:center}.help-footer nav{flex-wrap:wrap}}
    </style>
</head>
<body>
<header class="help-header">
    <div class="help-nav">
        <a class="brand" href="index.php">
            <span class="brand-mark"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
            <span><strong>LSC</strong><small>Learn · Share · Connect</small></span>
        </a>
        <nav class="account-nav" aria-label="Account navigation">
            <a href="login.php<?= $authSuffix ?>">Sign in</a>
            <a href="register.php<?= $authSuffix ?>">Create account</a>
        </nav>
    </div>
</header>
<div class="help-shell">
    <main><?= $page_content ?></main>
</div>
<footer class="help-footer">
    <div class="help-footer-inner">
        <span>&copy; <?= date('Y') ?> LSC · Academic collaboration, clearly organised.</span>
        <nav aria-label="Policies"><a href="privacy.php">Privacy policy</a><a href="terms.php">Terms and conditions</a></nav>
    </div>
</footer>
<?php require LSC_ROOT . '/views/partials/consent.php'; ?>
</body>
</html>
