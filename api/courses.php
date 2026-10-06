<?php
/**
 * Courses API endpoint
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
require_once __DIR__ . '/../models/Course.php';

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
            $courses = Course::get_user_courses($user_id);
            $response = ['success' => true, 'courses' => $courses];
            break;
            
        case 'create':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $user_id = get_current_user_id();
            $result = Course::create_course($user_id, $input['title'], $input['description'] ?? '', $input['code'] ?? null, $input['instructor'] ?? null);
            $response = $result;
            break;
            
        case 'update':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $user_id = get_current_user_id();
            $result = Course::update_course($input['course_id'], $user_id, $input['title'], $input['description'] ?? '', $input['code'] ?? null, $input['instructor'] ?? null);
            $response = $result;
            break;
            
        case 'delete':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $user_id = get_current_user_id();
            $result = Course::delete_course($input['course_id'], $user_id);
            $response = $result;
            break;
            
        default:
            $response['message'] = 'Invalid action';
    }
} catch (Exception $e) {
    error_log("Courses API error: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
