<?php
/**
 * Progress API endpoint
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
require_once __DIR__ . '/../models/Progress.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$response = ['success' => false, 'message' => 'Invalid request'];

try {
    switch ($action) {
        case 'get':
            if (!is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $user_id = get_current_user_id();
            $course_id = $_GET['course_id'] ?? null;
            $progress = Progress::get_user_progress($user_id, $course_id);
            $response = ['success' => true, 'progress' => $progress];
            break;
            
        case 'stats':
            if (!is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $user_id = get_current_user_id();
            $stats = Progress::get_overall_stats($user_id);
            $response = ['success' => true, 'stats' => $stats];
            break;
            
        case 'update_session':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $user_id = get_current_user_id();
            $result = Progress::update_study_session($user_id, $input['course_id'] ?? null, $input['duration'] ?? 0);
            $response = $result;
            break;
            
        default:
            $response['message'] = 'Invalid action';
    }
} catch (Exception $e) {
    error_log("Progress API error: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
