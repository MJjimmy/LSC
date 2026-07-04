<?php
// Variables are provided by FileController::index().

// icon map by extension
function fileIcon(string $ext): array {
    return match($ext) {
        'pdf'  => ['bi-file-earmark-pdf-fill',  '#c0392b'],
        'docx' => ['bi-file-earmark-text-fill',  '#1a4a80'],
        'pptx' => ['bi-file-earmark-ppt-fill',   '#c05020'],
        'xlsx' => ['bi-file-earmark-excel-fill',  '#1a7a40'],
        'png','jpg','jpeg' => ['bi-file-earmark-image-fill', '#1a6040'],
        default => ['bi-file-earmark-fill', '#6b5d50'],
    };
}
?>

<!-- ── PAGE HEADER ───────────────────────────────────────── -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:24px;flex-wrap:wrap;">
    <div>
        <div style="font-family:'Playfair Display',serif;font-size:26px;font-weight:800;letter-spacing:-0.5px;">File Library</div>
        <div style="font-size:13px;color:var(--text-secondary);margin-top:4px;">
            Share study materials, notes and resources with your group.
        </div>
    </div>
    <button onclick="document.getElementById('uploadModal').style.display='flex'" class="btn-primary">
        <i class="bi bi-upload"></i> Upload File
    </button>
</div>

<!-- ── STATS ──────────────────────────────────────────────── -->
<?php if ($fileFlash): ?>
<div style="margin-bottom:18px;padding:12px 14px;border-radius:9px;font-size:13px;
            <?= $fileFlash['type'] === 'success'
                ? 'background:var(--green-bg);border:1px solid var(--green-border);color:var(--green);'
                : 'background:var(--red-bg);border:1px solid var(--red-border);color:var(--red);' ?>">
    <?= htmlspecialchars($fileFlash['message']) ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px;">
    <div class="stat-card">
        <div class="stat-card-label">Total Files</div>
        <div class="stat-card-value"><?= count($files) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Current Group</div>
        <div style="font-size:15px;font-weight:600;margin-top:6px;color:var(--text-primary);
                    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            <?= htmlspecialchars($currentGroupName) ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card-label">Storage Used</div>
        <div class="stat-card-value"><?= $totalSize ?> <span style="font-size:16px;font-family:'DM Sans',sans-serif;font-weight:500;">MB</span></div>
    </div>
</div>

<!-- ── GROUP SELECTOR ────────────────────────────────────── -->
<div class="card" style="padding:16px 20px;margin-bottom:18px;">
    <label class="ss-label">Select Study Group</label>
    <select class="ss-input" style="width:auto;max-width:360px;"
            onchange="window.location.href='files.php?group_id='+this.value">
        <?php foreach ($myGroups as $g): ?>
            <option value="<?= $g['group_id'] ?>" <?= $selectedGroup==$g['group_id'] ? 'selected':'' ?>>
                <?= htmlspecialchars($g['group_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<!-- ── FILES TABLE ───────────────────────────────────────── -->
<div class="card">
    <?php if (count($files) > 0): ?>
    <div style="overflow-x:auto;">
        <table class="ss-table">
            <thead>
                <tr>
                    <th>File</th>
                    <th>Uploaded By</th>
                    <th>Size</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($files as $f):
                [$iconClass, $iconColor] = fileIcon(strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION)));
            ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <i class="bi <?= $iconClass ?>" style="font-size:22px;color:<?= $iconColor ?>;flex-shrink:0;"></i>
                            <div>
                                <a href="download.php?id=<?= (int)$f['file_id'] ?>"
                                   style="font-weight:600;color:var(--text-primary);text-decoration:none;font-size:13.5px;"
                                   onmouseover="this.style.color='var(--copper-dark)'"
                                   onmouseout="this.style.color='var(--text-primary)'">
                                    <?= htmlspecialchars($f['file_name']) ?>
                                </a>
                                <div style="font-size:11px;color:var(--text-muted);margin-top:1px;">
                                    <?= strtoupper(pathinfo($f['file_name'], PATHINFO_EXTENSION)) ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td style="color:var(--text-secondary);"><?= htmlspecialchars($f['uploaded_by_name']) ?></td>
                    <td style="color:var(--text-muted);"><?= round($f['file_size'] / 1024) ?> KB</td>
                    <td style="color:var(--text-muted);"><?= date('d M Y', strtotime($f['uploaded_at'])) ?></td>
                    <td style="text-align:right;">
                        <?php if ($f['uploaded_by'] == $_SESSION['user_id']): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this file?')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                            <input type="hidden" name="delete_file_id" value="<?= $f['file_id'] ?>">
                            <input type="hidden" name="group_id"       value="<?= $selectedGroup ?>">
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
        <div class="empty-state">
            <i class="bi bi-folder2-open" style="font-size:30px;display:block;margin-bottom:10px;color:var(--text-muted);"></i>
            <p>No files uploaded yet. Upload your first study resource to get started.</p>
        </div>
    <?php endif; ?>
</div>

<!-- ── UPLOAD MODAL ──────────────────────────────────────── -->
<div id="uploadModal" class="ss-modal-bg" style="display:none;">
    <div class="ss-modal">
        <div class="ss-modal-head">
            <div class="ss-modal-title">Upload File</div>
            <div class="ss-modal-sub">Share resources with your study group.</div>
        </div>
        <div class="ss-modal-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

                <div style="margin-bottom:14px;">
                    <label class="ss-label">Group</label>
                    <select name="group_id" required class="ss-input">
                        <?php foreach ($myGroups as $g): ?>
                            <option value="<?= $g['group_id'] ?>" <?= $selectedGroup==$g['group_id'] ? 'selected':'' ?>>
                                <?= htmlspecialchars($g['group_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom:10px;">
                    <label class="ss-label">Choose File</label>
                    <input type="file" name="file" required
                           accept=".pdf,.docx,.pptx,.xlsx,.txt,.csv,.zip,.png,.jpg,.jpeg,.gif,.webp"
                           class="ss-input">
                </div>

                <div style="background:var(--cream);border-radius:8px;padding:10px 14px;
                            font-size:12px;color:var(--text-muted);margin-bottom:4px;">
                    Max 100 MB &middot; Documents, spreadsheets, archives and images
                </div>

                <div class="ss-modal-footer" style="padding:16px 0 0;border:none;">
                    <button type="button" onclick="document.getElementById('uploadModal').style.display='none'" class="btn-ghost">Cancel</button>
                    <button type="submit" class="btn-primary btn-copper"><i class="bi bi-upload"></i> Upload</button>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('uploadModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php $page_content = ob_get_clean(); require STUDYSYNC_ROOT . '/views/layouts/app.php'; ?>
