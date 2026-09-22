<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'LSC | Learn. Share. Connect.';
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ── TOKENS (copied from auth-layout + layout) ── */
        :root {
            --bg-dark:       #123b63;
            --brand:        #2563eb;
            --brand-dark:   #1d4ed8;
            --brand-dim:    rgba(37,99,235,.12);
            --brand-border: rgba(147,197,253,.45);
            --green:         #16a34a;
            --cream:         #f4f8fc;
            --card-bg:       #ffffff;
            --border-light:  #dce6f0;
            --text-primary:  #172033;
            --text-secondary:#526277;
            --text-muted:    #7b8ba0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(160deg, #0f2f52 0%, var(--bg-dark) 58%, #175488 100%);
            color: #fff;
            line-height: 1.5;
        }

        /* Restrained brand accents */
        body::before {
            content: '';
            position: fixed;
            top: -100px;
            left: -100px;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(74,222,128,.13) 0%, transparent 65%);
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
            background: radial-gradient(circle, rgba(96,165,250,.13) 0%, transparent 65%);
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
            background: var(--brand-dim);
            border: 1px solid var(--brand-border);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: var(--brand);
        }

        .logo-text h1 {
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.3px;
            color: #fff;
        }

        .logo-text span { color: #4ade80; }
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
            border-radius: 8px;
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
            border-color: var(--brand);
        }

        .nav-btn-solid {
            background: #fff;
            color: var(--bg-dark);
            font-weight: 600;
        }

        .nav-btn-solid:hover {
            background: var(--brand);
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
            color: var(--brand);
            margin-bottom: 28px;
        }

        .hero h1 {
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 58px;
            font-weight: 800;
            letter-spacing: -1.5px;
            line-height: 1.2;
            margin-bottom: 20px;
            color: #fff;
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
            background: var(--brand);
            color: #fff;
            padding: 12px 28px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: background .2s;
        }

        .btn-primary:hover { background: var(--brand-dark); }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            border: 1px solid rgba(255,255,255,.2);
            color: #fff;
            padding: 12px 28px;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            transition: all .2s;
        }

        .btn-secondary:hover {
            border-color: var(--brand);
            background: rgba(37,99,235,.14);
        }

        /* Feature cards grid */
        .features {
            padding: 60px 0;
        }

        .section-title {
            font-family: 'Inter', system-ui, sans-serif;
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
            border-radius: 12px;
            padding: 28px 24px;
            transition: transform .2s, border-color .2s;
        }

        .feature-card:hover {
            transform: translateY(-2px);
            border-color: var(--brand-border);
            background: rgba(255,255,255,.06);
        }

        .feature-icon {
            width: 56px;
            height: 56px;
            background: var(--brand-dim);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: var(--brand);
            margin-bottom: 20px;
        }

        .feature-card h3 {
            font-family: 'Inter', system-ui, sans-serif;
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
            border-radius: 14px;
            padding: 56px 40px;
            text-align: center;
            margin: 40px 0 60px;
        }

        .cta h2 {
            font-family: 'Inter', system-ui, sans-serif;
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

        /* LSC light educational theme */
        body { background: #f4f8fc; color: var(--text-primary); }
        body::before, body::after { display: none; }
        .navbar { border-bottom-color: var(--border-light); }
        .logo-icon { background: #eff6ff; border-color: #bfdbfe; color: var(--brand); }
        .logo-text h1 { color: var(--text-primary); }
        .logo-text span { color: var(--green); }
        .logo-text p { color: var(--text-muted); }
        .nav-btn-outline { border-color: #bfdbfe; color: var(--brand-dark); }
        .nav-btn-outline:hover { background: #eff6ff; border-color: var(--brand); }
        .nav-btn-solid { background: var(--brand); color: #fff; }
        .nav-btn-solid:hover { background: var(--brand-dark); }
        .hero { margin-top: 24px; padding: 72px 32px; border-radius: 14px; background: linear-gradient(120deg, #123b63, #175488); }
        .hero-badge { background: rgba(255,255,255,.09); border-color: rgba(255,255,255,.16); color: #86efac; }
        .hero .btn-primary { background: var(--green); }
        .hero .btn-primary:hover { background: #15803d; }
        .section-title { color: var(--text-primary); }
        .feature-card { background: #fff; backdrop-filter: none; border-color: var(--border-light); box-shadow: 0 1px 2px rgba(18,59,99,.04); }
        .feature-card:hover { background: #fff; border-color: #bfdbfe; }
        .feature-card h3 { color: var(--text-primary); }
        .feature-card p { color: var(--text-secondary); }
        .cta { background: #ecfdf3; border-color: #bbf7d0; }
        .cta h2 { color: var(--text-primary); }
        .cta p { color: var(--text-secondary); }
        .cta .btn-primary { background: var(--brand) !important; color: #fff !important; }
        .footer { border-top-color: var(--border-light); color: var(--text-muted); }
    </style>
</head>
<body>

<div class="container">
    <!-- Navigation -->
    <nav class="navbar">
        <div class="logo">
            <div class="logo-icon"><i class="bi bi-mortarboard-fill"></i></div>
            <div class="logo-text">
                <h1>LS<span>C</span></h1>
                <p>Learn · Share · Connect</p>
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
            <i class="bi bi-mortarboard-fill" style="margin-right: 6px;"></i> Learning and collaboration workspace
        </div>
        <h1>Learn, share and move forward together.</h1>
        <p>One clear workspace for study groups, shared resources, tasks, deadlines and practical collaboration for students and lifelong learners.</p>
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
                <p>Invite peers, build a profile and never miss a group update.</p>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta">
        <h2>Ready to boost your productivity?</h2>
        <p>Join LSC and bring your learning, resources and group work into one place.</p>
        <a href="register.php" class="btn-primary" style="background: #fff; color: var(--bg-dark);">Start Free Today <i class="bi bi-arrow-right"></i></a>
    </section>

    <!-- Footer -->
    <footer class="footer">
        © <?= date('2026') ?> LSC — Learn. Share. Connect. 
    </footer>
</div>

</body>
</html>
