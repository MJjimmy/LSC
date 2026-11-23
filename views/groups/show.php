<?php
// Variables are provided by GroupController::show().
$isGroupCreator = (int)$group['created_by'] === $userId;
$pendingGroupTasks = array_values(array_filter($tasks, static fn(array $task): bool => $task['status'] === 'pending'));
$completedGroupTasks = array_values(array_filter($tasks, static fn(array $task): bool => $task['status'] === 'done'));
$nextGroupTask = $pendingGroupTasks[0] ?? null;
$recentMessages = array_slice(array_reverse($messages), 0, 4);
$recentFiles = array_slice($files, 0, 5);
?>

<style>
.group-hero{background:var(--sidebar);border:1px solid #123d66;color:#fff;margin-bottom:18px;min-height:220px;overflow:hidden;padding:34px 36px;position:relative}.group-hero::after{border:34px solid rgba(255,255,255,.045);border-radius:50%;content:'';height:175px;position:absolute;right:-35px;top:-65px;width:175px}.group-hero-main{display:flex;gap:28px;justify-content:space-between;position:relative;z-index:1}.group-hero-copy{max-width:760px}.group-kicker{color:#79d5a9;font-size:10px;font-weight:700;letter-spacing:.14em;margin-bottom:10px;text-transform:uppercase}.group-hero h1{font:800 clamp(29px,4vw,44px)/1.08 'Manrope',sans-serif;letter-spacing:-.045em;margin:0}.group-description{color:#b9cad8;font-size:14px;line-height:1.65;margin-top:12px;max-width:680px}.group-meta{display:flex;flex-wrap:wrap;gap:9px;margin-top:22px}.group-meta span{align-items:center;border:1px solid rgba(255,255,255,.17);color:#d5e1ea;display:inline-flex;font-size:11px;font-weight:600;gap:7px;padding:7px 10px}.group-hero-actions{align-items:flex-end;display:flex;flex-direction:column;gap:9px;position:relative;z-index:2}.group-hero-actions .btn-primary{background:#fff;color:var(--sidebar);white-space:nowrap}.group-hero-actions .btn-ghost{background:transparent;border-color:rgba(255,255,255,.22);color:#d8e4ed;white-space:nowrap}.group-danger-btn{background:transparent;border:0;color:#f4b4ae;cursor:pointer;font:600 11px 'DM Sans',sans-serif;padding:6px}.group-tabs{background:var(--card);border:1px solid var(--border);display:flex;margin-bottom:20px;overflow-x:auto;padding:5px}.group-tabs a{align-items:center;color:var(--text-secondary);display:flex;flex:1;font-size:12px;font-weight:700;gap:7px;justify-content:center;min-width:112px;padding:10px 14px;text-decoration:none}.group-tabs a:hover{background:var(--cream);color:var(--text-primary)}.group-tabs a.active{background:var(--sidebar);color:#fff}.group-tabs a.active i{color:#79d5a9}
.overview-stats{display:grid;gap:14px;grid-template-columns:repeat(4,1fr);margin-bottom:18px}.overview-stat{background:var(--card);border:1px solid var(--border);padding:19px 20px}.overview-stat span{color:var(--text-muted);display:block;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}.overview-stat strong{display:block;font:800 27px/1 'Manrope',sans-serif;letter-spacing:-.04em;margin-top:13px}.overview-grid{display:grid;gap:18px;grid-template-columns:minmax(0,1.25fr) minmax(300px,.75fr)}.overview-stack{display:grid;gap:18px}.overview-item{align-items:center;border-bottom:1px solid var(--border-soft);display:flex;gap:12px;padding:14px 20px}.overview-item:last-child{border-bottom:0}.overview-item-icon{align-items:center;background:var(--primary-soft);color:var(--primary);display:flex;flex:0 0 auto;font-size:16px;height:36px;justify-content:center;width:36px}.overview-item-copy{flex:1;min-width:0}.overview-item-copy strong{display:block;font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.overview-item-copy small{color:var(--text-muted);display:block;font-size:10.5px;margin-top:3px}.overview-members{display:flex;padding:18px 20px}.overview-member{align-items:center;background:var(--sidebar);border:3px solid var(--card);border-radius:50%;color:#fff;display:flex;font-size:10px;font-weight:800;height:38px;justify-content:center;margin-left:-8px;overflow:hidden;width:38px}.overview-member:first-child{margin-left:0}.overview-member img{height:100%;object-fit:cover;width:100%}.overview-request{align-items:center;background:var(--amber-bg);border:1px solid var(--amber-border);display:flex;gap:18px;justify-content:space-between;margin-bottom:18px;padding:16px 18px}.overview-request strong{font-size:13px}.overview-request p{color:var(--text-secondary);font-size:11px;margin-top:3px}
@media(max-width:900px){.overview-stats{grid-template-columns:repeat(2,1fr)}.overview-grid{grid-template-columns:1fr}.group-hero-main{align-items:flex-start;flex-direction:column}.group-hero-actions{align-items:flex-start;flex-direction:row;flex-wrap:wrap}}
@media(max-width:560px){.group-hero{padding:27px 22px}.group-meta{gap:6px}.overview-stats{gap:8px}.overview-stat{padding:16px}.overview-request{align-items:flex-start;flex-direction:column}.group-tabs a{min-width:100px}.group-hero-actions{flex-direction:column}}
</style>

<section class="group-hero">
    <div class="group-hero-main">
        <div class="group-hero-copy">
            <div class="group-kicker"><?= $canManageGroup ? 'Managed academic group' : 'Study group workspace' ?></div>
            <h1><?= htmlspecialchars($group['group_name']) ?></h1>
            <p class="group-description"><?= htmlspecialchars($group['description'] ?: 'Collaborate, share resources and move academic work forward together.') ?></p>
            <div class="group-meta">
                <span><i class="bi bi-people" aria-hidden="true"></i> <?= count($members) ?> members</span>
                <span><i class="bi <?= $group['is_private'] ? 'bi-lock' : 'bi-globe2' ?>" aria-hidden="true"></i> <?= $group['is_private'] ? 'Private group' : 'Public group' ?></span>
                <span><i class="bi bi-list-check" aria-hidden="true"></i> <?= count($pendingGroupTasks) ?> open tasks</span>
            </div>
        </div>
        <div class="group-hero-actions">
            <a href="?id=<?= $groupId ?>&tab=chat" class="btn-primary"><i class="bi bi-chat-dots-fill" aria-hidden="true"></i> Open chat</a>
            <?php if ($canManageGroup): ?>
                <a href="?id=<?= $groupId ?>&tab=members" class="btn-ghost" style="text-decoration:none;"><i class="bi bi-person-plus" aria-hidden="true"></i> Manage members</a>
                <form method="POST" onsubmit="return confirm('Delete this group permanently?')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <button type="submit" name="delete_group" class="group-danger-btn">Delete group</button>
                </form>
            <?php elseif ($isMember): ?>
                <form method="POST" onsubmit="return confirm('Leave this group? Shared tasks, files and messages will be kept.');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <button type="submit" name="leave_group" class="btn-ghost" <?= $isGroupCreator ? 'disabled aria-describedby="leave-group-note" style="opacity:.55;cursor:not-allowed;"' : '' ?>><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Leave group</button>
                    <?php if ($isGroupCreator): ?><span id="leave-group-note" hidden>Group creators cannot leave their own group.</span><?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($groupFlash): ?>
<div style="margin-bottom:18px;padding:13px 15px;border-radius:10px;
            <?= $groupFlash['type'] === 'success'
                ? 'background:var(--green-bg);border:1px solid var(--green-border);color:var(--green);'
                : 'background:var(--red-bg);border:1px solid var(--red-border);color:var(--red);' ?>">
    <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($groupFlash['message']) ?></div>
    <?php if (!empty($groupFlash['invite_path'])): ?>
    <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;">
        <input id="createdInviteLink" class="lsc-input" style="flex:1;min-width:220px;" readonly
               data-path="<?= htmlspecialchars($groupFlash['invite_path']) ?>">
        <button type="button" class="btn-primary btn-brand" onclick="copyInviteLink()">
            <i class="bi bi-copy"></i> Copy link
        </button>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<nav class="group-tabs" aria-label="Group workspace">
    <?php foreach ($tabs as $key => $t): ?>
    <a href="?id=<?= $groupId ?>&tab=<?= $key ?>" class="<?= $tab === $key ? 'active' : '' ?>">
        <i class="bi <?= $t['icon'] ?>" aria-hidden="true"></i>
        <?= $t['label'] ?>
    </a>
    <?php endforeach; ?>
</nav>

<!-- ── TAB CONTENT: OVERVIEW ──────────────────────────────── -->
<?php if ($tab === 'overview'): ?>

<?php if ($canManageGroup && $pendingJoinRequests): ?>
<div class="overview-request">
    <div><strong><?= count($pendingJoinRequests) ?> membership request<?= count($pendingJoinRequests) === 1 ? '' : 's' ?> awaiting review</strong><p>Review learners before granting access to this group.</p></div>
    <a href="?id=<?= $groupId ?>&tab=members" class="btn-primary">Review requests <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
</div>
<?php endif; ?>

<div class="overview-stats">
    <div class="overview-stat"><span>Members</span><strong><?= count($members) ?></strong></div>
    <div class="overview-stat"><span>Open tasks</span><strong><?= count($pendingGroupTasks) ?></strong></div>
    <div class="overview-stat"><span>Completed</span><strong><?= count($completedGroupTasks) ?></strong></div>
    <div class="overview-stat"><span>Shared files</span><strong><?= count($files) ?></strong></div>
</div>

<div class="overview-grid">
    <div class="overview-stack">
        <section class="card">
            <div class="panel-header"><div><div class="panel-title">Upcoming work</div><div class="panel-sub">The next tasks this group is moving forward</div></div><a class="panel-link" href="?id=<?= $groupId ?>&tab=tasks">All tasks</a></div>
            <?php if (!$pendingGroupTasks): ?>
                <div class="empty-state"><i class="bi bi-check2-circle" style="font-size:27px;" aria-hidden="true"></i><p>No open tasks in this group.</p></div>
            <?php else: ?>
                <?php foreach (array_slice($pendingGroupTasks, 0, 5) as $task):
                    $overdue = strtotime($task['due_date']) < strtotime('today');
                ?>
                <div class="overview-item">
                    <span class="overview-item-icon"><i class="bi <?= $overdue ? 'bi-exclamation-triangle' : 'bi-check2-square' ?>" aria-hidden="true"></i></span>
                    <span class="overview-item-copy"><strong><?= htmlspecialchars($task['title']) ?></strong><small><?= htmlspecialchars($task['assigned_to_name'] ?? 'Unassigned') ?> · Due <?= date('d M Y', strtotime($task['due_date'])) ?></small></span>
                    <span class="pill <?= $overdue ? 'pill-overdue' : 'pill-pending' ?>"><?= $overdue ? 'Overdue' : 'Pending' ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="panel-header"><div><div class="panel-title">Recent conversation</div><div class="panel-sub">The latest updates from group chat</div></div><a class="panel-link" href="?id=<?= $groupId ?>&tab=chat">Open chat</a></div>
            <?php if (!$recentMessages): ?>
                <div class="empty-state"><i class="bi bi-chat-square-text" style="font-size:27px;" aria-hidden="true"></i><p>No messages yet. Start the conversation.</p></div>
            <?php else: ?>
                <?php foreach ($recentMessages as $message): ?>
                <div class="overview-item">
                    <span class="overview-item-icon"><i class="bi bi-chat-left-text" aria-hidden="true"></i></span>
                    <span class="overview-item-copy"><strong><?= htmlspecialchars($message['user_name']) ?></strong><small><?= htmlspecialchars(mb_substr($message['message'] ?: 'Shared an attachment', 0, 90)) ?> · <?= date('d M, H:i', strtotime($message['created_at'])) ?></small></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>

    <div class="overview-stack">
        <section class="card">
            <div class="panel-header"><div><div class="panel-title">Group members</div><div class="panel-sub"><?= count($members) ?> people in this workspace</div></div><a class="panel-link" href="?id=<?= $groupId ?>&tab=members">View all</a></div>
            <div class="overview-members">
                <?php foreach (array_slice($members, 0, 8) as $member): ?>
                <a class="overview-member" href="profile.php?user_id=<?= (int)$member['user_id'] ?>" title="<?= htmlspecialchars($member['name']) ?>">
                    <?= htmlspecialchars(strtoupper(mb_substr($member['name'], 0, 1))) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card">
            <div class="panel-header"><div><div class="panel-title">Recent resources</div><div class="panel-sub">Files shared with this group</div></div><a class="panel-link" href="?id=<?= $groupId ?>&tab=files">File library</a></div>
            <?php if (!$recentFiles): ?>
                <div class="empty-state"><i class="bi bi-folder2-open" style="font-size:27px;" aria-hidden="true"></i><p>No resources shared yet.</p></div>
            <?php else: ?>
                <?php foreach ($recentFiles as $file): ?>
                <a class="overview-item" href="download.php?id=<?= (int)$file['file_id'] ?>" style="color:inherit;text-decoration:none;">
                    <span class="overview-item-icon"><i class="bi bi-file-earmark" aria-hidden="true"></i></span>
                    <span class="overview-item-copy"><strong><?= htmlspecialchars($file['file_name']) ?></strong><small><?= htmlspecialchars($file['uploaded_by_name']) ?> · <?= max(1, round($file['file_size'] / 1024)) ?> KB</small></span>
                    <i class="bi bi-download" style="color:var(--text-muted);" aria-hidden="true"></i>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</div>

<!-- ── TAB CONTENT: TASKS ─────────────────────────────────── -->
<?php elseif ($tab === 'tasks'): ?>

<div class="card">
    <?php if (count($tasks) > 0): ?>
    <div style="overflow-x:auto;">
        <table class="lsc-table">
            <thead>
                <tr>
                    <th>Task</th>
                    <th>Assigned To</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tasks as $t):
                $overdue = strtotime($t['due_date']) < strtotime('today') && $t['status'] === 'pending';
                $pillClass = $t['status']==='done' ? 'pill-done' : ($overdue ? 'pill-overdue' : 'pill-pending');
                $label     = $t['status']==='done' ? 'Completed' : ($overdue ? 'Overdue' : 'Pending');
            ?>
                <tr>
                    <td style="font-weight:500;"><?= htmlspecialchars($t['title']) ?></td>
                    <td style="color:var(--text-secondary);"><?= htmlspecialchars($t['assigned_to_name'] ?? 'Unassigned') ?></td>
                    <td style="<?= $overdue ? 'color:var(--red);font-weight:600;':'' ?>">
                        <?= date('d M Y', strtotime($t['due_date'])) ?>
                    </td>
                    <td><span class="pill <?= $pillClass ?>"><?= $label ?></span></td>
                    <td style="text-align:right;">
                        <?php if ($t['status'] !== 'done'): ?>
                        <form method="POST" action="tasks.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <input type="hidden" name="mark_done_id" value="<?= $t['task_id'] ?>">
                            <button type="submit" style="padding:5px 12px;border-radius:6px;border:1px solid var(--green-border);
                                    background:var(--green-bg);color:var(--green);font-size:12px;font-weight:600;
                                    cursor:pointer;font-family:'Inter',system-ui,sans-serif;">Mark Done</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="empty-state"><p>No tasks yet.</p></div>
    <?php endif; ?>
</div>

<!-- ── TAB CONTENT: MEMBERS ──────────────────────────────── -->
<?php elseif ($tab === 'members'): ?>

<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap;">
    <div>
        <div style="font-size:15px;font-weight:700;">Group members</div>
        <div style="font-size:12px;color:var(--text-muted);">View profiles and manage access.</div>
    </div>
    <?php if ($canManageGroup): ?>
    <button type="button" class="btn-primary btn-brand" onclick="document.getElementById('inviteModal').style.display='flex'">
        <i class="bi bi-person-plus-fill"></i> Invite people
    </button>
    <?php endif; ?>
</div>

<?php if ($canManageGroup && !empty($pendingJoinRequests)): ?>
<div class="card" style="margin-bottom:16px;">
    <div class="panel-header">
        <div>
            <div class="panel-title">Join Requests</div>
            <div class="panel-sub">Review learners who want to join this group.</div>
        </div>
        <span class="pill pill-pending"><?= count($pendingJoinRequests) ?> pending</span>
    </div>

    <?php foreach ($pendingJoinRequests as $request): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:13px 20px;
                border-bottom:1px solid var(--border-soft);">
        <div style="width:36px;height:36px;border-radius:50%;background:var(--brand-dim);
                    border:1px solid var(--brand-border);display:flex;align-items:center;
                    justify-content:center;font-weight:700;font-size:13px;color:var(--brand);flex-shrink:0;">
            <?= strtoupper(substr($request['name'], 0, 1)) ?>
        </div>
        <div style="flex:1;min-width:0;">
            <div style="font-size:13.5px;font-weight:600;"><?= htmlspecialchars($request['name']) ?></div>
            <div style="font-size:11.5px;color:var(--text-muted);margin-top:2px;">
                <?= htmlspecialchars($request['email']) ?> &middot; Requested <?= date('d M Y', strtotime($request['requested_at'])) ?>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-shrink:0;flex-wrap:wrap;justify-content:flex-end;">
            <form method="POST" action="?id=<?= $groupId ?>&tab=members">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="approve_request_id" value="<?= $request['request_id'] ?>">
                <button type="submit" style="padding:6px 12px;border-radius:7px;border:1px solid var(--green-border);
                        background:var(--green-bg);color:var(--green);font-size:12px;font-weight:600;
                        cursor:pointer;font-family:'Inter',system-ui,sans-serif;">Approve</button>
            </form>
            <form method="POST" action="?id=<?= $groupId ?>&tab=members">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="reject_request_id" value="<?= $request['request_id'] ?>">
                <button type="submit" style="padding:6px 12px;border-radius:7px;border:1px solid var(--red-border);
                        background:var(--red-bg);color:var(--red);font-size:12px;font-weight:600;
                        cursor:pointer;font-family:'Inter',system-ui,sans-serif;">Decline</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <?php foreach ($members as $m): ?>
    <?php $isCreator = (int)$m['user_id'] === (int)$group['created_by']; ?>
    <div style="display:flex;align-items:center;gap:12px;padding:13px 20px;
                border-bottom:1px solid var(--border-soft);">
        <a href="profile.php?user_id=<?= (int)$m['user_id'] ?>"
           aria-label="View <?= htmlspecialchars($m['name']) ?>'s profile"
           style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;color:inherit;text-decoration:none;">
            <div style="width:36px;height:36px;border-radius:50%;background:var(--brand-dim);
                        border:1px solid var(--brand-border);display:flex;align-items:center;
                        justify-content:center;font-weight:700;font-size:13px;color:var(--brand);flex-shrink:0;">
                <?= strtoupper(substr($m['name'], 0, 1)) ?>
            </div>
            <div style="min-width:0;">
                <div style="font-size:13.5px;font-weight:600;color:var(--text-primary);">
                    <?= htmlspecialchars($m['name']) ?>
                </div>
                <div style="font-size:11.5px;color:var(--text-muted);margin-top:2px;">
                    <?= $m['role'] === 'student' ? 'Learner' : ucfirst($m['role']) ?> &middot; Joined <?= date('d M Y', strtotime($m['joined_at'])) ?>
                </div>
            </div>
        </a>
        <?php if ($isCreator): ?>
            <span class="pill pill-admin">Creator</span>
        <?php elseif ($canManageGroup): ?>
            <form method="POST" action="?id=<?= $groupId ?>&tab=members"
                  onsubmit="return confirm('Remove this member from the group?')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="remove_member_user_id" value="<?= $m['user_id'] ?>">
                <button type="submit" style="padding:5px 11px;border-radius:7px;border:1px solid var(--red-border);
                        background:var(--red-bg);color:var(--red);font-size:12px;font-weight:600;
                        cursor:pointer;font-family:'Inter',system-ui,sans-serif;">Remove</button>
            </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── TAB CONTENT: FILES ────────────────────────────────── -->
<?php if ($canManageGroup): ?>
<div id="inviteModal" class="lsc-modal-bg" style="display:none;">
    <div class="lsc-modal">
        <div class="lsc-modal-head">
            <div class="lsc-modal-title">Invite people</div>
            <div class="lsc-modal-sub">Create a secure, seven-day invitation for this group.</div>
        </div>
        <div class="lsc-modal-body">
            <form method="POST" action="?id=<?= $groupId ?>&tab=members">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <label class="lsc-label" for="inviteEmail">Email address</label>
                <input id="inviteEmail" type="email" name="invite_email" class="lsc-input"
                       placeholder="student@example.com" autocomplete="email" required>
                <div style="font-size:12px;color:var(--text-muted);margin-top:9px;line-height:1.5;">
                    Registered users also receive an in-app notification. For everyone else, copy and share the generated link.
                </div>
                <div class="lsc-modal-footer" style="padding:18px 0 0;border:none;">
                    <button type="button" class="btn-ghost" onclick="document.getElementById('inviteModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn-primary btn-brand"><i class="bi bi-send-fill"></i> Create invite</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php elseif ($tab === 'files'): ?>

<div class="card">
    <?php if (count($files) > 0): ?>
    <div style="overflow-x:auto;">
        <table class="lsc-table">
            <thead>
                <tr>
                    <th>File</th>
                    <th>Uploaded By</th>
                    <th>Size</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($files as $f): ?>
                <tr>
                    <td>
                        <a href="download.php?id=<?= (int)$f['file_id'] ?>"
                           style="color:var(--brand-dark);font-weight:600;text-decoration:none;font-size:13.5px;"
                           onmouseover="this.style.color='var(--brand)'"
                           onmouseout="this.style.color='var(--brand-dark)'">
                            <?= htmlspecialchars($f['file_name']) ?>
                        </a>
                    </td>
                    <td style="color:var(--text-secondary);"><?= htmlspecialchars($f['uploaded_by_name']) ?></td>
                    <td style="color:var(--text-muted);"><?= round($f['file_size'] / 1024) ?> KB</td>
                    <td style="text-align:right;">
                        <?php if ($f['uploaded_by'] == $_SESSION['user_id']): ?>
                        <form method="POST" action="files.php" style="display:inline;"
                              onsubmit="return confirm('Delete this file?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <input type="hidden" name="delete_file_id" value="<?= $f['file_id'] ?>">
                            <input type="hidden" name="group_id"       value="<?= $groupId ?>">
                            <button type="submit" style="padding:5px 12px;border-radius:6px;border:1px solid var(--red-border);
                                    background:var(--red-bg);color:var(--red);font-size:12px;font-weight:600;
                                    cursor:pointer;font-family:'Inter',system-ui,sans-serif;">Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="empty-state"><p>No files uploaded yet.</p></div>
    <?php endif; ?>
</div>

<!-- ── TAB CONTENT: CHAT ─────────────────────────────────── -->
<?php elseif ($tab === 'chat'): ?>

<style>
.chat-workspace{display:grid;gap:18px;grid-template-columns:minmax(0,1fr) 300px}.chat-panel{background:var(--card);border:1px solid var(--border);display:flex;flex-direction:column;min-height:650px;overflow:hidden}.chat-head{align-items:center;border-bottom:1px solid var(--border);display:flex;gap:12px;justify-content:space-between;padding:17px 20px}.chat-head h2{font:800 14px 'Manrope',sans-serif}.chat-head p{color:var(--text-muted);font-size:10.5px;margin-top:2px}.chat-head-status{align-items:center;color:var(--green);display:flex;font-size:10px;font-weight:700;gap:6px;text-transform:uppercase}.chat-head-status::before{background:var(--green);border-radius:50%;content:'';height:7px;width:7px}.chat-messages{background:#f8fafc;flex:1;height:510px;overflow-y:auto;padding:24px 22px}.chat-date{align-items:center;color:var(--text-muted);display:flex;font-size:9px;font-weight:700;gap:12px;letter-spacing:.08em;margin:5px 0 22px;text-transform:uppercase}.chat-date::before,.chat-date::after{background:var(--border);content:'';flex:1;height:1px}.chat-message{display:flex;gap:10px;margin-bottom:20px;max-width:78%}.chat-message.mine{flex-direction:row-reverse;margin-left:auto}.chat-avatar{align-items:center;background:var(--sidebar);border-radius:50%;color:#fff;display:flex;flex:0 0 auto;font-size:11px;font-weight:800;height:34px;justify-content:center;overflow:hidden;width:34px}.chat-avatar img{height:100%;object-fit:cover;width:100%}.chat-message-content{min-width:0}.chat-message-meta{align-items:baseline;display:flex;gap:8px;margin:0 3px 5px}.chat-message.mine .chat-message-meta{justify-content:flex-end}.chat-message-meta a{color:var(--text-primary);font-size:11.5px;font-weight:700;text-decoration:none}.chat-message-meta time{color:var(--text-muted);font-size:9.5px}.chat-bubble{background:#fff;border:1px solid var(--border);color:var(--text-secondary);font-size:13px;line-height:1.55;padding:10px 12px;word-break:break-word}.chat-message.mine .chat-bubble{background:var(--sidebar);border-color:var(--sidebar);color:#edf4f9}.chat-attachment{background:#fff;border:1px solid var(--border);margin-top:7px;max-width:360px;overflow:hidden}.chat-attachment img{display:block;max-height:240px;object-fit:cover;width:100%}.chat-file-link{align-items:center;color:var(--text-primary);display:flex;gap:10px;padding:10px 12px;text-decoration:none}.chat-file-link:hover{background:var(--primary-soft)}.chat-message.mine .chat-file-link{background:#fff}.chat-file-link>i:first-child{color:var(--primary);font-size:20px}.chat-file-link small{color:var(--text-muted)}.chat-empty{color:var(--text-muted);padding:100px 20px;text-align:center}.chat-empty i{display:block;font-size:32px;margin-bottom:10px}.chat-empty strong{color:var(--text-primary);display:block;font-size:13px}.chat-empty span{display:block;font-size:11px;margin-top:4px}.chat-composer-wrap{background:#fff;border-top:1px solid var(--border);padding:13px 15px}.chat-composer{align-items:flex-end;display:flex;gap:9px}.chat-plus{align-items:center;background:var(--primary-soft);border:1px solid var(--primary-border);color:var(--primary);cursor:pointer;display:flex;flex:0 0 auto;font-size:17px;height:44px;justify-content:center;width:44px}.chat-plus:hover{background:#dcecff}.chat-compose-field{flex:1;min-width:0}.chat-compose-field textarea{max-height:120px;min-height:44px;padding:11px 13px;resize:none}.chat-attachment-name{align-items:center;color:var(--primary);display:none;font-size:10.5px;gap:6px;margin:6px 3px 0}.chat-send{height:44px;min-height:44px}.chat-readonly{background:#fff;border-top:1px solid var(--border);color:var(--text-muted);font-size:11px;padding:15px;text-align:center}.chat-context{display:grid;gap:16px}.chat-context-card{background:var(--card);border:1px solid var(--border);overflow:hidden}.chat-context-card>a,.chat-context-list a,.chat-context-item{align-items:center;border-bottom:1px solid var(--border-soft);color:inherit;display:flex;gap:10px;padding:12px 14px;text-decoration:none}.chat-context-card>a:last-child,.chat-context-list a:last-child,.chat-context-item:last-child{border-bottom:0}.chat-context-card>a:hover,.chat-context-list a:hover{background:var(--cream)}.chat-context-icon{align-items:center;background:var(--primary-soft);color:var(--primary);display:flex;flex:0 0 auto;height:32px;justify-content:center;width:32px}.chat-context-copy{flex:1;min-width:0}.chat-context-copy strong{display:block;font-size:11.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.chat-context-copy small{color:var(--text-muted);display:block;font-size:9.5px;margin-top:2px}.chat-guideline{background:var(--sidebar);border:1px solid #123d66;color:#fff;padding:17px}.chat-guideline strong{display:block;font:700 12px 'Manrope',sans-serif}.chat-guideline p{color:#aebfce;font-size:10.5px;line-height:1.55;margin-top:6px}
@media(max-width:1000px){.chat-workspace{grid-template-columns:1fr}.chat-context{grid-template-columns:1fr 1fr}.chat-guideline{grid-column:1/-1}}
@media(max-width:650px){.chat-messages{height:470px;padding:18px 13px}.chat-message{max-width:92%}.chat-message-meta a{max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.chat-context{grid-template-columns:1fr}.chat-guideline{grid-column:auto}.chat-send span{display:none}.chat-send{padding-left:13px;padding-right:13px}.chat-panel{min-height:600px}}
</style>

<div class="chat-workspace">
    <section class="chat-panel" aria-label="Group conversation">
        <header class="chat-head">
            <div><h2>Group conversation</h2><p><?= count($messages) ?> message<?= count($messages) === 1 ? '' : 's' ?> · <?= count($members) ?> members</p></div>
            <span class="chat-head-status">Workspace active</span>
        </header>

        <div id="chatMessagesNew" class="chat-messages" aria-live="polite">
            <?php if (empty($messages)): ?>
                <div class="chat-empty">
                    <i class="bi bi-chat-square-text" aria-hidden="true"></i>
                    <strong>Start the group conversation</strong>
                    <span>Share an update, ask a question or attach a useful resource.</span>
                </div>
            <?php else: ?>
                <?php $lastMessageDate = null; ?>
                <?php foreach ($messages as $msg):
                    $isImage = !empty($msg['file_type']) && in_array(strtolower($msg['file_type']), ['png','jpg','jpeg','gif','webp'], true);
                    $messageDate = date('Y-m-d', strtotime($msg['created_at']));
                    $isMine = (int)$msg['user_id'] === $userId;
                ?>
                <?php if ($messageDate !== $lastMessageDate): ?>
                    <div class="chat-date"><?= $messageDate === date('Y-m-d') ? 'Today' : date('d F Y', strtotime($msg['created_at'])) ?></div>
                    <?php $lastMessageDate = $messageDate; ?>
                <?php endif; ?>
                <div class="chat-message <?= $isMine ? 'mine' : '' ?>">
                    <a class="chat-avatar" href="profile.php?user_id=<?= (int)$msg['user_id'] ?>" aria-label="View profile">
                        <?php if (!empty($msg['user_avatar'])): ?><img src="avatar.php?user_id=<?= (int)$msg['user_id'] ?>" alt=""><?php else: ?><?= strtoupper(substr($msg['user_name'], 0, 1)) ?><?php endif; ?>
                    </a>
                    <div class="chat-message-content">
                        <div class="chat-message-meta">
                            <a href="profile.php?user_id=<?= (int)$msg['user_id'] ?>"><?= $isMine ? 'You' : htmlspecialchars($msg['user_name']) ?></a>
                            <time datetime="<?= htmlspecialchars(date(DATE_ATOM, strtotime($msg['created_at']))) ?>"><?= date('H:i', strtotime($msg['created_at'])) ?></time>
                        </div>
                        <?php if ($msg['message'] !== ''): ?><div class="chat-bubble"><?= nl2br(htmlspecialchars($msg['message'])) ?></div><?php endif; ?>
                        <?php if (!empty($msg['file_id'])): ?>
                        <div class="chat-attachment">
                            <?php if ($isImage): ?><a href="download.php?id=<?= (int)$msg['file_id'] ?>"><img src="download.php?id=<?= (int)$msg['file_id'] ?>&preview=1" alt="<?= htmlspecialchars($msg['file_name']) ?>"></a><?php endif; ?>
                            <a class="chat-file-link" href="download.php?id=<?= (int)$msg['file_id'] ?>">
                                <i class="bi <?= $isImage ? 'bi-file-earmark-image-fill' : 'bi-file-earmark-arrow-down-fill' ?>" aria-hidden="true"></i>
                                <span style="min-width:0;flex:1;"><strong style="font-size:11.5px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($msg['file_name']) ?></strong><small><?= strtoupper($msg['file_type']) ?> &middot; <?= max(1, round($msg['file_size'] / 1024)) ?> KB</small></span>
                                <i class="bi bi-download" aria-hidden="true"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($isMember): ?>
        <div class="chat-composer-wrap">
            <form id="chatForm" method="POST" enctype="multipart/form-data" class="chat-composer">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="send_message" value="1">
                <input id="chatAttachment" type="file" name="attachment" hidden accept=".pdf,.docx,.pptx,.xlsx,.txt,.csv,.zip,.png,.jpg,.jpeg,.gif,.webp">
                <label for="chatAttachment" class="chat-plus" title="Add a picture or file" aria-label="Add a picture or file"><i class="bi bi-plus-lg"></i></label>
                <div class="chat-compose-field">
                    <textarea id="chatMessage" name="message" placeholder="Write a message to the group…" class="lsc-input" rows="1" maxlength="4000" aria-label="Message"></textarea>
                    <div id="attachmentName" class="chat-attachment-name"><i class="bi bi-paperclip" aria-hidden="true"></i><span></span></div>
                </div>
                <button type="submit" class="btn-primary btn-brand chat-send"><i class="bi bi-send-fill"></i> <span>Send</span></button>
            </form>
        </div>
        <?php else: ?>
        <div class="chat-readonly"><i class="bi bi-eye" aria-hidden="true"></i> Administrative read-only view. Join the group to participate in chat.</div>
        <?php endif; ?>
    </section>

    <aside class="chat-context" aria-label="Group chat context">
        <section class="chat-context-card">
            <div class="panel-header"><div><div class="panel-title">Upcoming work</div><div class="panel-sub">Next group deadlines</div></div><a class="panel-link" href="?id=<?= $groupId ?>&tab=tasks">View all</a></div>
            <?php if (!$pendingGroupTasks): ?>
                <div class="chat-context-item"><span class="chat-context-copy"><strong>No open tasks</strong><small>The group is caught up.</small></span></div>
            <?php else: ?>
                <?php foreach (array_slice($pendingGroupTasks, 0, 4) as $task): ?>
                <a href="?id=<?= $groupId ?>&tab=tasks">
                    <span class="chat-context-icon"><i class="bi bi-check2-square" aria-hidden="true"></i></span>
                    <span class="chat-context-copy"><strong><?= htmlspecialchars($task['title']) ?></strong><small>Due <?= date('d M', strtotime($task['due_date'])) ?></small></span>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="chat-context-card">
            <div class="panel-header"><div><div class="panel-title">Shared files</div><div class="panel-sub">Recent group resources</div></div><a class="panel-link" href="?id=<?= $groupId ?>&tab=files">View all</a></div>
            <div class="chat-context-list">
            <?php if (!$files): ?>
                <div class="chat-context-item"><span class="chat-context-copy"><strong>No files yet</strong><small>Attach a resource to a message.</small></span></div>
            <?php else: ?>
                <?php foreach (array_slice($files, 0, 6) as $file): ?>
                <a href="download.php?id=<?= (int)$file['file_id'] ?>">
                    <span class="chat-context-icon"><i class="bi bi-file-earmark" aria-hidden="true"></i></span>
                    <span class="chat-context-copy"><strong><?= htmlspecialchars($file['file_name']) ?></strong><small><?= max(1, round($file['file_size'] / 1024)) ?> KB · <?= htmlspecialchars($file['uploaded_by_name']) ?></small></span>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </section>

        <section class="chat-guideline">
            <strong>Keep the conversation useful</strong>
            <p>Share course-related updates and resources. Files remain available to current group members from the library.</p>
        </section>
    </aside>
</div>

<script>
const chatNew = document.getElementById('chatMessagesNew');
if (chatNew) chatNew.scrollTop = chatNew.scrollHeight;
const attachment = document.getElementById('chatAttachment');
const attachmentName = document.getElementById('attachmentName');
attachment?.addEventListener('change', function () {
    attachmentName.querySelector('span').textContent = this.files.length ? this.files[0].name : '';
    attachmentName.style.display = this.files.length ? 'flex' : 'none';
});
const chatMessage = document.getElementById('chatMessage');
chatMessage?.addEventListener('input', function () {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});
document.getElementById('chatForm')?.addEventListener('submit', function (event) { const hasText = document.getElementById('chatMessage').value.trim() !== ''; const hasFile = attachment && attachment.files.length > 0; if (!hasText && !hasFile) { event.preventDefault(); document.getElementById('chatMessage').focus(); } });
</script>

<?php endif; ?>

<script>
const createdInviteLink = document.getElementById('createdInviteLink');
if (createdInviteLink) createdInviteLink.value = new URL(createdInviteLink.dataset.path, window.location.href).href;
function copyInviteLink() {
    if (!createdInviteLink) return;
    navigator.clipboard.writeText(createdInviteLink.value).then(() => {
        const button = createdInviteLink.nextElementSibling;
        if (button) button.innerHTML = '<i class="bi bi-check-lg"></i> Copied';
    });
}
document.getElementById('inviteModal')?.addEventListener('click', function (event) {
    if (event.target === this) this.style.display = 'none';
});
</script>

<?php $pageTitle = $group['group_name'] . ' | Group';
$page_content = ob_get_clean(); require LSC_ROOT . '/views/layouts/app.php'; ?>
