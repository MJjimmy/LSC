<?php // Variables are provided by ProfileController::show(). ?>

<style>
.profile-grid{display:grid;grid-template-columns:300px minmax(0,1fr);gap:18px;align-items:start}.profile-avatar{width:116px;height:116px;border-radius:30px;background:var(--sidebar);color:#fff;display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-size:38px;font-weight:800;margin:0 auto 16px;overflow:hidden;border:4px solid #fff;box-shadow:0 8px 28px rgba(22,20,18,.12)}.profile-avatar img{width:100%;height:100%;object-fit:cover}.profile-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:22px}.profile-stat{background:var(--cream);border:1px solid var(--border);border-radius:10px;padding:11px 6px;text-align:center}.profile-stat strong{display:block;font-size:18px}.profile-stat span{font-size:10px;color:var(--text-muted)}@media(max-width:780px){.profile-grid{grid-template-columns:1fr}.profile-avatar{width:96px;height:96px}.profile-stats{max-width:380px;margin:22px auto 0}}
</style>

<div style="margin-bottom:22px;">
    <div style="font-family:'Playfair Display',serif;font-size:27px;font-weight:800;">Profile</div>
    <div style="font-size:13px;color:var(--text-secondary);margin-top:3px;"><?= $isOwnProfile ? 'Manage how you appear across StudySync.' : 'Student profile and collaboration activity.' ?></div>
</div>

<?php if ($error || $success): ?>
<div style="margin-bottom:16px;padding:12px 14px;border-radius:9px;<?= $success ? 'background:var(--green-bg);border:1px solid var(--green-border);color:var(--green);' : 'background:var(--red-bg);border:1px solid var(--red-border);color:var(--red);' ?>">
    <?= htmlspecialchars($success ?: $error) ?>
</div>
<?php endif; ?>

<div class="profile-grid">
    <section class="card" style="padding:26px 22px;text-align:center;">
        <div class="profile-avatar">
            <?php if (!empty($profile['avatar_path'])): ?><img src="avatar.php?user_id=<?= (int)$profile['user_id'] ?>" alt="<?= htmlspecialchars($profile['name']) ?>"><?php else: ?><?= strtoupper(substr($profile['name'], 0, 1)) ?><?php endif; ?>
        </div>
        <div style="font-size:20px;font-weight:700;"><?= htmlspecialchars($profile['name']) ?></div>
        <div style="font-size:12px;color:var(--copper-dark);font-weight:600;margin-top:3px;"><?= ucfirst($profile['role']) ?></div>
        <?php if (!empty($profile['course'])): ?><div style="font-size:13px;color:var(--text-secondary);margin-top:11px;"><?= htmlspecialchars($profile['course']) ?></div><?php endif; ?>
        <?php if (!empty($profile['institution'])): ?><div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?= htmlspecialchars($profile['institution']) ?></div><?php endif; ?>

        <div class="profile-stats">
            <div class="profile-stat"><strong><?= (int)$profile['group_count'] ?></strong><span>Groups</span></div>
            <div class="profile-stat"><strong><?= (int)$profile['file_count'] ?></strong><span>Files</span></div>
            <div class="profile-stat"><strong><?= (int)$profile['completed_task_count'] ?></strong><span>Tasks done</span></div>
        </div>
        <div style="font-size:11px;color:var(--text-muted);margin-top:18px;">Member since <?= date('F Y', strtotime($profile['created_at'])) ?></div>
    </section>

    <section class="card" style="padding:24px;">
        <?php if ($isOwnProfile): ?>
            <div class="panel-title">About me</div>
            <div style="font-size:13.5px;color:var(--text-secondary);line-height:1.7;margin-top:12px;white-space:pre-line;"><?= htmlspecialchars($profile['bio'] ?: 'Add a bio so other group members can learn more about you.') ?></div>
            <div style="height:1px;background:var(--border-soft);margin:24px 0;"></div>
            <div class="panel-title" style="margin-bottom:3px;">Edit profile</div>
            <div class="panel-sub" style="margin-bottom:20px;">Add a photo and a little context for your study partners.</div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div style="grid-column:1/-1;"><label class="ss-label">Profile picture</label><input type="file" name="avatar" class="ss-input" accept=".png,.jpg,.jpeg,.webp"></div>
                    <div style="grid-column:1/-1;"><label class="ss-label">Full name</label><input type="text" name="name" class="ss-input" required maxlength="100" value="<?= htmlspecialchars($profile['name']) ?>"></div>
                    <div><label class="ss-label">Institution</label><input type="text" name="institution" class="ss-input" maxlength="150" placeholder="University or school" value="<?= htmlspecialchars($profile['institution'] ?? '') ?>"></div>
                    <div><label class="ss-label">Course</label><input type="text" name="course" class="ss-input" maxlength="150" placeholder="Programme or major" value="<?= htmlspecialchars($profile['course'] ?? '') ?>"></div>
                    <div style="grid-column:1/-1;"><label class="ss-label">Bio</label><textarea name="bio" class="ss-input" rows="5" maxlength="1000" placeholder="What are you studying, and what do you enjoy working on?"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea></div>
                </div>
                <div style="display:flex;justify-content:flex-end;margin-top:18px;"><button class="btn-primary btn-copper" type="submit"><i class="bi bi-check2"></i> Save profile</button></div>
            </form>
        <?php else: ?>
            <div class="panel-title">About <?= htmlspecialchars($profile['name']) ?></div>
            <div style="font-size:13.5px;color:var(--text-secondary);line-height:1.7;margin-top:16px;white-space:pre-line;"><?= htmlspecialchars($profile['bio'] ?: 'This student has not added a bio yet.') ?></div>
        <?php endif; ?>
    </section>
</div>

<?php $page_content = ob_get_clean(); require STUDYSYNC_ROOT . '/views/layouts/app.php'; ?>
