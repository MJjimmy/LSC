<?php
// Variables are provided by ProfileController::show(). Recompute policy for isolated view fixtures too.
$canEditProfile = $isOwnProfile || ($isSiteAdmin ?? false);
$canViewContact = $isOwnProfile || ($isSiteAdmin ?? false);
$allowedTabs = $canEditProfile ? ['overview', 'profile'] : ['overview'];
if ($isOwnProfile) {
    $allowedTabs[] = 'notifications';
    $allowedTabs[] = 'security';
}
$activeTab = (string)($_GET['tab'] ?? 'overview');
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'overview';
}
$profileQuery = !$isOwnProfile ? '&user_id=' . (int)$profile['user_id'] : '';
?>
<style>
.profile-heading{margin-bottom:20px}.profile-heading h1{font:800 30px/1.15 'Manrope',sans-serif;letter-spacing:-.04em}.profile-heading p{color:var(--text-secondary);font-size:13px;margin-top:6px}.profile-shell{align-items:start;display:grid;gap:18px;grid-template-columns:290px minmax(0,1fr)}.profile-card{padding:28px 22px;text-align:center}.profile-avatar{align-items:center;background:var(--sidebar);border:4px solid var(--card);border-radius:50%;box-shadow:0 8px 22px rgba(18,59,99,.14);color:#fff;display:flex;font:800 38px 'Manrope',sans-serif;height:112px;justify-content:center;margin:0 auto 16px;overflow:hidden;width:112px}.profile-avatar img{height:100%;object-fit:cover;width:100%}.profile-name{font:800 20px 'Manrope',sans-serif}.profile-role{color:var(--primary);font-size:11px;font-weight:700;letter-spacing:.06em;margin-top:3px;text-transform:uppercase}.profile-context{color:var(--text-secondary);font-size:12px;line-height:1.55;margin-top:12px}.profile-stats{display:grid;gap:7px;grid-template-columns:repeat(3,1fr);margin-top:22px}.profile-stat{background:var(--cream);border:1px solid var(--border);padding:11px 4px}.profile-stat strong{display:block;font:800 18px 'Manrope',sans-serif}.profile-stat span{color:var(--text-muted);font-size:9px}.profile-member{color:var(--text-muted);font-size:10px;margin-top:17px}.profile-tabs{background:var(--card);border:1px solid var(--border);display:flex;margin-bottom:14px;overflow-x:auto;padding:5px}.profile-tab{color:var(--text-secondary);font-size:11px;font-weight:700;padding:9px 13px;text-decoration:none;white-space:nowrap}.profile-tab:hover{background:var(--cream)}.profile-tab.active{background:var(--primary-soft);color:var(--primary)}.profile-panel{padding:26px}.profile-panel h2{font:800 19px 'Manrope',sans-serif;letter-spacing:-.02em}.profile-panel-lead{color:var(--text-secondary);font-size:12px;line-height:1.6;margin-top:5px}.profile-bio{color:var(--text-secondary);font-size:14px;line-height:1.75;margin-top:20px;white-space:pre-line}.profile-details{border-top:1px solid var(--border-soft);display:grid;gap:0;grid-template-columns:1fr 1fr;margin-top:24px}.profile-detail{border-bottom:1px solid var(--border-soft);padding:14px 5px}.profile-detail span{color:var(--text-muted);display:block;font-size:9px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}.profile-detail strong{display:block;font-size:12px;margin-top:4px;overflow-wrap:anywhere}.profile-form-grid{display:grid;gap:15px;grid-template-columns:1fr 1fr;margin-top:22px}.profile-wide{grid-column:1/-1}.profile-actions{display:flex;justify-content:flex-end;margin-top:19px}.profile-choice{align-items:flex-start;background:var(--cream);border:1px solid var(--border);display:flex;gap:11px;padding:16px}.profile-choice input{accent-color:var(--primary);height:17px;margin-top:1px;width:17px}.profile-choice strong{display:block;font-size:12px}.profile-choice span{color:var(--text-muted);display:block;font-size:10px;margin-top:3px}.security-list{display:grid;gap:10px;margin-top:22px}.security-item{align-items:center;border:1px solid var(--border);display:flex;gap:13px;padding:16px}.security-icon{align-items:center;background:var(--primary-soft);color:var(--primary);display:flex;flex:0 0 auto;height:37px;justify-content:center;width:37px}.security-copy{flex:1}.security-copy strong{display:block;font-size:12px}.security-copy span{color:var(--text-muted);display:block;font-size:10px;margin-top:3px}.security-action{border:1px solid var(--border);color:var(--text-secondary);font-size:10px;font-weight:700;padding:7px 9px;text-decoration:none}.security-action:hover{border-color:var(--primary-border);color:var(--primary)}.profile-alert{margin-bottom:16px;padding:12px 14px}.profile-alert.success{background:var(--green-bg);border:1px solid var(--green-border);color:var(--green)}.profile-alert.error{background:var(--red-bg);border:1px solid var(--red-border);color:var(--red)}
@media(max-width:800px){.profile-shell{grid-template-columns:1fr}.profile-card{padding:23px}.profile-avatar{height:94px;width:94px}.profile-card-copy{margin:auto;max-width:420px}.profile-panel{padding:21px}}@media(max-width:540px){.profile-form-grid,.profile-details{grid-template-columns:1fr}.profile-wide{grid-column:auto}.profile-actions .btn-primary{width:100%}.security-item{align-items:flex-start;flex-wrap:wrap}}
</style>

<header class="profile-heading"><h1><?= $isOwnProfile ? 'Account settings' : htmlspecialchars($profile['name']) ?></h1><p><?= $isOwnProfile ? 'Manage your profile, preferences and account security.' : 'Academic profile and collaboration activity.' ?></p></header>
<?php if ($error || $success): ?><div class="profile-alert <?= $success ? 'success':'error' ?>" role="status"><?= htmlspecialchars($success ?: $error) ?></div><?php endif; ?>

<div class="profile-shell">
    <aside class="card profile-card">
        <div class="profile-avatar"><?php if (!empty($profile['avatar_path'])): ?><img src="avatar.php?user_id=<?= (int)$profile['user_id'] ?>" alt="<?= htmlspecialchars($profile['name']) ?>"><?php else: ?><?= htmlspecialchars(strtoupper(substr($profile['name'], 0, 1))) ?><?php endif; ?></div>
        <div class="profile-card-copy"><div class="profile-name"><?= htmlspecialchars($profile['name']) ?></div><div class="profile-role"><?= $profile['role'] === 'student' ? 'Learner' : ucfirst($profile['role']) ?></div>
        <div class="profile-context"><?= htmlspecialchars($profile['course'] ?: 'Course not provided') ?><?php if ($profile['institution']): ?><br><?= htmlspecialchars($profile['institution']) ?><?php endif; ?></div></div>
        <div class="profile-stats"><div class="profile-stat"><strong><?= (int)$profile['group_count'] ?></strong><span>Groups</span></div><div class="profile-stat"><strong><?= (int)$profile['file_count'] ?></strong><span>Files</span></div><div class="profile-stat"><strong><?= (int)$profile['completed_task_count'] ?></strong><span>Tasks done</span></div></div>
        <div class="profile-member">Member since <?= date('F Y', strtotime($profile['created_at'])) ?></div>
    </aside>

    <div>
        <?php if (count($allowedTabs) > 1): ?>
        <nav class="profile-tabs" aria-label="Account settings">
            <?php foreach (['overview'=>'Overview','profile'=>'Edit profile','notifications'=>'Notifications','security'=>'Security'] as $tab=>$label): if (!in_array($tab,$allowedTabs,true)) continue; ?>
            <a class="profile-tab <?= $activeTab === $tab ? 'active':'' ?>" href="profile.php?tab=<?= $tab ?><?= $profileQuery ?>"<?= $activeTab === $tab ? ' aria-current="page"':'' ?>><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <?php if ($activeTab === 'profile'): ?>
        <section class="card profile-panel"><h2>Edit profile</h2><p class="profile-panel-lead">Keep your identity and academic context current for collaborators.</p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <div class="profile-form-grid">
                    <div class="profile-wide"><label class="lsc-label" for="profile-avatar">Profile picture</label><input id="profile-avatar" type="file" name="avatar" class="lsc-input" accept=".png,.jpg,.jpeg,.webp"></div>
                    <div class="profile-wide"><label class="lsc-label" for="profile-name">Full name</label><input id="profile-name" type="text" name="name" class="lsc-input" required maxlength="100" value="<?= htmlspecialchars($profile['name']) ?>"></div>
                    <div><label class="lsc-label" for="profile-institution">Institution</label><input id="profile-institution" type="text" name="institution" class="lsc-input" maxlength="150" value="<?= htmlspecialchars($profile['institution'] ?? '') ?>"></div>
                    <div><label class="lsc-label" for="profile-course">Course or programme</label><input id="profile-course" type="text" name="course" class="lsc-input" maxlength="150" value="<?= htmlspecialchars($profile['course'] ?? '') ?>"></div>
                    <div class="profile-wide"><label class="lsc-label" for="profile-bio">Bio</label><textarea id="profile-bio" name="bio" class="lsc-input" rows="5" maxlength="1000"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea></div>
                </div>
                <div class="profile-actions"><button class="btn-primary" type="submit" name="update_profile"><i class="bi bi-check2" aria-hidden="true"></i> Save profile</button></div>
            </form>
        </section>
        <?php elseif ($activeTab === 'notifications' && $isOwnProfile): ?>
        <section class="card profile-panel"><h2>Notification preferences</h2><p class="profile-panel-lead">Choose how LSC reaches you about invitations and group activity.</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <div class="profile-form-grid"><div class="profile-wide"><label class="lsc-label" for="profile-phone">Phone number</label><input id="profile-phone" type="tel" name="phone_number" class="lsc-input" placeholder="+27821234567" value="<?= htmlspecialchars((string)($profile['phone_number'] ?? '')) ?>"><div class="panel-sub" style="margin-top:5px;">Use international format. A number is required for SMS delivery.</div></div>
                    <label class="profile-choice"><input type="checkbox" name="notify_email" <?= (int)($profile['notify_email'] ?? 1) === 1 ? 'checked':'' ?>><span><strong>Email notifications</strong><span>Receive invitations and important updates by email.</span></span></label>
                    <label class="profile-choice"><input type="checkbox" name="notify_sms" <?= (int)($profile['notify_sms'] ?? 0) === 1 ? 'checked':'' ?>><span><strong>SMS notifications</strong><span>Receive time-sensitive updates on your phone.</span></span></label>
                </div><div class="profile-actions"><button class="btn-primary" type="submit" name="update_notifications"><i class="bi bi-bell" aria-hidden="true"></i> Save preferences</button></div>
            </form>
        </section>
        <?php elseif ($activeTab === 'security' && $isOwnProfile): ?>
        <section class="card profile-panel"><h2>Security and account</h2><p class="profile-panel-lead">Review your account access and recovery details.</p>
            <div class="security-list">
                <div class="security-item"><span class="security-icon"><i class="bi bi-envelope-check" aria-hidden="true"></i></span><div class="security-copy"><strong>Email address</strong><span><?= htmlspecialchars((string)($profile['email'] ?? 'Not provided')) ?> · <?= !empty($profile['email_verified_at']) ? 'Verified':'Verification pending' ?></span></div><?php if (empty($profile['email_verified_at'])): ?><a class="security-action" href="verify-email.php">Verify</a><?php endif; ?></div>
                <div class="security-item"><span class="security-icon"><i class="bi bi-key" aria-hidden="true"></i></span><div class="security-copy"><strong>Password</strong><span>Use account recovery to securely choose a new password.</span></div><a class="security-action" href="forgot-password.php">Reset password</a></div>
                <div class="security-item"><span class="security-icon"><i class="bi bi-person-badge" aria-hidden="true"></i></span><div class="security-copy"><strong>Account role</strong><span><?= ucfirst(htmlspecialchars($profile['role'])) ?> access · <?= !empty($profile['is_active']) ? 'Account active':'Account inactive' ?></span></div></div>
            </div>
        </section>
        <?php else: ?>
        <section class="card profile-panel"><h2><?= $isOwnProfile ? 'Profile overview' : 'About ' . htmlspecialchars($profile['name']) ?></h2><p class="profile-panel-lead"><?= $isOwnProfile ? 'The information your collaborators see across LSC.' : 'Shared academic context for better collaboration.' ?></p><div class="profile-bio"><?= htmlspecialchars($profile['bio'] ?: ($isOwnProfile ? 'Add a bio to help your study partners learn more about you.' : 'This member has not added a bio yet.')) ?></div>
            <div class="profile-details"><div class="profile-detail"><span>Institution</span><strong><?= htmlspecialchars($profile['institution'] ?: 'Not provided') ?></strong></div><div class="profile-detail"><span>Course</span><strong><?= htmlspecialchars($profile['course'] ?: 'Not provided') ?></strong></div><?php if ($canViewContact): ?><div class="profile-detail"><span>Email</span><strong><?= htmlspecialchars((string)($profile['email'] ?? 'Not provided')) ?></strong></div><div class="profile-detail"><span>Phone</span><strong><?= htmlspecialchars((string)($profile['phone_number'] ?? '') ?: 'Not provided') ?></strong></div><?php endif; ?></div>
        </section>
        <?php endif; ?>
    </div>
</div>
<?php $pageTitle = ($isOwnProfile ? 'Account Settings' : $profile['name']) . ' | LSC'; $page_content = ob_get_clean(); require LSC_ROOT . '/views/layouts/app.php'; ?>
