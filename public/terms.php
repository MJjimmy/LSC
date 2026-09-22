<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

$pageTitle = 'Terms and conditions | LSC';
$pageDescription = 'The terms for using the LSC collaboration platform: accounts, acceptable use, content and liability.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
<?php require LSC_ROOT . '/views/partials/theme-init.php'; ?>
<link rel="icon" type="image/svg+xml" href="/icons/lsc-icon.svg">
<meta name="theme-color" content="#123b63">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --bg: #f4f8fc; --card: #ffffff; --border: #dce6f0;
    --text: #172033; --text-2: #526277; --text-3: #7b8ba0;
    --brand: #2563eb; --navy: #123b63;
}
html[data-theme="dark"] {
    --bg: #0e1a28; --card: #152636; --border: #25394c;
    --text: #e7eff7; --text-2: #a9bcca; --text-3: #7e93a6;
    --navy: #10263c;
}
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); line-height: 1.65; }
.container { max-width: 780px; margin: 0 auto; padding: 0 24px; }
.navbar { display: flex; justify-content: space-between; align-items: center; padding: 18px 0; border-bottom: 1px solid var(--border); }
.brand { display: flex; align-items: center; gap: 10px; font-size: 19px; font-weight: 800; text-decoration: none; color: var(--text); }
.brand span { color: var(--brand); }
.navbar a.home { font-size: 13.5px; font-weight: 600; color: var(--text-2); text-decoration: none; }
.navbar a.home:hover { color: var(--brand); }
article { padding: 36px 0 56px; }
h1 { font-size: 30px; font-weight: 800; letter-spacing: -0.6px; }
.updated { font-size: 12.5px; color: var(--text-3); margin: 6px 0 26px; }
h2 { font-size: 17px; font-weight: 700; margin: 28px 0 8px; }
p, li { font-size: 14px; color: var(--text-2); }
p { margin-bottom: 10px; }
ul { margin: 0 0 10px 20px; }
li { margin-bottom: 5px; }
strong { color: var(--text); }
.footer { padding: 20px 0 40px; border-top: 1px solid var(--border); font-size: 13px; color: var(--text-3); display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.footer a { color: var(--text-2); text-decoration: none; margin-left: 16px; }
.footer a:hover { color: var(--brand); }
</style>
</head>
<body>
<div class="container">
    <nav class="navbar">
        <a href="index.php" class="brand">LSC <span>|</span> Learn. Share. Connect.</a>
        <div style="display:flex;align-items:center;gap:10px;">
            <?php require LSC_ROOT . '/views/partials/theme-toggle.php'; ?>
            <a href="index.php" class="home">Home</a>
        </div>
    </nav>

    <article>
        <h1>Terms and conditions</h1>
        <p class="updated">These terms apply when you use LSC. By creating an account or using the service, you agree to them.</p>

        <h2>1. The service</h2>
        <p>LSC is a collaboration workspace for study groups: shared groups, tasks, files, chat, invitations and profiles. It is provided for learning and course-related collaboration, free for students. The operator of the instance you use decides any additional conditions, such as who may register.</p>

        <h2>2. Accounts</h2>
        <ul>
            <li>You must provide a valid email address and keep your password secure. You are responsible for all activity that happens under your account.</li>
            <li>One account per person. Registering as a student or a lecturer is your choice; administrator access is granted only by the operator.</li>
            <li>Keep your credentials private and tell the operator immediately if you believe your account has been compromised.</li>
        </ul>

        <h2>3. Acceptable use</h2>
        <p>You may not use LSC to:</p>
        <ul>
            <li>Harass, threaten, bully or discriminate against other users.</li>
            <li>Upload or share content you do not have the right to share, or content that is illegal, defamatory or infringes another person's rights.</li>
            <li>Circumvent access controls, for example by sharing invite links or credentials with people who are not meant to have access to a group.</li>
            <li>Attack, overload or scrape the service, or interfere with other users' ability to use it.</li>
        </ul>

        <h2>4. Content and files</h2>
        <ul>
            <li>You keep the rights to the content and files you upload. By uploading them to a group, you give the members of that group the right to view and download them for the life of the group.</li>
            <li>Group files are limited to 100 MB per file and to the file types listed on the upload screen. Files that violate these terms or the law may be removed.</li>
            <li>When a group is deleted by its creator or an administrator, the files, tasks, messages and invitations in it are deleted with it.</li>
        </ul>

        <h2>5. Availability and support</h2>
        <p>LSC is provided on a best-efforts basis. We do not guarantee that the service will be uninterrupted, error-free or available at any particular time. Data is stored on the operator's infrastructure, and you are responsible for exporting anything you need before leaving a group or before your account is deactivated.</p>

        <h2>6. Changes and termination</h2>
        <p>The operator may deactivate accounts that break these terms, and may change these terms with notice on this page. If the service is discontinued, the operator will give reasonable advance notice where practicable.</p>

        <h2>7. Limitation of liability</h2>
        <p>To the maximum extent permitted by law, LSC and the operator of the instance you use are not liable for indirect or consequential losses, including loss of data, arising from use of the service. Nothing in these terms limits liability that cannot be limited under applicable law.</p>

        <h2>8. Contact</h2>
        <p>Questions about these terms should go to the administrator of the LSC instance you use. If you do not have their contact details, ask your institution's IT or support team.</p>
    </article>

    <footer class="footer">
        <div>&copy; <?= date('Y') ?> LSC</div>
        <div><a href="privacy.php">Privacy policy</a></div>
    </footer>
</div>

<?php require LSC_ROOT . '/views/partials/consent.php'; ?>

</body>
</html>
