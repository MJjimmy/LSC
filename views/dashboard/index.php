<?php // Variables are provided by DashboardController::index(). ?>

<style>
/* ── DASHBOARD RESPONSIVE ───────────────────────────────── */
.dash-stats   { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
.dash-quick   { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:22px; }
.dash-bottom  { display:grid; grid-template-columns:1fr 340px; gap:18px; }
.dash-admin   { display:flex; align-items:center; justify-content:space-between; gap:20px; flex-wrap:wrap; }
.dash-admin-stats { display:flex; gap:32px; flex-wrap:wrap; }

@media (max-width: 900px) {
    .dash-stats  { grid-template-columns:repeat(2,1fr); gap:10px; }
    .dash-quick  { grid-template-columns:repeat(2,1fr); gap:10px; }
    .dash-bottom { grid-template-columns:1fr; }
}

@media (max-width: 540px) {
    .dash-stats  { grid-template-columns:repeat(2,1fr); gap:8px; }
    .dash-quick  { grid-template-columns:1fr; gap:8px; }
    .dash-admin-stats { gap:18px; }
}
</style>

<!-- ── HERO ───────────────────────────────────────────────── -->
<div class="page-hero" style="margin-bottom:24px;">
    <div class="page-hero-eyebrow">Welcome Back</div>
    <div class="page-hero-title"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
    <div class="page-hero-sub"><?= date('l, d F Y') ?></div>
    <a href="groups.php" class="hero-action-btn">
        Browse Groups <i class="bi bi-arrow-right"></i>
    </a>
</div>

<!-- ── STAT CARDS ────────────────────────────────────────── -->
<div class="dash-stats">

    <div class="stat-card">
        <div class="stat-card-label"><i class="bi bi-people-fill" style="margin-right:5px;"></i>My Groups</div>
        <div class="stat-card-value"><?= count($myGroups) ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-card-label"><i class="bi bi-list-task" style="margin-right:5px;"></i>My Tasks</div>
        <div class="stat-card-value"><?= count($myTasks) ?></div>
    </div>

    <div class="stat-card copper-tint">
        <div class="stat-card-label"><i class="bi bi-clock-fill" style="margin-right:5px;"></i>Pending</div>
        <div class="stat-card-value"><?= count($pendingTasks) ?></div>
    </div>

    <div class="stat-card red-tint">
        <div class="stat-card-label"><i class="bi bi-exclamation-triangle-fill" style="margin-right:5px;"></i>Overdue</div>
        <div class="stat-card-value"><?= count($overdueTasks) ?></div>
    </div>

</div>

<!-- ── QUICK ACTIONS ─────────────────────────────────────── -->
<div class="dash-quick">

    <?php
    $quickActions = [
        ['href'=>'groups.php',   'icon'=>'bi-people-fill',        'title'=>'Browse Groups', 'desc'=>'Discover and join study communities'],
        ['href'=>'tasks.php',    'icon'=>'bi-check2-square',       'title'=>'Manage Tasks',  'desc'=>'Track assignments and deadlines'],
        ['href'=>'calendar.php', 'icon'=>'bi-calendar-event-fill', 'title'=>'Calendar',      'desc'=>'View upcoming deadlines at a glance'],
    ];
    foreach ($quickActions as $qa):
    ?>
    <a href="<?= $qa['href'] ?>" style="
        background:var(--card); border:1px solid var(--border); border-radius:14px;
        padding:16px 18px; display:flex; align-items:center; gap:12px;
        text-decoration:none; color:inherit; transition:border-color .15s, background .15s;
    " onmouseover="this.style.borderColor='rgba(37,99,235,.3)';this.style.background='#f8fbff'"
       onmouseout="this.style.borderColor='var(--border)';this.style.background='var(--card)'">
        <div style="width:38px;height:38px;border-radius:6px;background:#eff6ff;color:#1d4ed8;
                    display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;">
            <i class="bi <?= $qa['icon'] ?>"></i>
        </div>
        <div style="min-width:0;">
            <div style="font-size:13.5px;font-weight:600;"><?= $qa['title'] ?></div>
            <div style="font-size:11.5px;color:var(--text-muted);margin-top:2px;"><?= $qa['desc'] ?></div>
        </div>
        <i class="bi bi-arrow-right" style="margin-left:auto;color:var(--border);font-size:15px;flex-shrink:0;"></i>
    </a>
    <?php endforeach; ?>

</div>

<!-- ── ADMIN STRIP ────────────────────────────────────────── -->
<?php if ($stats): ?>
<div style="background:var(--sidebar);border:1px solid var(--sidebar-border);border-radius:14px;
            padding:20px 24px;margin-bottom:22px;">
    <div class="dash-admin">
        <div>
            <div style="font-family:'Inter',system-ui,sans-serif;font-size:15px;font-weight:700;color:#fff;
                        display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
                System Overview
                <span style="font-family:'Inter',system-ui,sans-serif;font-size:10px;padding:2px 8px;border-radius:20px;
                             background:rgba(74,222,128,.12);color:#86efac;border:1px solid rgba(134,239,172,.28);
                             font-weight:500;letter-spacing:.3px;">Administrator</span>
            </div>
            <div class="dash-admin-stats">
                <?php foreach([
                    ['Users',  $stats['total_users']],
                    ['Groups', $stats['total_groups']],
                    ['Tasks',  $stats['total_tasks']],
                    ['Files',  $stats['total_files']],
                ] as [$lbl,$val]): ?>
                <div>
                    <div style="font-size:11px;color:rgba(255,255,255,.35);margin-bottom:4px;"><?= $lbl ?></div>
                    <div style="font-family:'Inter',system-ui,sans-serif;font-size:24px;font-weight:800;color:#fff;letter-spacing:-0.5px;"><?= $val ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <a href="admin.php" class="btn-primary btn-copper">
            <i class="bi bi-shield-lock-fill"></i> Admin Panel
        </a>
    </div>
</div>
<?php endif; ?>

<!-- ── TASKS + GROUPS ─────────────────────────────────────── -->
<div class="dash-bottom">

    <!-- Recent Tasks -->
    <div class="card">
        <div class="panel-header">
            <div>
                <div class="panel-title">Recent Tasks</div>
                <div class="panel-sub">Your upcoming assignments and deadlines</div>
            </div>
            <a href="tasks.php" class="panel-link">View All →</a>
        </div>

        <?php if (empty($myTasks)): ?>
            <div class="empty-state">
                <i class="bi bi-check2-square" style="font-size:28px;display:block;margin-bottom:8px;color:var(--text-muted);"></i>
                <p>No tasks yet. Tasks assigned to you will appear here.</p>
            </div>
        <?php else: ?>
            <?php foreach (array_slice($myTasks, 0, 5) as $task):
                $isOverdue = $task['status'] === 'pending' && strtotime($task['due_date']) < strtotime('today');
                $pipColor  = $task['status'] === 'done' ? '#1a7a40' : ($isOverdue ? '#c0392b' : '#b7610a');
                $pillClass = $task['status'] === 'done' ? 'pill-done' : ($isOverdue ? 'pill-overdue' : 'pill-pending');
                $label     = $task['status'] === 'done' ? 'Completed' : ($isOverdue ? 'Overdue' : 'Pending');
            ?>
            <div style="display:flex;align-items:center;gap:13px;padding:12px 20px;
                        border-bottom:1px solid var(--border-soft);transition:background .1s;cursor:pointer;"
                 onmouseover="this.style.background='var(--cream)'" onmouseout="this.style.background=''">
                <div style="width:9px;height:9px;border-radius:50%;background:<?= $pipColor ?>;flex-shrink:0;"></div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:13.5px;font-weight:500;<?= $task['status']==='done' ? 'text-decoration:line-through;color:var(--text-muted);' : '' ?>">
                        <?= htmlspecialchars($task['title']) ?>
                    </div>
                    <div style="font-size:11.5px;color:var(--text-muted);margin-top:2px;">
                        Due <?= date('d M Y', strtotime($task['due_date'])) ?>
                    </div>
                </div>
                <span class="pill <?= $pillClass ?>"><?= $label ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- My Groups -->
    <div class="card">
        <div class="panel-header">
            <div>
                <div class="panel-title">My Groups</div>
                <div class="panel-sub">Active study communities</div>
            </div>
            <a href="groups.php" class="panel-link">View All →</a>
        </div>

        <?php if (empty($myGroups)): ?>
            <div class="empty-state">
                <i class="bi bi-people" style="font-size:28px;display:block;margin-bottom:8px;color:var(--text-muted);"></i>
                <p>No groups joined yet.</p>
                <a href="groups.php" style="display:inline-block;margin-top:12px;" class="btn-primary">Explore Groups</a>
            </div>
        <?php else: ?>
            <div style="padding:10px 0 6px;">
                <?php foreach ($myGroups as $g): ?>
                <a href="group.php?id=<?= $g['group_id'] ?>" style="
                    display:flex;align-items:center;gap:11px;padding:9px 14px;
                    margin:0 10px 7px;border-radius:10px;border:1px solid var(--border);
                    text-decoration:none;color:inherit;transition:border-color .15s,background .1s;
                " onmouseover="this.style.borderColor='rgba(37,99,235,.3)';this.style.background='#f8fbff'"
                   onmouseout="this.style.borderColor='var(--border)';this.style.background=''">
                    <div style="width:36px;height:36px;border-radius:6px;background:var(--sidebar);
                                color:rgba(255,255,255,.7);display:flex;align-items:center;justify-content:center;
                                font-weight:700;font-size:13px;flex-shrink:0;">
                        <?= strtoupper(substr($g['group_name'], 0, 2)) ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?= htmlspecialchars($g['group_name']) ?>
                        </div>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:1px;">
                            <?= $g['member_count'] ?> members
                        </div>
                    </div>
                    <i class="bi bi-chevron-right" style="color:var(--border);font-size:12px;flex-shrink:0;"></i>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php
$page_content = ob_get_clean();
require STUDYSYNC_ROOT . '/views/layouts/app.php';
?>
