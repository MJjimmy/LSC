<?php
// Shared LSC application layout.
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$unreadNotificationCount = isset($_SESSION['user_id'])
    ? (new \StudySync\Notifications\NotificationRepository(
        \StudySync\Core\Database::connection()
    ))->unreadCount((int)$_SESSION['user_id'])
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudySync — Learn. Share. Connect.</title>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Clean, readable interface typeface -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">

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
        --copper:         var(--primary);
        --copper-dark:    var(--primary-dark);
        --copper-dim:     rgba(37,99,235,.12);
        --copper-border:  rgba(147,197,253,.45);
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

    ::-webkit-scrollbar { width: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #b8c7d9; border-radius: 10px; }

    /* ── MOBILE OVERLAY ─────────────────────────────────────── */
    .ss-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.6);
        z-index: 150;
        opacity: 0;
        transition: opacity .25s;
    }
    .ss-overlay.open {
        display: block;
        opacity: 1;
    }

    /* ── SIDEBAR ────────────────────────────────────────────── */
    .ss-sidebar {
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

    .ss-logo {
        padding: 20px 18px 17px;
        border-bottom: 1px solid var(--sidebar-border);
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
    }

    .ss-logo-mark {
        font-family: 'Inter', system-ui, sans-serif;
        font-size: 21px;
        font-weight: 800;
        color: #fff;
        letter-spacing: -0.3px;
        text-decoration: none;
        display: block;
    }

    .ss-logo-mark span { color: #4ade80; }

    .ss-logo-sub {
        font-size: 10.5px;
        color: rgba(255,255,255,.65);
        margin-top: 2px;
        letter-spacing: 0.3px;
    }

    /* Close button — mobile only */
    .ss-close-btn {
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

    .ss-nav {
        flex: 1;
        padding: 16px 12px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .ss-nav-label {
        font-size: 9.5px;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        color: rgba(255,255,255,.22);
        padding: 14px 8px 6px;
        font-weight: 500;
    }

    .ss-nav a {
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

    .ss-nav a .bi { font-size: 15px; flex-shrink: 0; }

    .ss-nav a:hover {
        background: var(--sidebar-hover);
        color: rgba(255,255,255,.8);
    }

    .ss-nav a.active {
        background: rgba(255,255,255,.13);
        color: #fff;
        border-color: rgba(255,255,255,.16);
    }

    .ss-nav a.active .bi { color: #4ade80; }

    /* ── SIDEBAR BOTTOM ─────────────────────────────────────── */
    .ss-user {
        padding: 14px 16px;
        border-top: 1px solid var(--sidebar-border);
    }

    .ss-user-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }

    .ss-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: var(--copper-dim);
        border: 1px solid var(--copper-border);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 13px;
        color: var(--copper);
        flex-shrink: 0;
        overflow: hidden;
    }
    .ss-avatar img { width:100%; height:100%; object-fit:cover; }

    .ss-user-name { font-size: 13px; font-weight: 600; color: #fff; }
    .ss-user-role { font-size: 11px; color: rgba(255,255,255,.35); margin-top: 1px; }

    .ss-signout {
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

    .ss-signout:hover {
        background: rgba(255,255,255,.08);
        color: rgba(255,255,255,.65);
    }

    /* ── MAIN WRAPPER ───────────────────────────────────────── */
    .ss-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        min-width: 0;
    }

    /* ── TOPBAR ─────────────────────────────────────────────── */
    .ss-topbar {
        height: 56px;
        background: var(--card);
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 0 26px;
        flex-shrink: 0;
    }

    /* Hamburger — hidden on desktop */
    .ss-hamburger {
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

    .ss-topbar-title {
        font-family: 'Inter', system-ui, sans-serif;
        font-size: 17px;
        font-weight: 700;
        color: var(--text-primary);
        letter-spacing: -0.2px;
    }

    .ss-topbar-right {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ss-icon-btn {
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

    .ss-icon-btn:hover { background: var(--cream); }
    .ss-icon-btn { position:relative; }
    .ss-notification-badge { position:absolute;right:-5px;top:-5px;min-width:17px;height:17px;padding:0 4px;border-radius:9px;background:var(--red);color:#fff;border:2px solid #fff;font-size:9px;font-weight:700;display:flex;align-items:center;justify-content:center; }

    /* ── CONTENT AREA ───────────────────────────────────────── */
    .ss-content {
        flex: 1;
        overflow-y: auto;
        padding: 26px 28px;
    }

    /* ── SHARED COMPONENTS ──────────────────────────────────── */

    .card,
    .card-modern {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(18,59,99,.04);
    }

    .page-hero {
        background: linear-gradient(120deg, #123b63, #175488);
        border-radius: 12px;
        padding: 28px 30px;
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
        color: #fff;
    }

    .page-hero::after {
        content: '';
        position: absolute;
        top: -60px; right: -60px;
        width: 220px; height: 220px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(74,222,128,.18) 0%, transparent 70%);
        pointer-events: none;
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

    /* Hero action button — goes absolute on desktop, inline on mobile */
    .hero-action-btn {
        position: absolute;
        right: 30px;
        top: 50%;
        transform: translateY(-50%);
        padding: 9px 18px;
        border-radius: 10px;
        background: transparent;
        border: 1px solid var(--copper-border);
        color: var(--copper);
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
    .hero-action-btn:hover { background: var(--copper-dim); }

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

    .stat-card.copper-tint { background: var(--primary-soft); border-color: var(--primary-border); }
    .stat-card.copper-tint .stat-card-label { color: #1e40af; }
    .stat-card.copper-tint .stat-card-value { color: var(--copper-dark); }

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
    .btn-copper  { background: var(--copper) !important; color: #fff !important; }
    .btn-copper:hover { background: var(--copper-dark) !important; }

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

    .ss-input {
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
    .ss-input:focus { border-color: var(--copper); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }

    .ss-label {
        display: block;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--text-secondary);
        margin-bottom: 6px;
    }

    .section-heading {
        font-family: 'Inter', system-ui, sans-serif;
        font-size: 20px;
        font-weight: 800;
        letter-spacing: -0.3px;
        color: var(--text-primary);
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
        color: var(--copper-dark);
        text-decoration: none;
    }
    .panel-link:hover { color: var(--copper); }

    .ss-table { width: 100%; border-collapse: collapse; }
    .ss-table thead { background: var(--cream); }
    .ss-table th {
        padding: 12px 20px;
        text-align: left;
        font-size: 10.5px;
        font-weight: 600;
        letter-spacing: .8px;
        text-transform: uppercase;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border);
    }

    .ss-table td {
        padding: 13px 20px;
        font-size: 13.5px;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border-soft);
    }

    .ss-table tbody tr:last-child td { border-bottom: none; }
    .ss-table tbody tr:hover td { background: var(--cream); }

    /* Scrollable table wrapper */
    .ss-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .ss-modal-bg {
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

    .ss-modal {
        background: var(--card);
        border-radius: 12px;
        width: 100%;
        max-width: 480px;
        box-shadow: 0 20px 60px rgba(0,0,0,.18);
        overflow: hidden;
        max-height: 92vh;
        overflow-y: auto;
    }

    .ss-modal-head {
        padding: 22px 24px 18px;
        border-bottom: 1px solid var(--border-soft);
    }

    .ss-modal-title {
        font-family: 'Inter', system-ui, sans-serif;
        font-size: 20px;
        font-weight: 800;
    }

    .ss-modal-sub { font-size: 12.5px; color: var(--text-muted); margin-top: 3px; }
    .ss-modal-body { padding: 20px 24px; }
    .ss-modal-footer {
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
    .ss-bottom-nav {
        display: none;
        position: fixed;
        bottom: 0; left: 0; right: 0;
        height: 64px;
        background: var(--sidebar);
        border-top: 1px solid var(--sidebar-border);
        z-index: 100;
        padding: 0 4px;
    }

    .ss-bottom-nav-inner {
        display: flex;
        align-items: center;
        justify-content: space-around;
        height: 100%;
    }

    .ss-bnav-item {
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

    .ss-bnav-item .bi { font-size: 19px; }
    .ss-bnav-item.active { color: #4ade80; }
    .ss-bnav-item:hover  { color: rgba(255,255,255,.75); }

    /* ══════════════════════════════════════════════════════════
       RESPONSIVE
       ══════════════════════════════════════════════════════════ */

    @media (max-width: 900px) {

        /* Sidebar slides off-screen, comes back on open */
        .ss-sidebar {
            position: fixed;
            top: 0; left: 0;
            height: 100%;
            transform: translateX(-100%);
            z-index: 200;
        }

        .ss-sidebar.open { transform: translateX(0); }

        /* Show hamburger + close button */
        .ss-hamburger  { display: block; }
        .ss-close-btn  { display: block; }

        /* Content padding + extra bottom space for bottom nav */
        .ss-content { padding: 18px 16px 80px; }
        .ss-topbar  { padding: 0 16px; }

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
        .ss-bottom-nav { display: block; }

        /* Tables scroll on touch */
        .ss-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    }

    @media (max-width: 540px) {

        .ss-content  { padding: 14px 12px 80px; }
        .ss-topbar   { padding: 0 12px; gap: 8px; }

        .page-hero       { padding: 18px 16px; border-radius: 14px; }
        .page-hero-title { font-size: 22px; }
        .page-hero-sub   { font-size: 12px; }

        .stat-card-value { font-size: 24px; }

        .ss-modal-head   { padding: 18px 18px 14px; }
        .ss-modal-body   { padding: 16px 18px; }
        .ss-modal-footer { padding: 12px 18px; }

        .btn-primary { font-size: 12px; padding: 8px 14px; }
    }
    </style>
    <link rel="stylesheet" href="campus-field-guide.css?v=20260706">

<!-- ── PWA ──────────────────────────────────────────────────
     Paste this block inside <head> in layout.php AND auth-layout.php
     just before the closing </head> tag
─────────────────────────────────────────────────────────── -->

<!-- PWA manifest -->
<link rel="manifest" href="/manifest.json">

<!-- Theme colour (browser chrome on Android) -->
<meta name="theme-color" content="#102a43">

<!-- iOS home screen support -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="StudySync">
<link rel="apple-touch-icon" href="/icons/lsc-icon.svg">

<!-- Windows tile -->
<meta name="msapplication-TileImage" content="/icons/lsc-icon.svg">
<meta name="msapplication-TileColor" content="#102a43">

<!-- Favicon -->
<link rel="icon" type="image/svg+xml" href="/icons/lsc-icon.svg">

<!-- Register service worker -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
       navigator.serviceWorker.register('/service-worker.php')
            .then(reg => console.log('SW registered:', reg.scope))
            .catch(err => console.log('SW error:', err));
    });
}
</script>


</head>
<body>

    <!-- Dark overlay (mobile) -->
    <div class="ss-overlay" id="ssOverlay" onclick="closeSidebar()"></div>

    <!-- ── SIDEBAR ─────────────────────────────────────────── -->
    <aside class="ss-sidebar" id="ssSidebar">

        <div class="ss-logo">
            <div>
                <a href="dashboard.php" class="ss-logo-mark">Study<span>Sync</span></a>
                <div class="ss-logo-sub">Learn · Share · Connect</div>
            </div>
            <button class="ss-close-btn" onclick="closeSidebar()" aria-label="Close menu">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <nav class="ss-nav">
            <div class="ss-nav-label">Main</div>
            <a href="dashboard.php" onclick="closeSidebar()" class="<?= $current_page === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>
            <a href="groups.php" onclick="closeSidebar()" class="<?= in_array($current_page, ['groups','group']) ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> Study Groups
            </a>
            <a href="tasks.php" onclick="closeSidebar()" class="<?= $current_page === 'tasks' ? 'active' : '' ?>">
                <i class="bi bi-check2-square"></i> Tasks
            </a>
            <a href="files.php" onclick="closeSidebar()" class="<?= $current_page === 'files' ? 'active' : '' ?>">
                <i class="bi bi-folder-fill"></i> Files
            </a>
            <a href="calendar.php" onclick="closeSidebar()" class="<?= $current_page === 'calendar' ? 'active' : '' ?>">
                <i class="bi bi-calendar-event-fill"></i> Calendar
            </a>
            <a href="notifications.php" onclick="closeSidebar()" class="<?= $current_page === 'notifications' ? 'active' : '' ?>">
                <i class="bi bi-bell-fill"></i> Notifications
                <?php if ($unreadNotificationCount): ?><span style="margin-left:auto;background:var(--copper);color:#fff;border-radius:10px;padding:1px 6px;font-size:10px;"><?= min($unreadNotificationCount, 99) ?></span><?php endif; ?>
            </a>
            <a href="profile.php" onclick="closeSidebar()" class="<?= $current_page === 'profile' ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i> My Profile
            </a>

            <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
            <div class="ss-nav-label" style="margin-top:8px;">Administration</div>
            <a href="admin.php" onclick="closeSidebar()" class="<?= $current_page === 'admin' ? 'active' : '' ?>">
                <i class="bi bi-shield-lock-fill"></i> Admin Panel
            </a>
            <?php endif; ?>
        </nav>

        <div class="ss-user">
            <a href="profile.php" class="ss-user-row" style="text-decoration:none;">
                <div class="ss-avatar">
                    <?php if (!empty($_SESSION['user_avatar'])): ?>
                        <img src="avatar.php?user_id=<?= (int)($_SESSION['user_id'] ?? 0) ?>" alt="">
                    <?php else: ?>
                        <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="ss-user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></div>
                    <div class="ss-user-role"><?= ($_SESSION['user_role'] ?? '') === 'student' ? 'Learner' : ucfirst($_SESSION['user_role'] ?? '') ?></div>
                </div>
            </a>
            <a href="logout.php" class="ss-signout">
                <i class="bi bi-box-arrow-right"></i> Sign Out
            </a>
        </div>

    </aside>

    <!-- ── MAIN ───────────────────────────────────────────── -->
    <div class="ss-main">

        <!-- Topbar -->
        <header class="ss-topbar">
            <button class="ss-hamburger" onclick="openSidebar()" aria-label="Open menu">
                <i class="bi bi-list"></i>
            </button>
            <div class="ss-topbar-title"><?= ucwords(str_replace('-', ' ', $current_page)) ?></div>
            <div class="ss-topbar-right">
                <span class="ss-topbar-date"><?= date('d M Y') ?></span>
                <a href="profile.php" class="ss-icon-btn" title="Profile" aria-label="Profile">
                    <i class="bi bi-person"></i>
                </a>
                <a href="notifications.php" class="ss-icon-btn" title="Notifications" aria-label="Notifications">
                    <i class="bi bi-bell"></i>
                    <?php if ($unreadNotificationCount): ?><span class="ss-notification-badge"><?= min($unreadNotificationCount, 99) ?></span><?php endif; ?>
                </a>
                <a href="logout.php" class="ss-icon-btn" title="Sign out">
                    <i class="bi bi-box-arrow-right" style="font-size:14px;"></i>
                </a>
            </div>
        </header>

        <!-- Page content -->
        <div class="ss-content">
            <?= $page_content ?? '' ?>
        </div>

    </div>

    <!-- ── MOBILE BOTTOM NAV ──────────────────────────────── -->
    <nav class="ss-bottom-nav" aria-label="Mobile navigation">
        <div class="ss-bottom-nav-inner">
            <a href="dashboard.php" class="ss-bnav-item <?= $current_page === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Home</span>
            </a>
            <a href="groups.php" class="ss-bnav-item <?= in_array($current_page, ['groups','group']) ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i>
                <span>Groups</span>
            </a>
            <a href="tasks.php" class="ss-bnav-item <?= $current_page === 'tasks' ? 'active' : '' ?>">
                <i class="bi bi-check2-square"></i>
                <span>Tasks</span>
            </a>
            <a href="files.php" class="ss-bnav-item <?= $current_page === 'files' ? 'active' : '' ?>">
                <i class="bi bi-folder-fill"></i>
                <span>Files</span>
            </a>
            <a href="calendar.php" class="ss-bnav-item <?= $current_page === 'calendar' ? 'active' : '' ?>">
                <i class="bi bi-calendar-event-fill"></i>
                <span>Calendar</span>
            </a>
        </div>
    </nav>

    <script>
    function openSidebar() {
        document.getElementById('ssSidebar').classList.add('open');
        document.getElementById('ssOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        document.getElementById('ssSidebar').classList.remove('open');
        document.getElementById('ssOverlay').classList.remove('open');
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
        const actionKey = 'studysync:last-user-action';
        const refreshIntervalMs = 30000;
        const loadedAt = Date.now();
        let formIsDirty = false;
        let formIsSubmitting = false;

        function hasOpenModal() {
            return Array.from(document.querySelectorAll('.ss-modal-bg')).some(function(modal) {
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

</body>
</html>
