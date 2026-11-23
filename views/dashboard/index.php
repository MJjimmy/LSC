<?php
// Variables are provided by DashboardController::index().
$roleLabels = ['student' => 'Student workspace', 'lecturer' => 'Teaching workspace', 'admin' => 'Platform administration'];
$roleLabel = $roleLabels[$role] ?? 'Academic workspace';

$roleConfig = match ($role) {
    'lecturer' => [
        'eyebrow' => 'Teaching overview',
        'headline' => 'Guide your groups with a clearer view.',
        'summary' => 'Review participation, upcoming work and access requests across the groups you manage.',
        'actionHref' => 'teaching.php',
        'actionLabel' => 'Open teaching workspace',
        'actionIcon' => 'bi-people-fill',
    ],
    'admin' => [
        'eyebrow' => 'Platform overview',
        'headline' => 'Keep the academic community running well.',
        'summary' => 'Monitor people, groups, work and resources from one operational view.',
        'actionHref' => 'admin.php',
        'actionLabel' => 'Open admin portal',
        'actionIcon' => 'bi-shield-lock-fill',
    ],
    default => [
        'eyebrow' => 'Your academic day',
        'headline' => 'Know what needs your attention next.',
        'summary' => 'Stay close to upcoming work, active groups and the resources your team is sharing.',
        'actionHref' => 'tasks.php',
        'actionLabel' => 'View my tasks',
        'actionIcon' => 'bi-check2-square',
    ],
};

$dashboardTasks = $role === 'lecturer' ? $visibleTasks : $myTasks;
$dashboardGroups = $role === 'lecturer' ? $managedGroups : $myGroups;

$quickActions = match ($role) {
    'lecturer' => [
        ['groups.php', 'bi-plus-square', 'Create or manage a group', 'Set up a course space, review members and invite learners.'],
        ['tasks.php', 'bi-list-check', 'Coordinate group tasks', 'Review upcoming work across every group you belong to.'],
        ['files.php', 'bi-folder2-open', 'Share teaching resources', 'Keep notes, briefs and reference material easy to find.'],
    ],
    'admin' => [
        ['admin.php', 'bi-people', 'Manage platform users', 'Review account roles, access and active status.'],
        ['groups.php', 'bi-collection', 'Review all groups', 'Inspect public and private collaboration spaces.'],
        ['notifications.php', 'bi-bell', 'Check platform updates', 'Keep up with invitations and operational activity.'],
    ],
    default => [
        ['tasks.php', 'bi-check2-square', 'Continue with my tasks', 'See assigned work, due dates and completion status.'],
        ['groups.php', 'bi-people', 'Open my study groups', 'Return to group discussions, tasks and shared resources.'],
        ['calendar.php', 'bi-calendar3', 'Plan the week ahead', 'View every group deadline in one monthly schedule.'],
    ],
};
?>

<style>
.role-hero{align-items:end;background:var(--sidebar);border:1px solid #123d66;color:#fff;display:flex;gap:34px;justify-content:space-between;margin-bottom:22px;min-height:230px;overflow:hidden;padding:36px 38px;position:relative}
.role-hero::after{border:38px solid rgba(255,255,255,.045);border-radius:50%;content:'';height:190px;position:absolute;right:-35px;top:-65px;width:190px}
.role-hero-copy{max-width:720px;position:relative;z-index:1}.role-eyebrow{color:#79d5a9;font-size:10px;font-weight:700;letter-spacing:.14em;margin-bottom:13px;text-transform:uppercase}.role-hero h1{font:800 clamp(30px,4vw,48px)/1.08 'Manrope',sans-serif;letter-spacing:-.05em;margin:0}.role-hero p{color:#b9cad8;font-size:14px;line-height:1.65;margin:15px 0 0;max-width:640px}.role-hero-meta{align-items:flex-end;display:flex;flex-direction:column;gap:13px;position:relative;z-index:1}.role-date{color:#91a8bc;font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase}.role-hero .btn-primary{background:#fff;color:var(--sidebar);white-space:nowrap}.role-hero .btn-primary:hover{background:var(--primary-soft)}
.role-stats{display:grid;gap:14px;grid-template-columns:repeat(4,1fr);margin-bottom:22px}.role-stat{background:var(--card);border:1px solid var(--border);min-height:126px;padding:21px 22px}.role-stat-top{align-items:center;color:var(--text-muted);display:flex;font-size:11px;font-weight:700;justify-content:space-between;letter-spacing:.04em;text-transform:uppercase}.role-stat-top i{align-items:center;background:var(--primary-soft);color:var(--primary);display:flex;font-size:15px;height:31px;justify-content:center;width:31px}.role-stat strong{display:block;font:800 32px/1 'Manrope',sans-serif;letter-spacing:-.05em;margin-top:18px}.role-stat small{color:var(--text-muted);display:block;font-size:11px;margin-top:7px}
.role-actions{display:grid;gap:14px;grid-template-columns:repeat(3,1fr);margin-bottom:22px}.role-action{align-items:flex-start;background:var(--card);border:1px solid var(--border);color:inherit;display:flex;gap:14px;min-height:112px;padding:19px;text-decoration:none;transition:border-color .15s,box-shadow .15s,transform .15s}.role-action:hover{border-color:var(--primary-border);box-shadow:0 12px 28px rgba(12,39,65,.07);transform:translateY(-2px)}.role-action-icon{align-items:center;background:var(--primary-soft);color:var(--primary);display:flex;flex:0 0 auto;font-size:18px;height:39px;justify-content:center;width:39px}.role-action strong{display:block;font-size:13px;margin-bottom:4px}.role-action span{color:var(--text-muted);display:block;font-size:11.5px;line-height:1.5}.role-action>.bi{color:var(--border);font-size:13px;margin-left:auto;margin-top:4px}
.role-main-grid{display:grid;gap:18px;grid-template-columns:minmax(0,1.5fr) minmax(290px,.75fr)}.role-list-item{align-items:center;border-bottom:1px solid var(--border-soft);display:flex;gap:13px;padding:14px 20px}.role-list-item:last-child{border-bottom:0}.role-task-mark{background:var(--amber);height:9px;flex:0 0 auto;width:9px}.role-task-mark.done{background:var(--green)}.role-task-mark.overdue{background:var(--red)}.role-list-copy{flex:1;min-width:0}.role-list-copy strong{display:block;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.role-list-copy small{color:var(--text-muted);display:block;font-size:11px;margin-top:3px}.role-group-list{padding:9px 10px}.role-group{align-items:center;border:1px solid transparent;color:inherit;display:flex;gap:11px;padding:10px;text-decoration:none}.role-group:hover{background:var(--cream);border-color:var(--border)}.role-group-mark{align-items:center;background:var(--sidebar);color:#fff;display:flex;flex:0 0 auto;font-size:11px;font-weight:800;height:36px;justify-content:center;width:36px}.role-group-copy{flex:1;min-width:0}.role-group-copy strong{display:block;font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.role-group-copy small{color:var(--text-muted);font-size:10.5px}.role-group>.bi{color:var(--text-muted);font-size:11px}
.admin-callout{align-items:center;background:var(--sidebar);border:1px solid #123d66;color:#fff;display:flex;gap:24px;justify-content:space-between;margin-bottom:22px;padding:22px 24px}.admin-callout strong{display:block;font:800 16px 'Manrope',sans-serif}.admin-callout p{color:#aabed0;font-size:12px;margin-top:4px}.admin-callout .btn-primary{background:#fff;color:var(--sidebar);white-space:nowrap}
@media(max-width:1000px){.role-stats{grid-template-columns:repeat(2,1fr)}.role-main-grid{grid-template-columns:1fr}.role-actions{grid-template-columns:1fr 1fr}.role-actions .role-action:last-child{grid-column:1/-1}}
@media(max-width:680px){.role-hero{align-items:flex-start;flex-direction:column;min-height:auto;padding:28px 24px}.role-hero-meta{align-items:flex-start}.role-stats{gap:9px}.role-stat{min-height:112px;padding:17px}.role-stat strong{font-size:27px}.role-actions{grid-template-columns:1fr}.role-actions .role-action:last-child{grid-column:auto}.admin-callout{align-items:flex-start;flex-direction:column}.role-list-item{align-items:flex-start}.role-list-item .pill{margin-left:auto}}
</style>

<section class="role-hero">
    <div class="role-hero-copy">
        <div class="role-eyebrow"><?= htmlspecialchars($roleConfig['eyebrow']) ?> · <?= htmlspecialchars($roleLabel) ?></div>
        <h1><?= htmlspecialchars($roleConfig['headline']) ?></h1>
        <p><?= htmlspecialchars($roleConfig['summary']) ?> Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>.</p>
    </div>
    <div class="role-hero-meta">
        <span class="role-date"><?= date('l, d F Y') ?></span>
        <a href="<?= $roleConfig['actionHref'] ?>" class="btn-primary">
            <i class="bi <?= $roleConfig['actionIcon'] ?>" aria-hidden="true"></i>
            <?= htmlspecialchars($roleConfig['actionLabel']) ?>
        </a>
    </div>
</section>

<section class="role-stats" aria-label="<?= htmlspecialchars($roleLabel) ?> summary">
<?php if ($role === 'admin'): ?>
    <?php foreach ([
        ['Active users', $stats['total_users'], 'bi-people', 'Verified platform access'],
        ['Study groups', $stats['total_groups'], 'bi-collection', 'Public and private spaces'],
        ['Pending tasks', $stats['pending_tasks'], 'bi-list-task', $stats['overdue_tasks'] . ' currently overdue'],
        ['Shared files', $stats['total_files'], 'bi-folder2-open', 'Protected group resources'],
    ] as [$label, $value, $icon, $note]): ?>
    <article class="role-stat"><div class="role-stat-top"><span><?= $label ?></span><i class="bi <?= $icon ?>" aria-hidden="true"></i></div><strong><?= (int)$value ?></strong><small><?= htmlspecialchars($note) ?></small></article>
    <?php endforeach; ?>
<?php elseif ($role === 'lecturer'): ?>
    <?php foreach ([
        ['Managed groups', count($managedGroups), 'bi-collection', 'Groups created by you'],
        ['Group members', $managedMemberCount, 'bi-people', 'Across managed groups'],
        ['Open group tasks', count($visiblePendingTasks), 'bi-list-task', 'Visible across your memberships'],
        ['Join requests', $pendingJoinRequestCount, 'bi-person-plus', 'Awaiting your review'],
    ] as [$label, $value, $icon, $note]): ?>
    <article class="role-stat"><div class="role-stat-top"><span><?= $label ?></span><i class="bi <?= $icon ?>" aria-hidden="true"></i></div><strong><?= (int)$value ?></strong><small><?= htmlspecialchars($note) ?></small></article>
    <?php endforeach; ?>
<?php else: ?>
    <?php foreach ([
        ['My groups', count($myGroups), 'bi-people', 'Active study communities'],
        ['Assigned tasks', count($myTasks), 'bi-list-task', 'All assigned work'],
        ['Pending', count($pendingTasks), 'bi-clock', 'Still to complete'],
        ['Overdue', count($overdueTasks), 'bi-exclamation-triangle', 'Needs attention now'],
    ] as [$label, $value, $icon, $note]): ?>
    <article class="role-stat"><div class="role-stat-top"><span><?= $label ?></span><i class="bi <?= $icon ?>" aria-hidden="true"></i></div><strong><?= (int)$value ?></strong><small><?= htmlspecialchars($note) ?></small></article>
    <?php endforeach; ?>
<?php endif; ?>
</section>

<section class="role-actions" aria-label="Quick actions">
    <?php foreach ($quickActions as [$href, $icon, $title, $description]): ?>
    <a href="<?= $href ?>" class="role-action">
        <span class="role-action-icon"><i class="bi <?= $icon ?>" aria-hidden="true"></i></span>
        <span><strong><?= htmlspecialchars($title) ?></strong><span><?= htmlspecialchars($description) ?></span></span>
        <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
    </a>
    <?php endforeach; ?>
</section>

<?php if ($role === 'admin'): ?>
<section class="admin-callout">
    <div><strong>Administration centre</strong><p>Manage account access and inspect platform-wide activity from the dedicated admin portal.</p></div>
    <a href="admin.php" class="btn-primary"><i class="bi bi-shield-check" aria-hidden="true"></i> Manage platform</a>
</section>
<?php elseif ($role === 'lecturer' && $pendingJoinRequestCount > 0): ?>
<section class="admin-callout">
    <div><strong><?= $pendingJoinRequestCount ?> membership request<?= $pendingJoinRequestCount === 1 ? '' : 's' ?> need review</strong><p>Open a managed group and review pending learners from its Members tab.</p></div>
    <a href="groups.php" class="btn-primary"><i class="bi bi-person-check" aria-hidden="true"></i> Review groups</a>
</section>
<?php endif; ?>

<section class="role-main-grid">
    <div class="card">
        <div class="panel-header">
            <div>
                <div class="panel-title"><?= $role === 'lecturer' ? 'Upcoming group work' : 'Work requiring attention' ?></div>
                <div class="panel-sub"><?= $role === 'lecturer' ? 'Tasks visible across your group memberships' : 'Your nearest assigned deadlines' ?></div>
            </div>
            <a href="tasks.php" class="panel-link">View all <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </div>
        <?php if (!$dashboardTasks): ?>
            <div class="empty-state"><i class="bi bi-check2-circle" style="font-size:28px;" aria-hidden="true"></i><p>No tasks need attention right now.</p></div>
        <?php else: ?>
            <?php foreach (array_slice($dashboardTasks, 0, 6) as $task):
                $isDone = $task['status'] === 'done';
                $isOverdue = !$isDone && strtotime($task['due_date']) < strtotime('today');
                $marker = $isDone ? 'done' : ($isOverdue ? 'overdue' : '');
                $pill = $isDone ? 'pill-done' : ($isOverdue ? 'pill-overdue' : 'pill-pending');
                $label = $isDone ? 'Complete' : ($isOverdue ? 'Overdue' : 'Pending');
            ?>
            <div class="role-list-item">
                <span class="role-task-mark <?= $marker ?>"></span>
                <div class="role-list-copy">
                    <strong><?= htmlspecialchars($task['title']) ?></strong>
                    <small><?= htmlspecialchars($task['group_name']) ?> · Due <?= date('d M Y', strtotime($task['due_date'])) ?><?php if ($role === 'lecturer' && !empty($task['assigned_to_name'])): ?> · <?= htmlspecialchars($task['assigned_to_name']) ?><?php endif; ?></small>
                </div>
                <span class="pill <?= $pill ?>"><?= $label ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="panel-header">
            <div>
                <div class="panel-title"><?= $role === 'lecturer' ? 'Groups I manage' : 'My study groups' ?></div>
                <div class="panel-sub"><?= $role === 'lecturer' ? 'Spaces created by you' : 'Your active communities' ?></div>
            </div>
            <a href="groups.php" class="panel-link">View all</a>
        </div>
        <?php if (!$dashboardGroups): ?>
            <div class="empty-state"><i class="bi bi-people" style="font-size:27px;" aria-hidden="true"></i><p><?= $role === 'lecturer' ? 'Create a group to begin coordinating learners.' : 'Join a group to begin collaborating.' ?></p></div>
        <?php else: ?>
            <div class="role-group-list">
                <?php foreach (array_slice($dashboardGroups, 0, 6) as $group): ?>
                <a class="role-group" href="group.php?id=<?= (int)$group['group_id'] ?>">
                    <span class="role-group-mark"><?= htmlspecialchars(strtoupper(mb_substr($group['group_name'], 0, 2))) ?></span>
                    <span class="role-group-copy"><strong><?= htmlspecialchars($group['group_name']) ?></strong><small><?= (int)$group['member_count'] ?> members · <?= $group['is_private'] ? 'Private' : 'Public' ?></small></span>
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
$pageTitle = 'Dashboard | LSC';
$page_content = ob_get_clean();
require LSC_ROOT . '/views/layouts/app.php';
?>
