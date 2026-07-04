<?php
/* ═══════════════════════════════════════════════════════════════════
   admin.php — Obsidian & Copper theme
   All PHP logic is identical to the original.
═══════════════════════════════════════════════════════════════════ */
ob_start();
require_once __DIR__ . '/config/db_helpers.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/csrf.php';
requireLogin();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['deactivate'])) {
        $targetUserId = (int)$_POST['deactivate'];
        if ($targetUserId !== (int)$_SESSION['user_id']) {
            $pdo->prepare("UPDATE users SET is_active = 0 WHERE user_id = ?")
                ->execute([$targetUserId]);
        }
        header('Location: admin.php'); exit;
    }
    if (isset($_POST['activate'])) {
        $pdo->prepare("UPDATE users SET is_active = 1 WHERE user_id = ?")
            ->execute([(int)$_POST['activate']]);
        header('Location: admin.php'); exit;
    }
}

$stats = getAdminStats();
$users = $pdo->query("SELECT user_id, name, email, role, is_active FROM users ORDER BY user_id")->fetchAll();
?>

<!-- ── HERO ───────────────────────────────────────────────── -->
<div class="page-hero" style="margin-bottom:24px;">
    <div class="page-hero-eyebrow">Administration</div>
    <div class="page-hero-title">Admin Panel</div>
    <div class="page-hero-sub">Manage users, monitor activity and oversee the StudySync platform.</div>
</div>

<!-- ── STATS ──────────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px;">
    <?php foreach([
        ['bi-people-fill',     'Total Users',  $stats['total_users']],
        ['bi-collection-fill', 'Total Groups', $stats['total_groups']],
        ['bi-list-check',      'Total Tasks',  $stats['total_tasks']],
        ['bi-folder-fill',     'Total Files',  $stats['total_files']],
    ] as [$icon, $lbl, $val]): ?>
    <div class="stat-card">
        <div style="width:32px;height:32px;border-radius:6px;background:#f2ece5;color:var(--copper-dark);
                    display:flex;align-items:center;justify-content:center;font-size:15px;margin-bottom:12px;">
            <i class="bi <?= $icon ?>"></i>
        </div>
        <div class="stat-card-value"><?= $val ?></div>
        <div class="stat-card-label" style="margin-top:4px;"><?= $lbl ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── USER MANAGEMENT TABLE ─────────────────────────────── -->
<div class="card">
    <div class="panel-header">
        <div>
            <div class="panel-title">User Management</div>
            <div class="panel-sub">Activate, deactivate and monitor platform users.</div>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="ss-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th style="text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:34px;height:34px;border-radius:50%;background:var(--copper-dim);
                                        border:1px solid var(--copper-border);display:flex;align-items:center;
                                        justify-content:center;font-weight:700;font-size:13px;color:var(--copper);flex-shrink:0;">
                                <?= strtoupper(substr($u['name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div style="font-weight:600;"><?= htmlspecialchars($u['name']) ?></div>
                                <div style="font-size:11px;color:var(--text-muted);">ID #<?= $u['user_id'] ?></div>
                            </div>
                        </div>
                    </td>
                    <td style="color:var(--text-secondary);"><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <span class="pill <?= $u['role']==='admin' ? 'pill-admin':'pill-member' ?>">
                            <?= $u['role']==='admin' ? 'Administrator':'Member' ?>
                        </span>
                    </td>
                    <td>
                        <span class="pill <?= $u['is_active'] ? 'pill-active':'pill-inactive' ?>">
                            <?= $u['is_active'] ? 'Active':'Inactive' ?>
                        </span>
                    </td>
                    <td style="text-align:right;">
                        <?php if ($u['user_id'] != $_SESSION['user_id']): ?>
                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <?php if ($u['is_active']): ?>
                                <input type="hidden" name="deactivate" value="<?= $u['user_id'] ?>">
                                <button type="submit" style="padding:6px 12px;border-radius:6px;border:1px solid var(--red-border);
                                        background:var(--red-bg);color:var(--red);font-size:12px;font-weight:600;
                                        cursor:pointer;font-family:'DM Sans',sans-serif;">Deactivate</button>
                            <?php else: ?>
                                <input type="hidden" name="activate" value="<?= $u['user_id'] ?>">
                                <button type="submit" style="padding:6px 12px;border-radius:6px;border:1px solid var(--green-border);
                                        background:var(--green-bg);color:var(--green);font-size:12px;font-weight:600;
                                        cursor:pointer;font-family:'DM Sans',sans-serif;">Activate</button>
                            <?php endif; ?>
                        </form>
                        <?php else: ?>
                            <span style="font-size:12px;color:var(--text-muted);">Current User</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require 'layout.php';
?>
