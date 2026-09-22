<?php
// Dark mode toggle button. Self-styled: uses the page's CSS variables when
// available, with light-theme fallbacks on standalone pages.
?>
<button type="button" data-lsc-theme-toggle aria-label="Toggle dark mode" title="Toggle dark mode" onclick="lscToggleTheme()" style="width:34px;height:34px;flex-shrink:0;border-radius:8px;border:1px solid var(--border, #dce6f0);background:var(--card, #ffffff);color:var(--text-primary, #172033);cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:15px;">
    <i class="bi bi-moon-stars" data-lsc-theme-icon></i>
</button>
<script>
function lscSyncThemeIcon() {
    var icon = document.querySelector('[data-lsc-theme-icon]');
    if (icon) {
        icon.className = 'bi ' + (document.documentElement.getAttribute('data-theme') === 'dark' ? 'bi-sun' : 'bi-moon-stars');
    }
}
function lscToggleTheme() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    document.documentElement.setAttribute('data-theme', isDark ? 'light' : 'dark');
    try { localStorage.setItem('lsc-theme', isDark ? 'light' : 'dark'); } catch (error) {}
    lscSyncThemeIcon();
}
window.addEventListener('DOMContentLoaded', lscSyncThemeIcon);
</script>
