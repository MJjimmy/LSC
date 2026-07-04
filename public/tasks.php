<?php
ob_start();
require_once __DIR__ . '/config/db_helpers.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/csrf.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
}

if (isset($_POST['delete_task'])) {

    $taskId = (int)$_POST['delete_task'];

    $stmt = $pdo->prepare("
        SELECT group_id
        FROM tasks
        WHERE task_id = ?
    ");

    $stmt->execute([$taskId]);

    $task = $stmt->fetch();

    if (
        $task && (
        $_SESSION['user_role'] === 'admin' ||
        isGroupCreator(
            $task['group_id'],
            $_SESSION['user_id']
        )
        )
    ) {
        deleteTask($taskId);
    }

    header('Location: tasks.php');
    exit;
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['mark_done_id'])) {
        $taskId = (int)$_POST['mark_done_id'];
        $stmt = $pdo->prepare("SELECT task_id FROM tasks WHERE task_id = ? AND assigned_to = ?");
        $stmt->execute([$taskId, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            updateTaskStatus($taskId, 'done');
        }
        $group  = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
        $status = $_POST['status'] ?? 'all';
        header("Location: tasks.php?group_id=$group&status=$status");
        exit;
    }

    if (isset($_POST['create_task'])) {
        $group_id    = (int)$_POST['group_id'];
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $due_date    = $_POST['due_date'] ?? '';
        if ($title && $due_date && $group_id && strtotime($due_date) >= strtotime('today')) {
            $chk = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?");
            $chk->execute([$group_id, $_SESSION['user_id']]);
            if ($chk->fetch()) {
                createTask($group_id, $_SESSION['user_id'], $title, $description, $due_date);
            }
        }
        header("Location: tasks.php");
        exit;
    }
}

$myGroups        = getUserGroups($_SESSION['user_id']);
$selected_group  = isset($_GET['group_id']) && $_GET['group_id'] !== 'all'
                   ? (int)$_GET['group_id'] : 'all';
$selected_status = $_GET['status'] ?? 'all';

$tasks = [];
if ($selected_group !== 'all') {
    $tasks = getGroupTasks($selected_group);
} else {
    foreach ($myGroups as $g) {
        $tasks = array_merge($tasks, getGroupTasks($g['group_id']));
    }
}
 if ($selected_group !== 'all') {
    $tasks = getGroupTasks($selected_group);
} else {
    $tasks = getAllUserTasks($_SESSION['user_id']);
} 


if ($selected_status !== 'all') {
    $tasks = array_filter($tasks, fn($t) => $t['status'] === $selected_status);
}
$tasks = array_values($tasks);

$pendingCount = count(array_filter($tasks, fn($t) => $t['status'] === 'pending'));
$doneCount    = count(array_filter($tasks, fn($t) => $t['status'] === 'done'));
$overdueCount = count(array_filter($tasks, fn($t) =>
    $t['status'] === 'pending' && strtotime($t['due_date']) < strtotime('today')
));
?>

<!-- ── HERO ───────────────────────────────────────────────── -->
<div class="page-hero" style="margin-bottom:24px;">
    <div class="page-hero-eyebrow">Task Management</div>
    <div class="page-hero-title">My Tasks</div>
    <div class="page-hero-sub">Organise deadlines, track progress and stay productive.</div>
    <?php if (count($myGroups) > 0): ?>
    <button onclick="openTaskModal()" style="
        position:absolute;right:30px;top:50%;transform:translateY(-50%);
        padding:9px 18px;border-radius:10px;background:transparent;
        border:1px solid rgba(205,133,63,.28);color:#cd853f;
        font-size:13px;font-weight:600;cursor:pointer;z-index:1;
        font-family:'DM Sans',sans-serif;transition:background .15s;
    " onmouseover="this.style.background='rgba(205,133,63,.12)'"
       onmouseout="this.style.background='transparent'">
        <i class="bi bi-plus-lg" style="margin-right:5px;"></i>Create Task
    </button>
    <?php endif; ?>
</div>

<!-- ── STATS ──────────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px;">
    <div class="stat-card copper-tint">
        <div class="stat-card-label">Pending</div>
        <div class="stat-card-value"><?= $pendingCount ?></div>
    </div>
    <div class="stat-card" style="background:var(--green-bg);border-color:var(--green-border);">
        <div class="stat-card-label" style="color:#1a5a30;">Completed</div>
        <div class="stat-card-value" style="color:var(--green);"><?= $doneCount ?></div>
    </div>
    <div class="stat-card red-tint">
        <div class="stat-card-label">Overdue</div>
        <div class="stat-card-value"><?= $overdueCount ?></div>
    </div>
</div>

<!-- ── FILTERS ────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:18px;padding:16px 20px;">
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">

        <select onchange="window.location='tasks.php?group_id='+this.value+'&status=<?= htmlspecialchars($selected_status) ?>'"
                class="ss-input" style="width:auto;">
            <option value="all">All Groups</option>
            <?php foreach ($myGroups as $g): ?>
                <option value="<?= $g['group_id'] ?>" <?= $selected_group == $g['group_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($g['group_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select onchange="window.location='tasks.php?group_id=<?= $selected_group ?>&status='+this.value"
                class="ss-input" style="width:auto;">
            <option value="all"    <?= $selected_status==='all'     ? 'selected':'' ?>>All Statuses</option>
            <option value="pending"<?= $selected_status==='pending'  ? 'selected':'' ?>>Pending</option>
            <option value="done"   <?= $selected_status==='done'     ? 'selected':'' ?>>Completed</option>
        </select>

    </div>
</div>

<!-- ── TASK LIST ──────────────────────────────────────────── -->
<?php if (empty($tasks)): ?>
    <div class="card">
        <div class="empty-state">
            <i class="bi bi-check2-square" style="font-size:30px;display:block;margin-bottom:10px;color:var(--text-muted);"></i>
            <p>No tasks found. Create your first task to get started.</p>
        </div>
    </div>
<?php else: ?>
    <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($tasks as $t):
            $overdue = strtotime($t['due_date']) < strtotime('today') && $t['status'] === 'pending';
            $pillClass = $t['status']==='done' ? 'pill-done' : ($overdue ? 'pill-overdue' : 'pill-pending');
            $label     = $t['status']==='done' ? 'Completed' : ($overdue ? 'Overdue' : 'Pending');
        ?>
        <div class="card" style="padding:18px 22px;">
            <div style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:14px;">
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap;">
                        <div style="font-size:15px;font-weight:600;<?= $t['status']==='done' ? 'text-decoration:line-through;color:var(--text-muted);':'' ?>">
                            <?= htmlspecialchars($t['title']) ?>
                        </div>
                        <span class="pill <?= $pillClass ?>"><?= $label ?></span>
                    </div>
                    <?php if (!empty($t['description'])): ?>
                        <div style="font-size:13px;color:var(--text-secondary);margin-bottom:8px;">
                            <?= htmlspecialchars($t['description']) ?>
                        </div>
                    <?php endif; ?>
                    <div style="display:flex;flex-wrap:wrap;gap:16px;font-size:12.5px;color:var(--text-muted);">
                        <span>Group: <strong style="color:var(--text-primary);"><?= htmlspecialchars($t['group_name']) ?></strong></span>
                        <span>Due: <strong style="color:<?= $overdue ? 'var(--red)':'var(--text-primary)'; ?>"><?= date('d M Y', strtotime($t['due_date'])) ?></strong></span>
                        <span>Assigned: <strong style="color:var(--text-primary);"><?= htmlspecialchars($t['assigned_to_name'] ?? 'You') ?></strong></span>
                    </div>
                </div>
                <?php if ($t['status'] !== 'done' && $t['assigned_to'] == $_SESSION['user_id']): ?>
                <form method="POST" style="flex-shrink:0;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <input type="hidden" name="mark_done_id" value="<?= $t['task_id'] ?>">
                    <input type="hidden" name="group_id"     value="<?= $selected_group ?>">
                    <input type="hidden" name="status"       value="<?= htmlspecialchars($selected_status) ?>">
                    <button type="submit" class="btn-primary" style="background:var(--green);font-size:13px;">
                        <i class="bi bi-check-lg"></i> Mark Complete
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ── CREATE TASK MODAL ──────────────────────────────────── -->
<div id="createTaskModal" class="ss-modal-bg" style="display:none;">
    <div class="ss-modal">
        <div class="ss-modal-head">
            <div class="ss-modal-title">Create New Task</div>
            <div class="ss-modal-sub">Assign a task to yourself within a group.</div>
        </div>
        <div class="ss-modal-body">
            <form method="POST" id="taskForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

                <div style="margin-bottom:14px;">
                    <label class="ss-label">Group</label>
                    <select name="group_id" required class="ss-input">
                        <option value="">— Select group —</option>
                        <?php foreach ($myGroups as $g): ?>
                            <option value="<?= $g['group_id'] ?>"><?= htmlspecialchars($g['group_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom:14px;">
                    <label class="ss-label">Task Title</label>
                    <input type="text" name="title" required maxlength="200" class="ss-input">
                </div>

                <div style="margin-bottom:14px;">
                    <label class="ss-label">Description <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                    <textarea name="description" rows="3" class="ss-input" style="resize:vertical;"></textarea>
                </div>

                <div style="margin-bottom:6px;">
                    <label class="ss-label">Due Date</label>
                    <input type="date" name="due_date" required min="<?= date('Y-m-d') ?>" class="ss-input">
                </div>

                <div style="font-size:12px;color:var(--text-muted);margin-top:8px;">
                    Task will be assigned to you. Group members can reassign later.
                </div>

                <div class="ss-modal-footer" style="padding:16px 0 0;border:none;">
                    <button type="button" onclick="closeTaskModal()" class="btn-ghost">Cancel</button>
                    <button type="submit" name="create_task" class="btn-primary btn-copper">Create Task</button>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
function openTaskModal()  {
    document.getElementById('createTaskModal').style.display = 'flex';
    document.getElementById('taskForm').reset();
}
function closeTaskModal() {
    document.getElementById('createTaskModal').style.display = 'none';
}
document.getElementById('createTaskModal').addEventListener('click', function(e) {
    if (e.target === this) closeTaskModal();
});
</script>

<?php $page_content = ob_get_clean(); require 'layout.php'; ?>
