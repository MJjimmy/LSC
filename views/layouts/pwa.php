<?php
// Open Graph / social share tags. Pages may set $pageDescription before
// requiring the layout; a product-level default applies otherwise.
$ogBase = rtrim((string)getenv('APP_URL'), '/');
if ($ogBase === '') {
    $ogScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $ogBase = $ogScheme . '://' . (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
}
$ogTitle = $pageTitle ?? 'LSC | Learn. Share. Connect.';
$ogDescription = $pageDescription ?? 'Study groups, tasks, shared files and group chat for students. One workspace for your course work.';
$ogUrl = $ogBase . (string)($_SERVER['REQUEST_URI'] ?? '/');
$ogImage = $ogBase . '/icons/lsc-og.jpg';
?>
<!-- Open Graph / social share -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="LSC">
<meta property="og:title" content="<?= htmlspecialchars($ogTitle) ?>">
<meta property="og:description" content="<?= htmlspecialchars($ogDescription) ?>">
<meta property="og:url" content="<?= htmlspecialchars($ogUrl) ?>">
<meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
<meta name="twitter:card" content="summary_large_image">

<!-- PWA metadata -->
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#123b63">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="LSC">
<link rel="apple-touch-icon" href="/icons/lsc-icon.svg">
<meta name="msapplication-TileImage" content="/icons/lsc-icon.svg">
<meta name="msapplication-TileColor" content="#123b63">
<link rel="icon" type="image/svg+xml" href="/icons/lsc-icon.svg">

<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/service-worker.php')
            .then(reg => console.log('SW registered:', reg.scope))
            .catch(err => console.log('SW error:', err));
    });
}
</script>
