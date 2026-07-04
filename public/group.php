<?php
ob_start();
require_once __DIR__ . '/config/db_helpers.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/csrf.php';
require_once __DIR__ . '/config/uploads.php';
requireLogin();

$groupId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$groupId) { ob_end_clean(); header('Location: groups.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM study_groups WHERE group_id = ?");
$stmt->execute([$groupId]);
$group = $stmt->fetch();

if (!$group) { ob_end_clean(); header('Location: groups.php'); exit; }

$canManageGroup =
    $_SESSION['user_role'] === 'admin' ||
    isGroupCreator($groupId, $_SESSION['user_id']);

$isMember = isGroupMember($groupId, $_SESSION['user_id']);

if (!$isMember && !$canManageGroup) { ob_end_clean(); header('Location: groups.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['delete_group']) && $canManageGroup) {
        deleteGroup($groupId);

        header('Location: groups.php');
        exit;
    }

    if (isset($_POST['approve_request_id']) && $canManageGroup) {
        approveJoinRequest((int)$_POST['approve_request_id'], $_SESSION['user_id']);
        header("Location: group.php?id=$groupId&tab=members");
        exit;
    }

    if (isset($_POST['reject_request_id']) && $canManageGroup) {
        rejectJoinRequest((int)$_POST['reject_request_id'], $_SESSION['user_id']);
        header("Location: group.php?id=$groupId&tab=members");
        exit;
    }

    if (isset($_POST['remove_member_user_id']) && $canManageGroup) {
        removeGroupMember($groupId, (int)$_POST['remove_member_user_id'], $_SESSION['user_id']);
        header("Location: group.php?id=$groupId&tab=members");
        exit;
    }

    if (isset($_POST['invite_email']) && $canManageGroup) {
        $inviteResult = createGroupInvitation($groupId, $_SESSION['user_id'], (string)$_POST['invite_email']);
        if ($inviteResult['ok']) {
            $_SESSION['group_flash'] = [
                'type' => 'success',
                'message' => $inviteResult['registered']
                    ? 'Invitation created and added to the user\'s notifications.'
                    : 'Invitation created. Copy the link below and share it securely.',
                'invite_path' => 'invite.php?token=' . $inviteResult['token'],
            ];
        } else {
            $_SESSION['group_flash'] = ['type' => 'error', 'message' => $inviteResult['error']];
        }
        header("Location: group.php?id=$groupId&tab=members");
        exit;
    }

    if (isset($_POST['send_message']) && $isMember) {
        $msg = trim($_POST['message'] ?? '');
        $attachmentFileId = null;

        if (isset($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $stored = storeUploadedFile($_FILES['attachment'], 'group-files');
            if ($stored['ok']) {
                $attachmentFileId = uploadFile(
                    $groupId,
                    $_SESSION['user_id'],
                    $stored['file_name'],
                    $stored['file_path'],
                    $stored['file_type'],
                    $stored['file_size']
                );
                if (!$attachmentFileId) {
                    removeStoredUpload($stored['file_path']);
                    $_SESSION['group_flash'] = ['type' => 'error', 'message' => 'The attachment could not be saved.'];
                }
            } else {
                $_SESSION['group_flash'] = ['type' => 'error', 'message' => $stored['error']];
            }
        }

        if (($msg !== '' || $attachmentFileId) && !sendMessage($groupId, $_SESSION['user_id'], $msg, $attachmentFileId ?: null)) {
            $_SESSION['group_flash'] = ['type' => 'error', 'message' => 'The message could not be sent.'];
        }
        header("Location: group.php?id=$groupId&tab=chat");
        exit;
    }
}

$groupFlash = $_SESSION['group_flash'] ?? null;
unset($_SESSION['group_flash']);

$tab      = $_GET['tab'] ?? 'tasks';
$members  = getGroupMembers($groupId);
$tasks    = getGroupTasks($groupId);
$files    = getGroupFiles($groupId);
$messages = getMessages($groupId);
$pendingJoinRequests = $canManageGroup ? getPendingJoinRequests($groupId) : [];

$tabs = [
    'tasks'   => ['icon'=>'bi-check2-square',    'label'=>'Tasks'],
    'members' => ['icon'=>'bi-people-fill',       'label'=>'Members'],
    'files'   => ['icon'=>'bi-folder-fill',       'label'=>'Files'],
    'chat'    => ['icon'=>'bi-chat-dots-fill',    'label'=>'Chat'],
];
?>

<!-- ── HERO ───────────────────────────────────────────────── -->
<div class="page-hero" style="margin-bottom:24px;">
    <div class="page-hero-eyebrow">Study Group</div>
    <div class="page-hero-title"><?= htmlspecialchars($group['group_name']) ?></div>
    <div class="page-hero-sub">
        <?= htmlspecialchars($group['description'] ?? 'Collaborate, share resources and achieve academic success together.') ?>
    </div>

<?php if ($canManageGroup): ?>
<form method="POST"
      onsubmit="return confirm('Delete this group permanently?')"
      style="margin-top:15px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <button type="submit"
            name="delete_group"
            style="
                padding:8px 16px;
                border:none;
                border-radius:8px;
                background:#dc3545;
                color:white;
                font-weight:600;
                cursor:pointer;">
        Delete Group
    </button>
</form>
<?php endif; ?> 
</div>

<!-- ── TABS ──────────────────────────────────────────────── -->
<?php if ($groupFlash): ?>
<div style="margin-bottom:18px;padding:13px 15px;border-radius:10px;
            <?= $groupFlash['type'] === 'success'
                ? 'background:var(--green-bg);border:1px solid var(--green-border);color:var(--green);'
                : 'background:var(--red-bg);border:1px solid var(--red-border);color:var(--red);' ?>">
    <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($groupFlash['message']) ?></div>
    <?php if (!empty($groupFlash['invite_path'])): ?>
    <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;">
        <input id="createdInviteLink" class="ss-input" style="flex:1;min-width:220px;" readonly
               data-path="<?= htmlspecialchars($groupFlash['invite_path']) ?>">
        <button type="button" class="btn-primary btn-copper" onclick="copyInviteLink()">
            <i class="bi bi-copy"></i> Copy link
        </button>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div style="display:flex;gap:4px;margin-bottom:20px;background:var(--card);
            border:1px solid var(--border);border-radius:10px;padding:5px;">
    <?php foreach ($tabs as $key => $t): ?>
    <a href="?id=<?= $groupId ?>&tab=<?= $key ?>" style="
        flex:1;display:flex;align-items:center;justify-content:center;gap:7px;
        padding:9px 14px;border-radius:7px;font-size:13px;font-weight:500;
        text-decoration:none;transition:background .15s,color .15s;
        <?= $tab === $key
            ? 'background:var(--sidebar);color:#fff;'
            : 'color:var(--text-secondary);' ?>
    " onmouseover="<?= $tab !== $key ? "this.style.background='var(--cream)'" : '' ?>"
       onmouseout="<?= $tab !== $key ? "this.style.background=''" : '' ?>">
        <i class="bi <?= $t['icon'] ?>" style="font-size:14px;"></i>
        <?= $t['label'] ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- ── TAB CONTENT: TASKS ─────────────────────────────────── -->
<?php if ($tab === 'tasks'): ?>

<div class="card">
    <?php if (count($tasks) > 0): ?>
    <div style="overflow-x:auto;">
        <table class="ss-table">
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
                                    cursor:pointer;font-family:'DM Sans',sans-serif;">Mark Done</button>
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
    <button type="button" class="btn-primary btn-copper" onclick="document.getElementById('inviteModal').style.display='flex'">
        <i class="bi bi-person-plus-fill"></i> Invite people
    </button>
    <?php endif; ?>
</div>

<?php if ($canManageGroup && !empty($pendingJoinRequests)): ?>
<div class="card" style="margin-bottom:16px;">
    <div class="panel-header">
        <div>
            <div class="panel-title">Join Requests</div>
            <div class="panel-sub">Review students who want to join this group.</div>
        </div>
        <span class="pill pill-pending"><?= count($pendingJoinRequests) ?> pending</span>
    </div>

    <?php foreach ($pendingJoinRequests as $request): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:13px 20px;
                border-bottom:1px solid var(--border-soft);">
        <div style="width:36px;height:36px;border-radius:50%;background:var(--copper-dim);
                    border:1px solid var(--copper-border);display:flex;align-items:center;
                    justify-content:center;font-weight:700;font-size:13px;color:var(--copper);flex-shrink:0;">
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
                        cursor:pointer;font-family:'DM Sans',sans-serif;">Approve</button>
            </form>
            <form method="POST" action="?id=<?= $groupId ?>&tab=members">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="reject_request_id" value="<?= $request['request_id'] ?>">
                <button type="submit" style="padding:6px 12px;border-radius:7px;border:1px solid var(--red-border);
                        background:var(--red-bg);color:var(--red);font-size:12px;font-weight:600;
                        cursor:pointer;font-family:'DM Sans',sans-serif;">Decline</button>
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
        <div style="width:36px;height:36px;border-radius:50%;background:var(--copper-dim);
                    border:1px solid var(--copper-border);display:flex;align-items:center;
                    justify-content:center;font-weight:700;font-size:13px;color:var(--copper);flex-shrink:0;">
            <?= strtoupper(substr($m['name'], 0, 1)) ?>
        </div>
        <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:center;gap:8px;justify-content:space-between;flex-wrap:wrap;">
                <a href="profile.php?user_id=<?= (int)$m['user_id'] ?>"
                   style="font-size:13.5px;font-weight:600;color:var(--text-primary);text-decoration:none;">
                    <?= htmlspecialchars($m['name']) ?>
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
                                cursor:pointer;font-family:'DM Sans',sans-serif;">Remove</button>
                    </form>
                <?php endif; ?>
            </div>
            <div style="font-size:11.5px;color:var(--text-muted);margin-top:2px;">
                <?= ucfirst($m['role']) ?> · Joined <?= date('d M Y', strtotime($m['joined_at'])) ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── TAB CONTENT: FILES ────────────────────────────────── -->
<?php if ($canManageGroup): ?>
<div id="inviteModal" class="ss-modal-bg" style="display:none;">
    <div class="ss-modal">
        <div class="ss-modal-head">
            <div class="ss-modal-title">Invite people</div>
            <div class="ss-modal-sub">Create a secure, seven-day invitation for this group.</div>
        </div>
        <div class="ss-modal-body">
            <form method="POST" action="?id=<?= $groupId ?>&tab=members">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <label class="ss-label" for="inviteEmail">Email address</label>
                <input id="inviteEmail" type="email" name="invite_email" class="ss-input"
                       placeholder="student@example.com" autocomplete="email" required>
                <div style="font-size:12px;color:var(--text-muted);margin-top:9px;line-height:1.5;">
                    Registered users also receive an in-app notification. For everyone else, copy and share the generated link.
                </div>
                <div class="ss-modal-footer" style="padding:18px 0 0;border:none;">
                    <button type="button" class="btn-ghost" onclick="document.getElementById('inviteModal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn-primary btn-copper"><i class="bi bi-send-fill"></i> Create invite</button>
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
        <table class="ss-table">
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
                           style="color:var(--copper-dark);font-weight:600;text-decoration:none;font-size:13.5px;"
                           onmouseover="this.style.color='var(--copper)'"
                           onmouseout="this.style.color='var(--copper-dark)'">
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
                                    cursor:pointer;font-family:'DM Sans',sans-serif;">Delete</button>
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
.chat-layout{display:grid;grid-template-columns:minmax(0,1fr) 270px;gap:16px;align-items:start}.chat-message{display:flex;gap:10px;margin-bottom:18px}.chat-avatar{width:34px;height:34px;border-radius:50%;background:var(--sidebar);color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;overflow:hidden;flex-shrink:0}.chat-avatar img{width:100%;height:100%;object-fit:cover}.chat-attachment{margin-top:8px;border:1px solid var(--border);border-radius:10px;background:#fff;overflow:hidden;max-width:360px}.chat-attachment img{display:block;width:100%;max-height:260px;object-fit:cover;background:var(--cream)}.chat-file-link{display:flex;align-items:center;gap:10px;padding:10px 12px;color:var(--text-primary);text-decoration:none}.chat-file-link:hover{background:#fdf8f2}.chat-composer{display:flex;gap:9px;align-items:center}.chat-plus{width:42px;height:42px;border-radius:50%;border:1px solid var(--copper-border);background:var(--copper-dim);color:var(--copper);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;flex-shrink:0}.chat-plus:hover{background:rgba(205,133,63,.2)}@media(max-width:900px){.chat-layout{grid-template-columns:1fr}.chat-files-panel{order:-1}.chat-files-list{display:flex;overflow-x:auto}.chat-files-list>a{min-width:220px}}@media(max-width:600px){.chat-composer{align-items:flex-end}.chat-composer .btn-primary span{display:none}.chat-attachment{max-width:100%}}
</style>

<div class="chat-layout">
    <div class="card" style="overflow:hidden;">
        <div id="chatMessagesNew" style="height:470px;overflow-y:auto;padding:20px;background:var(--cream);">
            <?php if (empty($messages)): ?>
                <div style="text-align:center;padding:70px 20px;color:var(--text-muted);font-size:13px;">
                    <i class="bi bi-chat-heart" style="display:block;font-size:28px;margin-bottom:8px;"></i>
                    No messages yet. Start the conversation.
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg):
                    $isImage = !empty($msg['file_type']) && in_array(strtolower($msg['file_type']), ['png','jpg','jpeg','gif','webp'], true);
                ?>
                <div class="chat-message">
                    <a class="chat-avatar" href="profile.php?user_id=<?= (int)$msg['user_id'] ?>" aria-label="View profile">
                        <?php if (!empty($msg['user_avatar'])): ?><img src="<?= htmlspecialchars($msg['user_avatar']) ?>" alt=""><?php else: ?><?= strtoupper(substr($msg['user_name'], 0, 1)) ?><?php endif; ?>
                    </a>
                    <div style="min-width:0;flex:1;">
                        <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:3px;">
                            <a href="profile.php?user_id=<?= (int)$msg['user_id'] ?>" style="font-weight:600;font-size:13.5px;color:var(--text-primary);text-decoration:none;"><?= htmlspecialchars($msg['user_name']) ?></a>
                            <span style="font-size:11px;color:var(--text-muted);"><?= date('H:i', strtotime($msg['created_at'])) ?></span>
                        </div>
                        <?php if ($msg['message'] !== ''): ?><div style="font-size:13.5px;color:var(--text-secondary);line-height:1.55;word-break:break-word;"><?= nl2br(htmlspecialchars($msg['message'])) ?></div><?php endif; ?>
                        <?php if (!empty($msg['file_id'])): ?>
                        <div class="chat-attachment">
                            <?php if ($isImage): ?><a href="download.php?id=<?= (int)$msg['file_id'] ?>"><img src="download.php?id=<?= (int)$msg['file_id'] ?>&preview=1" alt="<?= htmlspecialchars($msg['file_name']) ?>"></a><?php endif; ?>
                            <a class="chat-file-link" href="download.php?id=<?= (int)$msg['file_id'] ?>">
                                <i class="bi <?= $isImage ? 'bi-file-earmark-image-fill' : 'bi-file-earmark-arrow-down-fill' ?>" style="font-size:22px;color:var(--copper);"></i>
                                <span style="min-width:0;flex:1;"><strong style="font-size:12.5px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($msg['file_name']) ?></strong><small style="color:var(--text-muted);"><?= strtoupper($msg['file_type']) ?> &middot; <?= max(1, round($msg['file_size'] / 1024)) ?> KB</small></span>
                                <i class="bi bi-download" aria-hidden="true"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="padding:12px 14px;border-top:1px solid var(--border);background:#fff;">
            <form id="chatForm" method="POST" enctype="multipart/form-data" class="chat-composer">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="send_message" value="1">
                <input id="chatAttachment" type="file" name="attachment" hidden accept=".pdf,.docx,.pptx,.xlsx,.txt,.csv,.zip,.png,.jpg,.jpeg,.gif,.webp">
                <label for="chatAttachment" class="chat-plus" title="Add a picture or file" aria-label="Add a picture or file"><i class="bi bi-plus-lg"></i></label>
                <div style="flex:1;min-width:0;">
                    <input id="chatMessage" type="text" name="message" placeholder="Type a message..." class="ss-input" style="width:100%;">
                    <div id="attachmentName" style="display:none;font-size:11px;color:var(--copper-dark);margin:5px 3px 0;"></div>
                </div>
                <button type="submit" class="btn-primary btn-copper" style="min-height:42px;"><i class="bi bi-send-fill"></i> <span>Send</span></button>
            </form>
        </div>
    </div>

    <aside class="card chat-files-panel" style="overflow:hidden;">
        <div class="panel-header" style="padding:15px 16px;">
            <div><div class="panel-title" style="font-size:14px;">Chat files</div><div class="panel-sub">Shared with this group</div></div>
            <a href="files.php?group_id=<?= $groupId ?>" title="Open file library" style="color:var(--copper);"><i class="bi bi-box-arrow-up-right"></i></a>
        </div>
        <div class="chat-files-list">
            <?php if (empty($files)): ?>
                <div style="padding:22px 16px;color:var(--text-muted);font-size:12px;text-align:center;">Attachments will appear here.</div>
            <?php else: ?>
                <?php foreach (array_slice($files, 0, 8) as $file): ?>
                <a href="download.php?id=<?= (int)$file['file_id'] ?>" style="display:flex;gap:10px;padding:12px 15px;border-bottom:1px solid var(--border-soft);text-decoration:none;color:inherit;">
                    <i class="bi bi-file-earmark-fill" style="color:var(--copper);font-size:18px;"></i>
                    <span style="min-width:0;"><strong style="font-size:12px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($file['file_name']) ?></strong><small style="color:var(--text-muted);"><?= htmlspecialchars($file['uploaded_by_name']) ?> &middot; <?= max(1, round($file['file_size'] / 1024)) ?> KB</small></span>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>
</div>

<script>
const chatNew = document.getElementById('chatMessagesNew');
if (chatNew) chatNew.scrollTop = chatNew.scrollHeight;
const attachment = document.getElementById('chatAttachment');
const attachmentName = document.getElementById('attachmentName');
attachment?.addEventListener('change', function () { attachmentName.textContent = this.files.length ? 'Attached: ' + this.files[0].name : ''; attachmentName.style.display = this.files.length ? 'block' : 'none'; });
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

<?php $page_content = ob_get_clean(); require 'layout.php'; ?>
