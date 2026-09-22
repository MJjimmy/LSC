<?php
// Cookie disclosure banner. LSC sets one required session cookie (login
// state) and uses no analytics or advertising cookies, so this is a
// disclosure with an acknowledge action rather than an opt-in form.
?>
<div id="lsc-consent" style="display:none;position:fixed;left:16px;right:16px;bottom:16px;z-index:9999;max-width:560px;margin:0 auto;background:var(--card, #ffffff);border:1px solid var(--border, #dce6f0);border-radius:10px;padding:14px 16px;box-shadow:0 8px 24px rgba(18,59,99,.18);font-size:13px;line-height:1.55;color:var(--text-primary, #172033);font-family:'Inter',system-ui,sans-serif;">
    <div style="display:flex;gap:10px;align-items:flex-start;">
        <i class="bi bi-shield-check" style="color:var(--green, #15803d);font-size:18px;margin-top:1px;flex-shrink:0;"></i>
        <div>
            <strong style="display:block;margin-bottom:2px;">About cookies on LSC</strong>
            LSC uses one required session cookie so you stay signed in. We do not use analytics or advertising cookies.
            Read the <a href="privacy.php" style="color:var(--brand, #2563eb);">privacy policy</a>.
        </div>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
        <button type="button" onclick="location.href='privacy.php'" style="border:1px solid var(--border, #dce6f0);background:transparent;color:var(--text-secondary, #526277);border-radius:8px;padding:7px 14px;font-size:12.5px;font-weight:600;cursor:pointer;">Privacy policy</button>
        <button type="button" id="lsc-consent-accept" style="border:1px solid var(--brand-dark, #1d4ed8);background:var(--brand, #2563eb);color:#fff;border-radius:8px;padding:7px 14px;font-size:12.5px;font-weight:600;cursor:pointer;">Understood</button>
    </div>
</div>
<script>
(function () {
    try {
        if (!localStorage.getItem('lsc-consent')) {
            var box = document.getElementById('lsc-consent');
            if (box) { box.style.display = 'block'; }
        }
        var accept = document.getElementById('lsc-consent-accept');
        if (accept) {
            accept.addEventListener('click', function () {
                localStorage.setItem('lsc-consent', String(new Date().toISOString()));
                var box = document.getElementById('lsc-consent');
                if (box) { box.style.display = 'none'; }
            });
        }
    } catch (error) { /* Consent stays hidden when storage is unavailable. */ }
})();
</script>
