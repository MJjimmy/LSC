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
