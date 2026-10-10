<?php
$page_title = 'Dashboard';
$active_page = 'dashboard';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/Auth.php';

// Require user login
Auth::require_login();

// Admins must not see the student dashboard — send them to the admin area
if (is_admin_logged_in()) {
    redirect(app_url('admin/dashboard.php'));
}

// Get user dashboard data
$db = get_db_connection();
$user_id = get_current_user_id();
$dashboard_data = [
    'courses_enrolled' => 0,
    'study_sessions' => 0,
    'quizzes_completed' => 0,
    'quiz_performance' => 0,
    'study_streak' => 0,
    'progress_percentage' => 0,
    'recent_activity' => [],
    'upcoming_goals' => []
];

if ($db) {
    try {
        // Get courses enrolled
        $stmt = $db->prepare("SELECT COUNT(*) FROM courses WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $dashboard_data['courses_enrolled'] = $stmt->fetchColumn();

        // Get study sessions
        $stmt = $db->prepare("SELECT COUNT(*) FROM study_sessions WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $dashboard_data['study_sessions'] = $stmt->fetchColumn();

        // Get completed quizzes count
        $stmt = $db->prepare("SELECT COUNT(*) FROM quiz_results WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $dashboard_data['quizzes_completed'] = $stmt->fetchColumn();

        // Get quiz performance (average score)
        $stmt = $db->prepare("SELECT AVG(percentage) FROM quiz_results WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $dashboard_data['quiz_performance'] = round($stmt->fetchColumn() ?: 0, 1);

        // Get study streak
        $stmt = $db->prepare("SELECT current_streak FROM progress WHERE user_id = ? ORDER BY current_streak DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $dashboard_data['study_streak'] = $stmt->fetchColumn() ?: 0;

        // Get overall progress
        $stmt = $db->prepare("SELECT AVG(average_quiz_score) FROM progress WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $dashboard_data['progress_percentage'] = round($stmt->fetchColumn() ?: 0, 1);

        // Get recent activity
        $stmt = $db->prepare("
            (SELECT 'note' as type, title, created_at FROM notes WHERE user_id = ? ORDER BY created_at DESC LIMIT 2)
            UNION ALL
            (SELECT 'flashcard' as type, question as title, created_at FROM flashcards WHERE user_id = ? ORDER BY created_at DESC LIMIT 2)
            UNION ALL
            (SELECT 'quiz' as type, title, created_at FROM quiz_results qr JOIN quizzes q ON qr.quiz_id = q.id WHERE qr.user_id = ? ORDER BY qr.completed_at DESC LIMIT 2)
            ORDER BY created_at DESC LIMIT 5
        ");
        $stmt->execute([$user_id, $user_id, $user_id]);
        $dashboard_data['recent_activity'] = $stmt->fetchAll();

        // Get upcoming goals
        $stmt = $db->prepare("SELECT title, target_date FROM study_goals WHERE user_id = ? AND is_completed = FALSE AND target_date >= CURDATE() ORDER BY target_date ASC LIMIT 3");
        $stmt->execute([$user_id]);
        $dashboard_data['upcoming_goals'] = $stmt->fetchAll();

        // Published announcements relevant to this student
        $dashboard_data['announcements'] = $db->prepare("SELECT title, body FROM announcements WHERE status='published' AND (publish_at IS NULL OR publish_at <= NOW()) AND (audience='all') ORDER BY created_at DESC LIMIT 3");
        $dashboard_data['announcements']->execute();
        $dashboard_data['announcements'] = $dashboard_data['announcements']->fetchAll();

        // Unread notifications (published announcements newer than last seen)
        $unread_notifications = 0;
        $last_seen = $_SESSION['notifications_seen_at'] ?? null;
        if ($last_seen) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM announcements WHERE status='published' AND (publish_at IS NULL OR publish_at <= NOW()) AND audience='all' AND created_at > ?");
            $stmt->execute([$last_seen]);
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM announcements WHERE status='published' AND (publish_at IS NULL OR publish_at <= NOW()) AND audience='all'");
            $stmt->execute();
        }
        $unread_notifications = (int)$stmt->fetchColumn();

    } catch (PDOException $e) {
        error_log("Dashboard data error: " . $e->getMessage());
    }
}

// Full-height app shell: sidebar spans the viewport, no top navbar / site footer
$layout_shell_only = true;
require_once __DIR__ . '/views/partials/navbar.php';
?>

<!-- Modern Dashboard Layout: sidebar sits beside content via Flexbox -->
<div class="modern-dashboard">
    <!-- Modern Sidebar -->
    <aside class="modern-sidebar">
        <?php include __DIR__ . '/views/partials/user_sidebar.php'; ?>
    </aside>

    <!-- Main Dashboard Content -->
    <main class="modern-main-content">
        <!-- Top Header -->
        <header class="modern-header">
            <div class="welcome-section">
                <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>! 👋</h1>
                <p>Let's continue your learning journey.</p>
            </div>
            <div class="user-area">
                <a href="<?php echo app_url('notifications.php'); ?>" class="notification-icon" title="Notifications" aria-label="Notifications">
                    🔔
                    <?php if (!empty($unread_notifications)): ?>
                    <span class="notification-badge"><?php echo $unread_notifications > 9 ? '9+' : $unread_notifications; ?></span>
                    <?php endif; ?>
                </a>
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></div>
                        <div class="user-role">Student</div>
                    </div>
                </div>
            </div>
        </header>

        <?php if (!empty($dashboard_data['announcements'])): ?>
        <div style="margin-bottom:var(--spacing-lg)">
            <?php foreach ($dashboard_data['announcements'] as $ann): ?>
            <div style="background:linear-gradient(135deg,#eff6ff,#dbeafe);border-radius:var(--border-radius-md);padding:var(--spacing-md) var(--spacing-lg);margin-bottom:var(--spacing-sm);border-left:4px solid #3b82f6">
                <div style="font-weight:600;color:#1e40af;font-size:var(--font-size-sm);margin-bottom:2px"><?php echo htmlspecialchars($ann['title']); ?></div>
                <div style="font-size:var(--font-size-sm);color:#1e3a5f"><?php echo htmlspecialchars(mb_substr($ann['body'], 0, 180)); ?><?php if (strlen($ann['body']) > 180) echo '...'; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card modern-card">
                <div class="stat-icon streak-icon">🔥</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($dashboard_data['study_streak']); ?> days</div>
                    <div class="stat-label">Study Streak</div>
                </div>
            </div>

            <div class="stat-card modern-card">
                <div class="stat-icon time-icon">⏰</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($dashboard_data['study_sessions']); ?> sessions</div>
                    <div class="stat-label">Study Time</div>
                </div>
            </div>

            <div class="stat-card modern-card">
                <div class="stat-icon quiz-icon">📝</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($dashboard_data['quizzes_completed']); ?></div>
                    <div class="stat-label">Quizzes Completed</div>
                </div>
            </div>

            <div class="stat-card modern-card">
                <div class="stat-icon accuracy-icon">🎯</div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $dashboard_data['quiz_performance']; ?>%</div>
                    <div class="stat-label">Accuracy</div>
                </div>
            </div>
        </div>

        <!-- Dashboard Content Grid -->
        <div class="dashboard-grid">
            <!-- Upcoming Study Session -->
            <div class="dashboard-card upcoming-session">
                <div class="card-header">
                    <div class="card-icon">📅</div>
                    <h3>Upcoming Study Session</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($dashboard_data['upcoming_goals'])): ?>
                        <?php $next_goal = $dashboard_data['upcoming_goals'][0]; ?>
                        <div class="session-info">
                            <div class="session-course"><?php echo htmlspecialchars($next_goal['title']); ?></div>
                            <div class="session-details">
                                <?php echo date('F j, Y', strtotime($next_goal['target_date'])); ?>
                            </div>
                            <a href="<?php echo app_url('study-session.php'); ?>" class="btn btn-primary">Start Session</a>
                        </div>
                    <?php else: ?>
                        <div class="session-info">
                            <div class="session-course">No upcoming sessions</div>
                            <div class="session-details">Set study goals to get started</div>
                            <a href="<?php echo app_url('study-session.php'); ?>#planner" class="btn btn-primary">Set Goals</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Progress Overview -->
            <div class="dashboard-card progress-overview">
                <div class="card-header">
                    <div class="card-icon">📈</div>
                    <h3>Progress Overview</h3>
                </div>
                <div class="card-body">
                    <div class="progress-chart">
                        <div class="chart-container">
                            <div class="chart-line" style="height: <?php echo max(4, (int) $dashboard_data['progress_percentage']); ?>%;"></div>
                        </div>
                        <div class="chart-labels">
                            <span>Start</span>
                            <span>Today</span>
                            <span>Goal</span>
                        </div>
                    </div>
                    <div class="progress-stats">
                        <div class="progress-stat">
                            <div class="stat-label">Overall Progress</div>
                            <div class="stat-value"><?php echo $dashboard_data['progress_percentage']; ?>%</div>
                        </div>
                        <a href="<?php echo app_url('progress.php'); ?>" class="btn btn-sm btn-outline">View Details</a>
                    </div>
                </div>
            </div>

            <!-- Recommended For You -->
            <div class="dashboard-card recommended-section">
                <div class="card-header">
                    <div class="card-icon">⭐</div>
                    <h3>Recommended For You</h3>
                </div>
                <div class="card-body">
                    <div class="recommended-grid">
                        <a href="<?php echo app_url('ai-tutor.php'); ?>" class="recommended-card">
                            <div class="rec-icon">🤖</div>
                            <div class="rec-content">
                                <h4>AI Tutor</h4>
                                <p>Get guided hints</p>
                            </div>
                        </a>

                        <a href="<?php echo app_url('quiz-center.php'); ?>" class="recommended-card">
                            <div class="rec-icon">📝</div>
                            <div class="rec-content">
                                <h4>Quiz Center</h4>
                                <p>Test your knowledge</p>
                            </div>
                        </a>

                        <a href="<?php echo app_url('flashcards.php'); ?>" class="recommended-card">
                            <div class="rec-icon">🃏</div>
                            <div class="rec-content">
                                <h4>Flashcards</h4>
                                <p>Review and remember</p>
                            </div>
                        </a>

                        <a href="<?php echo app_url('notes.php'); ?>" class="recommended-card">
                            <div class="rec-icon">📓</div>
                            <div class="rec-content">
                                <h4>Notes</h4>
                                <p>Write and organize</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="dashboard-card recent-activity">
                <div class="card-header">
                    <div class="card-icon">🕒</div>
                    <h3>Recent Activity</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($dashboard_data['recent_activity'])): ?>
                        <p class="empty-note">No recent activity yet. Start learning to see your progress here!</p>
                    <?php else: ?>
                        <ul class="activity-list">
                            <?php foreach ($dashboard_data['recent_activity'] as $activity): ?>
                                <li class="activity-item">
                                    <span class="activity-icon">
                                        <?php
                                        switch ($activity['type']) {
                                            case 'note': echo '📝'; break;
                                            case 'flashcard': echo '🃏'; break;
                                            case 'quiz': echo '📝'; break;
                                            default: echo '📚';
                                        }
                                        ?>
                                    </span>
                                    <div class="activity-details">
                                        <div class="activity-title"><?php echo htmlspecialchars($activity['title']); ?></div>
                                        <div class="activity-meta">
                                            <?php echo ucfirst($activity['type']); ?> •
                                            <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Goals -->
            <div class="dashboard-card upcoming-goals">
                <div class="card-header">
                    <div class="card-icon">🎯</div>
                    <h3>Upcoming Study Goals</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($dashboard_data['upcoming_goals'])): ?>
                        <p class="empty-note">No upcoming goals. Set some study goals to stay on track!</p>
                    <?php else: ?>
                        <ul class="activity-list">
                            <?php foreach ($dashboard_data['upcoming_goals'] as $goal): ?>
                                <li class="activity-item">
                                    <span class="activity-icon">🎯</span>
                                    <div class="activity-details">
                                        <div class="activity-title"><?php echo htmlspecialchars($goal['title']); ?></div>
                                        <div class="activity-meta">
                                            Due: <?php echo date('M j, Y', strtotime($goal['target_date'])); ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php
require_once __DIR__ . '/views/partials/footer.php';
?>
