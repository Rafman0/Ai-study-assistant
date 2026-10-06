<?php
/**
 * Notes API endpoint
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
require_once __DIR__ . '/../models/Note.php';

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
            $notes = Note::get_user_notes($user_id, $course_id);
            $response = ['success' => true, 'notes' => $notes];
            break;
            
        case 'create':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $user_id = get_current_user_id();
            $result = Note::create_note($user_id, $input['title'], $input['content'], $input['course_id'] ?? null);
            $response = $result;
            break;
            
        case 'update':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $user_id = get_current_user_id();
            $result = Note::update_note($input['note_id'], $user_id, $input['title'], $input['content'], $input['course_id'] ?? null);
            $response = $result;
            break;
            
        case 'delete':
            if ($method !== 'POST' || !is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $user_id = get_current_user_id();
            $result = Note::delete_note($input['note_id'], $user_id);
            $response = $result;
            break;
            
        case 'search':
            if (!is_logged_in()) {
                throw new Exception('Unauthorized');
            }
            $user_id = get_current_user_id();
            $query = $_GET['query'] ?? '';
            $notes = Note::search_notes($user_id, $query);
            $response = ['success' => true, 'notes' => $notes];
            break;
            
        default:
            $response['message'] = 'Invalid action';
    }
} catch (Exception $e) {
    error_log("Notes API error: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
