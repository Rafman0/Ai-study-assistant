<?php
$page_title = 'Course Management';
$admin_active = 'courses';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Auth.php';
Auth::require_admin();

$db = get_db_connection();
$notice = null;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $notice = ['type'=>'danger','text'=>'Invalid security token.'];
    } else {
        switch ($_POST['action']) {
            case 'create_course':
                $title = trim($_POST['title'] ?? '');
                $code = trim($_POST['code'] ?? '');
                $instructor = trim($_POST['instructor'] ?? '');
                $desc = trim($_POST['description'] ?? '');
                if ($title === '') { $notice = ['type'=>'danger','text'=>'Title is required.']; break; }
                $stmt = $db->prepare("INSERT INTO courses (title, code, instructor, description, user_id) VALUES (?,?,?,?,0)");
                $stmt->execute([$title, $code ?: null, $instructor ?: null, $desc]);
                $notice = ['type'=>'success','text'=>'Course "'.$title.'" created.'];
                break;

            case 'update_course':
                $cid = intval($_POST['course_id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $code = trim($_POST['code'] ?? '');
                $instructor = trim($_POST['instructor'] ?? '');
                $desc = trim($_POST['description'] ?? '');
                if ($cid && $title !== '') {
                    $db->prepare("UPDATE courses SET title=?, code=?, instructor=?, description=? WHERE id=?")->execute([$title, $code ?: null, $instructor ?: null, $desc, $cid]);
                    $notice = ['type'=>'success','text'=>'Course updated.'];
                }
                break;

            case 'delete_course':
                $cid = intval($_POST['course_id'] ?? 0);
                if ($cid) {
                    $db->prepare("DELETE FROM courses WHERE id=?")->execute([$cid]);
                    $notice = ['type'=>'success','text'=>'Course deleted.'];
                }
                break;

            case 'upload_material':
                $cid = intval($_POST['course_id'] ?? 0);
                if (empty($_FILES['material_pdf']) || $_FILES['material_pdf']['error'] !== UPLOAD_ERR_OK) {
                    $notice = ['type'=>'danger','text'=>'Upload failed.']; break;
                }
                $f = $_FILES['material_pdf'];
                if ($f['size'] > PDF_UPLOAD_MAX_SIZE) { $notice = ['type'=>'danger','text'=>'File too large (max '.round(PDF_UPLOAD_MAX_SIZE/1048576).'MB).']; break; }
                $head = file_get_contents($f['tmp_name'], false, null, 0, 8);
                if ($head === false || substr($head, 0, 5) !== '%PDF-') { $notice = ['type'=>'danger','text'=>'Only PDF files are accepted.']; break; }
                $dir = PDF_UPLOAD_DIR . DIRECTORY_SEPARATOR . 'materials';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $stored = bin2hex(random_bytes(16)) . '.pdf';
                $dest = $dir . DIRECTORY_SEPARATOR . $stored;
                if (!move_uploaded_file($f['tmp_name'], $dest)) { $notice = ['type'=>'danger','text'=>'Could not save file.']; break; }
                $name = strip_tags(trim(basename($f['name'])));
                $db->prepare("INSERT INTO course_materials (course_id, original_name, stored_name, file_size, uploaded_by) VALUES (?,?,?,?,?)")
                    ->execute([$cid, $name, $stored, $f['size'], get_current_user_id()]);
                $notice = ['type'=>'success','text'=>'Material "'.$name.'" uploaded.'];
                break;

            case 'delete_material':
                $mid = intval($_POST['material_id'] ?? 0);
                if ($mid) {
                    $stmt = $db->prepare("SELECT stored_name FROM course_materials WHERE id=? LIMIT 1");
                    $stmt->execute([$mid]); $mat = $stmt->fetch();
                    if ($mat) {
                        $path = PDF_UPLOAD_DIR . DIRECTORY_SEPARATOR . 'materials' . DIRECTORY_SEPARATOR . $mat['stored_name'];
                        if (is_file($path)) @unlink($path);
                        $db->prepare("DELETE FROM course_materials WHERE id=?")->execute([$mid]);
                    }
                    $notice = ['type'=>'success','text'=>'Material deleted.'];
                }
                break;
        }
    }
}

$search = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;
$where = '1=1'; $params = [];
if ($search !== '') { $where = "(title LIKE ? OR code LIKE ? OR instructor LIKE ?)"; $params = ["%$search%", "%$search%", "%$search%"]; }

$total = $db->prepare("SELECT COUNT(*) FROM courses WHERE $where"); $total->execute($params); $total = (int)$total->fetchColumn();
$courses = $db->prepare("SELECT c.*, (SELECT COUNT(*) FROM course_materials cm WHERE cm.course_id=c.id) as mat_count FROM courses c WHERE $where ORDER BY c.created_at DESC LIMIT ? OFFSET ?");
$params2 = array_merge($params, [$per_page, $offset]);
$courses->execute($params2); $courses = $courses->fetchAll();
$total_pages = max(1, ceil($total / $per_page));

require_once __DIR__ . '/../views/admin/partials/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div><span class="text-muted"><?php echo $total; ?> courses</span></div>
    <div class="d-flex gap-2">
        <form method="get" class="d-flex gap-2">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search courses..." value="<?php echo htmlspecialchars($search); ?>" style="min-width:180px">
            <button class="btn btn-sm btn-dark">Search</button>
        </form>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createCourseModal"><i class="bi bi-plus-lg"></i> New Course</button>
    </div>
</div>

<div class="card"><div class="card-body p-0">
<div class="table-responsive">
<table class="admin-table mb-0">
<thead><tr><th>Title</th><th>Code</th><th>Instructor</th><th>Materials</th><th>Created</th><th>Actions</th></tr></thead>
<tbody>
<?php if (empty($courses)): echo '<tr><td colspan="6" class="text-center text-muted py-4">No courses found.</td></tr>'; endif; ?>
<?php foreach ($courses as $c): ?>
<tr>
    <td><strong><?php echo htmlspecialchars($c['title']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars(mb_substr($c['description'] ?? '',0,60)); ?></small></td>
    <td><?php echo htmlspecialchars($c['code'] ?? '—'); ?></td>
    <td><?php echo htmlspecialchars($c['instructor'] ?? '—'); ?></td>
    <td><?php echo (int)$c['mat_count']; ?></td>
    <td class="text-nowrap"><?php echo date('M j, Y', strtotime($c['created_at'])); ?></td>
    <td class="text-nowrap">
        <button class="btn btn-sm btn-outline-secondary me-1" title="Edit" onclick="document.getElementById('edit-id').value='<?php echo $c['id']; ?>';document.getElementById('edit-title').value='<?php echo htmlspecialchars(addslashes($c['title'])); ?>';document.getElementById('edit-code').value='<?php echo htmlspecialchars(addslashes($c['code'] ?? '')); ?>';document.getElementById('edit-instructor').value='<?php echo htmlspecialchars(addslashes($c['instructor'] ?? '')); ?>';document.getElementById('edit-desc').value='<?php echo htmlspecialchars(addslashes($c['description'] ?? '')); ?>';new bootstrap.Modal(document.getElementById('editCourseModal')).show()"><i class="bi bi-pencil"></i></button>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete course and its materials?')"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="delete_course"><input type="hidden" name="course_id" value="<?php echo $c['id']; ?>"><button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash3"></i></button></form>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div></div>

<!-- Create Course Modal -->
<div class="modal fade" id="createCourseModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">New Course</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="post"><div class="modal-body">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <input type="hidden" name="action" value="create_course">
    <div class="mb-3"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" required></div>
    <div class="row g-2 mb-3"><div class="col"><label class="form-label">Course Code</label><input type="text" name="code" class="form-control" placeholder="CS101"></div><div class="col"><label class="form-label">Instructor</label><input type="text" name="instructor" class="form-control"></div></div>
    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create Course</button></div></form>
</div></div></div>

<!-- Edit Course Modal -->
<div class="modal fade" id="editCourseModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Edit Course</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="post"><div class="modal-body">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <input type="hidden" name="action" value="update_course">
    <input type="hidden" name="course_id" id="edit-id">
    <div class="mb-3"><label class="form-label">Title *</label><input type="text" name="title" id="edit-title" class="form-control" required></div>
    <div class="row g-2 mb-3"><div class="col"><label class="form-label">Code</label><input type="text" name="code" id="edit-code" class="form-control"></div><div class="col"><label class="form-label">Instructor</label><input type="text" name="instructor" id="edit-instructor" class="form-control"></div></div>
    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" id="edit-desc" class="form-control" rows="3"></textarea></div>
</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Changes</button></div></form>
</div></div></div>

<!-- Course Materials section -->
<h5 style="color:var(--color-primary-dark);margin:var(--spacing-xl) 0 var(--spacing-md);font-weight:600">Upload Material to a Course</h5>
<div class="card mb-4"><div class="card-body">
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="action" value="upload_material">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Select Course *</label>
                <select name="course_id" class="form-select" required>
                    <option value="">Choose...</option>
                    <?php foreach ($courses as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">PDF File *</label>
                <input type="file" name="material_pdf" class="form-control" accept="application/pdf,.pdf" required>
            </div>
            <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-upload"></i> Upload</button></div>
        </div>
    </form>
</div></div>

<h6 style="font-weight:600;color:var(--color-primary-dark);margin-bottom:var(--spacing-md)">Uploaded Materials</h6>
<?php
$all_mat = $db->prepare("SELECT cm.*, c.title as course_title FROM course_materials cm JOIN courses c ON cm.course_id=c.id ORDER BY cm.created_at DESC LIMIT 20");
$all_mat->execute(); $all_mat = $all_mat->fetchAll();
if (empty($all_mat)): echo '<p class="text-muted small">No materials uploaded yet.</p>'; endif;
?>
<div class="card"><div class="card-body p-0">
<table class="admin-table mb-0"><thead><tr><th>File</th><th>Course</th><th>Size</th><th>Uploaded</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($all_mat as $m): ?>
<tr>
    <td><i class="bi bi-file-earmark-pdf text-danger"></i> <?php echo htmlspecialchars($m['original_name']); ?></td>
    <td><?php echo htmlspecialchars($m['course_title']); ?></td>
    <td><?php echo round($m['file_size']/1024).'KB'; ?></td>
    <td><?php echo date('M j, Y', strtotime($m['created_at'])); ?></td>
    <td><form method="post" class="d-inline" onsubmit="return confirm('Delete this material?')"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="delete_material"><input type="hidden" name="material_id" value="<?php echo $m['id']; ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i></button></form></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>

<?php require_once __DIR__ . '/../views/admin/partials/footer.php'; ?>
