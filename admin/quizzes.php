<?php
$page_title = 'Quiz Management';
$admin_active = 'quizzes';
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
            case 'delete_quiz':
                $qid = intval($_POST['quiz_id'] ?? 0);
                if ($qid) { $db->prepare("DELETE FROM quizzes WHERE id=?")->execute([$qid]); $notice = ['type'=>'success','text'=>'Quiz deleted.']; }
                break;
        }
    }
}

$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;
$total = (int)$db->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();

$quizzes = $db->prepare("SELECT q.*, u.name as owner_name, (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id=q.id) as q_count, (SELECT COUNT(*) FROM quiz_results qr WHERE qr.quiz_id=q.id) as attempt_count, (SELECT ROUND(AVG(qr.percentage),1) FROM quiz_results qr WHERE qr.quiz_id=q.id) as avg_score FROM quizzes q LEFT JOIN users u ON q.user_id=u.id ORDER BY q.created_at DESC LIMIT ? OFFSET ?");
$quizzes->execute([$per_page, $offset]);
$quizzes = $quizzes->fetchAll();
$total_pages = max(1, ceil($total / $per_page));

require_once __DIR__ . '/../views/admin/partials/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <span class="text-muted"><?php echo $total; ?> quizzes</span>
</div>

<div class="card"><div class="card-body p-0">
<div class="table-responsive">
<table class="admin-table mb-0">
<thead><tr><th>Title</th><th>Owner</th><th>Questions</th><th>Attempts</th><th>Avg Score</th><th>Created</th><th>Actions</th></tr></thead>
<tbody>
<?php if (empty($quizzes)): echo '<tr><td colspan="7" class="text-center text-muted py-4">No quizzes found.</td></tr>'; endif; ?>
<?php foreach ($quizzes as $q): ?>
<tr>
    <td><strong><?php echo htmlspecialchars($q['title']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars(mb_substr($q['description'] ?? '',0,50)); ?></small></td>
    <td><?php echo htmlspecialchars($q['owner_name'] ?? '—'); ?></td>
    <td><?php echo (int)$q['q_count']; ?></td>
    <td><?php echo (int)$q['attempt_count']; ?></td>
    <td><?php echo $q['avg_score'] !== null ? number_format($q['avg_score'],1).'%' : '—'; ?></td>
    <td class="text-nowrap"><?php echo date('M j, Y', strtotime($q['created_at'])); ?></td>
    <td class="text-nowrap">
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this quiz and all its questions/results?')"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="delete_quiz"><input type="hidden" name="quiz_id" value="<?php echo $q['id']; ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i> Delete</button></form>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div></div>

<?php if ($total_pages > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm justify-content-center">
    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
    <li class="page-item <?php echo $p===$page?'active':''; ?>"><a class="page-link" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a></li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../views/admin/partials/footer.php'; ?>
