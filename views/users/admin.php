<?php // Variables are provided by AdminController::index(). ?>
<style>
.admin-hero{align-items:end;background:var(--sidebar);border:1px solid #123d66;color:#fff;display:flex;gap:30px;justify-content:space-between;margin-bottom:20px;min-height:220px;padding:34px 36px}.admin-kicker{color:#79d5a9;font-size:10px;font-weight:700;letter-spacing:.14em;margin-bottom:11px;text-transform:uppercase}.admin-hero h1{font:800 clamp(30px,4vw,46px)/1.08 'Manrope',sans-serif;letter-spacing:-.05em;margin:0}.admin-hero p{color:#b9cad8;font-size:14px;line-height:1.6;margin-top:12px;max-width:650px}.admin-health{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);padding:14px 16px;position:relative;z-index:1}.admin-health span{color:#9fb5c8;display:block;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase}.admin-health strong{display:block;font-size:13px;margin-top:5px}.admin-stats{display:grid;gap:14px;grid-template-columns:repeat(4,1fr);margin-bottom:20px}.admin-stat{background:var(--card);border:1px solid var(--border);padding:20px}.admin-stat-head{align-items:center;color:var(--text-muted);display:flex;font-size:10px;font-weight:700;justify-content:space-between;letter-spacing:.06em;text-transform:uppercase}.admin-stat-head i{align-items:center;background:var(--primary-soft);color:var(--primary);display:flex;height:31px;justify-content:center;width:31px}.admin-stat strong{display:block;font:800 29px/1 'Manrope',sans-serif;letter-spacing:-.04em;margin-top:16px}.admin-ops{display:grid;gap:12px;grid-template-columns:repeat(4,1fr);margin-bottom:20px}.admin-op{background:var(--cream);border:1px solid var(--border);padding:14px 16px}.admin-op span{color:var(--text-muted);font-size:10px}.admin-op strong{float:right;font-size:13px}.admin-warning{background:var(--red-bg);border-color:var(--red-border)}.admin-warning strong{color:var(--red)}.admin-grid{display:grid;gap:18px;grid-template-columns:minmax(0,1.45fr) minmax(320px,.55fr)}.admin-filter{align-items:end;display:grid;gap:10px;grid-template-columns:minmax(180px,1fr) 150px 150px auto;padding:16px 20px}.admin-filter label{color:var(--text-muted);display:block;font-size:9px;font-weight:700;letter-spacing:.07em;margin-bottom:5px;text-transform:uppercase}.admin-user{align-items:center;display:flex;gap:10px}.admin-avatar{align-items:center;background:var(--primary-soft);border:1px solid var(--primary-border);border-radius:50%;color:var(--primary);display:flex;flex:0 0 auto;font-size:12px;font-weight:800;height:35px;justify-content:center;width:35px}.admin-actions{display:flex;flex-wrap:wrap;gap:6px;justify-content:flex-end}.admin-action{border:1px solid var(--border);background:var(--card);color:var(--text-secondary);cursor:pointer;font:700 10px 'DM Sans',sans-serif;padding:7px 9px;text-decoration:none}.admin-action.danger{background:var(--red-bg);border-color:var(--red-border);color:var(--red)}.admin-action.success{background:var(--green-bg);border-color:var(--green-border);color:var(--green)}.admin-group{border-bottom:1px solid var(--border-soft);padding:15px 17px}.admin-group:last-child{border-bottom:0}.admin-group-top{align-items:center;display:flex;gap:8px;justify-content:space-between}.admin-group-top a{color:var(--text-primary);font-size:12px;font-weight:700;text-decoration:none}.admin-group p{color:var(--text-muted);font-size:10px;margin-top:4px}.admin-group-meta{display:flex;gap:12px;margin-top:9px}.admin-group-meta span{color:var(--text-secondary);font-size:9.5px}.admin-section-space{margin-top:18px}
@media(max-width:1100px){.admin-stats,.admin-ops{grid-template-columns:repeat(2,1fr)}.admin-grid{grid-template-columns:1fr}}@media(max-width:760px){.admin-hero{align-items:flex-start;flex-direction:column;padding:28px 23px}.admin-filter{grid-template-columns:1fr 1fr}.admin-filter>div:first-child{grid-column:1/-1}.admin-filter button{width:100%}.admin-table th:nth-child(2),.admin-table td:nth-child(2){display:none}}@media(max-width:500px){.admin-filter{grid-template-columns:1fr}.admin-filter>div:first-child{grid-column:auto}.admin-actions{justify-content:flex-start}.admin-table th:nth-child(3),.admin-table td:nth-child(3){display:none}}
</style>

<section class="admin-hero">
    <div><div class="admin-kicker">Platform administration</div><h1>Operate LSC with a clear view.</h1><p>Monitor access, collaboration spaces, academic activity and notification delivery from one guarded workspace.</p></div>
    <div class="admin-health"><span>Platform status</span><strong><?= $operations['queue_failed'] ? 'Delivery attention required' : 'Core operations healthy' ?></strong></div>
</section>

<section class="admin-stats" aria-label="Platform totals">
    <?php foreach ([
        ['Active users', $stats['total_users'], 'bi-people'],
        ['Study groups', $stats['total_groups'], 'bi-collection'],
        ['Open tasks', $stats['pending_tasks'], 'bi-list-task'],
        ['Shared files', $stats['total_files'], 'bi-folder2-open'],
    ] as [$label,$value,$icon]): ?>
    <article class="admin-stat"><div class="admin-stat-head"><span><?= $label ?></span><i class="bi <?= $icon ?>" aria-hidden="true"></i></div><strong><?= (int)$value ?></strong></article>
    <?php endforeach; ?>
</section>

<section class="admin-ops" aria-label="Operational details">
    <div class="admin-op"><span>Students</span><strong><?= $operations['students'] ?></strong></div>
    <div class="admin-op"><span>Lecturers</span><strong><?= $operations['lecturers'] ?></strong></div>
    <div class="admin-op <?= $operations['unverified_users'] ? 'admin-warning' : '' ?>"><span>Unverified accounts</span><strong><?= $operations['unverified_users'] ?></strong></div>
    <div class="admin-op <?= $operations['inactive_users'] ? 'admin-warning' : '' ?>"><span>Inactive accounts</span><strong><?= $operations['inactive_users'] ?></strong></div>
    <div class="admin-op"><span>Public groups</span><strong><?= $operations['public_groups'] ?></strong></div>
    <div class="admin-op"><span>Private groups</span><strong><?= $operations['private_groups'] ?></strong></div>
    <div class="admin-op"><span>Queued notifications</span><strong><?= $operations['queue_pending'] ?></strong></div>
    <div class="admin-op <?= $operations['queue_failed'] ? 'admin-warning' : '' ?>"><span>Failed notifications</span><strong><?= $operations['queue_failed'] ?></strong></div>
</section>

<div class="admin-grid">
    <section class="card">
        <div class="panel-header"><div><div class="panel-title">People and access</div><div class="panel-sub"><?= count($filteredUsers) ?> matching account<?= count($filteredUsers) === 1 ? '' : 's' ?></div></div></div>
        <form method="GET" class="admin-filter">
            <div><label for="admin-search">Search</label><input id="admin-search" class="lsc-input" type="search" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Name or email"></div>
            <div><label for="admin-role">Role</label><select id="admin-role" class="lsc-input" name="role"><option value="all">All roles</option><?php foreach (['student'=>'Students','lecturer'=>'Lecturers','admin'=>'Administrators'] as $value=>$label): ?><option value="<?= $value ?>" <?= $roleFilter === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
            <div><label for="admin-status">Status</label><select id="admin-status" class="lsc-input" name="status"><option value="all">All statuses</option><option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
            <button class="btn-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Filter</button>
        </form>
        <div style="overflow-x:auto;">
            <table class="lsc-table admin-table">
                <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($filteredUsers as $user): ?>
                <tr>
                    <td><div class="admin-user"><span class="admin-avatar"><?= htmlspecialchars(strtoupper(substr($user['name'],0,1))) ?></span><span><strong><?= htmlspecialchars($user['name']) ?></strong><small style="display:block;color:var(--text-muted);">ID #<?= (int)$user['user_id'] ?></small></span></div></td>
                    <td style="color:var(--text-secondary);"><?= htmlspecialchars($user['email']) ?></td>
                    <td><span class="pill <?= $user['role']==='admin' ? 'pill-admin':'pill-member' ?>"><?= $user['role'] === 'admin' ? 'Admin' : ucfirst($user['role']) ?></span></td>
                    <td><span class="pill <?= $user['is_active'] ? 'pill-active':'pill-inactive' ?>"><?= $user['is_active'] ? 'Active':'Inactive' ?></span></td>
                    <td><div class="admin-actions"><a class="admin-action" href="profile.php?user_id=<?= (int)$user['user_id'] ?>">Profile</a><?php if ((int)$user['user_id'] !== (int)$_SESSION['user_id']): ?><form method="POST" onsubmit="return confirm('Change this account status?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>"><?php if ($user['is_active']): ?><input type="hidden" name="deactivate" value="<?= (int)$user['user_id'] ?>"><button class="admin-action danger" type="submit">Deactivate</button><?php else: ?><input type="hidden" name="activate" value="<?= (int)$user['user_id'] ?>"><button class="admin-action success" type="submit">Activate</button><?php endif; ?></form><?php endif; ?></div></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$filteredUsers): ?><tr><td colspan="5"><div class="empty-state"><p>No accounts match these filters.</p></div></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <aside>
        <section class="card">
            <div class="panel-header"><div><div class="panel-title">Recent groups</div><div class="panel-sub">Newest collaboration spaces</div></div><a class="panel-link" href="groups.php">All groups</a></div>
            <?php foreach ($recentGroups as $group): ?>
            <article class="admin-group">
                <div class="admin-group-top"><a href="group.php?id=<?= (int)$group['group_id'] ?>"><?= htmlspecialchars($group['group_name']) ?></a><span class="pill <?= $group['is_private'] ? 'pill-private':'pill-public' ?>"><?= $group['is_private'] ? 'Private':'Public' ?></span></div>
                <p>Created by <?= htmlspecialchars($group['creator_name']) ?> · <?= date('d M Y', strtotime($group['created_at'])) ?></p>
                <div class="admin-group-meta"><span><i class="bi bi-people"></i> <?= (int)$group['member_count'] ?></span><span><i class="bi bi-list-task"></i> <?= (int)$group['task_count'] ?></span><span><i class="bi bi-folder"></i> <?= (int)$group['file_count'] ?></span></div>
            </article>
            <?php endforeach; ?>
            <?php if (!$recentGroups): ?><div class="empty-state"><p>No groups have been created.</p></div><?php endif; ?>
        </section>
    </aside>
</div>

<?php
$pageTitle = 'Administration | LSC';
$page_content = ob_get_clean();
require LSC_ROOT . '/views/layouts/app.php';
?>
