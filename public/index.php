<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'LSC | Learn. Share. Connect.';
$pageDescription = 'Create study groups, assign tasks with due dates, share files and chat with your group. One workspace for your course work, free for students.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">

<?php require LSC_ROOT . '/views/partials/theme-init.php'; ?>

    <!-- Bootstrap Icons + Fonts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="/icons/lsc-icon.svg">
    <meta name="theme-color" content="#123b63">

    <!-- Open Graph / social share -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="LSC">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:image" content="<?= htmlspecialchars(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/icons/lsc-og.jpg') ?>">
    <meta name="twitter:card" content="summary_large_image">

    <style>
        :root {
            --navy:       #123b63;
            --brand:      #2563eb;
            --brand-dark: #1d4ed8;
            --green:      #16a34a;
            --green-dark: #15803d;
            --bg:         #f4f8fc;
            --card:       #ffffff;
            --border:     #dce6f0;
            --text:       #172033;
            --text-2:     #526277;
            --text-3:     #7b8ba0;
        }

        html[data-theme="dark"] {
            --navy:   #10263c;
            --bg:     #0e1a28;
            --card:   #152636;
            --border: #25394c;
            --text:   #e7eff7;
            --text-2: #a9bcca;
            --text-3: #7e93a6;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }

        .container {
            max-width: 1180px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Navigation */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid var(--border);
        }

        .logo { display: flex; align-items: center; gap: 12px; }

        .logo-icon {
            width: 42px; height: 42px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 21px;
            color: var(--brand);
        }
        html[data-theme="dark"] .logo-icon { background: rgba(37,99,235,.15); border-color: rgba(147,197,253,.3); }

        .logo-text h1 { font-size: 23px; font-weight: 800; letter-spacing: -0.3px; }
        .logo-text h1 span { color: var(--green-dark); }
        .logo-text p { font-size: 11px; color: var(--text-3); margin-top: 1px; }

        .nav-actions { display: flex; align-items: center; gap: 10px; }

        .nav-btn {
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
        }
        .nav-btn-outline { border: 1px solid #bfdbfe; color: var(--brand-dark); }
        .nav-btn-outline:hover { background: #eff6ff; border-color: var(--brand); }
        html[data-theme="dark"] .nav-btn-outline { border-color: rgba(147,197,253,.35); color: #93c5fd; }
        html[data-theme="dark"] .nav-btn-outline:hover { background: rgba(37,99,235,.15); }
        .nav-btn-solid { background: var(--brand); color: #fff; font-weight: 600; }
        .nav-btn-solid:hover { background: var(--brand-dark); }

        /* Hero: solid panel, no gradients */
        .hero {
            margin-top: 32px;
            background: var(--navy);
            border-radius: 14px;
            padding: 64px 40px;
            color: #fff;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 500;
            color: #86efac;
            margin-bottom: 24px;
        }

        .hero h2 {
            font-size: 44px;
            font-weight: 800;
            letter-spacing: -1.2px;
            line-height: 1.15;
            max-width: 640px;
            margin-bottom: 16px;
        }

        .hero p {
            font-size: 17px;
            color: rgba(255,255,255,.72);
            max-width: 560px;
            margin-bottom: 28px;
        }

        .hero-buttons { display: flex; gap: 12px; flex-wrap: wrap; }

        .btn-green {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--green); color: #fff;
            padding: 11px 24px; border-radius: 8px;
            font-weight: 600; font-size: 14px; text-decoration: none;
        }
        .btn-green:hover { background: var(--green-dark); }

        .btn-ghost {
            display: inline-flex; align-items: center; gap: 8px;
            border: 1px solid rgba(255,255,255,.3); color: #fff;
            padding: 11px 24px; border-radius: 8px;
            font-weight: 500; font-size: 14px; text-decoration: none;
        }
        .btn-ghost:hover { border-color: #fff; }

        /* Features: each card says something different */
        .features { padding: 56px 0 8px; }

        .section-title { font-size: 28px; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 6px; }
        .section-sub { font-size: 14.5px; color: var(--text-2); margin-bottom: 28px; }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .feature-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 22px 22px 20px;
        }

        .feature-card h3 {
            display: flex; align-items: center; gap: 9px;
            font-size: 16px; font-weight: 700;
            margin-bottom: 8px;
        }
        .feature-card h3 i { color: var(--brand); font-size: 17px; }
        .feature-card p { font-size: 13.5px; color: var(--text-2); line-height: 1.6; }

        /* CTA */
        .cta {
            margin: 48px 0 56px;
            background: #ecfdf3;
            border: 1px solid #bbf7d0;
            border-radius: 14px;
            padding: 44px 36px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
        }
        html[data-theme="dark"] .cta { background: rgba(21,128,61,.14); border-color: rgba(21,128,61,.4); }

        .cta h2 { font-size: 26px; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 6px; }
        .cta p { font-size: 14.5px; color: var(--text-2); max-width: 520px; }
        .cta .btn-green { white-space: nowrap; }

        /* Footer */
        .footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            padding: 24px 0 36px;
            border-top: 1px solid var(--border);
            font-size: 13px;
            color: var(--text-3);
        }
        .footer nav { display: flex; gap: 18px; }
        .footer a { color: var(--text-2); text-decoration: none; }
        .footer a:hover { color: var(--brand-dark); }

        @media (max-width: 900px) {
            .cards-grid { grid-template-columns: 1fr 1fr; }
            .hero h2 { font-size: 34px; }
        }
        @media (max-width: 620px) {
            .cards-grid { grid-template-columns: 1fr; }
            .navbar { flex-direction: column; gap: 14px; }
            .hero { padding: 44px 24px; }
            .hero h2 { font-size: 29px; }
            .cta { padding: 32px 24px; }
        }
    </style>
</head>
<body>

<div class="container">
    <nav class="navbar">
        <div class="logo">
            <div class="logo-icon"><i class="bi bi-mortarboard-fill"></i></div>
            <div class="logo-text">
                <h1>LS<span>C</span></h1>
                <p>Learn | Share | Connect</p>
            </div>
        </div>
        <div class="nav-actions">
            <?php require LSC_ROOT . '/views/partials/theme-toggle.php'; ?>
            <a href="login.php" class="nav-btn nav-btn-outline">Sign in</a>
            <a href="register.php" class="nav-btn nav-btn-solid">Get started</a>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-badge">Study groups, tasks, files and chat</div>
        <h2>Your study group, in one place.</h2>
        <p>Create a group for each of your courses, assign tasks with owners and due dates, share the files the work actually needs, and keep the conversation right next to it.</p>
        <div class="hero-buttons">
            <a href="register.php" class="btn-green"><i class="bi bi-person-plus-fill"></i> Create your account</a>
            <a href="login.php" class="btn-ghost"><i class="bi bi-box-arrow-in-right"></i> Sign in</a>
        </div>
    </section>

    <section class="features">
        <h2 class="section-title">What is in LSC</h2>
        <p class="section-sub">Everything a student group needs to get a course project done.</p>
        <div class="cards-grid">
            <div class="feature-card">
                <h3><i class="bi bi-people-fill"></i> Study groups</h3>
                <p>Public groups join in one click. Private groups run on join requests, and the group creator approves or declines each one from the members tab.</p>
            </div>
            <div class="feature-card">
                <h3><i class="bi bi-check2-square"></i> Tasks with owners and due dates</h3>
                <p>Every task is assigned to one person, has a due date, and sits in one of two states: pending or done. No status soup.</p>
            </div>
            <div class="feature-card">
                <h3><i class="bi bi-folder-fill"></i> A shared file library</h3>
                <p>PDFs, slides, spreadsheets and notes, up to 100 MB per file. Downloads are limited to people who are in the group.</p>
            </div>
            <div class="feature-card">
                <h3><i class="bi bi-calendar-event-fill"></i> One calendar, every group</h3>
                <p>A month view that shows the due dates from every group you belong to, so the next deadline is never a surprise.</p>
            </div>
            <div class="feature-card">
                <h3><i class="bi bi-chat-dots-fill"></i> Group chat with attachments</h3>
                <p>Messages, pictures and documents in the same thread. A file shared in chat stays downloadable from the message itself.</p>
            </div>
            <div class="feature-card">
                <h3><i class="bi bi-envelope-check-fill"></i> Invites and profiles</h3>
                <p>Bring members in with an email invite that stays valid for seven days. Your profile shows your institution, course and contribution counts.</p>
            </div>
        </div>
    </section>

    <section class="cta">
        <div>
            <h2>Set up your first group in a minute.</h2>
            <p>Create an account, start a group, and send the invite link to your classmates. It is free for students.</p>
        </div>
        <a href="register.php" class="btn-green">Start free <i class="bi bi-arrow-right"></i></a>
    </section>

    <footer class="footer">
        <div>&copy; <?= date('Y') ?> LSC | Learn. Share. Connect.</div>
        <nav>
            <a href="privacy.php">Privacy policy</a>
            <a href="terms.php">Terms and conditions</a>
        </nav>
    </footer>
</div>

<?php require LSC_ROOT . '/views/partials/consent.php'; ?>

</body>
</html>
