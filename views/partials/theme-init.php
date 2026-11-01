<?php
// Light-only before first paint, including old saved dark preferences.
?>
<meta name="color-scheme" content="light">
<script>
(function () {
    document.documentElement.setAttribute('data-theme', 'light');
    document.documentElement.style.colorScheme = 'light';
    try { localStorage.setItem('lsc-theme', 'light'); } catch (error) { /* Storage is optional. */ }
})();
</script>
