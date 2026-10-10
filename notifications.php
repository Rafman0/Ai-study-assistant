<?php
$page_title = 'Notifications';
$active_page = 'notifications';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/Auth.php';

Auth::require_login();

// Admins must not see the student area — send them to the admin area
if (is_admin_logged_in()) {
    redirect(app_url('admin/dashboard.php'));
}

$user_id = get_current_user_id();
$db = get_db_connection();

$last_seen = $_SESSION['notifications_seen_at'] ?? null;
$unread_count = 0;
$notifications = [];

if ($db) {
    try {
        // Published announcements relevant to this student
        $stmt = $db->prepare("SELECT title, body, created_at FROM announcements WHERE status='published' AND (publish_at IS NULL OR publish_at <= NOW()) AND audience='all' ORDER BY created_at DESC LIMIT 30");
        $stmt->execute();
        $announcements = $stmt->fetchAll();

        // Upcoming goals (reminders)
        $stmt = $db->prepare("SELECT title, target_date FROM study_goals WHERE user_id = ? AND is_completed = FALSE AND target_date >= CURDATE() ORDER BY target_date ASC LIMIT 5");
        $stmt->execute([$user_id]);
        $goals = $stmt->fetchAll();

        foreach ($announcements as $ann) {
            $is_unread = !$last_seen || strtotime($ann['created_at']) > strtotime($last_seen);
            if ($is_unread) {
                $unread_count++;
            }
            $notifications[] = [
                'icon' => '📢',
                'title' => $ann['title'],
                'body' => $ann['body'],
                'date' => $ann['created_at'],
                'unread' => $is_unread,
            ];
        }

        foreach ($goals as $goal) {
            $days_left = max(0, (int)ceil((strtotime(date('Y-m-d', strtotime($goal['target_date']))) - strtotime(date('Y-m-d'))) / 86400));
            if ($days_left <= 1) {
                $when = 'Due today';
            } elseif ($days_left === 1) {
                $when = 'Due tomorrow';
            } else {
                $when = 'Due in ' . $days_left . ' days';
            }
            $notifications[] = [
                'icon' => '🎯',
                'title' => $when,
                'body' => $goal['title'],
                'date' => $goal['target_date'] . ' 00:00:00',
                'unread' => false,
            ];
        }

        usort($notifications, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
    } catch (PDOException $e) {
        error_log("Notifications data error: " . $e->getMessage());
    }
}

// Visiting this page marks everything as read
$_SESSION['notifications_seen_at'] = date('Y-m-d H:i:s');
$previous_unread = $unread_count;

$layout_app_shell = true;
require_once __DIR__ . '/views/partials/navbar.php';
?>

<header class="page-heading">
    <h1>Notifications</h1>
    <p>
        <?php if ($previous_unread > 0): ?>
            <?php echo $previous_unread; ?> new notification<?php echo $previous_unread > 1 ? 's' : ''; ?>
        <?php else: ?>
            Announcements and reminders from your study journey
        <?php endif; ?>
    </p>
</header>

<div class="card mb-4">
    <div class="card-body">
        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-md);">All Notifications</h3>

        <?php if (empty($notifications)): ?>
            <p class="empty-note">No notifications yet. You're all caught up!</p>
        <?php else: ?>
            <ul class="activity-list">
                <?php foreach ($notifications as $item): ?>
                    <li class="activity-item">
                        <span class="activity-icon"><?php echo $item['icon']; ?></span>
                        <div class="activity-details">
                            <div class="activity-title">
                                <?php echo htmlspecialchars($item['title']); ?>
                                <?php if ($item['unread']): ?>
                                    <span class="notification-unread-dot" title="Unread"></span>
                                <?php endif; ?>
                            </div>
                            <div class="activity-sub"><?php echo htmlspecialchars($item['body']); ?></div>
                            <div class="activity-meta"><?php echo date('M j, Y g:i A', strtotime($item['date'])); ?></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<a href="<?php echo app_url('dashboard.php'); ?>" class="btn btn-outline">
    ← Back to Dashboard
</a>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>