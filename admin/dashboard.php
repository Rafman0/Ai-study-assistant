<?php
$page_title = 'Admin Dashboard';
$admin_active = 'dashboard';
require_once __DIR__ . '/../views/admin/partials/header.php';
require_once __DIR__ . '/../utils/Auth.php';

$db = get_db_connection();

function aq($db, $sql, $p = []) {
    try { $stmt = $db->prepare($sql); $stmt->execute($p); return $stmt->fetchColumn(); }
    catch (Exception $e) { return 0; }
}

function aq_all($db, $sql, $p = []) {
    try { $stmt = $db->prepare($sql); $stmt->execute($p); return $stmt->fetchAll(); }
    catch (Exception $e) { return []; }
}

$stats = [
    'students'    => aq($db, "SELECT COUNT(*) FROM users WHERE role='user'"),
    'active_today'=> aq($db, "SELECT COUNT(*) FROM users WHERE role='user' AND DATE(last_login) = CURDATE()"),
    'courses'     => aq($db, "SELECT COUNT(*) FROM courses"),
    'quizzes'     => aq($db, "SELECT COUNT(*) FROM quizzes"),
    'flashcards'  => aq($db, "SELECT COUNT(*) FROM flashcards"),
    'ai_sessions' => aq($db, "SELECT COUNT(*) FROM ai_tutor_questions"),
    'summaries'   => aq($db, "SELECT COUNT(*) FROM summaries"),
    'notes'       => aq($db, "SELECT COUNT(*) FROM notes"),
];

$recent_reg   = aq_all($db, "SELECT id, name, email, created_at FROM users WHERE role='user' ORDER BY created_at DESC LIMIT 5");
$recent_logins= aq_all($db, "SELECT id, name, email, last_login FROM users WHERE role='user' AND last_login IS NOT NULL ORDER BY last_login DESC LIMIT 5");
$recent_ai    = aq_all($db, "SELECT atq.id, u.name, LEFT(atq.question,80) as question, atq.final_level, atq.answer_revealed, atq.created_at FROM ai_tutor_questions atq JOIN users u ON atq.user_id=u.id ORDER BY atq.created_at DESC LIMIT 5");
$recent_quiz  = aq_all($db, "SELECT qr.id, u.name, q.title, qr.score, qr.total_questions, qr.percentage, qr.completed_at FROM quiz_results qr JOIN users u ON qr.user_id=u.id JOIN quizzes q ON qr.quiz_id=q.id ORDER BY qr.completed_at DESC LIMIT 5");
$recent_summary = aq_all($db, "SELECT s.id, u.name, s.source_type, s.source_name, s.created_at FROM summaries s JOIN users u ON s.user_id=u.id ORDER BY s.created_at DESC LIMIT 5");

$top_courses  = aq_all($db, "SELECT c.id, c.title, c.code, (SELECT COUNT(*) FROM study_sessions ss WHERE ss.course_id=c.id) as sessions, (SELECT COUNT(*) FROM notes n WHERE n.course_id=c.id) as note_count, (SELECT COUNT(*) FROM flashcards f WHERE f.course_id=c.id) as fc_count FROM courses c ORDER BY (sessions+note_count+fc_count) DESC LIMIT 5");

// Activity log (last 10 diverse events)
$activity_log = aq_all($db, "SELECT al.*, u.name FROM activity_log al LEFT JOIN users u ON al.user_id=u.id ORDER BY al.created_at DESC LIMIT 10");

// Chart data: user registrations per day (last 14 days)
$reg_chart = aq_all($db, "SELECT DATE(created_at) as d, COUNT(*) as c FROM users WHERE role='user' AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) GROUP BY d ORDER BY d");

// Chart data: AI sessions per day (last 14 days)
$ai_chart = aq_all($db, "SELECT DATE(created_at) as d, COUNT(*) as c FROM ai_tutor_questions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) GROUP BY d ORDER BY d");

// Chart data: hint level distribution
$hint_dist = aq_all($db, "SELECT final_level, COUNT(*) as c FROM ai_tutor_questions GROUP BY final_level ORDER BY final_level");

$admin_load_charts = true;
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="admin-stat-card"><div class="admin-stat-value"><?php echo number_format($stats['students']); ?></div><div class="admin-stat-label">Total Students</div></div></div>
    <div class="col-6 col-lg-3"><div class="admin-stat-card" style="border-left-color:#22c55e"><div class="admin-stat-value"><?php echo number_format($stats['active_today']); ?></div><div class="admin-stat-label">Active Today</div></div></div>
    <div class="col-6 col-lg-3"><div class="admin-stat-card" style="border-left-color:#3b82f6"><div class="admin-stat-value"><?php echo number_format($stats['courses']); ?></div><div class="admin-stat-label">Total Courses</div></div></div>
    <div class="col-6 col-lg-3"><div class="admin-stat-card" style="border-left-color:#8b5cf6"><div class="admin-stat-value"><?php echo number_format($stats['quizzes']); ?></div><div class="admin-stat-label">Quizzes Created</div></div></div>
    <div class="col-6 col-lg-3"><div class="admin-stat-card" style="border-left-color:#f59e0b"><div class="admin-stat-value"><?php echo number_format($stats['flashcards']); ?></div><div class="admin-stat-label">Flashcard Sets</div></div></div>
    <div class="col-6 col-lg-3"><div class="admin-stat-card" style="border-left-color:#ec4899"><div class="admin-stat-value"><?php echo number_format($stats['ai_sessions']); ?></div><div class="admin-stat-label">AI Tutoring Sessions</div></div></div>
    <div class="col-6 col-lg-3"><div class="admin-stat-card" style="border-left-color:#06b6d4"><div class="admin-stat-value"><?php echo number_format($stats['summaries']); ?></div><div class="admin-stat-label">Course Summaries</div></div></div>
    <div class="col-6 col-lg-3"><div class="admin-stat-card" style="border-left-color:#14b8a6"><div class="admin-stat-value"><?php echo number_format($stats['notes']); ?></div><div class="admin-stat-label">Notes Created</div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="admin-chart-card">
            <h6>Student Registrations (14 days)</h6>
            <canvas id="regChart" height="180"></canvas>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="admin-chart-card">
            <h6>AI Tutor Sessions (14 days)</h6>
            <canvas id="aiChart" height="180"></canvas>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="admin-chart-card">
            <h6>Hint Level Distribution</h6>
            <canvas id="hintChart" height="180"></canvas>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h6 style="font-weight:600;color:var(--color-primary-dark);text-transform:uppercase;letter-spacing:0.04em;font-size:var(--font-size-xs);margin-bottom:var(--spacing-md);">Most Active Courses</h6>
                <?php if (empty($top_courses)): ?>
                    <p class="text-muted small">No course activity yet.</p>
                <?php else: ?>
                <table class="admin-table">
                    <thead><tr><th>Course</th><th>Sessions</th><th>Notes</th><th>Flashcards</th></tr></thead>
                    <tbody>
                    <?php foreach ($top_courses as $c): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($c['title']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($c['code'] ?? ''); ?></small></td>
                        <td><?php echo (int)$c['sessions']; ?></td>
                        <td><?php echo (int)$c['note_count']; ?></td>
                        <td><?php echo (int)$c['fc_count']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <h6 style="font-weight:600;color:var(--color-primary-dark);text-transform:uppercase;letter-spacing:0.04em;font-size:var(--font-size-xs);margin-bottom:var(--spacing-md);">Recent Registrations</h6>
            <?php foreach ($recent_reg as $r): ?>
            <div class="admin-activity-item">
                <div class="admin-activity-icon register"><i class="bi bi-person-plus"></i></div>
                <div class="admin-activity-text"><strong><?php echo htmlspecialchars($r['name']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($r['email']); ?></small></div>
                <div class="admin-activity-time"><?php echo date('M j', strtotime($r['created_at'])); ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recent_reg)) echo '<p class="text-muted small">None yet.</p>'; ?>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <h6 style="font-weight:600;color:var(--color-primary-dark);text-transform:uppercase;letter-spacing:0.04em;font-size:var(--font-size-xs);margin-bottom:var(--spacing-md);">Recent Logins</h6>
            <?php foreach ($recent_logins as $l): ?>
            <div class="admin-activity-item">
                <div class="admin-activity-icon login"><i class="bi bi-box-arrow-in-right"></i></div>
                <div class="admin-activity-text"><strong><?php echo htmlspecialchars($l['name']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($l['email']); ?></small></div>
                <div class="admin-activity-time"><?php echo $l['last_login'] ? date('M j g:i A', strtotime($l['last_login'])) : '—'; ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recent_logins)) echo '<p class="text-muted small">No logins yet.</p>'; ?>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <h6 style="font-weight:600;color:var(--color-primary-dark);text-transform:uppercase;letter-spacing:0.04em;font-size:var(--font-size-xs);margin-bottom:var(--spacing-md);">Recent AI Tutor Sessions</h6>
            <?php foreach ($recent_ai as $a): ?>
            <div class="admin-activity-item">
                <div class="admin-activity-icon ai"><i class="bi bi-robot"></i></div>
                <div class="admin-activity-text"><strong><?php echo htmlspecialchars($a['name']); ?></strong> — Level <?php echo (int)$a['final_level']; ?> / Revealed: <?php echo $a['answer_revealed'] ? 'Yes' : 'No'; ?><br><small class="text-muted"><?php echo htmlspecialchars($a['question']); ?></small></div>
                <div class="admin-activity-time"><?php echo date('M j', strtotime($a['created_at'])); ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recent_ai)) echo '<p class="text-muted small">No sessions yet.</p>'; ?>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card"><div class="card-body">
            <h6 style="font-weight:600;color:var(--color-primary-dark);text-transform:uppercase;letter-spacing:0.04em;font-size:var(--font-size-xs);margin-bottom:var(--spacing-md);">Recent Quiz Attempts</h6>
            <?php if (empty($recent_quiz)): echo '<p class="text-muted small">No quiz attempts yet.</p>'; endif; ?>
            <?php foreach ($recent_quiz as $q): ?>
            <div class="admin-activity-item">
                <div class="admin-activity-icon quiz"><i class="bi bi-check2-circle"></i></div>
                <div class="admin-activity-text"><strong><?php echo htmlspecialchars($q['name']); ?></strong> — <?php echo htmlspecialchars($q['title']); ?><br><small class="text-muted"><?php echo (int)$q['score']; ?>/<?php echo (int)$q['total_questions']; ?> (<?php echo number_format($q['percentage'], 1); ?>%)</small></div>
                <div class="admin-activity-time"><?php echo date('M j', strtotime($q['completed_at'])); ?></div>
            </div>
            <?php endforeach; ?>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-body">
            <h6 style="font-weight:600;color:var(--color-primary-dark);text-transform:uppercase;letter-spacing:0.04em;font-size:var(--font-size-xs);margin-bottom:var(--spacing-md);">Recent Summaries</h6>
            <?php if (empty($recent_summary)): echo '<p class="text-muted small">No summaries generated yet.</p>'; endif; ?>
            <?php foreach ($recent_summary as $s): ?>
            <div class="admin-activity-item">
                <div class="admin-activity-icon summary"><i class="bi bi-file-earmark-text"></i></div>
                <div class="admin-activity-text"><strong><?php echo htmlspecialchars($s['name']); ?></strong> — <?php echo ucfirst($s['source_type']); ?><br><small class="text-muted"><?php echo htmlspecialchars($s['source_name'] ?? 'Text input'); ?></small></div>
                <div class="admin-activity-time"><?php echo date('M j', strtotime($s['created_at'])); ?></div>
            </div>
            <?php endforeach; ?>
        </div></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
    var colors={primary:'#0f172a',accent:'#3b82f6',green:'#22c55e',purple:'#8b5cf6',amber:'#f59e0b',pink:'#ec4899',cyan:'#06b6d4'};

    function fillBar(base,labels,values){
        return {
            labels:labels,
            datasets:[{label:base.label,data:values,backgroundColor:base.color,borderRadius:6,barThickness:20}]
        };
    }

    var regLabels=<?php echo json_encode(array_map(function($r){return date('M j',strtotime($r['d']));},$reg_chart)); ?>;
    var regData=<?php echo json_encode(array_map(function($r){return (int)$r['c'];},$reg_chart)); ?>;
    new Chart(document.getElementById('regChart'),{type:'bar',data:fillBar({label:'Registrations',color:colors.accent},regLabels,regData),options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}});

    var aiLabels=<?php echo json_encode(array_map(function($r){return date('M j',strtotime($r['d']));},$ai_chart)); ?>;
    var aiData=<?php echo json_encode(array_map(function($r){return (int)$r['c'];},$ai_chart)); ?>;
    new Chart(document.getElementById('aiChart'),{type:'line',data:{labels:aiLabels,datasets:[{label:'AI Sessions',data:aiData,borderColor:colors.purple,backgroundColor:'rgba(139,92,246,0.1)',fill:true,tension:0.3,pointRadius:4}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}});

    var hintLabels=<?php echo json_encode(array_map(function($r){return 'Level '.(int)$r['final_level'];},$hint_dist)); ?>;
    var hintData=<?php echo json_encode(array_map(function($r){return (int)$r['c'];},$hint_dist)); ?>;
    var hintColors=[colors.green,colors.accent,colors.amber,colors.pink,colors.purple];
    new Chart(document.getElementById('hintChart'),{type:'doughnut',data:{labels:hintLabels,datasets:[{data:hintData,backgroundColor:hintColors.slice(0,hintLabels.length)}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{padding:12}}}}});
});
</script>

<?php require_once __DIR__ . '/../views/admin/partials/footer.php'; ?>
