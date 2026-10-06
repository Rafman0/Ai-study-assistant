<?php
$page_title = 'Study Session';
$active_page = 'study-session';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/Auth.php';
require_once __DIR__ . '/models/StudySession.php';
require_once __DIR__ . '/models/Course.php';

Auth::require_login();

$user_id = get_current_user_id();
$db = get_db_connection();
$sessions = StudySession::get_user_sessions($user_id);
$courses = Course::get_user_courses($user_id);
$total_study_time = StudySession::get_total_study_time($user_id);

$message = '';
$message_type = '';

// Get study goals (planner)
$goals = [];
if ($db) {
    try {
        $stmt = $db->prepare("SELECT * FROM study_goals WHERE user_id = ? ORDER BY is_completed ASC, target_date ASC");
        $stmt->execute([$user_id]);
        $goals = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Goals load error: " . $e->getMessage());
    }
}

// Handle session creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Invalid request. Please try again.';
        $message_type = 'danger';
    } else {
        $duration = intval($_POST['duration'] ?? 0);
        $topics = trim($_POST['topics'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $course_id = !empty($_POST['course_id']) ? intval($_POST['course_id']) : null;
        
        if ($duration <= 0) {
            $message = 'Duration must be greater than 0.';
            $message_type = 'danger';
        } else {
            $result = StudySession::create_session($user_id, $duration, $topics ?: null, $notes ?: null, $course_id);
            if ($result['success']) {
                // Update progress
                require_once __DIR__ . '/models/Progress.php';
                Progress::update_study_session($user_id, $course_id, $duration);

                // Check for newly unlocked achievements
                require_once __DIR__ . '/models/Achievement.php';
                $new_achievements = Achievement::check_achievements($user_id);

                $message = 'Study session recorded successfully!';
                if (!empty($new_achievements)) {
                    $message .= ' 🎉 New achievement unlocked: ' . implode(', ', $new_achievements) . '!';
                }
                $message_type = 'success';
                $sessions = StudySession::get_user_sessions($user_id);
                $total_study_time = StudySession::get_total_study_time($user_id);
            } else {
                $message = $result['message'];
                $message_type = 'danger';
            }
        }
    }
}

// Handle goal creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_goal') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Invalid request. Please try again.';
        $message_type = 'danger';
    } else {
        $title = trim($_POST['goal_title'] ?? '');
        $description = trim($_POST['goal_description'] ?? '');
        $target_date = trim($_POST['target_date'] ?? '');

        if ($title === '' || $target_date === '') {
            $message = 'Goal title and target date are required.';
            $message_type = 'danger';
        } elseif ($db) {
            try {
                $stmt = $db->prepare("INSERT INTO study_goals (user_id, title, description, target_date) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $title, $description ?: null, $target_date]);
                $message = 'Study goal added to your planner!';
                $message_type = 'success';

                $stmt = $db->prepare("SELECT * FROM study_goals WHERE user_id = ? ORDER BY is_completed ASC, target_date ASC");
                $stmt->execute([$user_id]);
                $goals = $stmt->fetchAll();
            } catch (PDOException $e) {
                error_log("Goal create error: " . $e->getMessage());
                $message = 'Failed to create goal. Please try again.';
                $message_type = 'danger';
            }
        }
    }
}

// Handle goal completion toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_goal') {
    if (isset($_POST['csrf_token']) && verify_csrf_token($_POST['csrf_token']) && !empty($_POST['goal_id'])) {
        try {
            $stmt = $db->prepare("UPDATE study_goals
                SET is_completed = NOT is_completed,
                    completed_at = IF(is_completed, NULL, NOW())
                WHERE id = ? AND user_id = ?");
            $stmt->execute([intval($_POST['goal_id']), $user_id]);

            $stmt = $db->prepare("SELECT * FROM study_goals WHERE user_id = ? ORDER BY is_completed ASC, target_date ASC");
            $stmt->execute([$user_id]);
            $goals = $stmt->fetchAll();
            $message = 'Goal updated!';
            $message_type = 'success';
        } catch (PDOException $e) {
            error_log("Goal toggle error: " . $e->getMessage());
        }
    }
}

// Handle goal deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_goal') {
    if (isset($_POST['csrf_token']) && verify_csrf_token($_POST['csrf_token']) && !empty($_POST['goal_id'])) {
        try {
            $stmt = $db->prepare("DELETE FROM study_goals WHERE id = ? AND user_id = ?");
            $stmt->execute([intval($_POST['goal_id']), $user_id]);
            $message = 'Goal removed.';
            $message_type = 'success';

            $stmt = $db->prepare("SELECT * FROM study_goals WHERE user_id = ? ORDER BY is_completed ASC, target_date ASC");
            $stmt->execute([$user_id]);
            $goals = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Goal delete error: " . $e->getMessage());
        }
    }
}

$layout_app_shell = true;
require_once __DIR__ . '/views/partials/navbar.php';
?>

<header class="page-heading">
    <h1>Study Session</h1>
    <p>Track your study time and focus</p>
</header>

<?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Total Study Time -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="text-center">
                            <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-sm);">Total Study Time</h3>
                            <div style="font-size: var(--font-size-4xl); font-weight: var(--font-weight-bold); color: var(--color-accent);">
                                <?php echo floor($total_study_time / 60); ?>h <?php echo $total_study_time % 60; ?>m
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Study Planner -->
                <div class="card mb-4" id="planner">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">Study Planner</h3>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="create_goal">

                            <div class="row">
                                <div class="col-12 col-md-7 mb-3">
                                    <label for="goal_title" class="form-label">Goal Title *</label>
                                    <input type="text" class="form-control" id="goal_title" name="goal_title" placeholder="e.g., Finish chapter 5 exercises" required maxlength="255">
                                </div>
                                <div class="col-12 col-md-5 mb-3">
                                    <label for="target_date" class="form-label">Target Date *</label>
                                    <input type="date" class="form-control" id="target_date" name="target_date" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="goal_description" class="form-label">Description (Optional)</label>
                                <textarea class="form-control" id="goal_description" name="goal_description" rows="2" placeholder="Details about this goal..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">Add Goal</button>
                        </form>

                        <?php if (!empty($goals)): ?>
                            <hr style="margin: var(--spacing-lg) 0;">
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                <?php foreach ($goals as $goal):
                                    $done = !empty($goal['is_completed']);
                                    $overdue = !$done && $goal['target_date'] && strtotime($goal['target_date']) < strtotime(date('Y-m-d'));
                                ?>
                                    <li style="display: flex; align-items: center; gap: var(--spacing-md); padding: var(--spacing-sm) 0; border-bottom: 1px solid var(--color-gray-200); flex-wrap: wrap;">
                                        <span style="font-size: var(--font-size-xl);"><?php echo $done ? '✅' : ($overdue ? '⚠️' : '🎯'); ?></span>
                                        <div style="flex: 1; min-width: 200px;">
                                            <div style="font-weight: var(--font-weight-medium); <?php echo $done ? 'text-decoration: line-through; color: var(--color-gray-400);' : ''; ?>">
                                                <?php echo htmlspecialchars($goal['title']); ?>
                                            </div>
                                            <div style="font-size: var(--font-size-sm); color: <?php echo $overdue ? 'var(--color-error)' : 'var(--color-gray-500)'; ?>;">
                                                Due <?php echo date('M j, Y', strtotime($goal['target_date'])); ?><?php echo $overdue ? ' • Overdue' : ''; ?>
                                                <?php if (!empty($goal['description'])): ?>
                                                    — <?php echo htmlspecialchars(mb_substr($goal['description'], 0, 60)); ?><?php echo mb_strlen($goal['description']) > 60 ? '…' : ''; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="toggle_goal">
                                            <input type="hidden" name="goal_id" value="<?php echo $goal['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline"><?php echo $done ? 'Reopen' : 'Done'; ?></button>
                                        </form>
                                        <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Delete this goal?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="delete_goal">
                                            <input type="hidden" name="goal_id" value="<?php echo $goal['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline" style="color: var(--color-error); border-color: var(--color-error);">Delete</button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p style="color: var(--color-gray-600); margin-top: var(--spacing-lg);">No goals yet. Add your first study goal above to build your plan!</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">Record Study Session</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="create">
                            
                            <div class="form-group">
                                <label for="course" class="form-label">Related Course</label>
                                <select class="form-control" id="course" name="course_id">
                                    <option value="">Select a course...</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="duration" class="form-label">Duration (minutes) *</label>
                                <input type="number" class="form-control" id="duration" name="duration" min="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="topics" class="form-label">Topics Covered</label>
                                <textarea class="form-control" id="topics" name="topics" rows="2" placeholder="e.g., Binary search, Time complexity"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="notes" class="form-label">Session Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Any additional notes about this session"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Record Session</button>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">Recent Sessions</h3>
                        
                        <?php if (empty($sessions)): ?>
                            <p style="color: var(--color-gray-600);">No study sessions yet. Start tracking your study time!</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table" style="width: 100%;">
                                    <thead>
                                        <tr style="background-color: var(--color-gray-100);">
                                            <th style="padding: var(--spacing-sm);">Date</th>
                                            <th style="padding: var(--spacing-sm);">Course</th>
                                            <th style="padding: var(--spacing-sm);">Duration</th>
                                            <th style="padding: var(--spacing-sm);">Topics</th>
                                            <th style="padding: var(--spacing-sm);">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sessions as $session): ?>
                                            <tr style="border-bottom: 1px solid var(--color-gray-200);">
                                                <td style="padding: var(--spacing-sm);"><?php echo date('M j, Y g:i A', strtotime($session['session_date'])); ?></td>
                                                <td style="padding: var(--spacing-sm);"><?php echo $session['course_title'] ? htmlspecialchars($session['course_title']) : 'General'; ?></td>
                                                <td style="padding: var(--spacing-sm);"><?php echo floor($session['duration_minutes'] / 60); ?>h <?php echo $session['duration_minutes'] % 60; ?>m</td>
                                                <td style="padding: var(--spacing-sm);"><?php echo $session['topics_covered'] ? htmlspecialchars(substr($session['topics_covered'], 0, 30)) : '-'; ?></td>
                                                <td style="padding: var(--spacing-sm);">
                                                    <button class="btn btn-sm btn-outline">View</button>
                                                    <button class="btn btn-sm btn-outline" style="color: var(--color-error); border-color: var(--color-error);">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
