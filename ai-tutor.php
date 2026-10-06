<?php
$page_title = 'AI Tutor';
$active_page = 'ai-tutor';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/Auth.php';
require_once __DIR__ . '/models/Course.php';

Auth::require_login();

$user_id = get_current_user_id();
$courses = Course::get_user_courses($user_id);

$layout_app_shell = true;
require_once __DIR__ . '/views/partials/navbar.php';
?>

<!-- AI Tutor Header -->
<header class="page-heading">
    <h1>AI Tutor</h1>
    <p>Learn through guided discovery - hints first, full answers only when you're ready</p>
</header>

<div class="alert alert-info d-flex flex-wrap align-items-center" style="gap: 12px;">
                    <strong>How it works:</strong>
                    <span>🧭 Guiding question</span>
                    <span>💡 Conceptual clue</span>
                    <span>🧩 Partial solution</span>
                    <span>✅ Full answer (last resort)</span>
                </div>

                <div class="card">
                    <div class="card-body">
                        <!-- Step 1: Ask a question -->
                        <div id="ask-section">
                            <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">Ask Your Question</h3>

                            <form id="ai-tutor-form">
                                <div class="form-group">
                                    <label for="question" class="form-label">What are you stuck on?</label>
                                    <textarea class="form-control" id="question" name="question" rows="4" placeholder="e.g., How does recursion work and when should I use it?" required></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="course" class="form-label">Related Course (Optional)</label>
                                    <select class="form-control" id="course" name="course">
                                        <option value="">Select a course...</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-primary" id="submit-btn">Start Learning Session</button>
                                <span id="loading-indicator" style="display: none; margin-left: var(--spacing-md); color: var(--color-gray-600);">
                                    <span class="spinner"></span> Thinking...
                                </span>
                            </form>
                        </div>

                        <!-- Step 2: Hint conversation -->
                        <div id="tutor-session" style="display: none;">
                            <div class="tutor-question-recap">
                                <span class="recap-label">Your question</span>
                                <p id="session-question"></p>
                            </div>

                            <div class="hint-tracker" id="hint-tracker">
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <div class="hint-step" data-step="<?php echo $i; ?>">
                                        <div class="hint-step-dot"><?php echo $i; ?></div>
                                        <div class="hint-step-text">
                                            <strong><?php echo [1 => 'Guiding', 2 => 'Concept', 3 => 'Partial', 4 => 'Full'][$i]; ?></strong>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>

                            <div id="conversation" class="conversation"></div>

                            <!-- Chat & answer panel -->
                            <div class="answer-attempt" id="answer-attempt" style="display: none;">
                                <div class="answer-attempt-label">💬 Chat with your AI Tutor</div>
                                <p class="answer-attempt-hint">Ask anything about the question, or type your attempt at an answer — the tutor will check it, tell you if you're right (with a confidence level), and give you the correct answer when you're not.</p>
                                <div class="answer-attempt-input-row">
                                    <textarea class="form-control" id="chat-input" rows="2" placeholder="Try answering from the hint, or ask a follow-up question... (Enter to send, Shift+Enter for a new line)"></textarea>
                                    <button type="button" class="btn btn-primary" id="send-btn">Send ➤</button>
                                </div>
                            </div>

                            <div class="tutor-controls">
                                <button type="button" class="btn btn-primary" id="next-hint-btn">Request Another Hint 💡</button>
                                <button type="button" class="btn btn-outline" id="reveal-btn" disabled title="View the complete answer & step-by-step solution">Complete Answer 🔓</button>
                                <button type="button" class="btn btn-secondary" id="new-question-btn">New Question</button>
                            </div>
                        </div>

                        <?php if (empty(AI_API_KEY)): ?>
                            <div class="alert alert-info mt-3">
                                <strong>Demo Mode:</strong> Tutor responses are simulated. Configure your AI API key in config/config.php for real AI responses.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

<script>const TUTOR_API_URL = <?php echo json_encode(app_url('api/ai.php')); ?>;</script>
<script src="<?php echo asset_url('js/tutor.js?v=2'); ?>"></script>

<?php
require_once __DIR__ . '/views/partials/footer.php';
?>
