<?php // Variables are provided by InvitationController::show(). ?>

<div style="max-width:680px;margin:30px auto;">
    <div class="card" style="padding:34px;text-align:center;">
        <div style="width:64px;height:64px;border-radius:18px;background:var(--copper-dim);border:1px solid var(--copper-border);color:var(--copper);display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:28px;">
            <i class="bi bi-people-fill"></i>
        </div>

        <?php if (!$invite): ?>
            <div class="page-hero-title" style="color:var(--text-primary);">Invitation not found</div>
            <p style="color:var(--text-secondary);margin-top:10px;">Ask the group owner to create a new invitation.</p>
        <?php elseif (!$isAvailable): ?>
            <div class="page-hero-title" style="color:var(--text-primary);">Invitation unavailable</div>
            <p style="color:var(--text-secondary);margin-top:10px;">This link has expired or has already been used.</p>
        <?php else: ?>
            <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--copper);margin-bottom:8px;">Group invitation</div>
            <div class="page-hero-title" style="color:var(--text-primary);"><?= htmlspecialchars($invite['group_name']) ?></div>
            <p style="color:var(--text-secondary);margin:10px auto 4px;max-width:500px;line-height:1.6;">
                <?= htmlspecialchars($invite['inviter_name']) ?> invited you to collaborate, share files and study together.
            </p>
            <?php if (!empty($invite['description'])): ?>
                <p style="color:var(--text-muted);font-size:12.5px;margin:8px auto 20px;max-width:500px;"><?= htmlspecialchars($invite['description']) ?></p>
            <?php endif; ?>

            <?php if ($error): ?>
                <div style="background:var(--red-bg);border:1px solid var(--red-border);color:var(--red);padding:11px 13px;border-radius:8px;margin:16px 0;font-size:13px;"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" style="margin-top:22px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <button type="submit" class="btn-primary btn-copper"><i class="bi bi-check2-circle"></i> Accept invitation</button>
                <a href="groups.php" class="btn-ghost" style="display:inline-flex;text-decoration:none;margin-left:8px;">Not now</a>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php $page_content = ob_get_clean(); require STUDYSYNC_ROOT . '/views/layouts/app.php'; ?>
