<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <?php require LSC_ROOT . '/views/partials/theme-init.php'; ?>
    <style>
        :root{--cream:#f4f8fc;--card:#fff;--border:#dce6f0;--text-primary:#172033;--text-secondary:#526277;--brand:#2563eb;--sidebar:#123b63}
        html[data-theme="dark"]{--cream:#0e1a28;--card:#152636;--border:#25394c;--text-primary:#e7eff7;--text-secondary:#a9bcca;--brand:#93c5fd;--sidebar:#10263c}
        *{box-sizing:border-box}body{margin:0;font:16px/1.65 system-ui,sans-serif;background:var(--cream);color:var(--text-primary)}
        .help-shell{max-width:960px;margin:auto;padding:20px}header,footer{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;padding:12px 0 24px}
        header a,footer a{color:var(--brand);padding:8px 0}header .brand{font-size:22px;font-weight:800;text-decoration:none;color:var(--text-primary)}
        nav{display:flex;gap:18px;align-items:center;flex-wrap:wrap}footer{margin-top:28px;border-top:1px solid var(--border);font-size:14px}
        a:focus-visible,summary:focus-visible{outline:3px solid var(--brand);outline-offset:4px}
    </style>
</head>
<body>
<div class="help-shell">
    <header>
        <a class="brand" href="index.php">LSC</a>
        <nav aria-label="Account navigation">
            <a href="login.php<?= $authSuffix ?>">Sign in</a>
            <a href="register.php<?= $authSuffix ?>">Create an account</a>
            <?php require LSC_ROOT . '/views/partials/theme-toggle.php'; ?>
        </nav>
    </header>
    <main><?= $page_content ?></main>
    <footer><span>LSC · Learn. Share. Connect.</span><nav aria-label="Policies"><a href="privacy.php">Privacy policy</a><a href="terms.php">Terms and conditions</a></nav></footer>
</div>
<?php require LSC_ROOT . '/views/partials/consent.php'; ?>
</body>
</html>
