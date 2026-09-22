<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

$pageTitle = 'Privacy policy | LSC';
$pageDescription = 'How LSC handles your account data, group content, files, cookies and notifications.';
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
        <h1>Privacy policy</h1>
        <p class="updated">This policy describes the LSC collaboration platform. If you use a deployed instance of LSC, the operator of that instance controls the data stored on it.</p>

        <h2>1. What we collect</h2>
        <ul>
            <li><strong>Account details:</strong> your name, email address and a salted password hash. We never store your password in plain text.</li>
            <li><strong>Profile details:</strong> your optional photo, institution, course, bio and phone number (only if you add them in your profile settings).</li>
            <li><strong>Group content:</strong> the groups you create or join, membership and join requests, tasks and their status, the files you upload, and the chat messages you send.</li>
            <li><strong>Invitation and notification records:</strong> group invitations, in-app notifications, and the delivery attempts for any email or SMS you choose to receive.</li>
            <li><strong>Connection data:</strong> a counter of failed login attempts per address and email, used only to rate-limit sign-in, and an IP address derived from the Cloudflare header only when the deployment runs behind Cloudflare.</li>
        </ul>

        <h2>2. What we do with it</h2>
        <ul>
            <li>Run the service: sign you in, show your groups, tasks, files and notifications, and keep group members restricted to people who belong to the group.</li>
            <li>Deliver the notifications you have turned on. Email delivery uses the Resend service and SMS delivery uses Africa's Talking, and the recipient address or phone number is shared with the provider only for that delivery.</li>
            <li>Prevent abuse: rate limiting on login, registration and password reset.</li>
        </ul>
        <p>We do not sell your data, and we do not use it for advertising or to build profiles of you outside the service.</p>

        <h2>3. Cookies</h2>
        <p>LSC sets one session cookie that identifies your signed-in state. It is required for the service to work and has no tracking purpose. We set no analytics or advertising cookies. You can browse without accepting any additional cookies; there is nothing optional to accept.</p>

        <h2>4. Storage and security</h2>
        <p>Account and group data are stored in a MySQL/MariaDB database, and uploaded files are stored on the deployment's persistent storage volume, outside the public web root. Passwords are hashed, reset tokens are stored only as SHA-256 hashes and expire after 60 minutes, file downloads verify your group membership, and all mutating forms are protected with CSRF tokens.</p>

        <h2>5. Your choices</h2>
        <ul>
            <li>Update or remove your profile details, phone number and notification channel preferences from your profile page at any time.</li>
            <li>Leave groups at any time from the group page.</li>
            <li>Ask the operator of the instance to deactivate your account or delete your data.</li>
        </ul>

        <h2>6. Contact</h2>
        <p>Questions about this policy should go to the administrator of the LSC instance you use. If you do not have their contact details, ask your institution's IT or support team.</p>
    </article>

    <footer class="footer">
        <div>&copy; <?= date('Y') ?> LSC</div>
        <div><a href="terms.php">Terms and conditions</a></div>
    </footer>
</div>

<?php require LSC_ROOT . '/views/partials/consent.php'; ?>

</body>
</html>
