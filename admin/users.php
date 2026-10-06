<?php
$page_title = 'User Management';
$admin_active = 'users';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Auth.php';
Auth::require_admin();

$db = get_db_connection();
$notice = null;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) { $notice = ['type'=>'danger','text'=>'Invalid security token. Please reload and try again.']; }
    else {
        $uid = intval($_POST['user_id'] ?? 0);
        switch ($action) {
            case 'toggle_status':
                if ($uid) {
                    $stmt = $db->prepare("SELECT id, name, status FROM users WHERE id=? AND role='user' LIMIT 1");
                    $stmt->execute([$uid]); $u = $stmt->fetch();
                    if ($u) {
                        $new = $u['status'] === 'active' ? 'suspended' : 'active';
                        $db->prepare("UPDATE users SET status=? WHERE id=?")->execute([$new, $uid]);
                        $notice = ['type'=>'success','text'=>$u['name'].' is now '.$new.'.'];
                    }
                }
                break;
            case 'delete_user':
                if ($uid) {
                    $stmt = $db->prepare("SELECT id, name FROM users WHERE id=? AND role='user' LIMIT 1");
                    $stmt->execute([$uid]); $u = $stmt->fetch();
                    if ($u) {
                        $db->prepare("DELETE FROM users WHERE id=? AND role='user'")->execute([$uid]);
                        $notice = ['type'=>'success','text'=>$u['name'].' has been deleted.'];
                    }
                }
                break;
        }
    }
}

// Filters
$search = trim($_GET['q'] ?? '');
$filter_status = $_GET['status'] ?? '';
$filter_dept   = trim($_GET['dept'] ?? '');

$where = ["role='user'"];
$params = [];
if ($search !== '') { $where[] = "(name LIKE ? OR email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filter_status !== '' && in_array($filter_status, ['active','suspended'])) { $where[] = "status=?"; $params[] = $filter_status; }
if ($filter_dept !== '') { $where[] = "department LIKE ?"; $params[] = "%$filter_dept%"; }
$wsql = implode(' AND ', $where);

$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;
$total = 0;

$users = [];
if ($db) {
    try {
        $total = (int)$db->prepare("SELECT COUNT(*) FROM users WHERE $wsql")->execute($params) ? $db->prepare("SELECT COUNT(*) FROM users WHERE $wsql") : null;
        if ($total) { $total->execute($params); $total = $total->fetchColumn(); }
        else { $total = 0; }
        $params2 = array_merge($params, [$per_page, $offset]);
        $cols = "u.id, u.name, u.email, u.status, u.department, u.created_at, u.last_login,
                 (SELECT COUNT(*) FROM ai_tutor_questions atq WHERE atq.user_id=u.id) as ai_count,
                 (SELECT COUNT(*) FROM notes n WHERE n.user_id=u.id) as note_count,
                 (SELECT COUNT(*) FROM summaries s WHERE s.user_id=u.id) as summary_count";
        $users = $db->prepare("SELECT $cols FROM users u WHERE $wsql ORDER BY u.created_at DESC LIMIT ? OFFSET ?");
        $users->execute($params2); $users = $users->fetchAll();
    } catch (Exception $e) { error_log('Admin users error: '.$e->getMessage()); }
}
$total_pages = max(1, ceil($total / $per_page));

require_once __DIR__ . '/../views/admin/partials/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div><span class="text-muted">Showing <?php echo count($users); ?> of <?php echo $total; ?> users</span></div>
    <form method="get" class="d-flex flex-wrap gap-2">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search name or email..." value="<?php echo htmlspecialchars($search); ?>" style="min-width:200px">
        <select name="status" class="form-select form-select-sm" style="width:130px">
            <option value="">All Status</option>
            <option value="active" <?php echo $filter_status==='active'?'selected':''; ?>>Active</option>
            <option value="suspended" <?php echo $filter_status==='suspended'?'selected':''; ?>>Suspended</option>
        </select>
        <input type="text" name="dept" class="form-control form-control-sm" placeholder="Department..." value="<?php echo htmlspecialchars($filter_dept); ?>" style="width:130px">
        <button class="btn btn-sm btn-dark">Filter</button>
    </form>
</div>

<div class="card"><div class="card-body p-0">
<div class="table-responsive">
<table class="admin-table mb-0">
<thead>
    <tr><th>User</th><th>Status</th><th>Department</th><th>Registered</th><th>Last Login</th><th>AI</th><th>Notes</th><th>Summaries</th><th>Actions</th></tr>
</thead>
<tbody>
<?php if (empty($users)): ?>
<tr><td colspan="9" class="text-center text-muted py-4">No users found.</td></tr>
<?php endif; ?>
<?php foreach ($users as $u): ?>
<tr>
    <td><a href="<?php echo app_url('admin/user_view.php?id='.$u['id']); ?>" class="fw-semibold text-decoration-none" style="color:var(--color-primary-dark)"><?php echo htmlspecialchars($u['name']); ?></a><br><small class="text-muted"><?php echo htmlspecialchars($u['email']); ?></small></td>
    <td><span class="<?php echo $u['status']==='suspended'?'badge-suspended':'badge-active'; ?>"><?php echo ucfirst($u['status'] ?? 'active'); ?></span></td>
    <td><?php echo htmlspecialchars($u['department'] ?? '—'); ?></td>
    <td class="text-nowrap"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
    <td class="text-nowrap"><?php echo $u['last_login'] ? date('M j, g:i A', strtotime($u['last_login'])) : '<span class="text-muted">Never</span>'; ?></td>
    <td><?php echo (int)$u['ai_count']; ?></td>
    <td><?php echo (int)$u['note_count']; ?></td>
    <td><?php echo (int)$u['summary_count']; ?></td>
    <td class="text-nowrap">
        <a href="<?php echo app_url('admin/user_view.php?id='.$u['id']); ?>" class="btn btn-sm btn-outline-secondary me-1" title="View"><i class="bi bi-eye"></i></a>
        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure?')">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
            <button class="btn btn-sm btn-outline-<?php echo $u['status']==='suspended'?'success':'warning'; ?>" title="<?php echo $u['status']==='suspended'?'Reactivate':'Suspend'; ?>">
                <i class="bi bi-<?php echo $u['status']==='suspended'?'play-circle':'pause-circle'; ?>"></i>
            </button>
        </form>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this user and all their data?')">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash3"></i></button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div></div>

<?php if ($total_pages > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm justify-content-center mb-0">
    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
    <li class="page-item <?php echo $p===$page?'active':''; ?>"><a class="page-link" href="?page=<?php echo $p; ?>&q=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>&dept=<?php echo urlencode($filter_dept); ?>"><?php echo $p; ?></a></li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../views/admin/partials/footer.php'; ?>
