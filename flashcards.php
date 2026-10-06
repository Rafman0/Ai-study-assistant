<?php
$page_title = 'Flashcards';
$active_page = 'flashcards';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/Auth.php';
require_once __DIR__ . '/models/Flashcard.php';
require_once __DIR__ . '/models/Course.php';

Auth::require_login();

$user_id = get_current_user_id();
$flashcards = Flashcard::get_user_flashcards($user_id);
$courses = Course::get_user_courses($user_id);

$message = '';
$message_type = '';

// Handle flashcard creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Invalid request. Please try again.';
        $message_type = 'danger';
    } else {
        $question = trim($_POST['question'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        $course_id = !empty($_POST['course_id']) ? intval($_POST['course_id']) : null;
        
        if (empty($question) || empty($answer)) {
            $message = 'Question and answer are required.';
            $message_type = 'danger';
        } else {
            $result = Flashcard::create_flashcard($user_id, $question, $answer, $course_id);
            if ($result['success']) {
                $message = 'Flashcard created successfully!';
                $message_type = 'success';
                $flashcards = Flashcard::get_user_flashcards($user_id);
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
    <h1>Flashcards</h1>
    <p>Create and study with digital flashcards</p>
</header>

<?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">Create New Flashcard</h3>
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
                                <label for="question" class="form-label">Question *</label>
                                <textarea class="form-control" id="question" name="question" rows="2" required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="answer" class="form-label">Answer *</label>
                                <textarea class="form-control" id="answer" name="answer" rows="3" required></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Create Flashcard</button>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">My Flashcards</h3>
                        
                        <?php if (empty($flashcards)): ?>
                            <p style="color: var(--color-gray-600);">No flashcards yet. Create your first flashcard above!</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($flashcards as $flashcard): ?>
                                    <div class="col-12 col-md-6 mb-4">
                                        <div class="flashcard">
                                            <div class="flashcard-inner">
                                                <div class="flashcard-front">
                                                    <div>
                                                        <?php if ($flashcard['course_title']): ?>
                                                            <span style="background-color: var(--color-flashcards); color: var(--color-white); padding: 2px 8px; border-radius: 4px; font-size: var(--font-size-sm); margin-bottom: var(--spacing-sm); display: inline-block;">
                                                                <?php echo htmlspecialchars($flashcard['course_title']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <p style="font-size: var(--font-size-lg); font-weight: var(--font-weight-medium);"><?php echo htmlspecialchars($flashcard['question']); ?></p>
                                                        <small style="color: var(--color-gray-500);">Click to flip</small>
                                                    </div>
                                                </div>
                                                <div class="flashcard-back">
                                                    <div>
                                                        <p style="font-size: var(--font-size-lg);"><?php echo htmlspecialchars($flashcard['answer']); ?></p>
                                                        <div style="margin-top: var(--spacing-md);">
                                                            <button class="btn btn-sm btn-outline" onclick="event.stopPropagation();">Edit</button>
                                                            <button class="btn btn-sm btn-outline" style="color: var(--color-error); border-color: var(--color-error);" onclick="event.stopPropagation();">Delete</button>
                                                        </div>
                                                    </div>
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
