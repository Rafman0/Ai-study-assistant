<?php
$page_title = 'Notes';
$active_page = 'notes';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/Auth.php';
require_once __DIR__ . '/models/Note.php';
require_once __DIR__ . '/models/Course.php';

Auth::require_login();

$user_id = get_current_user_id();
$notes = Note::get_user_notes($user_id);
$courses = Course::get_user_courses($user_id);

$message = '';
$message_type = '';

// Handle note creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Invalid request. Please try again.';
        $message_type = 'danger';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $course_id = !empty($_POST['course_id']) ? intval($_POST['course_id']) : null;
        
        if (empty($title) || empty($content)) {
            $message = 'Title and content are required.';
            $message_type = 'danger';
        } else {
            $result = Note::create_note($user_id, $title, $content, $course_id);
            if ($result['success']) {
                // Check for achievements
                require_once __DIR__ . '/models/Achievement.php';
                Achievement::check_achievements($user_id);
                
                $message = 'Note created successfully!';
                $message_type = 'success';
                $notes = Note::get_user_notes($user_id);
            } else {
                $message = $result['message'];
                $message_type = 'danger';
            }
        }
    }
}

// Handle search
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search_query) {
    $notes = Note::search_notes($user_id, $search_query);
}

$layout_app_shell = true;
require_once __DIR__ . '/views/partials/navbar.php';
?>

<header class="page-heading">
    <h1>Notes</h1>
    <p>Create and organize your study notes</p>
</header>

<?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">Create New Note</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="create">
                            
                            <div class="form-group">
                                <label for="title" class="form-label">Note Title *</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            
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
                                <label for="content" class="form-label">Content *</label>
                                <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Create Note</button>
                        </form>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 style="color: var(--color-primary-dark); margin-bottom: 0;">My Notes</h3>
                            <form method="GET" action="" class="d-flex gap-2">
                                <input type="text" class="form-control" name="search" placeholder="Search notes..." value="<?php echo htmlspecialchars($search_query); ?>" style="width: 200px;">
                                <button type="submit" class="btn btn-secondary">Search</button>
                            </form>
                        </div>
                        
                        <?php if (empty($notes)): ?>
                            <p style="color: var(--color-gray-600);">No notes found. Create your first note above!</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($notes as $note): ?>
                                    <div class="col-12 col-md-6 mb-4">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h4 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-sm);">
                                                    <?php echo htmlspecialchars($note['title']); ?>
                                                </h4>
                                                <?php if ($note['course_title']): ?>
                                                    <span style="background-color: var(--color-summarizer); color: var(--color-white); padding: 2px 8px; border-radius: 4px; font-size: var(--font-size-sm);">
                                                        <?php echo htmlspecialchars($note['course_title']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <p style="color: var(--color-gray-600); margin-top: var(--spacing-sm);">
                                                    <?php echo htmlspecialchars(substr($note['content'], 0, 150)); ?>...
                                                </p>
                                                <small style="color: var(--color-gray-500);">
                                                    Created: <?php echo date('M j, Y', strtotime($note['created_at'])); ?>
                                                </small>
                                                <div style="margin-top: var(--spacing-md);">
                                                    <button class="btn btn-sm btn-outline">View</button>
                                                    <button class="btn btn-sm btn-outline">Edit</button>
                                                    <button class="btn btn-sm btn-outline" style="color: var(--color-error); border-color: var(--color-error);">Delete</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
