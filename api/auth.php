<?php
/**
 * Authentication API endpoint
 * Handles AJAX authentication requests
 */

// Allow cross-origin requests if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Set JSON content type
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Security.php';
require_once __DIR__ . '/../utils/Validator.php';

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get action from query parameter or POST data
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Initialize response
$response = [
    'success' => false,
    'message' => 'Invalid request'
];

try {
    switch ($action) {
        case 'register':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            
            // Get JSON input
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                $input = $_POST;
            }
            
            $name = trim($input['name'] ?? '');
            $email = trim($input['email'] ?? '');
            $password = $input['password'] ?? '';
            $confirm_password = $input['confirm_password'] ?? '';
            
            // Validate
            $validation = Validator::validate([
                'name' => ['required', 'min:2', 'max:255'],
                'email' => ['required', 'email'],
                'password' => ['required', 'min:8'],
                'confirm_password' => ['required']
            ], [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'confirm_password' => $confirm_password
            ]);
            
            if (!$validation['valid']) {
                $response['message'] = implode(', ', $validation['errors']);
            } elseif ($password !== $confirm_password) {
                $response['message'] = 'Passwords do not match';
            } else {
                $password_check = Security::validate_password($password);
                if (!$password_check['valid']) {
                    $response['message'] = $password_check['message'];
                } else {
                    $result = Auth::register($name, $email, $password);
                    $response = $result;
                }
            }
            break;
            
        case 'login':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            
            // Get JSON input
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                $input = $_POST;
            }
            
            $email = trim($input['email'] ?? '');
            $password = $input['password'] ?? '';
            $remember = isset($input['remember']);
            
            if (empty($email) || empty($password)) {
                $response['message'] = 'Email and password are required';
            } else {
                $rate_limit = Security::check_rate_limit('login_' . md5($email));
                if (!$rate_limit['allowed']) {
                    $response['message'] = 'Too many login attempts. Please try again later.';
                } else {
                    $result = Auth::login($email, $password, $remember);
                    if ($result['success']) {
                        Security::clear_rate_limit('login_' . md5($email));
                        
                        // Update last login
                        $db = get_db_connection();
                        if ($db) {
                            try {
                                $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                                $stmt->execute([get_current_user_id()]);
                            } catch (PDOException $e) {
                                error_log("Failed to update last login: " . $e->getMessage());
                            }
                        }
                    } else {
                        Security::record_attempt('login_' . md5($email));
                    }
                    $response = $result;
                }
            }
            break;
            
        case 'logout':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            
            Auth::logout();
            $response = [
                'success' => true,
                'message' => 'Logged out successfully'
            ];
            break;
            
        case 'check':
            // Check if user is logged in
            $response = [
                'success' => true,
                'logged_in' => is_logged_in(),
                'user' => is_logged_in() ? [
                    'id' => get_current_user_id(),
                    'name' => $_SESSION['user_name'] ?? '',
                    'email' => $_SESSION['user_email'] ?? '',
                    'role' => $_SESSION['user_role'] ?? ''
                ] : null
            ];
            break;
            
        default:
            $response['message'] = 'Invalid action';
    }
} catch (Exception $e) {
    error_log("Auth API error: " . $e->getMessage());
    $response['message'] = 'An error occurred. Please try again.';
}

// Send JSON response
echo json_encode($response);
exit;
