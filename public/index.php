<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Midnight Campus — Focus after dark</title>
    <meta name="description" content="Keep study groups, tasks, deadlines and shared resources together in one clear workspace.">
    <meta name="theme-color" content="#070b14">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500;600&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="campus-field-guide.css?v=20260707">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/svg+xml" href="/icons/midnight-campus-icon.svg">
</head>
<body class="landing-page">
    <div class="landing-shell">
        <nav class="landing-nav" aria-label="Main navigation">
            <a href="index.php" class="landing-brand">Midnight<span>Campus</span><small>Plan · Focus · Finish</small></a>
            <div class="landing-nav-links">
                <a href="login.php" class="landing-link">Sign in</a>
                <a href="register.php" class="landing-button">Create your desk</a>
            </div>
        </nav>

        <main>
            <section class="landing-hero" aria-labelledby="landing-title">
                <div class="landing-hero-copy">
                    <div class="landing-eyebrow">Focus mode for ambitious students</div>
                    <h1 id="landing-title">Own the night. Finish the work.</h1>
                    <p>
                        Midnight Campus gives your group one focused place for the assignment, the deadline,
                        the notes and the conversation around them.
                    </p>
                    <div class="landing-hero-actions">
                        <a href="register.php" class="landing-button is-sun">Start with your next deadline <i class="bi bi-arrow-right"></i></a>
                        <span class="landing-note">Free to create an account.</span>
                    </div>
                </div>

                <aside class="landing-desk" aria-label="Example Midnight Campus dashboard">
                    <div class="landing-desk-head">
                        <strong>Today’s desk</strong>
                        <span>Live example</span>
                    </div>
                    <div class="landing-desk-date">
                        <strong><?= date('d') ?></strong>
                        <span><?= date('F') ?><br><?= date('l') ?></span>
                    </div>
                    <div class="landing-desk-list">
                        <div class="landing-desk-item"><span>01</span><span><strong>Research outline</strong><small>Due tomorrow · Research Methods</small></span><i class="bi bi-arrow-up-right"></i></div>
                        <div class="landing-desk-item"><span>02</span><span><strong>Calculus problem set</strong><small>Due Friday · Applied Mathematics</small></span><i class="bi bi-arrow-up-right"></i></div>
                        <div class="landing-desk-item"><span>03</span><span><strong>Group check-in</strong><small>4 new messages · Systems Analysis</small></span><i class="bi bi-arrow-up-right"></i></div>
                    </div>
                </aside>
            </section>

            <section class="landing-manifesto" aria-labelledby="manifesto-title">
                <div>
                    <div class="landing-eyebrow">How the desk works</div>
                    <h2 id="manifesto-title">Clarity before productivity.</h2>
                </div>
                <div class="landing-chapters">
                    <article class="landing-chapter"><div><h3>Put the real deadline in view.</h3><p>See tasks from every group together, then work from what is due first—not what is making the most noise.</p></div></article>
                    <article class="landing-chapter"><div><h3>Keep context with the people.</h3><p>Group chat, membership and shared responsibility stay connected to the subject instead of disappearing across apps.</p></div></article>
                    <article class="landing-chapter"><div><h3>Leave the resource where it belongs.</h3><p>Notes, presentations and reference files remain easy to find inside the group that needs them.</p></div></article>
                </div>
            </section>

            <section class="landing-proof" aria-labelledby="proof-title">
                <div class="landing-proof-head">
                    <div><div class="landing-eyebrow">Built around actual group work</div><h2 id="proof-title">Less hunting. More finishing.</h2></div>
                    <p>Midnight Campus keeps the essential tools close without turning your coursework into another complicated system to manage.</p>
                </div>
                <div class="landing-proof-row">
                    <article class="landing-proof-item"><i class="bi bi-people"></i><h3>Focused groups</h3><p>Request access, organise members and keep every subject in its own working space.</p></article>
                    <article class="landing-proof-item"><i class="bi bi-check2-square"></i><h3>Visible deadlines</h3><p>Assign tasks, track completion and read the week from a shared calendar.</p></article>
                    <article class="landing-proof-item"><i class="bi bi-folder2-open"></i><h3>Useful resources</h3><p>Share notes, documents and images where the rest of the group can find them again.</p></article>
                </div>
            </section>

            <section class="landing-final" aria-labelledby="final-title">
                <div><div class="landing-eyebrow">Your next move</div><h2 id="final-title">Bring the next deadline. We’ll clear the desk.</h2></div>
                <a href="register.php" class="landing-button is-sun">Create your account <i class="bi bi-arrow-right"></i></a>
            </section>
        </main>

        <footer class="landing-footer">
            <span>© <?= date('Y') ?> Midnight Campus · HatchSoft Group 6</span>
            <span>Plan · Focus · Finish</span>
        </footer>
    </div>

    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () { navigator.serviceWorker.register('/service-worker.php'); });
    }
    </script>
</body>
</html>
