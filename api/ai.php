<?php
/**
 * AI API endpoint
 * Handles AI Tutor (graduated hints) and Summarizer requests
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/AiClient.php';

define('TUTOR_MAX_LEVEL', 4);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$response = ['success' => false, 'message' => 'Invalid request'];

try {
    switch ($action) {
        case 'tutor_start':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $response = tutor_start($input);
            break;

        case 'tutor_hint':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $response = tutor_next_hint();
            break;

case 'tutor_reveal':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $explicit = !empty($input['explicit']);
            $response = tutor_reveal($explicit);
            break;

        case 'tutor_check':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $response = tutor_check_answer($input);
            break;

        case 'tutor_chat':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $response = tutor_chat($input);
            break;

        case 'tutor_state':
            if (!is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $response = tutor_state();
            break;

        case 'generate_quiz':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $topic = trim($input['topic'] ?? '');
            if ($topic === '') {
                throw new Exception('Topic is required');
            }
            $count = max(1, min(5, intval($input['count'] ?? 5)));
            $response = generate_quiz_questions($topic, $count);
            break;

        case 'summarize':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }

            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $content = trim($input['content'] ?? '');
            $course_id = $input['course_id'] ?? null;

            if (empty($content)) {
                throw new Exception('Content is required');
            }

            // Check if AI API is configured
            if (empty(AI_API_KEY) || empty(AI_API_URL)) {
                // Return a simulated response for demo purposes
                $response = [
                    'success' => true,
                    'summary' => generate_simulated_summary($content),
                    'key_points' => generate_simulated_key_points(),
                    'definitions' => generate_simulated_definitions(),
                    'exam_questions' => generate_simulated_exam_questions(),
                    'simulated' => true
                ];
            } else {
                // Make actual API call
                $response = call_ai_summarizer($content, $course_id);
            }
            break;

        default:
            $response['message'] = 'Invalid action';
    }
} catch (Exception $e) {
    error_log("AI API error: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;

/* ============================================================
 * GRADUATED HINT SYSTEM - TUTOR STATE MACHINE
 *
 * Levels:
 *   1 - Guiding questions, points toward relevant concepts
 *   2 - Conceptual clues, suggests formula/method/approach
 *   3 - Stronger hints and partial solution
 *   4 - Full solution and reasoning process
 *
 * The full answer is ONLY delivered by tutor_reveal() when the
 * student explicitly requests it or has exhausted all levels.
 * ============================================================ */

/**
 * Start a new tutoring session and deliver the Level 1 hint
 */
function tutor_start($input) {
    $question = trim($input['question'] ?? '');
    $course_id = $input['course_id'] ?? null;

    if ($course_id === '' || $course_id === '0') {
        $course_id = null;
    }

    if (empty($question)) {
        return ['success' => false, 'message' => 'Question is required'];
    }

    // Close any previous unfinished session before starting a new one
    tutor_close_session(false);

    $db_id = tutor_log_question($question, $course_id);

    $_SESSION['tutor_session'] = [
        'question' => $question,
        'course_id' => $course_id,
        'level' => 0,
        'revealed' => false,
        'db_id' => $db_id,
        'started_at' => time(),
    ];

    return tutor_deliver_level(1, false);
}

/**
 * Advance to the next hint level (max TUTOR_MAX_LEVEL)
 */
function tutor_next_hint() {
    $session = get_tutor_session();

    if (!$session) {
        return ['success' => false, 'message' => 'No active tutoring session. Ask a question first.'];
    }
    if ($session['revealed']) {
        return ['success' => false, 'message' => 'The full answer was already revealed. Start a new question.'];
    }
    if ($session['level'] >= TUTOR_MAX_LEVEL) {
        return [
            'success' => true,
            'exhausted' => true,
            'level' => TUTOR_MAX_LEVEL,
            'max_level' => TUTOR_MAX_LEVEL,
            'hint' => null,
            'message' => 'All hint levels have been used. You can now view the full answer.',
        ];
    }

    return tutor_deliver_level($session['level'] + 1, $session['revealed']);
}

/**
 * Reveal the full solution. Available from the very first hint so the
 * student always has the complete answer at hand when they want it.
 */
function tutor_reveal($explicit) {
    $session = get_tutor_session();

    if (!$session) {
        return ['success' => false, 'message' => 'No active tutoring session. Ask a question first.'];
    }
    if ($session['revealed']) {
        return tutor_full_answer_payload($session, false);
    }

    $answer = tutor_generate_answer($session['question'], $session);
    $exhausted = $session['level'] >= TUTOR_MAX_LEVEL;

    $_SESSION['tutor_session']['revealed'] = true;
    tutor_update_record($_SESSION['tutor_session']['db_id'], $session['level'], 1);

    return [
        'success' => true,
        'answer' => $answer,
        'level' => $session['level'],
        'max_level' => TUTOR_MAX_LEVEL,
        'exhausted' => $exhausted,
        'revealed_early' => !$exhausted,
        'simulated' => is_tutor_simulated(),
    ];
}

/**
 * Return current session state (used to restore the UI)
 */
function tutor_state() {
    $session = get_tutor_session();
    $stats = tutor_user_stats();

    if (!$session) {
        return ['success' => true, 'active' => false, 'stats' => $stats];
    }

    return [
        'success' => true,
        'active' => true,
        'question' => $session['question'],
        'level' => $session['level'],
        'max_level' => TUTOR_MAX_LEVEL,
        'revealed' => $session['revealed'],
        'stats' => $stats,
    ];
}

/**
 * Generate and store the hint for a given level, then build the payload
 */
function tutor_deliver_level($level, $revealed) {
    $session = get_tutor_session();
    $question = $session['question'];
    $avg_level = $session['stats']['avg_level'] ?? null;

    $hint = is_tutor_simulated()
        ? generate_simulated_hint($question, $level)
        : call_ai_tutor_hint($question, $level, $session['course_id'], $avg_level);

    if (!$hint) {
        return ['success' => false, 'message' => 'Failed to generate hint. Please try again.'];
    }

    $_SESSION['tutor_session']['level'] = $level;

return [
        'success' => true,
        'level' => $level,
        'max_level' => TUTOR_MAX_LEVEL,
        'hint' => $hint,
        'exhausted' => $level >= TUTOR_MAX_LEVEL,
        'can_reveal' => true,
        'revealed' => $revealed,
        'simulated' => is_tutor_simulated(),
    ];
}

function tutor_full_answer_payload($session, $exhausted) {
    return [
        'success' => true,
        'already_revealed' => true,
        'answer' => tutor_generate_answer($session['question'], $session),
        'level' => $session['level'],
        'max_level' => TUTOR_MAX_LEVEL,
        'exhausted' => $exhausted,
        'revealed_early' => !$exhausted,
        'simulated' => is_tutor_simulated(),
    ];
}

/**
 * Evaluate the student's attempt against the current question.
 *
 * Responses carry:
 *   verdict       - "correct" | "partial" | "incorrect"
 *   confidence    - AI's confidence in the verdict (0-100)
 *   feedback      - warm, specific feedback on the attempt
 *   correct_answer - full answer included whenever verdict != "correct"
 */
function tutor_check_answer($input) {
    $session = get_tutor_session();

    if (!$session) {
        return ['success' => false, 'message' => 'No active tutoring session. Ask a question first.'];
    }

    $answer = trim($input['answer'] ?? '');
    if ($answer === '') {
        return ['success' => false, 'message' => 'Please type an answer first.'];
    }
    if (mb_strlen($answer) > 4000) {
        return ['success' => false, 'message' => 'Your answer is a little long - try to keep it focused.'];
    }

    $question = $session['question'];

    if (!is_tutor_simulated()) {
        $result = call_ai_tutor_check($question, $answer);
        if ($result !== null) {
            return array_merge(['success' => true, 'simulated' => false], $result);
        }
    }

    $result = tutor_simulated_check($question, $answer);
    if (($result['verdict'] ?? '') !== 'correct') {
        $result['correct_answer'] = tutor_generate_answer($question, $session);
    }

    return array_merge(['success' => true, 'simulated' => true], $result);
}

/**
 * Real AI mode - ask the model to grade the attempt and return strict JSON.
 */
function call_ai_tutor_check($question, $answer) {
    $system = "You are a supportive, encouraging AI tutor grading a student's answer to a practice question. "
        . "Judge whether their answer shows they genuinely understand the core idea well enough to be marked correct. Be generous but honest. "
        . 'Respond with STRICT JSON only - no markdown fences, no commentary - using exactly this schema: '
        . '{"correct": boolean, "partial": boolean, "confidence": integer 0-100, '
        . '"feedback": "2-4 warm sentences that reference what they got right or what is missing", '
        . '"correct_answer": "the full correct answer - required whenever correct is false, otherwise an empty string"}';

    $data = [
        'model' => AI_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => "Question:\n{$question}\n\nStudent's answer:\n{$answer}"],
        ],
        'max_tokens' => 1000,
        'temperature' => 0.4,
    ];

    $raw = call_ai_api($data, 45);
    if ($raw === null) {
        return null;
    }

    // Strip possible code fences before decoding
    $clean = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($raw));
    $decoded = json_decode($clean, true);
    if (!is_array($decoded)) {
        return null;
    }

    $correctFlag = !empty($decoded['correct']);
    $partialFlag = !empty($decoded['partial']);
    $verdict = $correctFlag ? 'correct' : ($partialFlag ? 'partial' : 'incorrect');
    $confidence = max(0, min(100, (int) round((float) ($decoded['confidence'] ?? ($correctFlag ? 80 : 30)))));
    $correctAnswer = trim((string) ($decoded['correct_answer'] ?? ''));

    return [
        'verdict' => $verdict,
        'confidence' => $confidence,
        'feedback' => trim((string) ($decoded['feedback'] ?? '')),
        'correct_answer' => ($verdict !== 'correct' && $correctAnswer !== '') ? $correctAnswer : '',
    ];
}

/**
 * Simulated mode - heuristic check using the detected topic profile.
 */
function tutor_simulated_check($question, $answer) {
    $profile = detect_question_profile($question);
    $ans = strtolower(trim($answer));
    $concepts = array_slice($profile['concepts'], 0, 3);
    $hits = [];

    foreach ($concepts as $concept) {
        $words = preg_split('/\s+/', strtolower(trim($concept)));
        foreach ($words as $w) {
            $w = trim($w);
            if (strlen($w) > 3 && strpos($ans, $w) !== false) {
                $hits[] = $concept;
                break;
            }
        }
    }

    $ratio = count($hits) / count($concepts);
    $name = $_SESSION['user_name'] ?? 'there';

    if ($ratio >= 0.67) {
        $verdict = 'correct';
        $confidence = min(96, 70 + (int) round($ratio * 35));
        $feedback = "Spot on, {$name}! Your answer captures the core ideas (" . implode(', ', $hits) . "). "
            . "You clearly got the point of the hint - hold on to that reasoning for similar problems. 🙌";
    } elseif ($ratio >= 0.34) {
        $verdict = 'partial';
        $confidence = 40 + (int) round($ratio * 30);
        $missing = array_values(array_diff($concepts, $hits));
        $feedback = "You're on the right track, {$name}! You mentioned " . implode(', ', $hits) . " - "
            . "now think about how " . implode(', ', $missing) . " also fit in. Tighten your answer and try again.";
    } else {
        $verdict = 'incorrect';
        $confidence = 15 + (int) round($ratio * 25);
        $feedback = "Not quite yet, {$name} - but that's how real learning works! For \"" . $profile['topic'] . "\" the key ideas are: "
            . implode(', ', $concepts) . ". Have another go with the hint in front of you, or reveal the full answer below.";
    }

    return [
        'verdict' => $verdict,
        'confidence' => $confidence,
        'feedback' => $feedback,
    ];
}

/**
 * Conversational chat within an active tutoring session.
 *
 * The tutor decides whether the student's message is an attempt at the
 * question (validates it, with confidence + correct answer) or a general
 * follow-up (answers conversationally). Response shape:
 *   type          - "answer_check" | "chat"
 *   verdict       - "correct" | "partial" | "incorrect" (answer_check only)
 *   confidence    - 0-100 (answer_check only)
 *   feedback      - verdict feedback (answer_check only)
 *   correct_answer - full answer when verdict != correct
 *   reply         - conversational text (chat only)
 */
function tutor_chat($input) {
    $session = get_tutor_session();

    if (!$session) {
        return ['success' => false, 'message' => 'Start a session by asking a question first.'];
    }

    $message = trim($input['message'] ?? '');
    if ($message === '') {
        return ['success' => false, 'message' => 'Type a message first.'];
    }
    if (mb_strlen($message) > 4000) {
        return ['success' => false, 'message' => 'That message is a little long - try to keep it focused.'];
    }

    $question = $session['question'];

    if (!is_tutor_simulated()) {
        $result = call_ai_tutor_chat($question, $message);
        if ($result !== null) {
            return array_merge(['success' => true, 'simulated' => false], tutor_chat_finalize($result, $question, $session));
        }
    }

    return array_merge(['success' => true, 'simulated' => true], tutor_chat_finalize(tutor_simulated_chat($question, $message), $question, $session));
}

/**
 * Attach the complete answer whenever a wrong/partial attempt was graded.
 */
function tutor_chat_finalize($result, $question, $session) {
    if (($result['type'] ?? '') === 'answer_check' && ($result['verdict'] ?? '') !== 'correct' && empty($result['correct_answer'])) {
        $result['correct_answer'] = tutor_generate_answer($question, $session);
    }
    if (($result['type'] ?? '') === 'answer_check') {
        $result['reply'] = '';
    } else {
        $result['verdict'] = '';
        $result['confidence'] = null;
        $result['feedback'] = '';
        $result['correct_answer'] = '';
    }
    return $result;
}

/**
 * Real AI mode - the model classifies the message and responds in strict JSON.
 */
function call_ai_tutor_chat($question, $message) {
    $system = "You are a warm, engaging AI tutor inside a study app. The student asked this question earlier in the session: \"{$question}\". "
        . "Their new message is either (a) an attempt to answer the practice question, or (b) a general follow-up / clarifying question. "
        . "Classify it and respond ONLY with strict JSON - no markdown fences, no commentary - using exactly this schema: "
        . '{"type": "answer_check" or "chat", "correct": boolean, "partial": boolean, "confidence": integer 0-100, '
        . '"feedback": "when answering a check: 2-4 warm sentences referencing what they got right or what is missing", '
        . '"reply": "when type is chat: a helpful conversational reply, 2-5 sentences, that pushes their understanding without giving everything away", '
        . '"correct_answer": "when the attempt is wrong or partially wrong, include the full correct answer; otherwise empty string"}. '
        . 'For an answer_check type: correct=true means the attempt shows real understanding, partial=true means it is close but incomplete.';

    $data = [
        'model' => AI_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => "The question:\n{$question}\n\nStudent's new message:\n{$message}"],
        ],
        'max_tokens' => 1200,
        'temperature' => 0.5,
    ];

    $raw = call_ai_api($data, 45);
    if ($raw === null) {
        return null;
    }

    $clean = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($raw));
    $decoded = json_decode($clean, true);
    if (!is_array($decoded)) {
        return null;
    }

    $type = ($decoded['type'] ?? 'chat') === 'answer_check' ? 'answer_check' : 'chat';
    $correctFlag = !empty($decoded['correct']);
    $partialFlag = !empty($decoded['partial']);
    $confidence = max(0, min(100, (int) round((float) ($decoded['confidence'] ?? ($correctFlag ? 80 : 30)))));

    return [
        'type' => $type,
        'verdict' => $type === 'answer_check' ? ($correctFlag ? 'correct' : ($partialFlag ? 'partial' : 'incorrect')) : '',
        'confidence' => $type === 'answer_check' ? $confidence : null,
        'feedback' => $type === 'answer_check' ? trim((string) ($decoded['feedback'] ?? '')) : '',
        'reply' => $type === 'chat' ? trim((string) ($decoded['reply'] ?? '')) : '',
        'correct_answer' => $type === 'answer_check' && !$correctFlag ? trim((string) ($decoded['correct_answer'] ?? '')) : '',
    ];
}

/**
 * Simulated mode - decide attempt vs chat using the topic profile.
 */
function tutor_simulated_chat($question, $message) {
    $profile = detect_question_profile($question);
    $ans = strtolower(trim($message));
    $concepts = array_slice($profile['concepts'], 0, 3);
    $hits = [];

    foreach ($concepts as $concept) {
        $words = preg_split('/\s+/', strtolower(trim($concept)));
        foreach ($words as $w) {
            $w = trim($w);
            if (strlen($w) > 3 && strpos($ans, $w) !== false) {
                $hits[] = $concept;
                break;
            }
        }
    }

    $ratio = count($hits) / count($concepts);
    $name = $_SESSION['user_name'] ?? 'there';

    if ($ratio >= 0.34) {
        // Looks like an answer attempt - reuse the verdict logic
        if ($ratio >= 0.67) {
            $verdict = 'correct';
            $confidence = min(96, 70 + (int) round($ratio * 35));
            $feedback = "Spot on, {$name}! Your answer captures the core ideas (" . implode(', ', $hits) . "). "
                . "You clearly got the point of the hint - hold on to that reasoning for similar problems. 🙌";
        } else {
            $verdict = 'partial';
            $confidence = 40 + (int) round($ratio * 30);
            $missing = array_values(array_diff($concepts, $hits));
            $feedback = "You're on the right track, {$name}! You mentioned " . implode(', ', $hits) . " - "
                . "now think about how " . implode(', ', $missing) . " also fit in. Tighten your answer and try again.";
        }

        return [
            'type' => 'answer_check',
            'verdict' => $verdict,
            'confidence' => $confidence,
            'feedback' => $feedback,
            'reply' => '',
            'correct_answer' => '',
        ];
    }

    // Conversational follow-up
    $reply = "Good follow-up, {$name}! 🗨️\n\n"
        . "Revisiting \"" . $profile['topic'] . "\", the key is to " . $profile['method'] . ".\n\n"
        . "If you'd like, tell me how you'd approach it so far and I can check whether you're on the right track - "
        . "or hit \"Complete Answer\" any time to see the full solution.";

    return [
        'type' => 'chat',
        'verdict' => '',
        'confidence' => null,
        'feedback' => '',
        'reply' => $reply,
        'correct_answer' => '',
    ];
}

function get_tutor_session() {
    if (empty($_SESSION['tutor_session']) || empty($_SESSION['tutor_session']['question'])) {
        return null;
    }
    $session = $_SESSION['tutor_session'];
    $session['stats'] = tutor_user_stats();
    return $session;
}

function is_tutor_simulated() {
    return empty(AI_API_KEY) || empty(AI_API_URL);
}

/**
 * Per-user hint statistics used for adaptive difficulty
 */
function tutor_user_stats() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = ['total_questions' => 0, 'avg_level' => null, 'reveal_rate' => null];

    $db = get_db_connection();
    if (!$db) {
        return $cache;
    }

    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) AS total,
                   AVG(final_level) AS avg_level,
                   AVG(answer_revealed) AS reveal_rate
            FROM ai_tutor_questions
            WHERE user_id = ? AND final_level > 0
        ");
        $stmt->execute([get_current_user_id()]);
        $row = $stmt->fetch();

        if ($row && $row['total'] > 0) {
            $cache['total_questions'] = (int) $row['total'];
            $cache['avg_level'] = round((float) $row['avg_level'], 1);
            $cache['reveal_rate'] = round((float) $row['reveal_rate'] * 100);
        }
    } catch (PDOException $e) {
        error_log("Tutor stats error: " . $e->getMessage());
    }

    return $cache;
}

function tutor_log_question($question, $course_id) {
    $db = get_db_connection();
    if (!$db) {
        return null;
    }
    try {
        $stmt = $db->prepare("INSERT INTO ai_tutor_questions (user_id, course_id, question) VALUES (?, ?, ?)");
        $stmt->execute([get_current_user_id(), $course_id, $question]);
        
        // Platform analytics: record the tutoring session start
        if (class_exists('Auth')) {
            Auth::log_activity(get_current_user_id(), 'ai_session', $course_id, mb_substr($question, 0, 120));
        }
        
        return $db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Tutor log error: " . $e->getMessage());
        return null;
    }
}

function tutor_update_record($db_id, $final_level, $revealed) {
    if (!$db_id) {
        return;
    }
    $db = get_db_connection();
    if (!$db) {
        return;
    }
    try {
        $stmt = $db->prepare("UPDATE ai_tutor_questions SET final_level = ?, answer_revealed = ? WHERE id = ?");
        $stmt->execute([$final_level, $revealed, $db_id]);
    } catch (PDOException $e) {
        error_log("Tutor update error: " . $e->getMessage());
    }
}

/**
 * Close the session record without revealing (student moved on)
 */
function tutor_close_session($mark_revealed) {
    $session = get_tutor_session();
    if ($session && !$session['revealed'] && $session['level'] > 0) {
        tutor_update_record($session['db_id'], $session['level'], 0);
    }
    unset($_SESSION['tutor_session']);
}

/* ============================================================
 * SIMULATED HINT ENGINE (demo mode - no API key required)
 * ============================================================ */

/**
 * Topic profiles power realistic level-appropriate hints
 */
function detect_question_profile($question) {
    $q = strtolower($question);

    $profiles = [
        'recursion' => [
            'match' => ['recursion', 'recursive'],
            'topic' => 'recursion',
            'concepts' => ['base case', 'recursive case', 'call stack'],
            'method' => 'identify what the smallest input looks like, then define the problem in terms of a smaller version of itself',
            'steps' => [
                'Determine the base case (the simplest input where the answer is known immediately)',
                'Define the recursive case: how does f(n) relate to f(n-1) or a smaller subproblem?',
                'Trace one small example by hand to confirm both cases work together',
                'Combine and verify the result against a known value',
            ],
            'solution' => "A recursive function needs two parts:\n\n1. Base case - the smallest input with a directly known answer (prevents infinite calls).\n2. Recursive case - express the problem using a smaller version of itself, trusting the recursion to return the correct result.\n\nEach call pushes a frame onto the call stack until a base case is hit, then results bubble back up. Verify by tracing a small example (e.g., factorial(3) = 3 * factorial(2) = 3 * 2 * factorial(1) = 6).",
        ],
        'sorting' => [
            'match' => ['sort', 'bubble', 'quick sort', 'quicksort', 'merge sort', 'insertion'],
            'topic' => 'sorting algorithms',
            'concepts' => ['time complexity', 'comparison-based ordering', 'divide and conquer'],
            'method' => 'compare how elements are compared and swapped, then reason about how many passes are required',
            'steps' => [
                'Identify which pairs of elements get compared in each pass',
                'Determine what changes after each pass (what is guaranteed to be in place?)',
                'Count the worst-case number of comparisons as a function of n',
                'State the resulting time complexity and when this algorithm performs best/worst',
            ],
            'solution' => "Work through the algorithm's invariant first: after k passes, at least k elements are in their final position.\n\nEach pass compares adjacent (or partitioned) elements and swaps when out of order. The worst case requires comparing every element with every other candidate, giving O(n²) comparisons for simple sorts like bubble/insertion sort. Divide-and-conquer sorts (merge, quick) split the data log n times and do O(n) work per level, yielding O(n log n). Choose based on data size, memory limits, and whether the data is nearly sorted.",
        ],
        'database' => [
            'match' => ['sql', 'database', 'query', 'join', 'normaliz', 'primary key', 'foreign key', 'index'],
            'topic' => 'databases',
            'concepts' => ['tables and relationships', 'keys', 'normalization', 'joins'],
            'method' => 'start from the entities involved, identify their keys, then decide how rows from different tables relate',
            'steps' => [
                'List the entities (tables) involved and their primary keys',
                'Decide the relationship type between them (one-to-one, one-to-many, many-to-many)',
                'Write the condition that matches rows across tables (JOIN ON)',
                'Apply filtering/aggregation and verify with sample rows',
            ],
            'solution' => "Start by identifying each entity and its primary key. Foreign keys express relationships: place the key of the \"one\" side inside the table on the \"many\" side; resolve many-to-many with a junction table.\n\nA JOIN combines rows where the ON condition holds (usually foreign key = primary key). Filter with WHERE before aggregation, HAVING after. Normalize to reduce redundancy (1NF atomic values, 2NF full-key dependency, 3NF no transitive dependency), verifying each step against your sample data.",
        ],
        'oop' => [
            'match' => ['class', 'object', 'inheritance', 'polymorphism', 'encapsulation', 'oop'],
            'topic' => 'object-oriented programming',
            'concepts' => ['classes vs objects', 'encapsulation', 'inheritance', 'polymorphism'],
            'method' => 'model the real-world nouns as classes, decide what state they hold and what behaviour they expose',
            'steps' => [
                'Identify the noun(s): what data (attributes) does each object hold?',
                'Define the behaviour: what methods operate on that data?',
                'Consider visibility: what should be private vs public (encapsulation)?',
                'Check for shared behaviour that belongs in a parent class (inheritance/polymorphism)',
            ],
            'solution' => "Model each concept as a class holding its state in attributes and its behaviour in methods.\n\nEncapsulation keeps attributes private and exposes controlled access through public methods, protecting invariants. Inheritance lets a child class reuse and extend a parent's behaviour; polymorphism means the same method call can behave differently depending on the object's actual class at runtime. Apply the principle only where it reduces duplication - prefer composition when an \"is-a\" relationship does not truly hold.",
        ],
        'algorithm-complexity' => [
            'match' => ['complexity', 'big o', 'big-o', 'time complexity', 'efficien'],
            'topic' => 'algorithm complexity',
            'concepts' => ['growth rate', 'worst/average/best case', 'dominant term'],
            'method' => 'count how the number of operations grows as the input size n grows, keeping only the dominant term',
            'steps' => [
                'Identify the loops and how many times each runs relative to n',
                'Nested loops multiply; sequential blocks add up',
                'Drop constants and lower-order terms',
                'Express the result in Big-O and sanity-check with n=10 vs n=100',
            ],
            'solution' => "Count operations as a function of input size n.\n\nA single loop over n items contributes O(n); a loop nested inside another contributes O(n × n) = O(n²); halving the problem each step (binary search) contributes O(log n). When terms combine, keep only the fastest-growing one and drop constant factors: 3n² + 5n + 7 → O(n²). Sanity-check by doubling n: O(n) doubles runtime, O(n²) quadruples it, O(log n) barely changes.",
        ],
        'networking' => [
            'match' => ['tcp', 'udp', 'http', 'network', 'osi', 'protocol', 'ip address'],
            'topic' => 'computer networking',
            'concepts' => ['layered model', 'protocols', 'reliability vs speed'],
            'method' => 'place the question within its networking layer, then consider what guarantees that layer must provide',
            'steps' => [
                'Which layer does this concern (application, transport, network, link)?',
                'What does the relevant protocol guarantee - and what does it NOT?',
                'Compare the alternatives (e.g., TCP vs UDP) on reliability, ordering, overhead',
                'Justify which protocol fits the described scenario',
            ],
            'solution' => "First locate the layer involved, since each layer solves a different problem.\n\nThe transport layer moves data between processes: TCP adds connection setup, acknowledgements, retransmission and ordered delivery (reliable but heavier); UDP sends datagrams with no guarantees (fast, minimal overhead). Application protocols like HTTP sit above transport and define message semantics. For loss-tolerant, latency-sensitive traffic choose UDP; for anything needing completeness and order choose TCP.",
        ],
        'math-derivative' => [
            'match' => ['derivative', 'differentiat', 'integral', 'integrat', 'limit', 'calculus'],
            'topic' => 'calculus',
            'concepts' => ['rate of change', 'limits', 'power rule', 'chain rule'],
            'method' => 'rewrite the expression in a form where standard rules apply, then differentiate/integrate term by term',
            'steps' => [
                'Simplify/expand the expression first if possible',
                'Identify which rule applies to each term (power, product, chain)',
                'Differentiate or integrate term by term',
                'Check the result (e.g., derivative of the integral should return the original)',
            ],
            'solution' => "Rewrite the expression into standard form first - expand products, split fractions, convert roots to exponents.\n\nThen apply the rules term by term: power rule d/dx xⁿ = n·xⁿ⁻¹, product/quotient rules for combinations, and the chain rule whenever one function sits inside another (outer derivative × inner derivative). For definite integrals evaluate F(b) − F(a). Always sanity-check: differentiate your integration result or test a point numerically.",
        ],
        'programming-bug' => [
            'match' => ['error', 'bug', 'not working', 'undefined', 'exception', 'null', 'crash'],
            'topic' => 'debugging',
            'concepts' => ['reading error messages', 'isolating the failing line', 'checking assumptions'],
            'method' => 'reproduce the error reliably, read the exact message and stack trace, then test assumptions one by one',
            'steps' => [
                'Reproduce the bug with the smallest possible input',
                'Read the error message literally - which line/file/value does it name?',
                'Print/log the values just before the failure point',
                'Fix the smallest wrong assumption first, then re-test',
            ],
            'solution' => "Debug systematically instead of guessing:\n\n1. Reproduce deterministically with minimal input.\n2. Read the error message precisely - it names the file, line, and expectation that failed.\n3. Inspect every variable involved right before the failing line; the first value that differs from what you assumed is usually the culprit (typical causes: off-by-one indexes, uninitialized/null values, wrong types).\n4. Fix that single assumption, rerun, and repeat until clean. Add a regression test so the bug cannot silently return.",
        ],
    ];

    foreach ($profiles as $profile) {
        foreach ($profile['match'] as $keyword) {
            if (strpos($q, $keyword) !== false) {
                return $profile;
            }
        }
    }

    // Generic profile derived from the question itself
    $subject = extract_subject($question);

    return [
        'topic' => $subject,
        'concepts' => ['the core definition', 'why/how it works', 'a concrete example', 'common misconceptions'],
        'method' => 'break the question down: define the key terms, identify what is given and what is asked, then connect them step by step',
        'steps' => [
            'Restate the problem in your own words - what exactly is unknown?',
            'List the definitions/concepts the problem depends on',
            'Work a small, concrete example by hand',
            'Generalize from the example back to the general case',
        ],
        'solution' => "Approach \"" . $subject . "\" systematically:\n\n1. Define every key term in the question precisely.\n2. Identify what information is given and what result is required.\n3. Connect them using the relevant principles/rules, one step at a time.\n4. Validate the outcome with a small example and check units, edge cases, and boundaries.\n\nThis structure - define, plan, execute, verify - applies to nearly every academic problem in this subject area.",
    ];
}

function extract_subject($question) {
    $clean = preg_replace('/^(what|who|why|how|when|where|explain|describe|define|tell me about)\s+(is|are|do|does|did|the|a|an)?/i', '', trim($question));
    $clean = preg_replace('/\s+/', ' ', trim(preg_replace('/[^a-z0-9\s\-]/i', '', $clean)));
    $words = explode(' ', $clean);
    $words = array_slice($words, 0, 5);
    $subject = implode(' ', $words);

    if ($subject === '' || strlen($subject) < 3) {
        $subject = trim($question);
    }

    return strtolower(strlen($subject) > 60 ? substr($subject, 0, 57) . '...' : rtrim($subject, '?') );
}

/**
 * Level-specific simulated hints
 */
function generate_simulated_hint($question, $level) {
    $profile = detect_question_profile($question);
    $topic = $profile['topic'];
    $name = $_SESSION['user_name'] ?? 'there';

    switch ($level) {
        case 1:
            $concepts = implode(', ', array_slice($profile['concepts'], 0, 3));
            return "Good question, {$name} - let's work through it together rather than jumping to the answer. 🙂\n\n"
                . "First, ask yourself:\n"
                . "• In your own words, what is this problem really asking for?\n"
                . "• Which topics from your course does \"{$topic}\" connect to?\n\n"
                . "Relevant concepts to recall: {$concepts}. "
                . "Try writing down what you already know about each one, then request another hint when you're ready.";

        case 2:
            return "Here's a conceptual clue for \"{$topic}\":\n\n"
                . "• The key is to " . $profile['method'] . ".\n"
                . "• Think about which definition or rule acts as the bridge between what you're given and what you need.\n"
                . "• Sketch the overall approach as 2-3 bullet points before touching any details.\n\n"
                . "If you can describe the approach out loud, you understand the concept - move on to the next hint to sharpen it.";

        case 3:
            $steps = $profile['steps'];
            $partial = "- Step 1: {$steps[0]}\n- Step 2: {$steps[1]}";
            return "Let's get concrete. Here is the start of the solution - notice I've left the final steps for you:\n\n{$partial}\n\n"
                . "Your turn: complete steps 3 and 4 yourself:\n"
                . "- Step 3: " . str_replace(['Complete ', 'Finish '], '', $steps[2]) . "\n"
                . "- Step 4: " . str_replace(['Complete ', 'Finish ', 'State ', 'Combine and verify'], '', $steps[3]) . "\n\n"
                . "One more hint will reveal the full reasoning if you still need it.";

        case 4:
            $full = '';
            foreach ($profile['steps'] as $i => $step) {
                $full .= '- Step ' . ($i + 1) . ": {$step}\n";
            }
            return "You've persisted through every hint level - here is the complete walkthrough for \"{$topic}\":\n\n{$full}\n"
                . "Notice how each step builds on the previous one. Being able to reproduce this reasoning unaided is the real goal - try explaining it back, or test yourself with a similar problem.";
    }

    return null;
}

/**
 * Full solution - real API when configured, simulated engine otherwise
 */
function tutor_generate_answer($question, $session) {
    if (!is_tutor_simulated()) {
        $answer = call_ai_tutor_answer($question, $session['course_id'] ?? null);
        if ($answer !== null) {
            return $answer;
        }
        // Fall back to the simulated walkthrough if the API fails
    }

    $profile = detect_question_profile($question);

    return "Full solution for \"{$profile['topic']}\":\n\n"
        . $profile['solution'] . "\n\n"
        . "Why this works: we defined the key concepts, applied the appropriate method, and verified the result. "
        . "Review each hint level again and note where you got stuck - that's the exact spot to revise in your notes.";
}

/* ============================================================
 * REAL AI MODE - level-engineered prompts
 * ============================================================ */

/**
 * Call AI API for a specific hint level
 */
function call_ai_tutor_hint($question, $level, $course_id = null, $avg_level = null) {
    $prompts = [
        1 => "Give ONLY guiding questions that help the student think about the problem. Do NOT explain the solution or give any part of the answer. Point them toward the relevant concepts they should recall. Keep it under 120 words, warm and encouraging.",
        2 => "Give conceptual clues only: suggest which formula, method or approach applies and why it fits. Do NOT work any part of the actual solution. Under 130 words.",
        3 => "Give a strong partial solution: lay out the first half of the steps clearly, then stop and hand the remaining steps back to the student with a pointer for each. Never state the final result. Under 160 words.",
        4 => "Explain the complete solution step by step with the full reasoning process, ending with the final result. Still teach - briefly note why each step matters. Under 250 words.",
    ];

    $system = "You are a Socratic AI tutor inside a study app. Your core rule: guide discovery, never hand over answers prematurely. "
        . "The student is receiving hint LEVEL {$level} of 4. Follow this instruction strictly: {$prompts[$level]}";

    if ($avg_level !== null) {
        $adapt = $avg_level <= 1.5
            ? "This student typically solves problems with very few hints - challenge them to think harder before giving ground."
            : (($avg_level >= 3.5)
                ? "This student often needs most hint levels - be extra encouraging and make hints slightly more concrete."
                : "This student averages about {$avg_level} hints per problem.");
        $system .= " Adaptation note: {$adapt}";
    }

    $data = [
        'model' => AI_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $question],
        ],
        'max_tokens' => 1500,
        'temperature' => 0.7,
    ];

    $result = call_ai_api($data, 30);
    if ($result === null) {
        return null;
    }

    return $result;
}

/**
 * Call AI API for the full solution
 */
function call_ai_tutor_answer($question, $course_id = null) {
    $system = "You are a helpful AI tutor. The student has either exhausted all graduated hints or explicitly requested the answer. "
        . "Provide the complete solution with clear step-by-step reasoning, then add one short revision tip.";

    $data = [
        'model' => AI_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $question],
        ],
        'max_tokens' => 2500,
        'temperature' => 0.5,
    ];

    return call_ai_api($data, 45);
}

/**
 * Shared cURL call helper lives in utils/AiClient.php (single AI integration point)
 */

/**
 * Persist a generated summary for platform analytics (best-effort, never breaks the response)
 */
function ai_persist_summary($structured, $source_text, $source_type = 'text', $source_name = null, $course_id = null) {
    try {
        $db = get_db_connection();
        if (!$db || !is_logged_in()) {
            return;
        }
        $combined = trim(
            ($structured['summary'] ?? '') . "\n\n" .
            'Key Points: ' . implode('; ', (array)($structured['key_points'] ?? []))
        );
        $stmt = $db->prepare("INSERT INTO summaries (user_id, course_id, source_type, source_name, summary) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([get_current_user_id(), $course_id ?: null, $source_type, $source_name, $combined]);
        
        if (class_exists('Auth')) {
            Auth::log_activity(get_current_user_id(), 'summary_generated', $course_id ?: null, ucfirst($source_type) . ' summary');
        }
    } catch (Exception $e) {
        error_log('Summary persist error: ' . $e->getMessage());
    }
}


/**
 * Call AI Summarizer API
 */
function call_ai_summarizer($content, $course_id = null) {
    try {
        $prompt = "Please summarize the following course material. Provide:\n1. A concise summary\n2. Key points\n3. Important definitions\n4. Possible exam questions\n\nContent: " . $content;

        $data = [
            'model' => AI_MODEL,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful AI that summarizes educational content.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 3000,
            'temperature' => 0.5
        ];

        $data = ai_sanitize_utf8($data);
        $body = json_encode($data);
        if (!is_string($body) || $body === '') {
            throw new Exception('AI Summarizer API error: failed to encode UTF-8 payload');
        }

        $ch = curl_init(AI_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AI_API_KEY
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        if (defined('CA_BUNDLE_PATH') && CA_BUNDLE_PATH && file_exists(CA_BUNDLE_PATH)) {
            curl_setopt($ch, CURLOPT_CAINFO, CA_BUNDLE_PATH);
        }

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception('AI API request failed');
        }

        $api_response = json_decode($result, true);
        $summary = $api_response['choices'][0]['message']['content'] ?? 'No summary generated';

        // Parse the response into structured format
        $structured = parse_summary_response($summary);
        
        // Persist for platform analytics (best-effort)
        ai_persist_summary($structured, $content, 'text', null, $course_id);
        
        return [
            'success' => true,
            'summary' => $structured['summary'],
            'key_points' => $structured['key_points'],
            'definitions' => $structured['definitions'],
            'exam_questions' => $structured['exam_questions'],
            'simulated' => false
        ];

    } catch (Exception $e) {
        error_log("AI Summarizer API error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to generate summary. Please try again.'
        ];
    }
}

/**
 * Parse summary response into structured format
 */
function parse_summary_response($response) {
    // Simple parsing - in production, you'd want more sophisticated parsing
    $lines = explode("\n", $response);

    $summary = '';
    $key_points = [];
    $definitions = [];
    $exam_questions = [];

    $current_section = 'summary';

    foreach ($lines as $line) {
        $line = trim($line);

        if (stripos($line, 'summary') !== false && strlen($line) < 20) {
            $current_section = 'summary';
            continue;
        }
        if (stripos($line, 'key points') !== false) {
            $current_section = 'key_points';
            continue;
        }
        if (stripos($line, 'definitions') !== false) {
            $current_section = 'definitions';
            continue;
        }
        if (stripos($line, 'exam questions') !== false) {
            $current_section = 'exam_questions';
            continue;
        }

        if (!empty($line)) {
            switch ($current_section) {
                case 'summary':
                    $summary .= $line . ' ';
                    break;
                case 'key_points':
                    if (preg_match('/^[\d\-\*•]/', $line)) {
                        $key_points[] = preg_replace('/^[\d\-\*•]\s*/', '', $line);
                    }
                    break;
                case 'definitions':
                    if (strpos($line, ':') !== false) {
                        $parts = explode(':', $line, 2);
                        $definitions[trim($parts[0])] = trim($parts[1]);
                    }
                    break;
                case 'exam_questions':
                    if (preg_match('/^[\d\-\*•]/', $line)) {
                        $exam_questions[] = preg_replace('/^[\d\-\*•]\s*/', '', $line);
                    }
                    break;
            }
        }
    }

    return [
        'summary' => trim($summary),
        'key_points' => $key_points,
        'definitions' => $definitions,
        'exam_questions' => $exam_questions
    ];
}

/* ============================================================
 * SIMULATED SUMMARIZER (demo mode)
 * ============================================================ */

function generate_simulated_summary($content) {
    return "This content covers key concepts related to the subject matter. The main focus is on understanding fundamental principles and their practical applications. The material provides a comprehensive overview of the topic, suitable for students at various levels of expertise.";
}

function generate_simulated_key_points() {
    return [
        "Understanding the fundamental concepts is crucial for mastery",
        "Practical application reinforces theoretical knowledge",
        "Regular practice and review improve retention",
        "Connecting concepts to real-world examples enhances understanding"
    ];
}

function generate_simulated_definitions() {
    return [
        "Algorithm" => "A step-by-step procedure for solving a problem or completing a task",
        "Data Structure" => "A way of organizing and storing data to enable efficient access and modification",
        "Complexity" => "A measure of the resources required by an algorithm, typically time or space"
    ];
}

function generate_simulated_exam_questions() {
    return [
        "Explain the main difference between X and Y in the context of this topic",
        "Describe a real-world scenario where this concept would be applicable",
        "What are the advantages and disadvantages of the approach discussed?",
        "How would you implement this concept in a practical situation?"
    ];
}

/* ============================================================
 * AI QUIZ GENERATION
 * ============================================================ */

/**
 * Generate multiple-choice questions for a topic
 */
function generate_quiz_questions($topic, $count) {
    if (!is_tutor_simulated()) {
        $questions = call_ai_generate_quiz($topic, $count);
        if ($questions !== null && count($questions) > 0) {
            return ['success' => true, 'questions' => $questions, 'simulated' => false];
        }
    }

    return ['success' => true, 'questions' => generate_simulated_quiz_questions($topic, $count), 'simulated' => true];
}

/**
 * Real AI mode - request strict JSON MCQ output
 */
function call_ai_generate_quiz($topic, $count) {
    $system = "You are an exam-question generator. Respond ONLY with valid JSON - no markdown fences, no commentary. "
        . 'Schema: [{"question": string, "option_a": string, "option_b": string, "option_c": string, "option_d": string, '
        . '"correct_answer": "a"|"b"|"c"|"d", "explanation": string}]';

    $data = [
        'model' => AI_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => "Generate {$count} multiple-choice exam questions about: {$topic}. Vary difficulty from recall to application. Each question must have exactly 4 options and one correct answer."],
        ],
        'max_tokens' => 3000,
        'temperature' => 0.7,
    ];

    $raw = call_ai_api($data, 60);
    if ($raw === null) {
        return null;
    }

    // Strip possible code fences before decoding
    $clean = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($raw));
    $decoded = json_decode($clean, true);

    if (!is_array($decoded)) {
        return null;
    }

    $questions = [];
    foreach ($decoded as $q) {
        $letter = strtolower(substr(trim((string) ($q['correct_answer'] ?? '')), 0, 1));
        if (
            isset($q['question'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'])
            && in_array($letter, ['a', 'b', 'c', 'd'], true)
        ) {
            $questions[] = [
                'question' => (string) $q['question'],
                'option_a' => (string) $q['option_a'],
                'option_b' => (string) $q['option_b'],
                'option_c' => (string) $q['option_c'],
                'option_d' => (string) $q['option_d'],
                'correct_answer' => $letter,
                'explanation' => (string) ($q['explanation'] ?? ''),
            ];
        }
    }

    return $questions;
}

/**
 * Simulated quiz generator (demo mode)
 * Produces topic-aware conceptual questions
 */
function generate_simulated_quiz_questions($topic, $count) {
    $profile = detect_question_profile($topic);

    $bank = [
        [
            'question' => "Which of the following best describes the core idea of {$profile['topic']}?",
            'options' => [
                "A technique unrelated to the main concepts involved",
                "A structured approach based on " . $profile['concepts'][0] . " and related principles",
                "A random process that requires no planning",
                "A deprecated practice with no modern use",
            ],
            'correct' => 1,
            'explanation' => "The core of {$profile['topic']} is to " . $profile['method'] . ".",
        ],
        [
            'question' => "What is typically the FIRST step when solving a problem involving {$profile['topic']}?",
            'options' => [
                "Memorising the final answer format",
                "Skipping directly to writing code or calculations",
                "Identifying key concepts such as " . $profile['concepts'][0] . " and what is being asked",
                "Ignoring any given constraints",
            ],
            'correct' => 2,
            'explanation' => "Understanding the problem and identifying relevant concepts always comes first.",
        ],
        [
            'question' => "Which concept is most relevant when working with {$profile['topic']}?",
            'options' => [
                $profile['concepts'][1],
                "Unrelated historical trivia",
                "None - it has no theoretical basis",
                "Only aesthetic considerations",
            ],
            'correct' => 0,
            'explanation' => "{$profile['concepts'][1]} is one of the foundational ideas behind {$profile['topic']}.",
        ],
        [
            'question' => "When verifying a solution about {$profile['topic']}, which approach is most reliable?",
            'options' => [
                "Assuming it works because it looks right",
                "Testing with a small concrete example and checking edge cases",
                "Avoiding any verification to save time",
                "Changing the problem until the solution fits",
            ],
            'correct' => 1,
            'explanation' => "Working a small example by hand validates reasoning before generalising.",
        ],
        [
            'question' => "A common mistake students make with {$profile['topic']} is:",
            'options' => [
                "Applying the method without understanding why each step works",
                "Reading definitions before attempting problems",
                "Practising with worked examples",
                "Asking for hints when stuck",
            ],
            'correct' => 0,
            'explanation' => "Mechanical application without understanding leads to errors on unfamiliar problems.",
        ],
        [
            'question' => "How does mastering {$profile['concepts'][2]} improve your work with {$profile['topic']}?",
            'options' => [
                "It has no practical impact",
                "It only helps in exams, never in practice",
                "It provides a deeper understanding that transfers to harder problems",
                "It replaces the need for practice entirely",
            ],
            'correct' => 2,
            'explanation' => "Deep mastery of core concepts like {$profile['concepts'][2]} builds transferable problem-solving skill.",
        ],
    ];

    $letters = ['a', 'b', 'c', 'd'];
    $questions = [];
    foreach (array_slice($bank, 0, min($count, count($bank))) as $item) {
        $questions[] = [
            'question' => $item['question'],
            'option_a' => $item['options'][0],
            'option_b' => $item['options'][1],
            'option_c' => $item['options'][2],
            'option_d' => $item['options'][3],
            'correct_answer' => $letters[$item['correct']],
            'explanation' => $item['explanation'],
        ];
    }

    return $questions;
}
