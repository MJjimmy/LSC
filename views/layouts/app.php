<?php
// Shared LSC application layout.
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$ariaCurrent = static fn(bool $active): string => $active ? ' aria-current="page"' : '';
$unreadNotificationCount = isset($_SESSION['user_id'])
    ? (new \LSC\Notifications\NotificationRepository(
        \LSC\Core\Database::connection()
    ))->unreadCount((int)$_SESSION['user_id'])
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'LSC | Learn. Share. Connect.') ?></title>

<?php require LSC_ROOT . '/views/partials/theme-init.php'; ?>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Product typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">

    <style>
    /* ── TOKENS ─────────────────────────────────────────────── */
    :root {
        --sidebar:        #123b63;
        --sidebar-border: rgba(255,255,255,.12);
        --sidebar-hover:  rgba(255,255,255,.09);
        --primary:        #2563eb;
        --primary-dark:   #1d4ed8;
        --primary-soft:   #eff6ff;
        --primary-border: #bfdbfe;
        /* Compatibility aliases for existing components. */
        --brand:         var(--primary);
        --brand-dark:    var(--primary-dark);
        --brand-dim:     rgba(37,99,235,.12);
        --brand-border:  rgba(147,197,253,.45);
        --cream:          #f4f8fc;
        --card:           #ffffff;
        --border:         #dce6f0;
        --border-soft:    #edf2f7;
        --text-primary:   #172033;
        --text-secondary: #526277;
        --text-muted:     #7b8ba0;
        --text-sidebar:   #d8e6f3;
        /* status */
        --red:            #c0392b;
        --red-bg:         #fdf1f0;
        --red-border:     #f5c6c0;
        --amber:          #b7610a;
        --amber-bg:       #fdf6ec;
        --amber-border:   #f5d9b0;
        --green:          #15803d;
        --green-bg:       #ecfdf3;
        --green-border:   #bbf7d0;
    }

    /* Dark theme overrides */
    html[data-theme="dark"] {
        --sidebar:        #10263c;
        --sidebar-border: rgba(255,255,255,.08);
        --primary-soft:   rgba(37,99,235,.18);
        --primary-border: rgba(147,197,253,.30);
        --brand-dim:      rgba(59,130,246,.18);
        --brand-border:   rgba(147,197,253,.35);
        --cream:          #0e1a28;
        --card:           #152636;
        --border:         #25394c;
        --border-soft:    #1c2e3f;
        --text-primary:   #e7eff7;
        --text-secondary: #a9bcca;
        --text-muted:     #7e93a6;
        --red-bg:         rgba(192,57,43,.16);
        --red-border:     rgba(192,57,43,.45);
        --amber-bg:       rgba(183,97,10,.16);
        --amber-border:   rgba(183,97,10,.45);
        --green-bg:       rgba(21,128,61,.20);
        --green-border:   rgba(21,128,61,.50);
    }

    html[data-theme="dark"] .card { box-shadow: 0 1px 3px rgba(0,0,0,.35); }

    /* ── RESET ──────────────────────────────────────────────── */
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    html, body { height:100%; }

    body {
        font-family: 'Inter', system-ui, sans-serif;
        background: var(--cream);
        color: var(--text-primary);
        font-size: 14px;
        line-height: 1.5;
        display: flex;
        overflow: hidden;
    }
    :where(a,button,input,select,textarea):focus-visible { outline:3px solid #60a5fa; outline-offset:2px; }
    .lsc-skip-link { background:#fff; color:#123b63; font-weight:700; left:12px; padding:10px 14px; position:fixed; top:-60px; z-index:1000; }
    .lsc-skip-link:focus { top:12px; }

    ::-webkit-scrollbar { width: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #b8c7d9; border-radius: 10px; }

    /* ── MOBILE OVERLAY ─────────────────────────────────────── */
    .lsc-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.6);
        z-index: 150;
        opacity: 0;
        transition: opacity .25s;
    }
    .lsc-overlay.open {
        display: block;
        opacity: 1;
    }

    /* ── SIDEBAR ────────────────────────────────────────────── */
    .lsc-sidebar {
        width: 264px;
        min-height: 100vh;
        background: var(--sidebar);
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
        border-right: 1px solid var(--sidebar-border);
        transition: transform .25s ease;
        z-index: 200;
        position: relative;
    }

    .lsc-logo {
        padding: 20px 18px 17px;
        border-bottom: 1px solid var(--sidebar-border);
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
    }

    .lsc-logo-mark {
        font-family: 'Inter', system-ui, sans-serif;
        font-size: 21px;
        font-weight: 800;
        color: #fff;
        letter-spacing: -0.3px;
        text-decoration: none;
        display: block;
    }

    .lsc-logo-mark span { color: #4ade80; }

    .lsc-logo-sub {
        font-size: 10.5px;
        color: rgba(255,255,255,.65);
        margin-top: 2px;
        letter-spacing: 0.3px;
    }

    /* Close button: mobile only */
    .lsc-close-btn {
        display: none;
        background: none;
        border: none;
        color: rgba(255,255,255,.4);
        font-size: 20px;
        cursor: pointer;
        padding: 0;
        line-height: 1;
        flex-shrink: 0;
    }

    .lsc-nav {
        flex: 1;
        padding: 16px 12px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .lsc-nav-label {
        font-size: 9.5px;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        color: rgba(255,255,255,.22);
        padding: 14px 8px 6px;
        font-weight: 500;
    }

    .lsc-nav a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 10px;
        border-radius: 8px;
        color: var(--text-sidebar);
        font-size: 13.5px;
        font-weight: 500;
        text-decoration: none;
        transition: background .15s, color .15s;
        border: 1px solid transparent;
    }

    .lsc-nav a .bi { font-size: 15px; flex-shrink: 0; }

    .lsc-nav a:hover {
        background: var(--sidebar-hover);
        color: rgba(255,255,255,.8);
    }

    .lsc-nav a.active {
        background: rgba(255,255,255,.13);
        color: #fff;
        border-color: rgba(255,255,255,.16);
    }

    .lsc-nav a.active .bi { color: #4ade80; }

    /* ── SIDEBAR BOTTOM ─────────────────────────────────────── */
    .lsc-user {
        padding: 14px 16px;
        border-top: 1px solid var(--sidebar-border);
    }

    .lsc-user-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }

    .lsc-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: var(--brand-dim);
        border: 1px solid var(--brand-border);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 13px;
        color: var(--brand);
        flex-shrink: 0;
        overflow: hidden;
    }
    .lsc-avatar img { width:100%; height:100%; object-fit:cover; }

    .lsc-user-name { font-size: 13px; font-weight: 600; color: #fff; }
    .lsc-user-role { font-size: 11px; color: rgba(255,255,255,.35); margin-top: 1px; }

    .lsc-signout {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 8px;
        border-radius: 6px;
        background: rgba(255,255,255,.04);
        border: 1px solid rgba(255,255,255,.07);
        color: rgba(255,255,255,.38);
        font-size: 12.5px;
        font-family: 'Inter', system-ui, sans-serif;
        cursor: pointer;
        text-decoration: none;
        transition: background .15s, color .15s;
    }

    .lsc-signout-form, .lsc-icon-form { margin: 0; padding: 0; }
    .lsc-signout-form { width: 100%; }
    button.lsc-signout, button.lsc-icon-btn { appearance: none; font: inherit; }
    button.lsc-signout { font-size: 12.5px; font-weight: 500; line-height: 1.2; }
    .lsc-signout:hover {
        background: rgba(255,255,255,.08);
        color: rgba(255,255,255,.65);
    }

    /* ── MAIN WRAPPER ───────────────────────────────────────── */
    .lsc-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        min-width: 0;
    }

    /* ── TOPBAR ─────────────────────────────────────────────── */
    .lsc-topbar {
        height: 56px;
        background: var(--card);
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 0 26px;
        flex-shrink: 0;
    }

    /* Hamburger: hidden on desktop */
    .lsc-hamburger {
        display: none;
        background: none;
        border: none;
        color: var(--text-secondary);
        font-size: 22px;
        cursor: pointer;
        padding: 2px;
        flex-shrink: 0;
        line-height: 1;
    }

    .lsc-topbar-title {
        font-family: 'Inter', system-ui, sans-serif;
        font-size: 17px;
        font-weight: 700;
        color: var(--text-primary);
        letter-spacing: -0.2px;
    }

    .lsc-topbar-right {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .lsc-icon-btn {
        width: 34px;
        height: 34px;
        border-radius: 6px;
        border: 1px solid var(--border);
        background: var(--card);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-secondary);
        cursor: pointer;
        transition: background .15s;
        text-decoration: none;
    }

    .lsc-icon-btn:hover { background: var(--cream); }
    .lsc-icon-btn { position:relative; }
    .lsc-notification-badge { position:absolute;right:-5px;top:-5px;min-width:17px;height:17px;padding:0 4px;border-radius:9px;background:var(--red);color:#fff;border:2px solid #fff;font-size:9px;font-weight:700;display:flex;align-items:center;justify-content:center; }

    /* ── CONTENT AREA ───────────────────────────────────────── */
    .lsc-content {
        flex: 1;
        overflow-y: auto;
        padding: 26px 28px;
    }

    /* ── SHARED COMPONENTS ──────────────────────────────────── */

    .card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(18,59,99,.04);
    }

    .page-hero {
        background: #123b63;
        border-radius: 12px;
        padding: 28px 30px;
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
        color: #fff;
    }

    .page-hero-eyebrow {
        font-size: 10px;
        letter-spacing: 1.4px;
        text-transform: uppercase;
        color: #86efac;
        margin-bottom: 7px;
        font-weight: 500;
    }

    .page-hero-title {
        font-family: 'Inter', system-ui, sans-serif;
        font-size: 32px;
        font-weight: 800;
        letter-spacing: -0.5px;
        line-height: 1.1;
        margin-bottom: 6px;
    }

    .page-hero-sub { font-size: 13px; color: rgba(255,255,255,.5); }

    /* Hero action button: goes absolute on desktop, inline on mobile */
    .hero-action-btn {
        position: absolute;
        right: 30px;
        top: 50%;
        transform: translateY(-50%);
        padding: 9px 18px;
        border-radius: 10px;
        background: transparent;
        border: 1px solid var(--brand-border);
        color: var(--brand);
        font-family: 'Inter', system-ui, sans-serif;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        z-index: 1;
        transition: background .15s;
    }
    .hero-action-btn:hover { background: var(--brand-dim); }

    .stat-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 18px 20px;
    }

    .stat-card-label {
        font-size: 12px;
        color: var(--text-secondary);
        font-weight: 500;
        margin-bottom: 8px;
    }

    .stat-card-value {
        font-family: 'Inter', system-ui, sans-serif;
        font-size: 30px;
        font-weight: 800;
        letter-spacing: -1px;
        line-height: 1;
        color: var(--text-primary);
    }

    .stat-card.brand-tint { background: var(--primary-soft); border-color: var(--primary-border); }
    .stat-card.brand-tint .stat-card-label { color: #1e40af; }
    .stat-card.brand-tint .stat-card-value { color: var(--brand-dark); }

    .stat-card.red-tint { background: var(--red-bg); border-color: var(--red-border); }
    .stat-card.red-tint .stat-card-label { color: #943228; }
    .stat-card.red-tint .stat-card-value { color: var(--red); }

    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: var(--text-primary);
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: 9px 16px;
        font-size: 13px;
        font-weight: 600;
        font-family: 'Inter', system-ui, sans-serif;
        cursor: pointer;
        transition: background .15s;
        text-decoration: none;
    }

    .btn-primary:hover { background: var(--primary-dark); }
    .btn-brand  { background: var(--brand) !important; color: #fff !important; }
    .btn-brand:hover { background: var(--brand-dark) !important; }

    .pill {
        font-size: 11px;
        font-weight: 600;
        padding: 3px 9px;
        border-radius: 20px;
        display: inline-block;
        white-space: nowrap;
    }

    .pill-done    { background: var(--green-bg);  color: var(--green);  border: 1px solid var(--green-border); }
    .pill-overdue { background: var(--red-bg);    color: var(--red);    border: 1px solid var(--red-border); }
    .pill-pending { background: var(--amber-bg);  color: var(--amber);  border: 1px solid var(--amber-border); }
    .pill-active  { background: var(--green-bg);  color: var(--green);  border: 1px solid var(--green-border); }
    .pill-inactive{ background: var(--red-bg);    color: var(--red);    border: 1px solid var(--red-border); }
    .pill-admin   { background: #eef2ff; color: #3730a3; border: 1px solid #c7d2fe; }
    .pill-member  { background: #e8f0fd; color: #1a3a80; border: 1px solid #b8cef5; }
    .pill-private { background: var(--amber-bg);  color: var(--amber);  border: 1px solid var(--amber-border); }
    .pill-public  { background: var(--green-bg);  color: var(--green);  border: 1px solid var(--green-border); }

    .lsc-input {
        width: 100%;
        padding: 10px 14px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: var(--card);
        color: var(--text-primary);
        font-family: 'Inter', system-ui, sans-serif;
        font-size: 13.5px;
        outline: none;
        transition: border-color .15s;
    }
    .lsc-input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }

    .lsc-label {
        display: block;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--text-secondary);
        margin-bottom: 6px;
    }

    .panel-header {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-soft);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
    }

    .panel-title { font-size: 13.5px; font-weight: 600; }
    .panel-sub   { font-size: 11.5px; color: var(--text-muted); margin-top: 2px; }

    .panel-link {
        font-size: 12px;
        font-weight: 600;
        color: var(--brand-dark);
        text-decoration: none;
    }
    .panel-link:hover { color: var(--brand); }

    .lsc-table { width: 100%; border-collapse: collapse; }
    .lsc-table thead { background: var(--cream); }
    .lsc-table th {
        padding: 12px 20px;
        text-align: left;
        font-size: 10.5px;
        font-weight: 600;
        letter-spacing: .8px;
        text-transform: uppercase;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border);
    }

    .lsc-table td {
        padding: 13px 20px;
        font-size: 13.5px;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border-soft);
    }

    .lsc-table tbody tr:last-child td { border-bottom: none; }
    .lsc-table tbody tr:hover td { background: var(--cream); }

    .lsc-modal-bg {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.55);
        backdrop-filter: blur(3px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 500;
        padding: 16px;
    }

    .lsc-modal {
        background: var(--card);
        border-radius: 12px;
        width: 100%;
        max-width: 480px;
        box-shadow: 0 20px 60px rgba(0,0,0,.18);
        overflow: hidden;
        max-height: 92vh;
        overflow-y: auto;
    }

    .lsc-modal-head {
        padding: 22px 24px 18px;
        border-bottom: 1px solid var(--border-soft);
    }

    .lsc-modal-title {
        font-family: 'Inter', system-ui, sans-serif;
        font-size: 20px;
        font-weight: 800;
    }

    .lsc-modal-sub { font-size: 12.5px; color: var(--text-muted); margin-top: 3px; }
    .lsc-modal-body { padding: 20px 24px; }
    .lsc-modal-footer {
        padding: 16px 24px;
        border-top: 1px solid var(--border-soft);
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
    }

    .btn-ghost {
        padding: 8px 16px;
        border-radius: 8px;
        background: var(--cream);
        border: 1px solid var(--border);
        color: var(--text-secondary);
        font-size: 13px;
        font-family: 'Inter', system-ui, sans-serif;
        cursor: pointer;
        transition: background .15s;
    }
    .btn-ghost:hover { background: #e7eef6; }

    .empty-state {
        padding: 52px 20px;
        text-align: center;
        color: var(--text-muted);
    }
    .empty-state p { font-size: 13px; margin-top: 8px; }

    /* ── MOBILE BOTTOM NAV ──────────────────────────────────── */
    .lsc-bottom-nav {
        display: none;
        position: fixed;
        bottom: 0; left: 0; right: 0;
        height: 64px;
        background: var(--sidebar);
        border-top: 1px solid var(--sidebar-border);
        z-index: 100;
        padding: 0 4px;
    }

    .lsc-bottom-nav-inner {
        display: flex;
        align-items: center;
        justify-content: space-around;
        height: 100%;
    }

    .lsc-bnav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 3px;
        padding: 6px 8px;
        border-radius: 10px;
        text-decoration: none;
        color: rgba(255,255,255,.38);
        font-size: 10px;
        font-weight: 500;
        transition: color .15s;
        min-width: 54px;
    }

    .lsc-bnav-item .bi { font-size: 19px; }
    .lsc-bnav-item.active { color: #4ade80; }
    .lsc-bnav-item:hover  { color: rgba(255,255,255,.75); }

    /* ══════════════════════════════════════════════════════════
       RESPONSIVE
       ══════════════════════════════════════════════════════════ */

    @media (max-width: 900px) {

        /* Sidebar slides off-screen, comes back on open */
        .lsc-sidebar {
            position: fixed;
            top: 0; left: 0;
            height: 100%;
            transform: translateX(-100%);
            z-index: 200;
        }

        .lsc-sidebar.open { transform: translateX(0); }

        /* Show hamburger + close button */
        .lsc-hamburger  { display: block; }
        .lsc-close-btn  { display: block; }

        /* Content padding + extra bottom space for bottom nav */
        .lsc-content { padding: 18px 16px 80px; }
        .lsc-topbar  { padding: 0 16px; }

        /* Hero */
        .page-hero       { padding: 20px 20px 20px; }
        .page-hero-title { font-size: 26px; }

        /* Hero action button stacks below text */
        .hero-action-btn {
            position: static;
            transform: none;
            margin-top: 16px;
            align-self: flex-start;
        }

        /* Show bottom nav */
        .lsc-bottom-nav { display: block; }

    }

    @media (max-width: 540px) {

        .lsc-content  { padding: 14px 12px 80px; }
        .lsc-topbar   { padding: 0 12px; gap: 8px; }

        .page-hero       { padding: 18px 16px; border-radius: 14px; }
        .page-hero-title { font-size: 22px; }
        .page-hero-sub   { font-size: 12px; }

        .stat-card-value { font-size: 24px; }

        .lsc-modal-head   { padding: 18px 18px 14px; }
        .lsc-modal-body   { padding: 16px 18px; }
        .lsc-modal-footer { padding: 12px 18px; }

        .btn-primary { font-size: 12px; padding: 8px 14px; }
    }

    /* ── MODERN ACADEMIC SYSTEM ─────────────────────────────── */
    :root {
        --sidebar: #0b2b4b;
        --sidebar-border: rgba(255,255,255,.12);
        --sidebar-hover: rgba(255,255,255,.08);
        --primary: #1756a9;
        --primary-dark: #10488e;
        --primary-soft: #eef6ff;
        --primary-border: #c8def4;
        --brand: var(--primary);
        --brand-dark: var(--primary-dark);
        --brand-dim: rgba(36,104,197,.11);
        --brand-border: rgba(36,104,197,.28);
        --cream: #f7f9fb;
        --card: #ffffff;
        --border: #dce4ec;
        --border-soft: #eaf0f5;
        --text-primary: #142233;
        --text-secondary: #546579;
        --text-muted: #78889a;
        --text-sidebar: #c8d7e4;
        --green: #16734b;
        --green-bg: #edf9f3;
        --green-border: #b9e2ce;
        --red: #a4362e;
        --red-bg: #fff3f1;
        --red-border: #efc7c3;
        --amber: #9b5b12;
        --amber-bg: #fff8eb;
        --amber-border: #eed7ae;
    }

    body {
        font-family: 'DM Sans', system-ui, sans-serif;
        font-size: 14px;
        line-height: 1.55;
    }

    .lsc-sidebar {
        box-shadow: 10px 0 32px rgba(7,29,50,.08);
        width: 254px;
    }

    .lsc-logo {
        align-items: center;
        min-height: 76px;
        padding: 16px 18px;
    }

    .lsc-logo > div {
        align-items: center;
        display: flex;
        gap: 11px;
    }

    .lsc-logo-mark {
        align-items: center;
        background: #fff;
        border-radius: 9px;
        color: var(--sidebar);
        display: inline-flex;
        font-family: 'Manrope', sans-serif;
        font-size: 18px;
        height: 40px;
        justify-content: center;
        width: 40px;
    }

    .lsc-logo-mark span { color: inherit; }

    .lsc-logo-copy {
        display: grid;
    }

    .lsc-logo-name {
        color: #fff;
        font-family: 'Manrope', sans-serif;
        font-size: 19px;
        font-weight: 800;
        letter-spacing: -.04em;
        line-height: 1;
    }

    .lsc-logo-sub {
        color: #91a8bc;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .12em;
        margin-top: 6px;
        text-transform: uppercase;
    }

    .lsc-nav {
        gap: 3px;
        padding: 20px 13px;
    }

    .lsc-nav-label {
        color: #7892a8;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .14em;
        padding: 18px 11px 7px;
    }

    .lsc-nav a {
        border-radius: 7px;
        font-size: 13px;
        gap: 11px;
        min-height: 42px;
        padding: 9px 11px;
    }

    .lsc-nav a .bi {
        color: #8fa6ba;
        font-size: 16px;
        width: 18px;
    }

    .lsc-nav a.active {
        background: #fff;
        border-color: #fff;
        color: var(--sidebar);
        font-weight: 700;
    }

    .lsc-nav a.active .bi { color: var(--primary); }

    .lsc-user {
        background: rgba(0,0,0,.08);
        padding: 15px 16px 17px;
    }

    .lsc-avatar {
        background: #fff;
        border-color: rgba(255,255,255,.55);
        color: var(--sidebar);
        height: 38px;
        width: 38px;
    }

    .lsc-user-name { font-size: 12.5px; }
    .lsc-user-role { color: #8fa6ba; }

    .lsc-signout {
        border-color: rgba(255,255,255,.14);
        border-radius: 7px;
        color: #b4c5d4;
        min-height: 36px;
    }

    .lsc-topbar {
        height: 72px;
        padding: 0 30px;
    }

    .lsc-topbar-title {
        font-family: 'Manrope', sans-serif;
        font-size: 16px;
        font-weight: 800;
        letter-spacing: -.025em;
    }

    .lsc-icon-btn {
        border-radius: 8px;
        height: 38px;
        width: 38px;
    }

    .lsc-content {
        padding: 32px 36px 42px;
    }

    .card {
        border-radius: 9px;
        box-shadow: 0 8px 24px rgba(12,39,65,.045);
    }

    .page-hero {
        background: var(--sidebar);
        border: 1px solid #123d66;
        border-radius: 10px;
        padding: 32px 34px;
    }

    .page-hero::after {
        border: 30px solid rgba(255,255,255,.045);
        border-radius: 50%;
        content: '';
        height: 145px;
        position: absolute;
        right: -20px;
        top: -40px;
        width: 145px;
    }

    .page-hero-eyebrow {
        color: #79d5a9;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .13em;
    }

    .page-hero-title {
        font-family: 'Manrope', sans-serif;
        font-size: 32px;
        letter-spacing: -.045em;
    }

    .page-hero-sub {
        color: #b6c8d7;
        font-size: 13px;
        max-width: 680px;
    }

    .hero-action-btn {
        background: #fff;
        border-color: #fff;
        border-radius: 8px;
        color: var(--sidebar);
        z-index: 2;
    }

    .hero-action-btn:hover { background: var(--primary-soft); }

    .stat-card {
        border-radius: 9px;
        min-height: 112px;
        padding: 20px 22px;
    }

    .stat-card-label {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .stat-card-value {
        font-family: 'Manrope', sans-serif;
        font-size: 32px;
        letter-spacing: -.045em;
    }

    .btn-primary,
    .btn-ghost {
        align-items: center;
        border-radius: 8px;
        display: inline-flex;
        font-family: 'DM Sans', system-ui, sans-serif;
        font-weight: 700;
        justify-content: center;
        min-height: 40px;
    }

    .btn-primary {
        background: var(--primary);
        padding: 9px 16px;
    }

    .btn-primary:hover { background: var(--primary-dark); }

    .btn-ghost {
        background: var(--card);
        padding: 8px 15px;
    }

    .pill {
        border-radius: 5px;
        font-size: 10px;
        letter-spacing: .02em;
        padding: 4px 8px;
        text-transform: uppercase;
    }

    .lsc-input {
        border-color: #cfd9e3;
        border-radius: 7px;
        font-family: 'DM Sans', system-ui, sans-serif;
        min-height: 44px;
    }

    textarea.lsc-input { padding-top: 11px; }

    .lsc-label {
        color: var(--text-primary);
        font-size: 12px;
        font-weight: 700;
    }

    .panel-header { padding: 18px 21px; }
    .panel-title { font-family: 'Manrope', sans-serif; font-size: 13px; font-weight: 800; }
    .panel-link { color: var(--primary); }

    .lsc-table th {
        background: #f3f6f9;
        font-size: 9.5px;
        letter-spacing: .1em;
        padding: 13px 20px;
    }

    .lsc-table td { padding: 15px 20px; }
    .lsc-table tbody tr:hover td { background: #f8fafc; }

    .lsc-modal-bg {
        background: rgba(7,29,50,.68);
        backdrop-filter: blur(5px);
    }

    .lsc-modal {
        border: 1px solid var(--border);
        border-radius: 10px;
        box-shadow: 0 28px 80px rgba(7,29,50,.25);
    }

    .lsc-modal-title {
        font-family: 'Manrope', sans-serif;
        letter-spacing: -.03em;
    }

    .empty-state {
        padding: 64px 24px;
    }

    .lsc-bottom-nav {
        background: rgba(11,43,75,.98);
        height: 68px;
    }

    .lsc-bnav-item {
        border-radius: 7px;
        color: #8fa6ba;
    }

    .lsc-bnav-item.active { color: #fff; }
    .lsc-bnav-item.active .bi { color: #79d5a9; }

    @media (max-width: 900px) {
        .lsc-content { padding: 24px 20px 88px; }
        .lsc-topbar { height: 64px; padding: 0 18px; }
        .page-hero { padding: 26px 24px; }
    }

    @media (max-width: 540px) {
        .lsc-content { padding: 16px 12px 86px; }
        .page-hero { border-radius: 8px; }
        .page-hero-title { font-size: 25px; }
        .card, .stat-card { border-radius: 8px; }
    }
    </style>

<?php require LSC_ROOT . '/views/layouts/pwa.php'; ?>


</head>
<body>
    <a class="lsc-skip-link" href="#main-content">Skip to main content</a>

    <!-- Dark overlay (mobile) -->
    <div class="lsc-overlay" id="lscOverlay" onclick="closeSidebar()"></div>

    <!-- ── SIDEBAR ─────────────────────────────────────────── -->
    <aside class="lsc-sidebar" id="lscSidebar">

        <div class="lsc-logo">
            <div>
                <a href="dashboard.php" class="lsc-logo-mark" aria-label="LSC dashboard"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></a>
                <span class="lsc-logo-copy">
                    <span class="lsc-logo-name">LSC</span>
                    <span class="lsc-logo-sub">Learn · Share · Connect</span>
                </span>
            </div>
            <button class="lsc-close-btn" onclick="closeSidebar()" aria-label="Close menu">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <nav class="lsc-nav">
            <div class="lsc-nav-label">Main</div>
            <a href="dashboard.php" onclick="closeSidebar()" class="<?= $current_page === 'dashboard' ? 'active' : '' ?>"<?= $ariaCurrent($current_page === 'dashboard') ?>>
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>
            <?php if (($_SESSION['user_role'] ?? '') === 'lecturer'): ?>
            <a href="teaching.php" onclick="closeSidebar()" class="<?= $current_page === 'teaching' ? 'active' : '' ?>"<?= $ariaCurrent($current_page === 'teaching') ?>>
                <i class="bi bi-easel2-fill"></i> Teaching
            </a>
            <?php endif; ?>
            <a href="groups.php" onclick="closeSidebar()" class="<?= in_array($current_page, ['groups','group']) ? 'active' : '' ?>"<?= $ariaCurrent(in_array($current_page, ['groups','group'], true)) ?>>
                <i class="bi bi-people-fill"></i> Study Groups
            </a>
            <a href="tasks.php" onclick="closeSidebar()" class="<?= $current_page === 'tasks' ? 'active' : '' ?>"<?= $ariaCurrent($current_page === 'tasks') ?>>
                <i class="bi bi-check2-square"></i> Tasks
            </a>
            <a href="files.php" onclick="closeSidebar()" class="<?= $current_page === 'files' ? 'active' : '' ?>"<?= $ariaCurrent($current_page === 'files') ?>>
                <i class="bi bi-folder-fill"></i> Files
            </a>
            <a href="calendar.php" onclick="closeSidebar()" class="<?= $current_page === 'calendar' ? 'active' : '' ?>"<?= $ariaCurrent($current_page === 'calendar') ?>>
                <i class="bi bi-calendar-event-fill"></i> Calendar
            </a>
            <a href="notifications.php" onclick="closeSidebar()" class="<?= $current_page === 'notifications' ? 'active' : '' ?>"<?= $ariaCurrent($current_page === 'notifications') ?>>
                <i class="bi bi-bell-fill"></i> Notifications
                <?php if ($unreadNotificationCount): ?><span style="margin-left:auto;background:var(--brand);color:#fff;border-radius:10px;padding:1px 6px;font-size:10px;"><?= min($unreadNotificationCount, 99) ?></span><?php endif; ?>
            </a>
            <a href="profile.php" onclick="closeSidebar()" class="<?= $current_page === 'profile' ? 'active' : '' ?>"<?= $ariaCurrent($current_page === 'profile') ?>>
                <i class="bi bi-person-circle"></i> My Profile
            </a>

            <a href="help.php" onclick="closeSidebar()" class="<?= $current_page === 'help' ? 'active' : '' ?>"<?= $ariaCurrent($current_page === 'help') ?>>
                <i class="bi bi-question-circle"></i> Help
            </a>

            <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
            <div class="lsc-nav-label" style="margin-top:8px;">Administration</div>
            <a href="admin.php" onclick="closeSidebar()" class="<?= $current_page === 'admin' ? 'active' : '' ?>"<?= $ariaCurrent($current_page === 'admin') ?>>
                <i class="bi bi-shield-lock-fill"></i> Admin Panel
            </a>
            <?php endif; ?>
        </nav>

        <div class="lsc-user">
            <a href="profile.php" class="lsc-user-row" style="text-decoration:none;">
                <div class="lsc-avatar">
                    <?php if (!empty($_SESSION['user_avatar'])): ?>
                        <img src="avatar.php?user_id=<?= (int)($_SESSION['user_id'] ?? 0) ?>" alt="">
                    <?php else: ?>
                        <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="lsc-user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></div>
                    <div class="lsc-user-role"><?= ($_SESSION['user_role'] ?? '') === 'student' ? 'Learner' : ucfirst($_SESSION['user_role'] ?? '') ?></div>
                </div>
            </a>
            <form method="post" action="logout.php" class="lsc-signout-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\LSC\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="lsc-signout">
                    <i class="bi bi-box-arrow-right"></i> Sign Out
                </button>
            </form>
        </div>

    </aside>

    <!-- ── MAIN ───────────────────────────────────────────── -->
    <div class="lsc-main">

        <!-- Topbar -->
        <header class="lsc-topbar">
            <button class="lsc-hamburger" onclick="openSidebar()" aria-label="Open menu">
                <i class="bi bi-list"></i>
            </button>
            <div class="lsc-topbar-title"><?= ucwords(str_replace('-', ' ', $current_page)) ?></div>
            <div class="lsc-topbar-right">
                <?php require LSC_ROOT . '/views/partials/theme-toggle.php'; ?>
                <a href="profile.php" class="lsc-icon-btn" title="Profile" aria-label="Profile">
                    <i class="bi bi-person"></i>
                </a>
                <a href="notifications.php" class="lsc-icon-btn" title="Notifications" aria-label="Notifications">
                    <i class="bi bi-bell"></i>
                    <?php if ($unreadNotificationCount): ?><span class="lsc-notification-badge"><?= min($unreadNotificationCount, 99) ?></span><?php endif; ?>
                </a>
                <form method="post" action="logout.php" class="lsc-icon-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\LSC\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="lsc-icon-btn" title="Sign out" aria-label="Sign out">
                        <i class="bi bi-box-arrow-right" style="font-size:14px;"></i>
                    </button>
                </form>
            </div>
        </header>

        <!-- Page content -->
        <main class="lsc-content" id="main-content" tabindex="-1">
            <?= $page_content ?? '' ?>
        </main>

    </div>

    <!-- ── MOBILE BOTTOM NAV ──────────────────────────────── -->
    <nav class="lsc-bottom-nav" aria-label="Mobile navigation">
        <div class="lsc-bottom-nav-inner">
            <a href="dashboard.php" class="lsc-bnav-item <?= $current_page === 'dashboard' ? 'active' : '' ?>"<?= $ariaCurrent($current_page === 'dashboard') ?>>
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Home</span>
            </a>
            <a href="groups.php" class="lsc-bnav-item <?= in_array($current_page, ['groups','group']) ? 'active' : '' ?>"<?= $ariaCurrent(in_array($current_page, ['groups','group'], true)) ?>>
                <i class="bi bi-people-fill"></i>
                <span>Groups</span>
            </a>
            <a href="tasks.php" class="lsc-bnav-item <?= $current_page === 'tasks' ? 'active' : '' ?>"<?= $ariaCurrent($current_page === 'tasks') ?>>
                <i class="bi bi-check2-square"></i>
                <span>Tasks</span>
            </a>
            <?php if (($_SESSION['user_role'] ?? '') === 'lecturer'): ?>
            <a href="teaching.php" class="lsc-bnav-item <?= $current_page === 'teaching' ? 'active' : '' ?>"<?= $ariaCurrent($current_page === 'teaching') ?>><i class="bi bi-easel2-fill"></i><span>Teaching</span></a>
            <?php elseif (($_SESSION['user_role'] ?? '') === 'admin'): ?>
            <a href="admin.php" class="lsc-bnav-item <?= $current_page === 'admin' ? 'active' : '' ?>"<?= $ariaCurrent($current_page === 'admin') ?>><i class="bi bi-shield-lock-fill"></i><span>Admin</span></a>
            <?php else: ?>
            <a href="files.php" class="lsc-bnav-item <?= $current_page === 'files' ? 'active' : '' ?>"<?= $ariaCurrent($current_page === 'files') ?>><i class="bi bi-folder-fill"></i><span>Files</span></a>
            <?php endif; ?>
            <a href="calendar.php" class="lsc-bnav-item <?= $current_page === 'calendar' ? 'active' : '' ?>"<?= $ariaCurrent($current_page === 'calendar') ?>>
                <i class="bi bi-calendar-event-fill"></i>
                <span>Calendar</span>
            </a>
        </div>
    </nav>

    <script>
    function openSidebar() {
        document.getElementById('lscSidebar').classList.add('open');
        document.getElementById('lscOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        document.getElementById('lscSidebar').classList.remove('open');
        document.getElementById('lscOverlay').classList.remove('open');
        document.body.style.overflow = '';
    }

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeSidebar();
    });

    /**
     * Keep server-rendered pages current without interrupting work in progress.
     * Native form submissions already reload their own page; the storage event
     * refreshes other open LSC tabs after the same action.
     */
    (function setupSafeAutoRefresh() {
        const actionKey = 'lsc:last-user-action';
        const refreshIntervalMs = 30000;
        const loadedAt = Date.now();
        let formIsDirty = false;
        let formIsSubmitting = false;

        function hasOpenModal() {
            return Array.from(document.querySelectorAll('.lsc-modal-bg')).some(function(modal) {
                return window.getComputedStyle(modal).display !== 'none';
            });
        }

        function canRefreshSafely() {
            return document.visibilityState === 'visible'
                && !formIsDirty
                && !formIsSubmitting
                && !hasOpenModal();
        }

        function refreshIfSafe() {
            if (canRefreshSafely()) {
                window.location.reload();
            }
        }

        document.addEventListener('input', function(event) {
            if (event.target.closest('form')) formIsDirty = true;
        });

        document.addEventListener('change', function(event) {
            if (event.target.closest('form')) formIsDirty = true;
        });

        document.addEventListener('submit', function() {
            formIsSubmitting = true;
            try {
                localStorage.setItem(actionKey, String(Date.now()));
            } catch (error) {
                // Auto-refresh is optional; form submission must still continue.
            }
        });

        window.addEventListener('storage', function(event) {
            if (event.key === actionKey && Number(event.newValue) > loadedAt) {
                refreshIfSafe();
            }
        });

        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState !== 'visible') return;

            try {
                if (Number(localStorage.getItem(actionKey)) > loadedAt) {
                    refreshIfSafe();
                }
            } catch (error) {
                // The timed refresh below still works when storage is unavailable.
            }
        });

        window.setInterval(refreshIfSafe, refreshIntervalMs);
    })();
    </script>

<?php require LSC_ROOT . '/views/partials/consent.php'; ?>

</body>
</html>
