<?php
/**
 * Quizzes API endpoint
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
require_once __DIR__ . '/../models/Quiz.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$response = ['success' => false, 'message' => 'Invalid request'];

try {
    switch ($action) {
        case 'list':
            if (!is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $user_id = get_current_user_id();
            $course_id = $_GET['course_id'] ?? null;
            $quizzes = Quiz::get_user_quizzes($user_id, $course_id);
            $response = ['success' => true, 'quizzes' => $quizzes];
            break;
            
        case 'get':
            if (!is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $user_id = get_current_user_id();
            $quiz_id = $_GET['quiz_id'] ?? 0;
            $quiz = Quiz::get_quiz($quiz_id, $user_id);
            $response = ['success' => true, 'quiz' => $quiz];
            break;

        case 'take':
            if (!is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $user_id = get_current_user_id();
            $quiz_id = intval($_GET['quiz_id'] ?? 0);
            $quiz = Quiz::get_quiz_for_taking($quiz_id, $user_id);
            if (!$quiz) {
                throw new Exception('Quiz not found');
            }
            $response = ['success' => true, 'quiz' => $quiz];
            break;

        case 'grade':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $user_id = get_current_user_id();
            $answers = [];
            foreach ((array) ($input['answers'] ?? []) as $question_id => $letter) {
                $answers[intval($question_id)] = (string) $letter;
            }
            $result = Quiz::grade_and_save_result(intval($input['quiz_id']), $user_id, $answers);
            if (!empty($result['success']) && class_exists('Auth')) {
                Auth::log_activity($user_id, 'quiz_attempt', null, 'Quiz #' . intval($input['quiz_id']) . ' — ' . ($result['percentage'] ?? '?') . '%');
            }
            $response = $result;
            break;

        case 'delete':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $user_id = get_current_user_id();
            $result = Quiz::delete_quiz(intval($input['quiz_id']), $user_id);
            $response = $result;
            break;
            
        case 'create':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $user_id = get_current_user_id();
            $result = Quiz::create_quiz($user_id, $input['title'], $input['description'] ?? '', $input['questions'], $input['course_id'] ?? null);
            $response = $result;
            break;
            
        case 'submit_result':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $user_id = get_current_user_id();
            $result = Quiz::save_quiz_result($input['quiz_id'], $user_id, $input['score'], $input['total_questions'], $input['answers']);
            $response = $result;
            break;
            
        case 'results':
            if (!is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $user_id = get_current_user_id();
            $results = Quiz::get_user_results($user_id);
            $response = ['success' => true, 'results' => $results];
            break;
            
        default:
            $response['message'] = 'Invalid action';
    }
} catch (Exception $e) {
    error_log("Quizzes API error: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
