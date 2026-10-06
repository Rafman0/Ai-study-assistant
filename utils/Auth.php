<?php
/**
 * Authentication utility class
 */

require_once __DIR__ . '/../config/config.php';

class Auth {
    
    /**
     * Login user
     * @param string $email
     * @param string $password
     * @param bool $remember
     * @return array
     */
    public static function login($email, $password, $remember = false) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            $stmt = $db->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Blocked accounts cannot sign in
                if (($user['status'] ?? 'active') === 'suspended') {
                    return ['success' => false, 'message' => 'This account has been suspended. Please contact an administrator.'];
                }
                
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                
                // For admin accounts, also populate the admin session keys so
                // the admin area (Auth::require_admin / is_admin_logged_in)
                // works regardless of which login form was used.
                if ($user['role'] === 'admin') {
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_name'] = $user['name'];
                    $_SESSION['admin_email'] = $user['email'];
                    $_SESSION['admin_role'] = $user['role'];
                }
                
                // Remember me (optional implementation)
                if ($remember) {
                    // Could implement token-based remember me
                }
                
                self::log_activity($user['id'], 'login');
                
                return ['success' => true, 'message' => 'Login successful'];
            }
            
            return ['success' => false, 'message' => 'Invalid email or password'];
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed. Please try again.'];
        }
    }
    
    /**
     * Register new user
     * @param string $name
     * @param string $email
     * @param string $password
     * @return array
     */
    public static function register($name, $email, $password) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email already registered'];
            }
            
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $db->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, 'user', NOW())");
            $stmt->execute([$name, $email, $password_hash]);
            
            self::log_activity((int)$db->lastInsertId(), 'register');
            
            return ['success' => true, 'message' => 'Registration successful'];
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }
    
    /**
     * Logout user
     * @return void
     */
    public static function logout() {
        // Destroy session
        session_unset();
        session_destroy();
        
        // Clear session cookie
        if (isset($_COOKIE[SESSION_NAME])) {
            setcookie(SESSION_NAME, '', time() - 3600, '/');
        }
    }
    
    /**
     * Record a platform activity event (best-effort; never interrupts the request)
     * @param int $user_id
     * @param string $type login|register|ai_session|quiz_attempt|summary_generated|...
     * @param int|null $course_id
     * @param string|null $details
     */
    public static function log_activity($user_id, $type, $course_id = null, $details = null) {
        try {
            $db = get_db_connection();
            if (!$db) { return; }
            $stmt = $db->prepare("INSERT INTO activity_log (user_id, activity_type, course_id, details) VALUES (?, ?, ?, ?)");
            $stmt->execute([(int)$user_id, (string)$type, $course_id !== null ? (int)$course_id : null, $details !== null ? mb_substr((string)$details, 0, 255) : null]);
        } catch (Exception $e) {
            error_log('Activity log error: ' . $e->getMessage());
        }
    }
    
    /**
     * Require user login
     * @return void
     */
    public static function require_login() {
        if (!is_logged_in()) {
            redirect(app_url('login.php'));
        }
    }
    
    /**
     * Require admin login
     * @return void
     */
    public static function require_admin() {
        if (!is_admin_logged_in()) {
            redirect(app_url('admin/login.php'));
        }
    }
    
    /**
     * Check if current user is admin
     * @return bool
     */
    public static function is_admin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
    
    /**
     * Get current user data
     * @return array|null
     */
    public static function get_current_user() {
        if (!is_logged_in()) {
            return null;
        }
        
        $db = get_db_connection();
        if (!$db) {
            return null;
        }
        
        try {
            $stmt = $db->prepare("SELECT id, name, email, role, created_at FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([get_current_user_id()]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            return null;
        }
    }
}
