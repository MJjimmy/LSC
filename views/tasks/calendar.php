<?php // Variables are provided by CalendarController::index(). ?>

<style>
/* ── CALENDAR RESPONSIVE ─────────────────────────────────── */
.cal-hero-month {
    position: absolute;
    right: 30px; top: 50%;
    transform: translateY(-50%);
    z-index: 1;
    background: var(--copper-dim);
    border: 1px solid var(--copper-border);
    border-radius: 10px;
    padding: 10px 18px;
    text-align: center;
}

.cal-nav-bar {
    padding: 12px 18px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.cal-nav-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.cal-month-label {
    padding: 7px 16px;
    background: var(--cream);
    border-radius: 6px;
    font-size: 13.5px;
    font-weight: 600;
    min-width: 140px;
    text-align: center;
}

/* Calendar grid */
.cal-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: var(--cream);
    border-bottom: 1px solid var(--border);
}

.cal-weekday {
    padding: 12px 0;
    text-align: center;
    font-size: 10.5px;
    font-weight: 600;
    color: var(--text-muted);
    letter-spacing: .5px;
}

.cal-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background: var(--border-soft);
}

.cal-cell {
    background: var(--card);
    min-height: 100px;
    padding: 8px;
    transition: background .1s;
    cursor: pointer;
}

.cal-cell:hover { background: var(--cream); }
.cal-cell.empty { cursor: default; }
.cal-cell.empty:hover { background: var(--card); }

.cal-day-num {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.cal-day-circle {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: var(--text-primary);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
}

.cal-task-count {
    background: var(--copper-dim);
    color: var(--copper-dark);
    border: 1px solid var(--copper-border);
    font-size: 10px;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: 20px;
}

.cal-task-item {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    color: var(--text-secondary);
    margin-bottom: 2px;
    overflow: hidden;
}

.cal-task-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

.cal-task-label {
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

/* Mobile task list (shown instead of grid on small screens) */
.cal-mobile-list { display: none; }

/* ── RESPONSIVE ─────────────────────────────────────────── */
@media (max-width: 700px) {
    .cal-hero-month {
        position: static;
        transform: none;
        display: inline-block;
        margin-top: 14px;
    }

    .cal-weekday { font-size: 9px; padding: 8px 0; letter-spacing: 0; }

    .cal-cell { min-height: 44px; padding: 5px 4px; }

    .cal-day-num { font-size: 11px; margin-bottom: 2px; }

    .cal-day-circle {
        width: 22px;
        height: 22px;
        font-size: 10px;
    }

    /* Hide task titles on mobile — just show dot indicators */
    .cal-task-label { display: none; }

    .cal-task-item { justify-content: center; }

    .cal-task-dot { width: 7px; height: 7px; }

    /* Hide task count badge on very small cells */
    .cal-task-count { display: none; }

    /* Show mobile task list below calendar */
    .cal-mobile-list { display: block; }
}

@media (max-width: 400px) {
    .cal-cell { min-height: 36px; padding: 4px 3px; }
    .cal-weekday { font-size: 8px; }
    .cal-day-num { font-size: 10px; }
}
</style>

<!-- ── HERO ───────────────────────────────────────────────── -->
<div class="page-hero" style="margin-bottom:24px;">
    <div class="page-hero-eyebrow">Planning & Deadlines</div>
    <div class="page-hero-title">Calendar</div>
    <div class="page-hero-sub">Keep track of assignments, due dates and group activities.</div>
    <div class="cal-hero-month">
        <div style="font-size:11px;color:rgba(255,255,255,.45);margin-bottom:3px;">Current Month</div>
        <div style="font-family:'Playfair Display',serif;font-size:18px;font-weight:800;color:#fff;">
            <?= date('F Y', $firstDay) ?>
        </div>
    </div>
</div>

<!-- ── NAV BAR ───────────────────────────────────────────── -->
<div class="card cal-nav-bar">
    <div style="font-size:14px;font-weight:600;">Monthly Schedule</div>
    <div class="cal-nav-controls">
        <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>"
           style="width:34px;height:34px;border-radius:6px;border:1px solid var(--border);
                  background:var(--card);display:flex;align-items:center;justify-content:center;
                  color:var(--text-secondary);text-decoration:none;">
            <i class="bi bi-chevron-left" style="font-size:13px;"></i>
        </a>
        <div class="cal-month-label"><?= date('F Y', $firstDay) ?></div>
        <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>"
           style="width:34px;height:34px;border-radius:6px;border:1px solid var(--border);
                  background:var(--card);display:flex;align-items:center;justify-content:center;
                  color:var(--text-secondary);text-decoration:none;">
            <i class="bi bi-chevron-right" style="font-size:13px;"></i>
        </a>
        <a href="calendar.php" class="btn-primary" style="font-size:12px;padding:7px 14px;">Today</a>
    </div>
</div>

<!-- ── CALENDAR GRID ──────────────────────────────────────── -->
<div class="card" style="margin-bottom:18px;overflow:hidden;">

    <!-- Weekday headers -->
    <div class="cal-weekdays">
        <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $wd): ?>
        <div class="cal-weekday"><?= $wd ?></div>
        <?php endforeach; ?>
    </div>

    <!-- Day cells -->
    <div class="cal-days">
        <?php foreach ($calendar as $day):
            $currentDay = $isTodayMonth && $day === $today;
            $hasTasks   = $day && isset($tasksByDay[$day]);
        ?>
        <div class="cal-cell <?= !$day ? 'empty' : '' ?>">
            <?php if ($day): ?>

                <div class="cal-day-num">
                    <?php if ($currentDay): ?>
                        <span class="cal-day-circle"><?= $day ?></span>
                    <?php else: ?>
                        <span><?= $day ?></span>
                    <?php endif; ?>
                    <?php if ($hasTasks): ?>
                        <span class="cal-task-count"><?= count($tasksByDay[$day]) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($hasTasks): ?>
                    <div>
                        <?php foreach (array_slice($tasksByDay[$day], 0, 3) as $task):
                            $isDone    = $task['status'] === 'done';
                            $isOverdue = !$isDone && strtotime($task['due_date']) < strtotime('today');
                            $dotColor  = $isDone ? 'var(--green)' : ($isOverdue ? 'var(--red)' : 'var(--amber)');
                        ?>
                        <div class="cal-task-item" title="<?= htmlspecialchars($task['title']) ?>">
                            <span class="cal-task-dot" style="background:<?= $dotColor ?>;"></span>
                            <span class="cal-task-label"><?= htmlspecialchars(mb_substr($task['title'], 0, 16)) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── MOBILE TASK LIST ───────────────────────────────────── -->
<!-- Shows on mobile instead of tiny calendar task labels -->
<div class="cal-mobile-list">
    <?php
    $hasAnyTasks = false;
    foreach ($tasksByDay as $day => $tasks) {
        if (!empty($tasks)) { $hasAnyTasks = true; break; }
    }
    ?>

    <?php if ($hasAnyTasks): ?>
    <div class="card" style="margin-bottom:18px;">
        <div class="panel-header">
            <div>
                <div class="panel-title">Tasks this month</div>
                <div class="panel-sub"><?= date('F Y', $firstDay) ?></div>
            </div>
        </div>
        <?php
        ksort($tasksByDay);
        foreach ($tasksByDay as $day => $tasks):
            foreach ($tasks as $task):
                $isDone    = $task['status'] === 'done';
                $isOverdue = !$isDone && strtotime($task['due_date']) < strtotime('today');
                $pillClass = $isDone ? 'pill-done' : ($isOverdue ? 'pill-overdue' : 'pill-pending');
                $label     = $isDone ? 'Done' : ($isOverdue ? 'Overdue' : 'Pending');
                $pipColor  = $isDone ? 'var(--green)' : ($isOverdue ? 'var(--red)' : 'var(--amber)');
        ?>
        <div style="display:flex;align-items:center;gap:12px;padding:11px 18px;
                    border-bottom:1px solid var(--border-soft);">
            <div style="width:8px;height:8px;border-radius:50%;background:<?= $pipColor ?>;flex-shrink:0;"></div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:13px;font-weight:500;<?= $isDone ? 'text-decoration:line-through;color:var(--text-muted);':'' ?>">
                    <?= htmlspecialchars($task['title']) ?>
                </div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                    <?= date('d M', strtotime($task['due_date'])) ?>
                    <?php if (!empty($task['group_name'])): ?>
                     · <?= htmlspecialchars($task['group_name']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <span class="pill <?= $pillClass ?>"><?= $label ?></span>
        </div>
        <?php endforeach; endforeach; ?>
    </div>
    <?php else: ?>
    <div class="card" style="margin-bottom:18px;">
        <div class="empty-state">
            <i class="bi bi-calendar-check" style="font-size:28px;display:block;margin-bottom:8px;color:var(--text-muted);"></i>
            <p>No tasks due this month.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ── LEGEND ─────────────────────────────────────────────── -->
<div class="card" style="padding:14px 20px;">
    <div style="font-size:13px;font-weight:600;margin-bottom:10px;">Status Legend</div>
    <div style="display:flex;flex-wrap:wrap;gap:18px;font-size:13px;color:var(--text-secondary);">
        <div style="display:flex;align-items:center;gap:7px;">
            <span style="width:10px;height:10px;border-radius:50%;background:var(--amber);flex-shrink:0;"></span> Pending
        </div>
        <div style="display:flex;align-items:center;gap:7px;">
            <span style="width:10px;height:10px;border-radius:50%;background:var(--red);flex-shrink:0;"></span> Overdue
        </div>
        <div style="display:flex;align-items:center;gap:7px;">
            <span style="width:10px;height:10px;border-radius:50%;background:var(--green);flex-shrink:0;"></span> Completed
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require STUDYSYNC_ROOT . '/views/layouts/app.php';
?>
