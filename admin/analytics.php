<?php
$page_title = 'AI Learning Analytics';
$admin_active = 'analytics';
$admin_load_charts = true;
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Auth.php';
Auth::require_admin();

$db = get_db_connection();

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

$total_sessions = aq_col($db, "SELECT COUNT(*) FROM ai_tutor_questions");
$avg_level = aq_col($db, "SELECT ROUND(AVG(final_level),1) FROM ai_tutor_questions") ?? 0;
$revealed_count = aq_col($db, "SELECT COUNT(*) FROM ai_tutor_questions WHERE answer_revealed=1");
$solved_without = aq_col($db, "SELECT COUNT(*) FROM ai_tutor_questions WHERE answer_revealed=0 AND final_level<4");
$needing_reveal = aq_col($db, "SELECT COUNT(*) FROM ai_tutor_questions WHERE answer_revealed=1 OR final_level=4");
$active_students = aq_col($db, "SELECT COUNT(DISTINCT user_id) FROM ai_tutor_questions");

// Most requested subjects (by course)
$subjects = aq_all($db, "SELECT COALESCE(c.title, c.code, 'General') as subject, COUNT(*) as sessions, ROUND(AVG(atq.final_level),1) as avg_level, ROUND(100*SUM(CASE WHEN atq.answer_revealed=1 THEN 1 ELSE 0 END)/COUNT(*),1) as reveal_rate FROM ai_tutor_questions atq LEFT JOIN courses c ON atq.course_id=c.id GROUP BY c.id ORDER BY sessions DESC LIMIT 10");

// Students requiring the most hints
$heavy_users = aq_all($db, "SELECT u.id, u.name, u.email, COUNT(*) as sessions, ROUND(AVG(atq.final_level),1) as avg_level, SUM(atq.answer_revealed) as reveals FROM ai_tutor_questions atq JOIN users u ON atq.user_id=u.id GROUP BY u.id ORDER BY sessions DESC LIMIT 10");

// Hint usage per course
$course_hints = aq_all($db, "SELECT COALESCE(c.title, c.code, 'General') as course_name, atq.final_level, COUNT(*) as cnt FROM ai_tutor_questions atq LEFT JOIN courses c ON atq.course_id=c.id GROUP BY c.id, atq.final_level ORDER BY c.id, atq.final_level");

// Improvement trends: first vs most recent hint level per student
$improvement = aq_all($db, "SELECT u.name, MIN(atq.final_level) as first_level, MAX(atq.final_level) as latest_level, COUNT(*) as total, ROUND(AVG(atq.final_level),1) as avg FROM ai_tutor_questions atq JOIN users u ON atq.user_id=u.id GROUP BY u.id ORDER BY u.name LIMIT 10");

// AI usage trend (daily, 14 days)
$ai_trend = aq_all($db, "SELECT DATE(created_at) as d, COUNT(*) as c FROM ai_tutor_questions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) GROUP BY d ORDER BY d");

require_once __DIR__ . '/../views/admin/partials/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="admin-stat-card"><div class="admin-stat-value"><?php echo number_format($total_sessions); ?></div><div class="admin-stat-label">Total AI Sessions</div></div></div>
    <div class="col-6 col-lg-3"><div class="admin-stat-card" style="border-left-color:#8b5cf6"><div class="admin-stat-value"><?php echo $avg_level; ?></div><div class="admin-stat-label">Avg Hint Level</div></div></div>
    <div class="col-6 col-lg-3"><div class="admin-stat-card" style="border-left-color:#22c55e"><div class="admin-stat-value"><?php echo number_format($solved_without); ?></div><div class="admin-stat-label">Solved Without Answer</div></div></div>
    <div class="col-6 col-lg-3"><div class="admin-stat-card" style="border-left-color:#f59e0b"><div class="admin-stat-value"><?php echo number_format($needing_reveal); ?></div><div class="admin-stat-label">Required Final Answer</div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="admin-chart-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 style="margin:0">AI Usage Trend (14 days)</h6>
                <span class="text-muted small">Sessions per day</span>
            </div>
            <div class="ai-chart-wrap">
                <canvas id="aiTrendChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <h6 style="font-weight:600;color:var(--color-primary-dark);text-transform:uppercase;letter-spacing:0.04em;font-size:var(--font-size-xs);margin-bottom:var(--spacing-md)">Improvement Trends</h6>
            <?php if (empty($improvement)): echo '<p class="text-muted small">Not enough data yet.</p>'; endif; ?>
            <?php foreach ($improvement as $imp): ?>
            <div class="d-flex justify-content-between align-items-center py-1" style="border-bottom:1px solid var(--color-gray-100)">
                <span class="small"><?php echo htmlspecialchars($imp['name']); ?></span>
                <span>
                    <small class="text-muted">Lvl <?php echo (int)$imp['first_level']; ?> →</small>
                    <strong style="color:<?php echo $imp['latest_level'] < $imp['first_level'] ? '#22c55e' : ($imp['latest_level'] > $imp['first_level'] ? '#ef4444' : '#64748b'); ?>"><?php echo (int)$imp['latest_level']; ?></strong>
                    <small class="text-muted">(avg <?php echo $imp['avg']; ?>)</small>
                </span>
            </div>
            <?php endforeach; ?>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card"><div class="card-body">
            <h6 style="font-weight:600;color:var(--color-primary-dark);text-transform:uppercase;letter-spacing:0.04em;font-size:var(--font-size-xs);margin-bottom:var(--spacing-md)">Most Requested Subjects</h6>
            <?php if (empty($subjects)): echo '<p class="text-muted small">No data yet.</p>'; endif; ?>
            <table class="admin-table"><thead><tr><th>Subject</th><th>Sessions</th><th>Avg Level</th><th>Reveal Rate</th></tr></thead><tbody>
            <?php foreach ($subjects as $s): ?>
            <tr><td><strong><?php echo htmlspecialchars($s['subject']); ?></strong></td><td><?php echo (int)$s['sessions']; ?></td><td><?php echo $s['avg_level']; ?></td><td><?php echo $s['reveal_rate']; ?>%</td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-body">
            <h6 style="font-weight:600;color:var(--color-primary-dark);text-transform:uppercase;letter-spacing:0.04em;font-size:var(--font-size-xs);margin-bottom:var(--spacing-md)">Students Requiring Most Hints</h6>
            <?php if (empty($heavy_users)): echo '<p class="text-muted small">No data yet.</p>'; endif; ?>
            <table class="admin-table"><thead><tr><th>Student</th><th>Sessions</th><th>Avg Level</th><th>Reveals</th></tr></thead><tbody>
            <?php foreach ($heavy_users as $u): ?>
            <tr>
                <td><a href="<?php echo app_url('admin/user_view.php?id='.$u['id']); ?>" class="text-decoration-none" style="color:var(--color-primary-dark)"><strong><?php echo htmlspecialchars($u['name']); ?></strong></a></td>
                <td><?php echo (int)$u['sessions']; ?></td>
                <td><?php echo $u['avg_level']; ?></td>
                <td><?php echo (int)$u['reveals']; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody></table>
        </div></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
    var labels=<?php echo json_encode(array_map(function($r){return date('M j',strtotime($r['d']));},$ai_trend)); ?>;
    var data=<?php echo json_encode(array_map(function($r){return (int)$r['c'];},$ai_trend)); ?>;
    new Chart(document.getElementById('aiTrendChart'),{type:'line',data:{labels:labels,datasets:[{label:'AI Sessions',data:data,borderColor:'#8b5cf6',backgroundColor:'rgba(139,92,246,0.1)',fill:true,tension:0.3,pointRadius:3,pointHoverRadius:5,borderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:false},tooltip:{backgroundColor:'rgba(15,23,42,0.9)',padding:10,cornerRadius:8,titleFont:{weight:'600'}}},scales:{y:{beginAtZero:true,grid:{color:'rgba(148,163,184,0.15)'},ticks:{stepSize:1,precision:0,font:{size:11}}},x:{grid:{display:false},ticks:{maxTicksLimit:7,autoSkip:true,maxRotation:0,minRotation:0,font:{size:11}}}}}});
});
</script>

<?php require_once __DIR__ . '/../views/admin/partials/footer.php'; ?>
