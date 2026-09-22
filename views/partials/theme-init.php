<?php
// Sets the saved theme before first paint to prevent a light-mode flash.
// Required from the <head> of every page.
?>
<script>
(function () {
    try {
        var saved = localStorage.getItem('lsc-theme');
        var theme = saved === 'dark' || saved === 'light'
            ? saved
            : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    } catch (error) { /* Storage unavailable: keep the default light theme. */ }
})();
</script>
