<?php
/**
 * Study Sessions API endpoint
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
require_once __DIR__ . '/../models/StudySession.php';

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
            $sessions = StudySession::get_user_sessions($user_id, $course_id);
            $response = ['success' => true, 'sessions' => $sessions];
            break;
            
        case 'create':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $user_id = get_current_user_id();
            $result = StudySession::create_session($user_id, $input['duration'], $input['topics'] ?? null, $input['notes'] ?? null, $input['course_id'] ?? null);
            $response = $result;
            break;
            
        case 'update':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $user_id = get_current_user_id();
            $result = StudySession::update_session($input['session_id'], $user_id, $input['duration'], $input['topics'] ?? null, $input['notes'] ?? null, $input['course_id'] ?? null);
            $response = $result;
            break;
            
        case 'delete':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $user_id = get_current_user_id();
            $result = StudySession::delete_session($input['session_id'], $user_id);
            $response = $result;
            break;
            
        case 'total_time':
            if (!is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $user_id = get_current_user_id();
            $total_time = StudySession::get_total_study_time($user_id);
            $response = ['success' => true, 'total_minutes' => $total_time];
            break;
            
        default:
            $response['message'] = 'Invalid action';
    }
} catch (Exception $e) {
    error_log("Study Sessions API error: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
