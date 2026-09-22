<?php // Variables are provided by GroupController::index(). ?>

<!-- ── PAGE HEADER ───────────────────────────────────────── -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:24px;flex-wrap:wrap;">
    <div>
        <div style="font-family:'Inter',system-ui,sans-serif;font-size:26px;font-weight:800;letter-spacing:-0.5px;">Study Groups</div>
        <div style="font-size:13px;color:var(--text-secondary);margin-top:4px;">
            Collaborate with peers, share resources and manage projects.
        </div>
    </div>
    <button onclick="document.getElementById('createModal').style.display='flex'" class="btn-primary">
        <i class="bi bi-plus-lg"></i> Create Group
    </button>
</div>

<!-- ── SUMMARY STATS ─────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:26px;">
    <div class="stat-card">
        <div class="stat-card-label">Joined Groups</div>
        <div class="stat-card-value"><?= count($myGroups) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Public Groups</div>
        <div class="stat-card-value"><?= count($publicGroups) ?></div>
    </div>
    <div class="stat-card" style="background:#eff6ff;border-color:#bfdbfe;cursor:pointer;"
         onclick="document.getElementById('createModal').style.display='flex'"
         onmouseover="this.style.borderColor='rgba(37,99,235,.5)'" onmouseout="this.style.borderColor='#bfdbfe'">
        <div class="stat-card-label" style="color:#1e40af;">
            <i class="bi bi-plus-circle-fill" style="margin-right:5px;"></i>Start a Group
        </div>
        <div style="font-size:13px;color:var(--brand-dark);margin-top:8px;font-weight:500;">
            Create a private space for your team →
        </div>
    </div>
</div>

<!-- ── MY GROUPS ─────────────────────────────────────────── -->
<div style="font-size:10.5px;font-weight:600;letter-spacing:.8px;text-transform:uppercase;
            color:var(--text-muted);margin-bottom:14px;">My Groups</div>

<?php if (!empty($myGroups)): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:14px;margin-bottom:28px;">
        <?php foreach ($myGroups as $g): ?>
        <a href="group.php?id=<?= $g['group_id'] ?>" style="
            background:var(--card);border:1px solid var(--border);border-radius:14px;
            padding:18px 20px;text-decoration:none;color:inherit;display:block;
            transition:border-color .15s,background .15s;
        " onmouseover="this.style.borderColor='rgba(37,99,235,.3)';this.style.background='#f8fbff'"
           onmouseout="this.style.borderColor='var(--border)';this.style.background='var(--card)'">

            <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
                <div style="width:44px;height:44px;border-radius:10px;background:var(--sidebar);
                            color:rgba(255,255,255,.7);display:flex;align-items:center;justify-content:center;
                            font-weight:700;font-size:15px;flex-shrink:0;">
                    <?= htmlspecialchars(strtoupper(mb_substr($g['group_name'], 0, 2))) ?>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:14px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?= htmlspecialchars($g['group_name']) ?>
                    </div>
                    <div style="font-size:11.5px;color:var(--text-muted);margin-top:2px;">
                        <?= $g['member_count'] ?> members ·
                        <span class="pill <?= $g['is_private'] ? 'pill-private':'pill-public' ?>" style="padding:1px 6px;">
                            <?= $g['is_private'] ? 'Private':'Public' ?>
                        </span>
                    </div>
                </div>
                <span class="pill pill-public" style="flex-shrink:0;">Member</span>
            </div>

            <div style="font-size:12.5px;color:var(--text-secondary);margin-bottom:12px;line-height:1.5;
                        overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
                <?= htmlspecialchars($g['description'] ?: 'No description available.') ?>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;">
                <div style="font-size:11.5px;color:var(--text-muted);">
                    <?= $g['member_count'] ?> members
                </div>
                <span style="font-size:12.5px;font-weight:600;color:var(--brand-dark);">Open →</span>
            </div>

        </a>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card" style="margin-bottom:28px;">
        <div class="empty-state">
            <i class="bi bi-people" style="font-size:30px;display:block;margin-bottom:10px;color:var(--text-muted);"></i>
            <p>No groups yet. Create your first study group or join an existing one.</p>
            <button onclick="document.getElementById('createModal').style.display='flex'"
                    class="btn-primary" style="margin-top:16px;">Create Group</button>
        </div>
    </div>
<?php endif; ?>

<!-- ── DISCOVER GROUPS ────────────────────────────────────── -->
<?php if (!empty($publicGroups)): ?>
<div style="font-size:10.5px;font-weight:600;letter-spacing:.8px;text-transform:uppercase;
            color:var(--text-muted);margin-bottom:14px;">Discover Public Groups</div>
<div class="card">
    <?php foreach ($publicGroups as $g): ?>
    <div style="display:flex;align-items:center;gap:14px;padding:14px 20px;
                border-bottom:1px solid var(--border-soft);transition:background .1s;"
         onmouseover="this.style.background='var(--cream)'" onmouseout="this.style.background=''">

        <div style="width:40px;height:40px;border-radius:8px;background:var(--sidebar);
                    color:rgba(255,255,255,.65);display:flex;align-items:center;justify-content:center;
                    font-weight:700;font-size:14px;flex-shrink:0;">
            <?= htmlspecialchars(strtoupper(mb_substr($g['group_name'], 0, 2))) ?>
        </div>

        <div style="flex:1;min-width:0;">
            <div style="font-size:13.5px;font-weight:600;"><?= htmlspecialchars($g['group_name']) ?></div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">
                <?= htmlspecialchars(mb_substr($g['description'] ?: 'No description.', 0, 80)) ?>
            </div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:3px;"><?= $g['member_count'] ?> members</div>
        </div>

        <?php if (!empty($g['pending_request_id'])): ?>
            <span class="pill pill-pending" style="flex-shrink:0;">Request Pending</span>
        <?php else: ?>
            <form method="POST" style="flex-shrink:0;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="request_join_group_id" value="<?= $g['group_id'] ?>">
                <button type="submit" style="
                    font-size:12px;font-weight:600;padding:7px 16px;border-radius:8px;
                    border:1px solid var(--brand-border);color:var(--brand-dark);
                    background:#eff6ff;cursor:pointer;font-family:'Inter',system-ui,sans-serif;
                    transition:background .15s;
                " onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'">
                    Request to Join
                </button>
            </form>
        <?php endif; ?>

    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── CREATE GROUP MODAL ─────────────────────────────────── -->
<div id="createModal" class="lsc-modal-bg" style="display:none;">
    <div class="lsc-modal">
        <div class="lsc-modal-head">
            <div class="lsc-modal-title">Create New Group</div>
            <div class="lsc-modal-sub">Start collaborating with other learners.</div>
        </div>
        <div class="lsc-modal-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

                <div style="margin-bottom:14px;">
                    <label class="lsc-label">Group Name</label>
                    <input type="text" name="group_name" required maxlength="100" class="lsc-input">
                </div>

                <div style="margin-bottom:14px;">
                    <label class="lsc-label">Description</label>
                    <textarea name="description" rows="3" class="lsc-input" style="resize:vertical;"></textarea>
                </div>

                <label style="display:flex;align-items:center;gap:10px;margin-bottom:6px;cursor:pointer;">
                    <input type="checkbox" name="is_private" style="width:15px;height:15px;accent-color:var(--brand);">
                    <span style="font-size:13.5px;font-weight:500;">Private Group</span>
                </label>

                <div class="lsc-modal-footer" style="padding:16px 0 0;border:none;">
                    <button type="button" onclick="document.getElementById('createModal').style.display='none'" class="btn-ghost">Cancel</button>
                    <button type="submit" name="create_group" class="btn-primary btn-brand">Create Group</button>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('createModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php $pageTitle = 'Study Groups | LSC';
$page_content = ob_get_clean(); require LSC_ROOT . '/views/layouts/app.php'; ?>
