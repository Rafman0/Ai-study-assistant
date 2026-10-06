<?php
$page_title = 'Flashcard Management';
$admin_active = 'flashcards';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Auth.php';
Auth::require_admin();

$db = get_db_connection();
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $notice = ['type'=>'danger','text'=>'Invalid security token.'];
    } else {
        $fid = intval($_POST['flashcard_id'] ?? 0);
        switch ($_POST['action']) {
            case 'delete_card':
                if ($fid) { $db->prepare("DELETE FROM flashcards WHERE id=?")->execute([$fid]); $notice = ['type'=>'success','text'=>'Flashcard deleted.']; }
                break;
        }
    }
}

$search = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = '1=1'; $params = [];
if ($search !== '') { $where = "(f.question LIKE ? OR f.answer LIKE ?)"; $params = ["%$search%", "%$search%"]; }

$total = $db->prepare("SELECT COUNT(*) FROM flashcards f WHERE $where"); $total->execute($params); $total = (int)$total->fetchColumn();

$cards = $db->prepare("SELECT f.*, u.name as owner_name, c.title as course_title FROM flashcards f LEFT JOIN users u ON f.user_id=u.id LEFT JOIN courses c ON f.course_id=c.id WHERE $where ORDER BY f.created_at DESC LIMIT ? OFFSET ?");
$params2 = array_merge($params, [$per_page, $offset]);
$cards->execute($params2);
$cards = $cards->fetchAll();
$total_pages = max(1, ceil($total / $per_page));

$grouped = [];
foreach ($cards as $fc) {
    $key = ($fc['owner_name'] ?? 'Unknown') . ' — ' . ($fc['course_title'] ?? 'No course');
    $grouped[$key][] = $fc;
}

require_once __DIR__ . '/../views/admin/partials/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <span class="text-muted"><?php echo $total; ?> flashcards</span>
    <form method="get" class="d-flex gap-2">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search flashcards..." value="<?php echo htmlspecialchars($search); ?>" style="min-width:200px">
        <button class="btn btn-sm btn-dark">Search</button>
    </form>
</div>

<?php if (empty($cards)): ?>
<div class="card"><div class="card-body text-center text-muted py-5">No flashcards found.</div></div>
<?php else: ?>
<?php foreach ($grouped as $group => $items): ?>
<h6 style="font-weight:600;color:var(--color-primary-dark);margin:var(--spacing-lg) 0 var(--spacing-sm);font-size:var(--font-size-sm)"><i class="bi bi-collection"></i> <?php echo htmlspecialchars($group); ?> (<?php echo count($items); ?>)</h6>
<div class="card mb-3"><div class="card-body p-0">
<table class="admin-table mb-0">
<thead><tr><th style="width:45%">Question</th><th style="width:45%">Answer</th><th style="width:10%">Actions</th></tr></thead>
<tbody>
<?php foreach ($items as $fc): ?>
<tr>
    <td class="text-break"><?php echo htmlspecialchars(mb_substr($fc['question'], 0, 120)); ?></td>
    <td class="text-break"><?php echo htmlspecialchars(mb_substr($fc['answer'], 0, 120)); ?></td>
    <td class="text-nowrap">
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this flashcard?')"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="action" value="delete_card"><input type="hidden" name="flashcard_id" value="<?php echo $fc['id']; ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i></button></form>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($total_pages > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm justify-content-center">
    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
    <li class="page-item <?php echo $p===$page?'active':''; ?>"><a class="page-link" href="?page=<?php echo $p; ?>&q=<?php echo urlencode($search); ?>"><?php echo $p; ?></a></li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../views/admin/partials/footer.php'; ?>
