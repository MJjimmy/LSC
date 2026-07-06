<?php
// Variables are provided by DashboardController::index().
$fullName = trim((string)($_SESSION['user_name'] ?? 'Student'));
$firstName = explode(' ', $fullName)[0] ?: 'Student';
$focusTask = null;

foreach ($pendingTasks as $task) {
    if ($focusTask === null || strtotime($task['due_date']) < strtotime($focusTask['due_date'])) {
        $focusTask = $task;
    }
}
?>

<section class="field-hero" aria-labelledby="dashboard-greeting">
    <div class="field-hero-main">
        <div>
            <div class="field-kicker">Your study field guide</div>
            <h1 id="dashboard-greeting">Hello, <?= htmlspecialchars($firstName) ?>.</h1>
            <p class="field-hero-copy">
                Start with the nearest deadline, then check in with your people. Everything else can wait.
            </p>
        </div>
        <div class="field-hero-actions">
            <a href="tasks.php" class="btn-primary">Open task list <i class="bi bi-arrow-right"></i></a>
            <a href="groups.php" class="field-text-link">Find a study group</a>
        </div>
    </div>

    <aside class="field-focus" aria-label="Today's focus">
        <div class="field-focus-date">
            <span class="field-focus-day"><?= date('d') ?></span>
            <span class="field-focus-month"><?= date('F · l') ?></span>
        </div>
        <div>
            <div class="field-focus-label">Next on your desk</div>
            <?php if ($focusTask): ?>
                <div class="field-focus-title"><?= htmlspecialchars($focusTask['title']) ?></div>
                <div class="field-focus-meta">Due <?= date('d M Y', strtotime($focusTask['due_date'])) ?></div>
            <?php else: ?>
                <div class="field-focus-title">Your desk is clear.</div>
                <div class="field-focus-meta">Add a task when the next brief arrives.</div>
            <?php endif; ?>
        </div>
    </aside>
</section>

<section class="field-metrics" aria-label="Study overview">
    <div class="field-metric"><strong><?= count($pendingTasks) ?></strong><span>Pending tasks</span></div>
    <div class="field-metric"><strong><?= count($myGroups) ?></strong><span>Study groups</span></div>
    <div class="field-metric"><strong><?= count($myTasks) ?></strong><span>Total tasks</span></div>
    <div class="field-metric <?= count($overdueTasks) ? 'is-alert' : '' ?>"><strong><?= count($overdueTasks) ?></strong><span>Need attention</span></div>
</section>

<section class="field-routes" aria-labelledby="field-routes-title">
    <div class="field-section-head">
        <div>
            <div class="field-section-kicker">Choose your route</div>
            <h2 id="field-routes-title">What needs moving?</h2>
        </div>
    </div>
    <div class="field-route-grid">
        <a href="tasks.php" class="field-route">
            <span class="field-route-index">01 / Plan</span>
            <span class="field-route-title">Work the list <i class="bi bi-arrow-up-right"></i></span>
            <span class="field-route-copy">Sort assignments by urgency and close the next task.</span>
        </a>
        <a href="groups.php" class="field-route">
            <span class="field-route-index">02 / Gather</span>
            <span class="field-route-title">Meet your group <i class="bi bi-arrow-up-right"></i></span>
            <span class="field-route-copy">Continue the conversation and share responsibility.</span>
        </a>
        <a href="calendar.php" class="field-route">
            <span class="field-route-index">03 / Look ahead</span>
            <span class="field-route-title">Read the week <i class="bi bi-arrow-up-right"></i></span>
            <span class="field-route-copy">See deadlines together before they compete for your time.</span>
        </a>
    </div>
</section>

<div class="field-columns">
    <section class="field-panel" aria-labelledby="recent-tasks-title">
        <div class="field-panel-head">
            <div>
                <h2 id="recent-tasks-title">Nearest deadlines</h2>
                <p>Your first five assignments, ordered by the task list.</p>
            </div>
            <a href="tasks.php" class="field-panel-link">All tasks →</a>
        </div>

        <?php if (empty($myTasks)): ?>
            <div class="field-empty">No tasks yet. Your assigned work will appear here.</div>
        <?php else: ?>
            <?php foreach (array_slice($myTasks, 0, 5) as $task):
                $isOverdue = $task['status'] === 'pending' && strtotime($task['due_date']) < strtotime('today');
                $pillClass = $task['status'] === 'done' ? 'pill-done' : ($isOverdue ? 'pill-overdue' : 'pill-pending');
                $label = $task['status'] === 'done' ? 'Completed' : ($isOverdue ? 'Overdue' : 'Pending');
            ?>
                <div class="field-task">
                    <div class="field-task-date">
                        <strong><?= date('d', strtotime($task['due_date'])) ?></strong><?= date('M', strtotime($task['due_date'])) ?>
                    </div>
                    <div>
                        <div class="field-task-title"><?= htmlspecialchars($task['title']) ?></div>
                        <div class="field-task-meta"><?= $isOverdue ? 'Needs attention now' : 'Keep this on the desk' ?></div>
                    </div>
                    <span class="pill <?= $pillClass ?>"><?= $label ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="field-panel" aria-labelledby="my-groups-title">
        <div class="field-panel-head">
            <div>
                <h2 id="my-groups-title">Your people</h2>
                <p>Groups you can return to now.</p>
            </div>
            <a href="groups.php" class="field-panel-link">All groups →</a>
        </div>

        <?php if (empty($myGroups)): ?>
            <div class="field-empty">No groups yet. Browse groups to find your people.</div>
        <?php else: ?>
            <?php foreach (array_slice($myGroups, 0, 5) as $group): ?>
                <a href="group.php?id=<?= (int)$group['group_id'] ?>" class="field-group">
                    <span class="field-group-mark"><?= htmlspecialchars(strtoupper(substr($group['group_name'], 0, 2))) ?></span>
                    <span>
                        <span class="field-group-title"><?= htmlspecialchars($group['group_name']) ?></span>
                        <span class="field-group-meta"><?= (int)$group['member_count'] ?> members</span>
                    </span>
                    <i class="bi bi-arrow-right"></i>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

<?php if ($stats): ?>
    <section class="field-admin" aria-labelledby="admin-overview-title">
        <div>
            <div class="field-section-kicker">Administrator view</div>
            <h2 id="admin-overview-title">Platform field report</h2>
            <div class="field-admin-stats">
                <?php foreach ([
                    ['Users', $stats['total_users']],
                    ['Groups', $stats['total_groups']],
                    ['Tasks', $stats['total_tasks']],
                    ['Files', $stats['total_files']],
                ] as [$label, $value]): ?>
                    <span><strong><?= (int)$value ?></strong><?= $label ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <a href="admin.php" class="btn-primary"><i class="bi bi-shield-lock"></i> Open admin</a>
    </section>
<?php endif; ?>

<?php
$page_content = ob_get_clean();
require STUDYSYNC_ROOT . '/views/layouts/app.php';
?>
