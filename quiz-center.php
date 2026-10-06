<?php
$page_title = 'Quiz Center';
$active_page = 'quiz-center';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/Auth.php';
require_once __DIR__ . '/models/Quiz.php';
require_once __DIR__ . '/models/Course.php';

Auth::require_login();

$user_id = get_current_user_id();
$quizzes = Quiz::get_user_quizzes($user_id);
$results = Quiz::get_user_results($user_id);
$courses = Course::get_user_courses($user_id);

$message = '';
$message_type = '';

// Handle quiz creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Invalid request. Please try again.';
        $message_type = 'danger';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $course_id = !empty($_POST['course_id']) ? intval($_POST['course_id']) : null;

        // Collect questions
        $questions = [];
        for ($i = 1; $i <= 5; $i++) {
            $question = trim($_POST["question_$i"] ?? '');
            $option_a = trim($_POST["option_a_$i"] ?? '');
            $option_b = trim($_POST["option_b_$i"] ?? '');
            $option_c = trim($_POST["option_c_$i"] ?? '');
            $option_d = trim($_POST["option_d_$i"] ?? '');
            $correct = $_POST["correct_$i"] ?? '';

            if (!empty($question) && !empty($option_a) && !empty($option_b) && !empty($option_c) && !empty($option_d) && in_array($correct, ['a', 'b', 'c', 'd'])) {
                $questions[] = [
                    'question' => $question,
                    'option_a' => $option_a,
                    'option_b' => $option_b,
                    'option_c' => $option_c,
                    'option_d' => $option_d,
                    'correct_answer' => $correct,
                    'explanation' => trim($_POST["explanation_$i"] ?? '')
                ];
            }
        }

        if (empty($title) || empty($questions)) {
            $message = 'Title and at least one complete question are required.';
            $message_type = 'danger';
        } else {
            $result = Quiz::create_quiz($user_id, $title, $description, $questions, $course_id);
            if ($result['success']) {
                $message = 'Quiz created successfully!';
                $message_type = 'success';
                $quizzes = Quiz::get_user_quizzes($user_id);
            } else {
                $message = $result['message'];
                $message_type = 'danger';
            }
        }
    }
}

// Handle quiz deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_quiz') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Invalid request. Please try again.';
        $message_type = 'danger';
    } else {
        $result = Quiz::delete_quiz(intval($_POST['quiz_id'] ?? 0), $user_id);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'danger';
        $quizzes = Quiz::get_user_quizzes($user_id);
    }
}

$layout_app_shell = true;
require_once __DIR__ . '/views/partials/navbar.php';
?>

<header class="page-heading">
    <h1>Quiz Center</h1>
    <p>Test your knowledge with quizzes you build or generate with AI</p>
</header>

<?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <!-- AI Quiz Generator -->
                <div class="card mb-4" style="border-left: 4px solid var(--color-accent);">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-sm);">🤖 AI Quiz Generator</h3>
                        <p style="color: var(--color-gray-600);">Enter a topic and let the AI draft questions straight into the form below - then tweak anything you like.</p>

                        <div class="row align-items-end">
                            <div class="col-12 col-md-8 mb-3 mb-md-0">
                                <label for="gen-topic" class="form-label">Topic</label>
                                <input type="text" class="form-control" id="gen-topic" placeholder="e.g., Recursion, SQL joins, Big-O notation" maxlength="120">
                            </div>
                            <div class="col-6 col-md-2 mb-3 mb-md-0">
                                <label for="gen-count" class="form-label"># Questions</label>
                                <select class="form-control" id="gen-count">
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5" selected>5</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <button type="button" class="btn btn-primary w-100" id="generate-btn">Generate ✨</button>
                            </div>
                        </div>
                        <span id="gen-loading" style="display: none; color: var(--color-gray-600);">
                            <span class="spinner"></span> Generating questions...
                        </span>
                        <div id="gen-status" style="margin-top: 8px;"></div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">Create New Quiz</h3>
                        <form method="POST" action="" id="quiz-create-form">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="create">

                            <div class="form-group">
                                <label for="title" class="form-label">Quiz Title *</label>
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
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                            </div>

                            <h4 style="color: var(--color-primary-dark); margin: var(--spacing-lg) 0 var(--spacing-md);">Questions</h4>

                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <fieldset style="background-color: var(--color-gray-50); padding: var(--spacing-md); border-radius: var(--border-radius-md); margin-bottom: var(--spacing-md); border: none;" data-question="<?php echo $i; ?>">
                                    <legend style="font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); width: auto; padding: 0 var(--spacing-sm);">Question <?php echo $i; ?></legend>

                                    <div class="form-group">
                                        <input type="text" class="form-control q-text" name="question_<?php echo $i; ?>" placeholder="Question text">
                                    </div>

                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-group">
                                                <input type="text" class="form-control q-opt-a" name="option_a_<?php echo $i; ?>" placeholder="Option A">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-group">
                                                <input type="text" class="form-control q-opt-b" name="option_b_<?php echo $i; ?>" placeholder="Option B">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-group">
                                                <input type="text" class="form-control q-opt-c" name="option_c_<?php echo $i; ?>" placeholder="Option C">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-group">
                                                <input type="text" class="form-control q-opt-d" name="option_d_<?php echo $i; ?>" placeholder="Option D">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Correct Answer</label>
                                        <div>
                                            <label style="margin-right: var(--spacing-md);"><input type="radio" name="correct_<?php echo $i; ?>" value="a" class="q-correct"> A</label>
                                            <label style="margin-right: var(--spacing-md);"><input type="radio" name="correct_<?php echo $i; ?>" value="b" class="q-correct"> B</label>
                                            <label style="margin-right: var(--spacing-md);"><input type="radio" name="correct_<?php echo $i; ?>" value="c" class="q-correct"> C</label>
                                            <label><input type="radio" name="correct_<?php echo $i; ?>" value="d" class="q-correct"> D</label>
                                        </div>
                                    </div>

                                    <div class="form-group mb-0">
                                        <input type="text" class="form-control q-expl" name="explanation_<?php echo $i; ?>" placeholder="Explanation (optional)">
                                    </div>
                                </fieldset>
                            <?php endfor; ?>

                            <button type="submit" class="btn btn-primary">Create Quiz</button>
                        </form>
                    </div>
                </div>

                <!-- My Quizzes -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">My Quizzes</h3>

                        <?php if (empty($quizzes)): ?>
                            <p style="color: var(--color-gray-600);">No quizzes yet. Create your first quiz above - or let the AI generator draft one!</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($quizzes as $quiz): ?>
                                    <div class="col-12 col-md-6 mb-4" data-quiz-card="<?php echo $quiz['id']; ?>">
                                        <div class="card h-100">
                                            <div class="card-body d-flex flex-column">
                                                <h4 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-sm);">
                                                    <?php echo htmlspecialchars($quiz['title']); ?>
                                                </h4>
                                                <?php if (!empty($quiz['course_title'])): ?>
                                                    <span style="background-color: var(--color-quiz); color: var(--color-white); padding: 2px 8px; border-radius: 4px; font-size: var(--font-size-sm); align-self: flex-start;">
                                                        <?php echo htmlspecialchars($quiz['course_title']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($quiz['description'])): ?>
                                                    <p style="color: var(--color-gray-600); margin-top: var(--spacing-sm);">
                                                        <?php echo htmlspecialchars(mb_substr($quiz['description'], 0, 100)); ?><?php echo mb_strlen($quiz['description']) > 100 ? '…' : ''; ?>
                                                    </p>
                                                <?php endif; ?>
                                                <div style="margin-top: auto; padding-top: var(--spacing-md);">
                                                    <button class="btn btn-sm btn-primary take-quiz-btn" data-quiz-id="<?php echo $quiz['id']; ?>">Take Quiz</button>
                                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Delete this quiz and all its results?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <input type="hidden" name="action" value="delete_quiz">
                                                        <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline" style="color: var(--color-error); border-color: var(--color-error);">Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quiz Results History -->
                <div class="card">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">My Results</h3>

                        <?php if (empty($results)): ?>
                            <p style="color: var(--color-gray-600);">No attempts yet. Take a quiz to see your results here!</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table" style="width: 100%;">
                                    <thead>
                                        <tr style="background-color: var(--color-gray-100);">
                                            <th style="padding: var(--spacing-sm);">Date</th>
                                            <th style="padding: var(--spacing-sm);">Quiz</th>
                                            <th style="padding: var(--spacing-sm);">Score</th>
                                            <th style="padding: var(--spacing-sm);">Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results as $result): ?>
                                            <tr style="border-bottom: 1px solid var(--color-gray-200);">
                                                <td style="padding: var(--spacing-sm);"><?php echo date('M j, Y g:i A', strtotime($result['completed_at'])); ?></td>
                                                <td style="padding: var(--spacing-sm);"><?php echo htmlspecialchars($result['quiz_title']); ?></td>
                                                <td style="padding: var(--spacing-sm);"><?php echo $result['score']; ?>/<?php echo $result['total_questions']; ?></td>
                                                <td style="padding: var(--spacing-sm);">
                                                    <span style="color: <?php echo $result['percentage'] >= 70 ? 'var(--color-success)' : (($result['percentage'] >= 50) ? 'var(--color-warning)' : 'var(--color-error)'); ?>; font-weight: var(--font-weight-semibold);">
                                                        <?php echo round($result['percentage']); ?>%
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

<!-- Take Quiz Modal -->
<div class="quiz-modal-overlay" id="quizModal" role="dialog" aria-modal="true" aria-labelledby="quizModalTitle">
    <div class="quiz-modal">
        <div class="quiz-modal-header">
            <h4 id="quizModalTitle">Loading quiz...</h4>
            <button type="button" class="quiz-modal-close" id="quizCloseBtn" aria-label="Close">&times;</button>
        </div>
        <div class="quiz-modal-body" id="quizModalBody"></div>
        <div class="quiz-modal-footer" id="quizModalFooter"></div>
    </div>
</div>

<script>
    const QUIZ_API_URL = <?php echo json_encode(app_url('api/quizzes.php')); ?>;
    const TUTOR_API_URL = <?php echo json_encode(app_url('api/ai.php')); ?>;
</script>
<script src="<?php echo asset_url('js/quiz.js'); ?>"></script>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
