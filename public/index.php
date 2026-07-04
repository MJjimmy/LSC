<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'StudySync | Smart Academic Collaboration';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Bootstrap Icons + Fonts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        /* ── TOKENS (copied from auth-layout + layout) ── */
        :root {
            --bg-dark:       #161412;
            --copper:        #cd853f;
            --copper-dark:   #a8642a;
            --copper-dim:    rgba(205,133,63,.12);
            --copper-border: rgba(205,133,63,.28);
            --cream:         #faf8f6;
            --card-bg:       #ffffff;
            --border-light:  #ede8e2;
            --text-primary:  #1e1810;
            --text-secondary:#6b5d50;
            --text-muted:    #a09080;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg-dark);
            color: #fff;
            line-height: 1.5;
        }

        /* radial glows (like auth-left) */
        body::before {
            content: '';
            position: fixed;
            top: -100px;
            left: -100px;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(205,133,63,.12) 0%, transparent 65%);
            pointer-events: none;
            z-index: 0;
        }

        body::after {
            content: '';
            position: fixed;
            bottom: -80px;
            right: -80px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(205,133,63,.08) 0%, transparent 65%);
            pointer-events: none;
            z-index: 0;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
            position: relative;
            z-index: 1;
        }

        /* Navigation */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px 0;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 44px;
            height: 44px;
            background: var(--copper-dim);
            border: 1px solid var(--copper-border);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: var(--copper);
        }

        .logo-text h1 {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.3px;
            color: #fff;
        }

        .logo-text span { color: var(--copper); }
        .logo-text p {
            font-size: 11px;
            color: rgba(255,255,255,.35);
            margin-top: 2px;
        }

        .nav-links {
            display: flex;
            gap: 12px;
        }

        .nav-btn {
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all .2s;
        }

        .nav-btn-outline {
            border: 1px solid rgba(255,255,255,.2);
            color: rgba(255,255,255,.8);
        }

        .nav-btn-outline:hover {
            background: rgba(255,255,255,.05);
            border-color: var(--copper);
        }

        .nav-btn-solid {
            background: #fff;
            color: var(--bg-dark);
            font-weight: 600;
        }

        .nav-btn-solid:hover {
            background: var(--copper);
            color: #fff;
        }

        /* Hero section */
        .hero {
            text-align: center;
            padding: 80px 0 60px;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(255,255,255,.05);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 60px;
            padding: 6px 16px;
            font-size: 13px;
            font-weight: 500;
            color: var(--copper);
            margin-bottom: 28px;
        }

        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: 58px;
            font-weight: 800;
            letter-spacing: -1.5px;
            line-height: 1.2;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #fff 0%, var(--copper) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .hero p {
            font-size: 18px;
            color: rgba(255,255,255,.65);
            max-width: 700px;
            margin: 0 auto 32px;
        }

        .hero-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--copper);
            color: #fff;
            padding: 12px 28px;
            border-radius: 40px;
            font-weight: 600;
            text-decoration: none;
            transition: background .2s;
        }

        .btn-primary:hover { background: var(--copper-dark); }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            border: 1px solid rgba(255,255,255,.2);
            color: #fff;
            padding: 12px 28px;
            border-radius: 40px;
            font-weight: 500;
            text-decoration: none;
            transition: all .2s;
        }

        .btn-secondary:hover {
            border-color: var(--copper);
            background: rgba(205,133,63,.1);
        }

        /* Feature cards grid */
        .features {
            padding: 60px 0;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 800;
            text-align: center;
            margin-bottom: 48px;
            color: #fff;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }

        .feature-card {
            background: rgba(255,255,255,.04);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 24px;
            padding: 28px 24px;
            transition: transform .2s, border-color .2s;
        }

        .feature-card:hover {
            transform: translateY(-6px);
            border-color: var(--copper-border);
            background: rgba(255,255,255,.06);
        }

        .feature-icon {
            width: 56px;
            height: 56px;
            background: var(--copper-dim);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: var(--copper);
            margin-bottom: 20px;
        }

        .feature-card h3 {
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #fff;
        }

        .feature-card p {
            color: rgba(255,255,255,.6);
            font-size: 14px;
            line-height: 1.5;
        }

        /* CTA section */
        .cta {
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 32px;
            padding: 56px 40px;
            text-align: center;
            margin: 40px 0 60px;
        }

        .cta h2 {
            font-family: 'Playfair Display', serif;
            font-size: 34px;
            font-weight: 800;
            margin-bottom: 16px;
        }

        .cta p {
            font-size: 16px;
            color: rgba(255,255,255,.6);
            max-width: 550px;
            margin: 0 auto 28px;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 32px 0;
            border-top: 1px solid rgba(255,255,255,.06);
            font-size: 13px;
            color: rgba(255,255,255,.4);
        }

        @media (max-width: 768px) {
            .hero h1 { font-size: 40px; }
            .navbar { flex-direction: column; gap: 16px; }
            .cta { padding: 40px 24px; }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Navigation -->
    <nav class="navbar">
        <div class="logo">
            <div class="logo-icon"><i class="bi bi-mortarboard-fill"></i></div>
            <div class="logo-text">
                <h1>Study<span>Sync</span></h1>
                <p>Collaborate · Share · Succeed</p>
            </div>
        </div>
        <div class="nav-links">
            <a href="login.php" class="nav-btn nav-btn-outline">Sign In</a>
            <a href="register.php" class="nav-btn nav-btn-solid">Get Started</a>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero">
        <div class="hero-badge">
            <i class="bi bi-stars" style="margin-right: 6px;"></i> Smart Collaboration Platform
        </div>
        <h1>Study Smarter.<br>Together.</h1>
        <p>Organize study groups, manage assignments, share resources, track deadlines, and collaborate with classmates in one powerful workspace.</p>
        <div class="hero-buttons">
            <a href="register.php" class="btn-primary"><i class="bi bi-person-plus-fill"></i> Create Free Account</a>
            <a href="login.php" class="btn-secondary"><i class="bi bi-box-arrow-in-right"></i> Sign In</a>
        </div>
    </section>

    <!-- Feature Cards -->
    <section class="features">
        <h2 class="section-title">Everything you need to succeed</h2>
        <div class="cards-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="bi bi-people-fill"></i></div>
                <h3>Study Groups</h3>
                <p>Create or join collaborative study groups for every subject.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="bi bi-check2-square"></i></div>
                <h3>Task Management</h3>
                <p>Assign work, track progress and meet deadlines together.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="bi bi-folder-fill"></i></div>
                <h3>File Sharing</h3>
                <p>Upload notes, presentations, PDFs and resources securely.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="bi bi-calendar-event-fill"></i></div>
                <h3>Smart Calendar</h3>
                <p>Visualize assignments and deadlines across all groups.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="bi bi-chat-dots-fill"></i></div>
                <h3>Rich Group Chat</h3>
                <p>Share messages, pictures and downloadable files in context.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="bi bi-bell-fill"></i></div>
                <h3>Profiles & Notifications</h3>
                <p>Invite classmates, build a profile and never miss a group update.</p>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta">
        <h2>Ready to boost your productivity?</h2>
        <p>Join StudySync today and streamline the way your study groups work.</p>
        <a href="register.php" class="btn-primary" style="background: #fff; color: var(--bg-dark);">Start Free Today <i class="bi bi-arrow-right"></i></a>
    </section>

    <!-- Footer -->
    <footer class="footer">
        © <?= date('Y') ?> StudySync · HatchSoft Group 6
    </footer>
</div>

</body>
</html>
