<?php
$page_title = 'Study Journal';
$active_page = 'journal';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/Auth.php';
require_once __DIR__ . '/models/Journal.php';

Auth::require_login();

$user_id = get_current_user_id();
$entries = Journal::get_user_entries($user_id);

$message = '';
$message_type = '';

// Handle journal entry creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Invalid request. Please try again.';
        $message_type = 'danger';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $mood = $_POST['mood'] ?? 'neutral';
        
        if (empty($title) || empty($content)) {
            $message = 'Title and content are required.';
            $message_type = 'danger';
        } else {
            $result = Journal::create_entry($user_id, $title, $content, $mood);
            if ($result['success']) {
                // Check for newly unlocked achievements
                require_once __DIR__ . '/models/Achievement.php';
                $new_achievements = Achievement::check_achievements($user_id);

                $message = 'Journal entry created successfully!';
                if (!empty($new_achievements)) {
                    $message .= ' 🎉 New achievement unlocked: ' . implode(', ', $new_achievements) . '!';
                }
                $message_type = 'success';
                $entries = Journal::get_user_entries($user_id);
            } else {
                $message = $result['message'];
                $message_type = 'danger';
            }
        }
    }
}

$layout_app_shell = true;
require_once __DIR__ . '/views/partials/navbar.php';
?>

<header class="page-heading">
    <h1>Study Journal</h1>
    <p>Track your learning journey and reflections</p>
</header>

<?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">New Journal Entry</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="create">
                            
                            <div class="form-group">
                                <label for="title" class="form-label">Title *</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="mood" class="form-label">How are you feeling?</label>
                                <select class="form-control" id="mood" name="mood">
                                    <option value="happy">😊 Happy</option>
                                    <option value="neutral" selected>😐 Neutral</option>
                                    <option value="sad">😢 Sad</option>
                                    <option value="stressed">😰 Stressed</option>
                                    <option value="motivated">💪 Motivated</option>
                                    <option value="tired">😴 Tired</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="content" class="form-label">Content *</label>
                                <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Save Entry</button>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">Previous Entries</h3>
                        
                        <?php if (empty($entries)): ?>
                            <p style="color: var(--color-gray-600);">No journal entries yet. Start documenting your learning journey!</p>
                        <?php else: ?>
                            <?php foreach ($entries as $entry): ?>
                                <div style="background-color: var(--color-gray-50); padding: var(--spacing-lg); border-radius: var(--border-radius-md); margin-bottom: var(--spacing-md);">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h4 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-xs);">
                                                <?php echo htmlspecialchars($entry['title']); ?>
                                            </h4>
                                            <div style="margin-bottom: var(--spacing-sm);">
                                                <span style="font-size: var(--font-size-sm); color: var(--color-gray-600);">
                                                    <?php echo date('M j, Y g:i A', strtotime($entry['created_at'])); ?>
                                                </span>
                                                <span style="margin-left: var(--spacing-md); font-size: var(--font-size-sm);">
                                                    <?php
                                                    $mood_emojis = [
                                                        'happy' => '😊',
                                                        'neutral' => '😐',
                                                        'sad' => '😢',
                                                        'stressed' => '😰',
                                                        'motivated' => '💪',
                                                        'tired' => '😴'
                                                    ];
                                                    echo $mood_emojis[$entry['mood']] ?? '😐';
                                                    ?>
                                                </span>
                                            </div>
                                            <p style="color: var(--color-gray-700); white-space: pre-wrap;"><?php echo htmlspecialchars($entry['content']); ?></p>
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-outline">Edit</button>
                                            <button class="btn btn-sm btn-outline" style="color: var(--color-error); border-color: var(--color-error);">Delete</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
