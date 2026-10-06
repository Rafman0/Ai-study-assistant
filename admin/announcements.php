<?php
$page_title = 'Announcements';
$admin_active = 'announcements';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Auth.php';
Auth::require_admin();

$db = get_db_connection();
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $notice = ['type'=>'danger','text'=>'Invalid security token.'];
    } else {
        switch ($_POST['action']) {
            case 'create_announcement':
                $title = trim($_POST['title'] ?? '');
                $body = trim($_POST['body'] ?? '');
                $audience = $_POST['audience'] ?? 'all';
                $target = trim($_POST['target_value'] ?? '');
                $status = $_POST['status'] ?? 'published';
                $publish_at = $_POST['publish_at'] ?? null;
                if ($title === '' || $body === '') { $notice = ['type'=>'danger','text'=>'Title and body are required.']; break; }
                if (!in_array($audience, ['all','course','department'])) $audience = 'all';
                if (!in_array($status, ['draft','scheduled','published'])) $status = 'published';
                $admin_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
                $stmt = $db->prepare("INSERT INTO announcements (title, body, audience, target_value, status, publish_at, created_by) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$title, $body, $audience, $target ?: null, $status, $publish_at ?: null, $admin_id]);
                $notice = ['type'=>'success','text'=>'Announcement created.'];
                break;

            case 'update_announcement':
                $aid = intval($_POST['announcement_id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $body = trim($_POST['body'] ?? '');
                $audience = $_POST['audience'] ?? 'all';
                $target = trim($_POST['target_value'] ?? '');
                $status = $_POST['status'] ?? 'published';
                $publish_at = $_POST['publish_at'] ?? null;
                if ($aid && $title !== '' && $body !== '') {
                    $db->prepare("UPDATE announcements SET title=?, body=?, audience=?, target_value=?, status=?, publish_at=? WHERE id=?")->execute([$title, $body, $audience, $target ?: null, $status, $publish_at ?: null, $aid]);
                    $notice = ['type'=>'success','text'=>'Announcement updated.'];
                }
                break;

            case 'delete_announcement':
                $aid = intval($_POST['announcement_id'] ?? 0);
                if ($aid) { $db->prepare("DELETE FROM announcements WHERE id=?")->execute([$aid]); $notice = ['type'=>'success','text'=>'Announcement deleted.']; }
                break;
        }
    }
}

$announcements = $db->query("SELECT a.*, u.name as author_name FROM announcements a LEFT JOIN users u ON a.created_by=u.id ORDER BY a.created_at DESC")->fetchAll();

require_once __DIR__ . '/../views/admin/partials/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?php echo count($announcements); ?> announcements</span>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal"><i class="bi bi-plus-lg"></i> New Announcement</button>
</div>

<?php if (empty($announcements)): ?>
<div class="card"><div class="card-body text-center text-muted py-5">No announcements yet.</div></div>
<?php else: ?>
<?php foreach ($announcements as $a): ?>
<div class="card mb-3">
<div class="card-body">
    <div class="d-flex justify-content-between align-items-start mb-2">
        <div>
            <h6 class="mb-1" style="color:var(--color-primary-dark);font-weight:600"><?php echo htmlspecialchars($a['title']); ?></h6>
            <small class="text-muted">
                By <?php echo htmlspecialchars($a['author_name'] ?? 'Admin'); ?> · <?php echo date('M j, Y g:i A', strtotime($a['created_at'])); ?>
                · Audience: <strong><?php echo ucfirst($a['audience']); ?></strong>
                <?php if ($a['target_value']): ?> (<?php echo htmlspecialchars($a['target_value']); ?>)<?php endif; ?>
            </small>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge-<?php echo $a['status']; ?>"><?php echo ucfirst($a['status']); ?></span>
            <?php if ($a['publish_at']): ?><small class="text-muted"><?php echo date('M j, g:i A', strtotime($a['publish_at'])); ?></small><?php endif; ?>
        </div>
    </div>
    <p class="mb-2" style="font-size:var(--font-size-sm)"><?php echo nl2br(htmlspecialchars($a['body'])); ?></p>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('edit-aid').value='<?php echo $a['id']; ?>';document.getElementById('edit-a-title').value='<?php echo htmlspecialchars(addslashes($a['title'])); ?>';document.getElementById('edit-a-body').value='<?php echo htmlspecialchars(addslashes($a['body'])); ?>';document.getElementById('edit-a-audience').value='<?php echo $a['audience']; ?>';document.getElementById('edit-a-target').value='<?php echo htmlspecialchars(addslashes($a['target_value'] ?? '')); ?>';document.getElementById('edit-a-status').value='<?php echo $a['status']; ?>';document.getElementById('edit-a-pubat').value='<?php echo $a['publish_at'] ? date('Y-m-d\TH:i', strtotime($a['publish_at'])) : ''; ?>';new bootstrap.Modal(document.getElementById('editAnnouncementModal')).show()"><i class="bi bi-pencil"></i> Edit</button>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this announcement?')"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="delete_announcement"><input type="hidden" name="announcement_id" value="<?php echo $a['id']; ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i> Delete</button></form>
    </div>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Create Modal -->
<div class="modal fade" id="createAnnouncementModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">New Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="post"><div class="modal-body">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <input type="hidden" name="action" value="create_announcement">
    <div class="mb-3"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Body *</label><textarea name="body" class="form-control" rows="4" required></textarea></div>
    <div class="row g-2 mb-3">
        <div class="col-md-4"><label class="form-label">Audience</label><select name="audience" class="form-select"><option value="all">All Students</option><option value="course">Specific Course</option><option value="department">Department</option></select></div>
        <div class="col-md-4"><label class="form-label">Target</label><input type="text" name="target_value" class="form-control" placeholder="Course ID or dept name"></div>
        <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option value="published">Published</option><option value="draft">Draft</option><option value="scheduled">Scheduled</option></select></div>
    </div>
    <div class="mb-3"><label class="form-label">Publish At (optional — leave blank for immediate)</label><input type="datetime-local" name="publish_at" class="form-control"></div>
</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create</button></div></form>
</div></div></div>

<!-- Edit Modal -->
<div class="modal fade" id="editAnnouncementModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Edit Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="post"><div class="modal-body">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <input type="hidden" name="action" value="update_announcement">
    <input type="hidden" name="announcement_id" id="edit-aid">
    <div class="mb-3"><label class="form-label">Title *</label><input type="text" name="title" id="edit-a-title" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Body *</label><textarea name="body" id="edit-a-body" class="form-control" rows="4" required></textarea></div>
    <div class="row g-2 mb-3">
        <div class="col-md-4"><label class="form-label">Audience</label><select name="audience" id="edit-a-audience" class="form-select"><option value="all">All Students</option><option value="course">Specific Course</option><option value="department">Department</option></select></div>
        <div class="col-md-4"><label class="form-label">Target</label><input type="text" name="target_value" id="edit-a-target" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Status</label><select name="status" id="edit-a-status" class="form-select"><option value="published">Published</option><option value="draft">Draft</option><option value="scheduled">Scheduled</option></select></div>
    </div>
    <div class="mb-3"><label class="form-label">Publish At</label><input type="datetime-local" name="publish_at" id="edit-a-pubat" class="form-control"></div>
</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Changes</button></div></form>
</div></div></div>

<?php require_once __DIR__ . '/../views/admin/partials/footer.php'; ?>
