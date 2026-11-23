<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'LSC | Better group work starts here';
$pageDescription = 'Bring study groups, tasks, deadlines, shared files and conversation into one focused academic workspace.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">

<?php require LSC_ROOT . '/views/partials/theme-init.php'; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/svg+xml" href="/icons/lsc-icon.svg">
    <meta name="theme-color" content="#0b2b4b">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="LSC">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:image" content="<?= htmlspecialchars(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/icons/lsc-og.jpg') ?>">
    <meta name="twitter:card" content="summary_large_image">

    <style>
        :root {
            --navy-950: #071d32;
            --navy-900: #0b2b4b;
            --navy-800: #123d66;
            --blue-700: #1756a9;
            --blue-600: #2468c5;
            --blue-100: #dcecff;
            --blue-50: #eef6ff;
            --green-700: #16734b;
            --green-100: #d7f2e4;
            --green-50: #edf9f3;
            --ink: #142233;
            --slate: #546579;
            --muted: #78889a;
            --line: #dce4ec;
            --surface: #ffffff;
            --canvas: #f7f9fb;
            --warm: #f5f2ec;
            --shadow-sm: 0 12px 30px rgba(12, 39, 65, .08);
            --shadow-lg: 0 28px 70px rgba(7, 29, 50, .16);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background: var(--surface);
            color: var(--ink);
            font-family: 'DM Sans', system-ui, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        a {
            color: inherit;
        }

        img {
            display: block;
            max-width: 100%;
        }

        .shell {
            margin: 0 auto;
            max-width: 1240px;
            padding: 0 32px;
        }

        .site-header {
            background: rgba(255, 255, 255, .96);
            border-bottom: 1px solid var(--line);
            position: sticky;
            top: 0;
            z-index: 20;
        }

        .header-inner {
            align-items: center;
            display: flex;
            height: 76px;
            justify-content: space-between;
        }

        .brand {
            align-items: center;
            display: inline-flex;
            gap: 11px;
            text-decoration: none;
        }

        .brand-mark {
            align-items: center;
            background: var(--navy-900);
            border-radius: 10px;
            color: #fff;
            display: inline-flex;
            font-size: 20px;
            height: 42px;
            justify-content: center;
            width: 42px;
        }

        .brand-copy {
            display: grid;
            line-height: 1;
        }

        .brand-name {
            color: var(--navy-950);
            font-family: 'Manrope', sans-serif;
            font-size: 21px;
            font-weight: 800;
            letter-spacing: -.04em;
        }

        .brand-line {
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .13em;
            margin-top: 6px;
            text-transform: uppercase;
        }

        .primary-nav {
            align-items: center;
            display: flex;
            gap: 30px;
            margin-left: auto;
            margin-right: 36px;
        }

        .primary-nav a {
            color: var(--slate);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .primary-nav a:hover {
            color: var(--navy-900);
        }

        .header-actions {
            align-items: center;
            display: flex;
            gap: 10px;
        }

        .button {
            align-items: center;
            border: 1px solid transparent;
            border-radius: 8px;
            display: inline-flex;
            font-size: 14px;
            font-weight: 700;
            gap: 8px;
            justify-content: center;
            min-height: 44px;
            padding: 0 18px;
            text-decoration: none;
            transition: background-color .2s ease, border-color .2s ease, color .2s ease, transform .2s ease;
        }

        .button:hover {
            transform: translateY(-1px);
        }

        .button:focus-visible,
        .primary-nav a:focus-visible,
        .footer-links a:focus-visible {
            outline: 3px solid rgba(36, 104, 197, .28);
            outline-offset: 3px;
        }

        .button-secondary {
            border-color: var(--line);
            color: var(--navy-900);
        }

        .button-secondary:hover {
            background: var(--blue-50);
            border-color: #b8d4f2;
        }

        .button-primary {
            background: var(--blue-700);
            color: #fff;
        }

        .button-primary:hover {
            background: #10488e;
        }

        .button-light {
            background: #fff;
            color: var(--navy-900);
        }

        .button-light:hover {
            background: var(--blue-50);
        }

        .hero {
            background: var(--canvas);
            border-bottom: 1px solid var(--line);
            overflow: hidden;
            padding: 76px 0 82px;
            position: relative;
        }

        .hero::after {
            border: 36px solid rgba(18, 61, 102, .06);
            border-radius: 50%;
            content: '';
            height: 210px;
            position: absolute;
            right: -50px;
            top: -30px;
            width: 210px;
        }

        .hero-grid {
            align-items: center;
            display: grid;
            gap: 70px;
            grid-template-columns: minmax(0, .92fr) minmax(470px, 1.08fr);
            position: relative;
            z-index: 1;
        }

        .eyebrow {
            align-items: center;
            color: var(--green-700);
            display: inline-flex;
            font-size: 13px;
            font-weight: 700;
            gap: 9px;
            letter-spacing: .08em;
            margin-bottom: 24px;
            text-transform: uppercase;
        }

        .eyebrow::before {
            background: var(--green-700);
            content: '';
            height: 2px;
            width: 28px;
        }

        .hero h1 {
            color: var(--navy-950);
            font-family: 'Manrope', sans-serif;
            font-size: clamp(42px, 5vw, 66px);
            font-weight: 800;
            letter-spacing: -.052em;
            line-height: 1.04;
            max-width: 650px;
        }

        .hero-lead {
            color: var(--slate);
            font-size: 18px;
            line-height: 1.7;
            margin: 25px 0 31px;
            max-width: 585px;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .hero-note {
            align-items: center;
            color: var(--muted);
            display: flex;
            flex-wrap: wrap;
            font-size: 13px;
            gap: 18px;
            margin-top: 24px;
        }

        .hero-note span {
            align-items: center;
            display: inline-flex;
            gap: 7px;
        }

        .hero-note i {
            color: var(--green-700);
        }

        .hero-visual {
            padding: 10px 0 24px 20px;
            position: relative;
        }

        .photo-frame {
            background: var(--surface);
            border: 1px solid #cfd9e3;
            box-shadow: var(--shadow-lg);
            padding: 10px;
            position: relative;
        }

        .photo-frame img {
            aspect-ratio: 4 / 3;
            object-fit: cover;
            width: 100%;
        }

        .photo-caption {
            align-items: center;
            background: rgba(7, 29, 50, .92);
            bottom: 24px;
            color: #fff;
            display: flex;
            gap: 12px;
            left: 24px;
            padding: 13px 16px;
            position: absolute;
        }

        .photo-caption i {
            color: #79d5a9;
            font-size: 21px;
        }

        .photo-caption strong,
        .photo-caption span {
            display: block;
        }

        .photo-caption strong {
            font-size: 13px;
        }

        .photo-caption span {
            color: #bfd0df;
            font-size: 11px;
        }

        .deadline-card {
            background: var(--surface);
            border: 1px solid var(--line);
            box-shadow: var(--shadow-sm);
            padding: 17px;
            position: absolute;
            right: -24px;
            top: -16px;
            width: 188px;
        }

        .deadline-label {
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .deadline-title {
            color: var(--navy-900);
            font-size: 14px;
            font-weight: 700;
            margin: 5px 0 10px;
        }

        .deadline-meta {
            align-items: center;
            color: var(--green-700);
            display: flex;
            font-size: 12px;
            font-weight: 700;
            gap: 6px;
        }

        .proof-strip {
            background: var(--navy-900);
            color: #fff;
        }

        .proof-grid {
            align-items: center;
            display: grid;
            gap: 34px;
            grid-template-columns: 1.1fr repeat(3, 1fr);
            min-height: 112px;
        }

        .proof-intro {
            color: #d8e4ee;
            font-family: 'Manrope', sans-serif;
            font-size: 16px;
            font-weight: 700;
            max-width: 250px;
        }

        .proof-item {
            border-left: 1px solid rgba(255, 255, 255, .16);
            padding-left: 28px;
        }

        .proof-item strong {
            display: block;
            font-family: 'Manrope', sans-serif;
            font-size: 24px;
            letter-spacing: -.03em;
        }

        .proof-item span {
            color: #9fb5c8;
            font-size: 12px;
        }

        .section {
            padding: 100px 0;
        }

        .section-soft {
            background: var(--canvas);
            border-bottom: 1px solid var(--line);
            border-top: 1px solid var(--line);
        }

        .section-heading {
            align-items: end;
            display: flex;
            gap: 36px;
            justify-content: space-between;
            margin-bottom: 44px;
        }

        .section-kicker {
            color: var(--blue-700);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .12em;
            margin-bottom: 11px;
            text-transform: uppercase;
        }

        .section-heading h2,
        .workflow-copy h2,
        .institution-copy h2,
        .final-cta h2 {
            color: var(--navy-950);
            font-family: 'Manrope', sans-serif;
            font-size: clamp(32px, 4vw, 45px);
            font-weight: 800;
            letter-spacing: -.045em;
            line-height: 1.12;
        }

        .section-heading > p {
            color: var(--slate);
            max-width: 480px;
        }

        .feature-grid {
            display: grid;
            gap: 18px;
            grid-template-columns: repeat(3, 1fr);
        }

        .feature-card {
            background: var(--surface);
            border: 1px solid var(--line);
            min-height: 270px;
            padding: 30px;
            transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
        }

        .feature-card:hover {
            border-color: #b8c9d9;
            box-shadow: var(--shadow-sm);
            transform: translateY(-3px);
        }

        .feature-card:nth-child(2) {
            background: var(--navy-900);
            border-color: var(--navy-900);
            color: #fff;
        }

        .feature-icon {
            align-items: center;
            background: var(--blue-50);
            color: var(--blue-700);
            display: flex;
            font-size: 22px;
            height: 46px;
            justify-content: center;
            margin-bottom: 42px;
            width: 46px;
        }

        .feature-card:nth-child(2) .feature-icon {
            background: rgba(255, 255, 255, .1);
            color: #8fc4ff;
        }

        .feature-card h3 {
            font-family: 'Manrope', sans-serif;
            font-size: 19px;
            letter-spacing: -.02em;
            margin-bottom: 11px;
        }

        .feature-card p {
            color: var(--slate);
            font-size: 14px;
        }

        .feature-card:nth-child(2) p {
            color: #b9cad8;
        }

        .workflow-grid {
            align-items: center;
            display: grid;
            gap: 90px;
            grid-template-columns: .82fr 1.18fr;
        }

        .workflow-copy p {
            color: var(--slate);
            margin: 20px 0 28px;
            max-width: 470px;
        }

        .text-link {
            align-items: center;
            color: var(--blue-700);
            display: inline-flex;
            font-weight: 700;
            gap: 8px;
            text-decoration: none;
        }

        .workflow-list {
            border-top: 1px solid var(--line);
        }

        .workflow-item {
            align-items: start;
            border-bottom: 1px solid var(--line);
            display: grid;
            gap: 20px;
            grid-template-columns: 44px 1fr;
            padding: 25px 0;
        }

        .workflow-number {
            align-items: center;
            background: var(--green-50);
            color: var(--green-700);
            display: flex;
            font-family: 'Manrope', sans-serif;
            font-size: 13px;
            font-weight: 800;
            height: 40px;
            justify-content: center;
            width: 40px;
        }

        .workflow-item h3 {
            font-size: 16px;
            margin-bottom: 4px;
        }

        .workflow-item p {
            color: var(--slate);
            font-size: 14px;
        }

        .institution-panel {
            align-items: center;
            background: var(--warm);
            border: 1px solid #e4ded2;
            display: grid;
            gap: 70px;
            grid-template-columns: 1fr 1fr;
            padding: 58px;
        }

        .institution-copy > p {
            color: var(--slate);
            margin-top: 18px;
        }

        .trust-list {
            display: grid;
            gap: 14px;
        }

        .trust-item {
            align-items: center;
            background: rgba(255, 255, 255, .72);
            border: 1px solid #e4ded2;
            display: flex;
            gap: 14px;
            padding: 16px 18px;
        }

        .trust-item i {
            color: var(--green-700);
            font-size: 18px;
        }

        .trust-item span {
            font-size: 14px;
            font-weight: 600;
        }

        .final-cta-wrap {
            padding: 0 0 100px;
        }

        .final-cta {
            align-items: center;
            background: var(--navy-900);
            color: #fff;
            display: flex;
            justify-content: space-between;
            min-height: 260px;
            overflow: hidden;
            padding: 54px 62px;
            position: relative;
        }

        .final-cta::after {
            border: 46px solid rgba(255, 255, 255, .05);
            border-radius: 50%;
            content: '';
            height: 250px;
            position: absolute;
            right: -75px;
            top: -75px;
            width: 250px;
        }

        .final-cta h2 {
            color: #fff;
            max-width: 610px;
        }

        .final-cta p {
            color: #b9cad8;
            margin-top: 12px;
        }

        .final-cta .button {
            flex: 0 0 auto;
            margin-left: 32px;
            position: relative;
            z-index: 1;
        }

        .site-footer {
            background: var(--navy-950);
            color: #fff;
            padding: 48px 0 34px;
        }

        .footer-top {
            align-items: start;
            display: flex;
            justify-content: space-between;
            padding-bottom: 36px;
        }

        .site-footer .brand-name {
            color: #fff;
        }

        .site-footer .brand-line {
            color: #8da4b9;
        }

        .site-footer .brand-mark {
            background: #fff;
            color: var(--navy-900);
        }

        .footer-links {
            display: flex;
            gap: 28px;
        }

        .footer-links a {
            color: #b8c9d8;
            font-size: 13px;
            text-decoration: none;
        }

        .footer-links a:hover {
            color: #fff;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, .12);
            color: #7f98ae;
            font-size: 12px;
            padding-top: 24px;
        }

        @media (max-width: 1020px) {
            .primary-nav {
                display: none;
            }

            .hero-grid {
                gap: 48px;
                grid-template-columns: 1fr 1fr;
            }

            .deadline-card {
                right: -10px;
            }

            .proof-grid {
                grid-template-columns: repeat(3, 1fr);
                padding: 24px 0;
            }

            .proof-intro {
                grid-column: 1 / -1;
                max-width: none;
            }

            .workflow-grid {
                gap: 56px;
            }
        }

        @media (max-width: 820px) {
            .shell {
                padding: 0 22px;
            }

            .hero {
                padding: 60px 0 66px;
            }

            .hero-grid,
            .workflow-grid,
            .institution-panel {
                grid-template-columns: 1fr;
            }

            .hero-visual {
                margin-top: 10px;
                padding-left: 0;
            }

            .feature-grid {
                grid-template-columns: 1fr;
            }

            .feature-card {
                min-height: auto;
            }

            .feature-icon {
                margin-bottom: 25px;
            }

            .section {
                padding: 76px 0;
            }

            .section-heading {
                align-items: start;
                flex-direction: column;
            }

            .institution-panel {
                gap: 42px;
                padding: 42px;
            }

            .final-cta {
                align-items: start;
                flex-direction: column;
                gap: 28px;
                padding: 46px;
            }

            .final-cta .button {
                margin-left: 0;
            }
        }

        @media (max-width: 580px) {
            .header-inner {
                height: 68px;
            }

            .brand-line,
            .header-actions .button-secondary {
                display: none;
            }

            .brand-mark {
                height: 38px;
                width: 38px;
            }

            .hero h1 {
                font-size: 42px;
            }

            .hero-lead {
                font-size: 16px;
            }

            .hero-actions .button {
                width: 100%;
            }

            .deadline-card {
                position: static;
                width: 100%;
            }

            .photo-frame {
                margin-top: 12px;
            }

            .photo-caption {
                bottom: 18px;
                left: 18px;
            }

            .proof-grid {
                gap: 20px;
                grid-template-columns: 1fr;
                padding: 28px 0;
            }

            .proof-intro {
                grid-column: auto;
            }

            .proof-item {
                padding-left: 20px;
            }

            .section {
                padding: 64px 0;
            }

            .institution-panel,
            .final-cta {
                padding: 32px 24px;
            }

            .final-cta-wrap {
                padding-bottom: 64px;
            }

            .footer-top {
                flex-direction: column;
                gap: 28px;
            }

            .footer-links {
                flex-wrap: wrap;
                gap: 14px 22px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            .button,
            .feature-card {
                transition: none;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="shell header-inner">
            <a class="brand" href="index.php" aria-label="LSC home">
                <span class="brand-mark"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
                <span class="brand-copy">
                    <span class="brand-name">LSC</span>
                    <span class="brand-line">Learn · Share · Connect</span>
                </span>
            </a>

            <nav class="primary-nav" aria-label="Primary navigation">
                <a href="#workspace">Workspace</a>
                <a href="#how-it-works">How it works</a>
                <a href="#institutions">For institutions</a>
                <a href="help.php">Help</a>
            </nav>

            <div class="header-actions">
                <a href="login.php" class="button button-secondary">Sign in</a>
                <a href="register.php" class="button button-primary">Create account</a>
            </div>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="shell hero-grid">
                <div class="hero-copy">
                    <div class="eyebrow">Built for focused academic work</div>
                    <h1>Better group work starts with a shared place.</h1>
                    <p class="hero-lead">Bring your people, plans, deadlines, files and conversation together in one focused workspace built for student collaboration.</p>
                    <div class="hero-actions">
                        <a href="register.php" class="button button-primary">Start your workspace <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                        <a href="#workspace" class="button button-secondary">Explore the platform</a>
                    </div>
                    <div class="hero-note" aria-label="Account benefits">
                        <span><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Free for students</span>
                        <span><i class="bi bi-check-circle-fill" aria-hidden="true"></i> No payment details</span>
                    </div>
                </div>

                <div class="hero-visual">
                    <div class="deadline-card" aria-hidden="true">
                        <div class="deadline-label">Next deadline</div>
                        <div class="deadline-title">Research proposal</div>
                        <div class="deadline-meta"><i class="bi bi-calendar-check"></i> Friday, 16:00</div>
                    </div>
                    <div class="photo-frame">
                        <img src="/images/home-students.png" alt="Four university students working together around a laptop in a campus library">
                        <div class="photo-caption" aria-hidden="true">
                            <i class="bi bi-people-fill"></i>
                            <div>
                                <strong>One group, one clear view</strong>
                                <span>Tasks, files and discussion stay connected</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="proof-strip" aria-label="Platform highlights">
            <div class="shell proof-grid">
                <p class="proof-intro">Designed around the way academic teams actually work.</p>
                <div class="proof-item">
                    <strong>100 MB</strong>
                    <span>per shared file</span>
                </div>
                <div class="proof-item">
                    <strong>One view</strong>
                    <span>for every group deadline</span>
                </div>
                <div class="proof-item">
                    <strong>7 days</strong>
                    <span>for secure group invitations</span>
                </div>
            </div>
        </section>

        <section class="section" id="workspace">
            <div class="shell">
                <div class="section-heading">
                    <div>
                        <p class="section-kicker">A complete group workspace</p>
                        <h2>Less coordination.<br>More progress.</h2>
                    </div>
                    <p>LSC keeps the work close to the conversation, so your group always knows what is next, who owns it and where to find what they need.</p>
                </div>

                <div class="feature-grid">
                    <article class="feature-card">
                        <div class="feature-icon"><i class="bi bi-check2-square" aria-hidden="true"></i></div>
                        <h3>Tasks that stay accountable</h3>
                        <p>Assign a clear owner, set a due date and keep everyone aligned on what is pending and what is complete.</p>
                    </article>
                    <article class="feature-card">
                        <div class="feature-icon"><i class="bi bi-chat-square-text-fill" aria-hidden="true"></i></div>
                        <h3>Conversation with context</h3>
                        <p>Keep group chat beside the work. Share a document in the discussion and find it again without searching another app.</p>
                    </article>
                    <article class="feature-card">
                        <div class="feature-icon"><i class="bi bi-calendar3" aria-hidden="true"></i></div>
                        <h3>Every deadline in view</h3>
                        <p>See upcoming work from every group in one calendar and plan the week before deadlines become surprises.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section section-soft" id="how-it-works">
            <div class="shell workflow-grid">
                <div class="workflow-copy">
                    <p class="section-kicker">Simple from the start</p>
                    <h2>From new group to shared momentum.</h2>
                    <p>Create the structure once, then let everyone contribute. LSC keeps access clear without adding administrative weight to the work.</p>
                    <a class="text-link" href="register.php">Create your first group <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>

                <div class="workflow-list">
                    <div class="workflow-item">
                        <div class="workflow-number">01</div>
                        <div>
                            <h3>Create a group for your course or project</h3>
                            <p>Choose public access or review requests before new members join.</p>
                        </div>
                    </div>
                    <div class="workflow-item">
                        <div class="workflow-number">02</div>
                        <div>
                            <h3>Invite the people doing the work</h3>
                            <p>Share a secure seven-day invitation and bring the team into one place.</p>
                        </div>
                    </div>
                    <div class="workflow-item">
                        <div class="workflow-number">03</div>
                        <div>
                            <h3>Plan, share and move forward together</h3>
                            <p>Coordinate tasks, files, deadlines and discussion from the same workspace.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section" id="institutions">
            <div class="shell">
                <div class="institution-panel">
                    <div class="institution-copy">
                        <p class="section-kicker">A responsible academic foundation</p>
                        <h2>Built with institutional trust in mind.</h2>
                        <p>Clear membership, protected group files and purposeful roles give students a focused place to collaborate while supporting responsible oversight.</p>
                    </div>
                    <div class="trust-list">
                        <div class="trust-item"><i class="bi bi-shield-check" aria-hidden="true"></i><span>Group-restricted file access</span></div>
                        <div class="trust-item"><i class="bi bi-person-check" aria-hidden="true"></i><span>Verified accounts and explicit membership</span></div>
                        <div class="trust-item"><i class="bi bi-lock" aria-hidden="true"></i><span>Secure sessions and protected actions</span></div>
                        <div class="trust-item"><i class="bi bi-building-check" aria-hidden="true"></i><span>Ready for institution-managed deployment</span></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="final-cta-wrap">
            <div class="shell">
                <div class="final-cta">
                    <div>
                        <h2>Give your next project a clearer place to begin.</h2>
                        <p>Create an account, bring in your group and start working together.</p>
                    </div>
                    <a href="register.php" class="button button-light">Create your free account <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="shell">
            <div class="footer-top">
                <a class="brand" href="index.php" aria-label="LSC home">
                    <span class="brand-mark"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
                    <span class="brand-copy">
                        <span class="brand-name">LSC</span>
                        <span class="brand-line">Learn · Share · Connect</span>
                    </span>
                </a>
                <nav class="footer-links" aria-label="Footer navigation">
                    <a href="help.php">Help centre</a>
                    <a href="privacy.php">Privacy policy</a>
                    <a href="terms.php">Terms and conditions</a>
                    <a href="login.php">Sign in</a>
                </nav>
            </div>
            <div class="footer-bottom">&copy; <?= date('Y') ?> LSC. A focused workspace for academic collaboration.</div>
        </div>
    </footer>

<?php require LSC_ROOT . '/views/partials/consent.php'; ?>
</body>
</html>
