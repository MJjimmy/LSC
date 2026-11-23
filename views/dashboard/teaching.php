<?php // Variables are provided by TeachingController::index(). ?>
<style>
.teach-hero{align-items:end;background:var(--sidebar);border:1px solid #123d66;color:#fff;display:flex;gap:30px;justify-content:space-between;margin-bottom:20px;min-height:220px;padding:34px 36px}.teach-kicker{color:#79d5a9;font-size:10px;font-weight:700;letter-spacing:.14em;margin-bottom:11px;text-transform:uppercase}.teach-hero h1{font:800 clamp(30px,4vw,46px)/1.08 'Manrope',sans-serif;letter-spacing:-.05em;margin:0}.teach-hero p{color:#b9cad8;font-size:14px;line-height:1.6;margin-top:12px;max-width:650px}.teach-hero .btn-primary{background:#fff;color:var(--sidebar);white-space:nowrap}.teach-stats{display:grid;gap:14px;grid-template-columns:repeat(4,1fr);margin-bottom:20px}.teach-stat{background:var(--card);border:1px solid var(--border);padding:20px}.teach-stat span{color:var(--text-muted);font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}.teach-stat strong{display:block;font:800 29px/1 'Manrope',sans-serif;letter-spacing:-.04em;margin-top:15px}.teach-alert{align-items:center;background:var(--amber-bg);border:1px solid var(--amber-border);display:flex;gap:20px;justify-content:space-between;margin-bottom:20px;padding:16px 18px}.teach-alert strong{font-size:13px}.teach-alert p{color:var(--text-secondary);font-size:11px;margin-top:3px}.teach-grid{display:grid;gap:18px;grid-template-columns:minmax(0,1.25fr) minmax(310px,.75fr)}.teach-group{border-bottom:1px solid var(--border-soft);display:grid;gap:14px;grid-template-columns:minmax(0,1fr) auto;padding:17px 20px}.teach-group:last-child{border-bottom:0}.teach-group-title{align-items:center;display:flex;gap:12px}.teach-group-mark{align-items:center;background:var(--sidebar);color:#fff;display:flex;flex:0 0 auto;font-size:11px;font-weight:800;height:38px;justify-content:center;width:38px}.teach-group strong{display:block;font-size:13px}.teach-group small{color:var(--text-muted);font-size:10.5px}.teach-group-actions{align-items:center;display:flex;gap:8px}.teach-group-actions a{border:1px solid var(--border);color:var(--text-secondary);font-size:10.5px;font-weight:700;padding:7px 9px;text-decoration:none}.teach-group-actions a:hover{border-color:var(--primary-border);color:var(--primary)}.teach-task{align-items:center;border-bottom:1px solid var(--border-soft);display:flex;gap:11px;padding:13px 16px}.teach-task:last-child{border-bottom:0}.teach-task-icon{align-items:center;background:var(--primary-soft);color:var(--primary);display:flex;flex:0 0 auto;height:32px;justify-content:center;width:32px}.teach-task-copy{flex:1;min-width:0}.teach-task-copy strong{display:block;font-size:11.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.teach-task-copy small{color:var(--text-muted);font-size:9.5px}.teach-actions{display:grid;gap:10px;margin-top:18px}.teach-actions a{align-items:center;background:var(--card);border:1px solid var(--border);color:var(--text-primary);display:flex;font-size:12px;font-weight:700;gap:10px;padding:12px 14px;text-decoration:none}.teach-actions a i:last-child{color:var(--text-muted);margin-left:auto}
@media(max-width:980px){.teach-stats{grid-template-columns:repeat(2,1fr)}.teach-grid{grid-template-columns:1fr}}@media(max-width:620px){.teach-hero{align-items:flex-start;flex-direction:column;padding:28px 23px}.teach-alert{align-items:flex-start;flex-direction:column}.teach-group{grid-template-columns:1fr}.teach-group-actions{flex-wrap:wrap}.teach-stat{padding:16px}}
</style>

<section class="teach-hero">
    <div><div class="teach-kicker">Lecturer workspace</div><h1>Coordinate teaching groups with confidence.</h1><p>Review participation, group access and upcoming work across every academic space you manage.</p></div>
    <a class="btn-primary" href="groups.php"><i class="bi bi-plus-lg" aria-hidden="true"></i> Create a group</a>
</section>

<section class="teach-stats" aria-label="Teaching summary">
    <?php foreach ([
        ['Managed groups', count($managedGroups)],
        ['Group members', $memberCount],
        ['Open tasks', count($openTasks)],
        ['Overdue tasks', count($overdueTasks)],
    ] as [$label, $value]): ?>
    <article class="teach-stat"><span><?= $label ?></span><strong><?= (int)$value ?></strong></article>
    <?php endforeach; ?>
</section>

<?php if ($pendingRequestCount): ?>
<section class="teach-alert">
    <div><strong><?= $pendingRequestCount ?> learner request<?= $pendingRequestCount === 1 ? '' : 's' ?> awaiting review</strong><p>Open the appropriate group to approve or decline access.</p></div>
    <a class="btn-primary" href="groups.php">Review groups <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
</section>
<?php endif; ?>

<div class="teach-grid">
    <section class="card">
        <div class="panel-header"><div><div class="panel-title">Groups I manage</div><div class="panel-sub">Membership, work and resources by group</div></div><a class="panel-link" href="groups.php">All groups</a></div>
        <?php if (!$managedGroups): ?>
            <div class="empty-state"><i class="bi bi-collection" style="font-size:28px;" aria-hidden="true"></i><p>Create your first group to begin coordinating learners.</p></div>
        <?php else: ?>
            <?php foreach ($managedGroups as $group): ?>
            <article class="teach-group">
                <div class="teach-group-title">
                    <span class="teach-group-mark"><?= htmlspecialchars(strtoupper(substr($group['group_name'], 0, 2))) ?></span>
                    <span><strong><?= htmlspecialchars($group['group_name']) ?></strong><small><?= (int)$group['member_count'] ?> members · <?= $group['is_private'] ? 'Private' : 'Public' ?><?php if ($group['pending_request_count']): ?> · <?= (int)$group['pending_request_count'] ?> requests<?php endif; ?></small></span>
                </div>
                <div class="teach-group-actions">
                    <a href="group.php?id=<?= (int)$group['group_id'] ?>">Overview</a>
                    <a href="group.php?id=<?= (int)$group['group_id'] ?>&tab=members">Members</a>
                    <a href="group.php?id=<?= (int)$group['group_id'] ?>&tab=chat">Chat</a>
                </div>
            </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <aside>
        <section class="card">
            <div class="panel-header"><div><div class="panel-title">Upcoming group work</div><div class="panel-sub">Nearest managed-group deadlines</div></div><a class="panel-link" href="tasks.php">All tasks</a></div>
            <?php if (!$openTasks): ?>
                <div class="empty-state"><i class="bi bi-check2-circle" style="font-size:26px;" aria-hidden="true"></i><p>No open managed-group tasks.</p></div>
            <?php else: ?>
                <?php foreach (array_slice($openTasks, 0, 7) as $task): ?>
                <div class="teach-task">
                    <span class="teach-task-icon"><i class="bi bi-check2-square" aria-hidden="true"></i></span>
                    <span class="teach-task-copy"><strong><?= htmlspecialchars($task['title']) ?></strong><small><?= htmlspecialchars($task['group_name']) ?> · <?= htmlspecialchars($task['assigned_to_name'] ?? 'Unassigned') ?> · <?= date('d M', strtotime($task['due_date'])) ?></small></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
        <nav class="teach-actions" aria-label="Teaching shortcuts">
            <a href="files.php"><i class="bi bi-folder2-open" aria-hidden="true"></i> Shared resources <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            <a href="calendar.php"><i class="bi bi-calendar3" aria-hidden="true"></i> Group calendar <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            <a href="profile.php?tab=notifications"><i class="bi bi-bell" aria-hidden="true"></i> Notification settings <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </nav>
    </aside>
</div>

<?php
$pageTitle = 'Teaching Workspace | LSC';
$page_content = ob_get_clean();
require LSC_ROOT . '/views/layouts/app.php';
?>
