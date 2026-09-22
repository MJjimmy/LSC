<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

http_response_code(404);
$pageTitle = 'Page not found | LSC';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="robots" content="noindex">
<link rel="icon" type="image/svg+xml" href="/icons/lsc-icon.svg">
<meta name="theme-color" content="#123b63">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Inter', system-ui, sans-serif;
    background: #123b63;
    color: #fff;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 24px;
    text-align: center;
}
.code {
    font-size: 84px;
    font-weight: 800;
    letter-spacing: -3px;
    line-height: 1;
    color: #86efac;
}
h1 { font-size: 24px; font-weight: 700; margin-top: 14px; }
p { font-size: 14.5px; color: rgba(255,255,255,.68); max-width: 420px; margin-top: 10px; line-height: 1.6; }
.actions { display: flex; gap: 10px; margin-top: 26px; flex-wrap: wrap; justify-content: center; }
.actions a {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 20px; border-radius: 8px;
    font-size: 13.5px; font-weight: 600; text-decoration: none;
}
.btn-home { background: #16a34a; color: #fff; }
.btn-home:hover { background: #15803d; }
.btn-dash { border: 1px solid rgba(255,255,255,.3); color: #fff; }
.btn-dash:hover { border-color: #fff; }
.brand { position: absolute; top: 24px; left: 28px; font-size: 17px; font-weight: 800; letter-spacing: -0.3px; }
.brand span { color: #86efac; }
</style>
</head>
<body>
<div class="brand">LS<span>C</span></div>
<div class="code">404</div>
<h1>That page does not exist</h1>
<p>The link may be old, or the group, file or invitation it pointed to was removed. If you arrived here from an invite link, ask the group creator to send a fresh one.</p>
<div class="actions">
    <a href="index.php" class="btn-home"><i class="bi bi-house-door"></i> Back to home</a>
    <a href="dashboard.php" class="btn-dash"><i class="bi bi-grid-1x2"></i> Go to dashboard</a>
</div>
</body>
</html>
