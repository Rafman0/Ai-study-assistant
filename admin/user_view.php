<?php
$page_title = 'Student Profile';
$admin_active = 'users';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Auth.php';
Auth::require_admin();

$db = get_db_connection();
$uid = intval($_GET['id'] ?? 0);
if (!$uid) { redirect(app_url('admin/users.php')); }

function aq($db, $sql, $p = []) {
    try { $stmt = $db->prepare($sql); $stmt->execute($p); return $stmt->fetch(); }
    catch (Exception $e) { return false; }
}
function aq_col($db, $sql, $p = []) {
    try { $stmt = $db->prepare($sql); $stmt->execute($p); return $stmt->fetchColumn(); }
    catch (Exception $e) { return 0; }
}
function aq_all($db, $sql, $p = []) {
    try { $stmt = $db->prepare($sql); $stmt->execute($p); return $stmt->fetchAll(); }
    catch (Exception $e) { return []; }
}

$user = aq($db, "SELECT * FROM users WHERE id=? AND role='user' LIMIT 1", [$uid]);
if (!$user) { redirect(app_url('admin/users.php')); }
$page_title = htmlspecialchars($user['name']) . ' — Profile';

$stats = [
    'ai_sessions'  => aq_col($db, "SELECT COUNT(*) FROM ai_tutor_questions WHERE user_id=?", [$uid]),
    'avg_level'    => aq_col($db, "SELECT ROUND(AVG(final_level),1) FROM ai_tutor_questions WHERE user_id=?", [$uid]),
    'revealed'     => aq_col($db, "SELECT COUNT(*) FROM ai_tutor_questions WHERE user_id=? AND answer_revealed=1", [$uid]),
    'notes'        => aq_col($db, "SELECT COUNT(*) FROM notes WHERE user_id=?", [$uid]),
    'summaries'    => aq_col($db, "SELECT COUNT(*) FROM summaries WHERE user_id=?", [$uid]),
    'quizzes_taken'=> aq_col($db, "SELECT COUNT(*) FROM quiz_results WHERE user_id=?", [$uid]),
    'avg_quiz'     => aq_col($db, "SELECT ROUND(AVG(percentage),1) FROM quiz_results WHERE user_id=?", [$uid]),
    'flashcards'   => aq_col($db, "SELECT COUNT(*) FROM flashcards WHERE user_id=?", [$uid]),
    'study_time'   => aq_col($db, "SELECT COALESCE(SUM(duration_minutes),0) FROM study_sessions WHERE user_id=?", [$uid]),
    'achievements' => aq_col($db, "SELECT COUNT(*) FROM user_achievements WHERE user_id=?", [$uid]),
];

$recent_quizzes = aq_all($db, "SELECT q.title, qr.score, qr.total_questions, qr.percentage, qr.completed_at FROM quiz_results qr JOIN quizzes q ON qr.quiz_id=q.id WHERE qr.user_id=? ORDER BY qr.completed_at DESC LIMIT 5", [$uid]);
$recent_ai = aq_all($db, "SELECT question, final_level, answer_revealed, created_at FROM ai_tutor_questions WHERE user_id=? ORDER BY created_at DESC LIMIT 5", [$uid]);
$recent_notes = aq_all($db, "SELECT title, created_at FROM notes WHERE user_id=? ORDER BY created_at DESC LIMIT 5", [$uid]);

require_once __DIR__ . '/../views/admin/partials/header.php';
?>

<div class="mb-4">
    <a href="<?php echo app_url('admin/users.php'); ?>" class="text-decoration-none" style="color:var(--color-primary-dark);font-size:var(--font-size-sm)"><i class="bi bi-arrow-left"></i> Back to Users</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card"><div class="card-body text-center py-4">
            <div style="width:64px;height:64px;border-radius:50%;background:var(--color-primary-dark);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:700;margin-bottom:var(--spacing-md)"><?php echo strtoupper(substr($user['name'],0,1)); ?></div>
            <h5 style="color:var(--color-primary-dark);margin-bottom:4px"><?php echo htmlspecialchars($user['name']); ?></h5>
            <p class="text-muted small mb-2"><?php echo htmlspecialchars($user['email']); ?></p>
            <div class="d-flex justify-content-center gap-2 mb-2">
                <span class="<?php echo ($user['status'] ?? 'active')==='suspended'?'badge-suspended':'badge-active'; ?>"><?php echo ucfirst($user['status'] ?? 'active'); ?></span>
                <?php if ($user['department']): ?><span class="badge bg-light text-dark"><?php echo htmlspecialchars($user['department']); ?></span><?php endif; ?>
            </div>
            <p class="text-muted small mb-0">Registered <?php echo date('M j, Y', strtotime($user['created_at'])); ?></p>
            <p class="text-muted small">Last login: <?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?></p>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="row g-3">
            <div class="col-6 col-md-4"><div class="admin-stat-card"><div class="admin-stat-value"><?php echo number_format($stats['ai_sessions']); ?></div><div class="admin-stat-label">AI Sessions</div></div></div>
            <div class="col-6 col-md-4"><div class="admin-stat-card" style="border-left-color:#8b5cf6"><div class="admin-stat-value"><?php echo $stats['avg_level'] ?? '—'; ?></div><div class="admin-stat-label">Avg Hint Level</div></div></div>
            <div class="col-6 col-md-4"><div class="admin-stat-card" style="border-left-color:#f59e0b"><div class="admin-stat-value"><?php echo number_format($stats['quizzes_taken']); ?></div><div class="admin-stat-label">Quizzes Taken</div></div></div>
            <div class="col-6 col-md-4"><div class="admin-stat-card" style="border-left-color:#22c55e"><div class="admin-stat-value"><?php echo number_format($stats['notes']); ?></div><div class="admin-stat-label">Notes Created</div></div></div>
            <div class="col-6 col-md-4"><div class="admin-stat-card" style="border-left-color:#06b6d4"><div class="admin-stat-value"><?php echo number_format($stats['summaries']); ?></div><div class="admin-stat-label">Summaries</div></div></div>
            <div class="col-6 col-md-4"><div class="admin-stat-card" style="border-left-color:#ec4899"><div class="admin-stat-value"><?php echo number_format($stats['study_time']); ?>m</div><div class="admin-stat-label">Study Time</div></div></div>
            <div class="col-6 col-md-4"><div class="admin-stat-card" style="border-left-color:#14b8a6"><div class="admin-stat-value"><?php echo number_format($stats['flashcards']); ?></div><div class="admin-stat-label">Flashcards</div></div></div>
            <div class="col-6 col-md-4"><div class="admin-stat-card" style="border-left-color:#a855f7"><div class="admin-stat-value"><?php echo number_format($stats['achievements']); ?></div><div class="admin-stat-label">Achievements</div></div></div>
            <div class="col-6 col-md-4"><div class="admin-stat-card" style="border-left-color:#0ea5e9"><div class="admin-stat-value"><?php echo $stats['avg_quiz'] ? $stats['avg_quiz'].'%' : '—'; ?></div><div class="admin-stat-label">Avg Quiz Score</div></div></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <h6 style="font-weight:600;color:var(--color-primary-dark);text-transform:uppercase;letter-spacing:0.04em;font-size:var(--font-size-xs);margin-bottom:var(--spacing-md)">Recent AI Tutor Sessions</h6>
            <?php if (empty($recent_ai)): echo '<p class="text-muted small">None yet.</p>'; endif; ?>
            <?php foreach ($recent_ai as $a): ?>
            <div class="admin-activity-item">
                <div class="admin-activity-icon ai"><i class="bi bi-robot"></i></div>
                <div class="admin-activity-text">Level <?php echo (int)$a['final_level']; ?> | <?php echo $a['answer_revealed']?'<span class="text-danger">Revealed</span>':'<span class="text-success">Self-solved</span>'; ?><br><small class="text-muted"><?php echo htmlspecialchars(mb_substr($a['question'],0,60)); ?></small></div>
                <div class="admin-activity-time"><?php echo date('M j', strtotime($a['created_at'])); ?></div>
            </div>
            <?php endforeach; ?>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <h6 style="font-weight:600;color:var(--color-primary-dark);text-transform:uppercase;letter-spacing:0.04em;font-size:var(--font-size-xs);margin-bottom:var(--spacing-md)">Recent Quizzes</h6>
            <?php if (empty($recent_quizzes)): echo '<p class="text-muted small">None yet.</p>'; endif; ?>
            <?php foreach ($recent_quizzes as $q): ?>
            <div class="admin-activity-item">
                <div class="admin-activity-icon quiz"><i class="bi bi-check2-circle"></i></div>
                <div class="admin-activity-text"><strong><?php echo htmlspecialchars($q['title']); ?></strong><br><small class="text-muted"><?php echo (int)$q['score']; ?>/<?php echo (int)$q['total_questions']; ?> (<?php echo number_format($q['percentage'],1); ?>%)</small></div>
                <div class="admin-activity-time"><?php echo date('M j', strtotime($q['completed_at'])); ?></div>
            </div>
            <?php endforeach; ?>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <h6 style="font-weight:600;color:var(--color-primary-dark);text-transform:uppercase;letter-spacing:0.04em;font-size:var(--font-size-xs);margin-bottom:var(--spacing-md)">Recent Notes</h6>
            <?php if (empty($recent_notes)): echo '<p class="text-muted small">None yet.</p>'; endif; ?>
            <?php foreach ($recent_notes as $n): ?>
            <div class="admin-activity-item">
                <div class="admin-activity-icon summary"><i class="bi bi-journal-text"></i></div>
                <div class="admin-activity-text"><strong><?php echo htmlspecialchars($n['title']); ?></strong></div>
                <div class="admin-activity-time"><?php echo date('M j', strtotime($n['created_at'])); ?></div>
            </div>
            <?php endforeach; ?>
        </div></div>
    </div>
</div>

<?php require_once __DIR__ . '/../views/admin/partials/footer.php'; ?>
