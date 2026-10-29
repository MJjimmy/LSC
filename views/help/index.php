<style>
.help-page{max-width:880px;margin:0 auto;color:var(--text-primary)}.help-page h1{font-size:clamp(26px,5vw,36px);line-height:1.2;margin:0 0 12px}.help-page>p{color:var(--text-secondary);margin-bottom:24px}.help-topics{display:flex;flex-wrap:wrap;gap:10px;margin:20px 0 28px}.help-page a{color:var(--brand)}.help-topics a{padding:10px 14px;border:1px solid var(--border);background:var(--card);border-radius:10px;text-decoration:none}.help-section{padding:22px;margin:0 0 16px;border:1px solid var(--border);border-radius:12px;background:var(--card);scroll-margin-top:16px}.help-section h2{font-size:20px;margin:0 0 14px}.help-section p,.help-section li{color:var(--text-secondary);line-height:1.7}.help-section ol,.help-section ul{padding-left:23px}.help-section li{margin-bottom:8px}.help-section summary{cursor:pointer;font-weight:600;min-height:44px;padding:10px 0}.help-section details+details{border-top:1px solid var(--border)}.help-page a:focus-visible,.help-page summary:focus-visible{outline:3px solid var(--brand);outline-offset:3px}@media(max-width:480px){.help-section{padding:18px}.help-topics a{flex:1 1 42%;text-align:center}}
</style>
<article class="help-page">
    <h1>Help &amp; getting started</h1>
    <p>A practical guide to using LSC on the website and Android app.</p>
    <nav class="help-topics" aria-label="Help topics">
        <a href="#account">Account &amp; sign-in</a><a href="#groups">Study groups</a><a href="#invitations">Invitations</a><a href="#tasks">Tasks &amp; calendar</a><a href="#files">Files &amp; chat</a><a href="#settings">Profile &amp; settings</a><a href="#android">Android app</a><a href="#troubleshooting">Troubleshooting</a>
    </nav>
    <section class="help-section" id="account">
        <h2>Account &amp; sign-in</h2>
        <ol><li>Choose <a href="register.php<?= $authSuffix ?>">Create an account</a> and complete the registration form.</li><li><a href="login.php<?= $authSuffix ?>">Sign in</a> using your LSC email and password. The dashboard opens after sign-in.</li><li>Use <a href="forgot-password.php<?= $authSuffix ?>">Forgot password</a> if you cannot remember your password. Check your inbox and spam folder for the reset email.</li></ol>
        <p><strong>Google:</strong> Continue with Google is available on sign-in and registration. If that email already belongs to a password-based LSC account and is not already connected to Google, sign in with your LSC password instead. Accounts are not automatically linked by matching email.</p>
    </section>
    <section class="help-section" id="groups">
        <h2>Study groups</h2>
        <ol><li>Open <a href="groups.php">Study Groups</a> from the main menu.</li><li>Create a group for your course or browse existing groups.</li><li>Under Discover Public Groups, choose <strong>Request to Join</strong> and wait for the group creator or an authorised manager to approve it. For a private group, ask its creator for an invitation.</li><li>Open your group to find its members, tasks, files and chat. Group management actions depend on your membership and permissions.</li></ol>
        <p><strong>Leave a group:</strong> Open the group, choose <strong>Leave group</strong> and confirm. Your membership is removed, but shared tasks, files and chat history stay with the group. Group creators cannot leave their own group.</p>
    </section>
    <section class="help-section" id="invitations">
        <h2>Invitations &amp; notifications</h2>
        <ol><li>If you manage a group, open the group's member/invitation controls and enter the recipient's correct email address.</li><li>Recipients can open the invitation link and follow the sign-in or registration steps. Use the account matching the invited email.</li><li>Check <a href="notifications.php">Notifications</a> for in-app updates. In <a href="profile.php">My Profile</a>, enable Email notifications and save to receive eligible group invitation emails.</li></ol>
        <p>If an email does not arrive, allow a short time for processing, check spam/junk and verify the address. A created invitation does not guarantee inbox delivery. Ask the group creator for a fresh invitation if the link expired.</p>
    </section>
    <section class="help-section" id="tasks">
        <h2>Tasks &amp; calendar</h2>
        <p>Use <a href="tasks.php">Tasks</a> or your group's task area to view work, owners and due dates. Where you have permission, create tasks, assign an owner and update their completion status. Open <a href="calendar.php">Calendar</a> to see group deadlines together.</p>
    </section>
    <section class="help-section" id="files">
        <h2>Files &amp; group chat</h2>
        <p>Open <a href="files.php">Files</a> or the group's files area to upload permitted study resources. Follow the file-type and size limits displayed by the upload form. Downloads are limited to authorised group members.</p>
        <p>Use the group's chat to send messages and permitted attachments. If an upload fails, check the connection, file type and displayed size limit before trying again.</p>
    </section>
    <section class="help-section" id="settings">
        <h2>Profile &amp; settings</h2>
        <p>Open <a href="profile.php">My Profile</a> to edit your name, picture, institution, course and bio. Save profile changes. Notification preferences have their own <strong>Save notifications</strong> button.</p>
        <p>LSC uses a consistent light appearance, including when your device or a previous preference uses dark mode. On a phone, open the menu using the menu icon to reach Help, Profile and the other sections. Sign out on shared devices.</p>
    </section>
    <section class="help-section" id="android">
        <h2>Android app</h2>
        <ol><li>The app opens on its native Welcome to LSC screen. Choose Create an account, Sign in or Help.</li><li>The app uses the same PHP service and account as the website. An internet connection is required to load your account and study data.</li><li>Google sign-in opens your system browser. Complete Google's prompts and use the displayed return-to-app action to finish signing in.</li><li>Use Android Back to return through pages or to the welcome screen. If a connection error appears, restore your connection and tap Try again.</li></ol>
    </section>
    <section class="help-section" id="troubleshooting">
        <h2>Troubleshooting &amp; support</h2>
        <details><summary>I cannot sign in</summary><p>Check your email and password, try Forgot password and make sure the account is active. If Google reports an existing LSC account, use that account's LSC email and password.</p></details>
        <details><summary>I cannot see a group or file</summary><p>Confirm you signed in to the correct account and that your membership or private-group request was approved. Ask the group creator to check your access.</p></details>
        <details><summary>The app or website is not loading</summary><p>Check your internet connection and try again. If only the app fails, try signing in through the website to help identify the issue. Never bypass a certificate/security warning.</p></details>
        <details><summary>I still need help</summary><p>Contact your group creator for membership or invitation issues, or your LSC administrator for account problems. Include the page, steps and error message. Never send your password, reset link or verification code.</p></details>
    </section>
</article>
