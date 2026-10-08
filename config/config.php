<?php

/**
 * Load environment variables from .env
 * Keeps secrets out of the source code.
 */
function load_env_file($file) {
    if (!is_readable($file)) {
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Ignore comments
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        // Split NAME=VALUE
        $parts = explode('=', $line, 2);

        if (count($parts) !== 2) {
            continue;
        }

        $name = trim($parts[0]);
        $value = trim($parts[1]);

        // Remove surrounding quotes
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];

            if (($first === '"' && $last === '"') ||
                ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        // Only set it if the environment doesn't already provide it
        if (getenv($name) === false) {
            putenv($name . '=' . $value);
        }
    }
}

// Load the project's local .env file
load_env_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

/**
 * AI Study Assistant - Configuration File
 * Centralized configuration for the entire application
 */

// Define that config is being loaded (must be before the security check)
define('APP_LOADED', true);

// Prevent direct web access but allow CLI and test scripts
// Check if this file is being accessed directly by checking the calling script
if (php_sapi_name() !== 'cli') {
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
    if (empty($backtrace)) {
        die('Direct access not permitted');
    }
}

// Application Base URL - CRITICAL for subdirectory installation
// The application is installed at: C:\AppServ\www\AI study assistant\
// Accessible at: http://localhost/AI%20study%20assistant/
define('APP_BASE_URL', getenv('APP_BASE_URL') ?: 'http://localhost/AI%20study%20assistant/');

// Application paths (always use APP_BASE_URL to generate URLs)
define('APP_PATH', realpath(__DIR__ . '/..'));

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'ai_study_assistant');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// AI API Configuration
// Uses any OpenAI-compatible chat-completions endpoint.
// For Google Gemini, use the OpenAI compatibility layer URL shown below.
define('AI_API_KEY', getenv('AI_API_KEY') ?: '');
define('AI_API_URL', getenv('AI_API_URL') ?: '');
define('AI_MODEL', getenv('AI_MODEL') ?: '');

// CA bundle for outbound HTTPS (Windows dev stacks often have no system CA store for cURL).
// Leave empty on systems where curl.cainfo / openssl.cafile are configured.
define('CA_BUNDLE_PATH', getenv('CA_BUNDLE_PATH') ?: '');

// Session Configuration
define('SESSION_NAME', 'AI_STUDY_SESSION');
define('SESSION_LIFETIME', 3600); // 1 hour

// Security Settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('HASH_ALGORITHM', 'sha256');

// Application Settings
define('APP_NAME', 'AI Study Assistant');
define('APP_TAGLINE', 'Learn Smarter, Not Harder.');
define('APP_VERSION', '1.0.0');

// File Upload Settings
define('UPLOAD_MAX_SIZE', 5242880); // 5MB in bytes
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);

// PDF Upload Settings (course material summarization)
define('PDF_UPLOAD_MAX_SIZE', 15728640); // 15MB in bytes
define('PDF_UPLOAD_DIR', APP_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads');

/**
 * Convert PHP ini shorthand byte values (e.g. "2M", "512K", "-1") to bytes
 * @param string $val Raw ini value
 * @return int Bytes, or PHP_INT_MAX when unlimited
 */
function ini_bytes($val) {
    $val = trim((string)$val);
    if ($val === '' || $val === '-1') {
        return PHP_INT_MAX;
    }
    $unit = strtolower(substr($val, -1));
    $num = (int)$val;
    switch ($unit) {
        case 'g': return $num * 1024 * 1024 * 1024;
        case 'm': return $num * 1024 * 1024;
        case 'k': return $num * 1024;
        default:  return $num;
    }
}

// Pagination
define('ITEMS_PER_PAGE', 10);

// Timezone
date_default_timezone_set('UTC');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

/**
 * Generate absolute URL using base URL
 * @param string $path Relative path from application root
 * @return string Full URL
 */
function app_url($path = '') {
    $path = ltrim($path, '/');
    return rtrim(APP_BASE_URL, '/') . '/' . $path;
}

/**
 * Generate asset URL
 * @param string $path Relative path from assets directory
 * @return string Full asset URL
 */
function asset_url($path) {
    $path = ltrim($path, '/');
    return app_url('assets/' . $path);
}

/**
 * Get database connection
 * @return PDO
 */
function get_db_connection() {

    try {

        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        // Enable SSL for Aiven MySQL
        $caBundle = getenv('CA_BUNDLE_PATH') ?: APP_PATH . '/certs/aiven-ca.pem';

        if (file_exists($caBundle)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $caBundle;
             $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }

        return new PDO($dsn, DB_USER, DB_PASS, $options);

    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Database connection failed: " . htmlspecialchars($e->getMessage()));
    }
}

/**
 * Generate CSRF token
 * @return string
 */
function generate_csrf_token() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF token
 * @param string $token
 * @return bool
 */
function verify_csrf_token($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Sanitize input
 * @param string $data
 * @return string
 */
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Check if user is logged in
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if admin is logged in
 * @return bool
 */
function is_admin_logged_in() {
    // Admin session set by /admin/login.php
    if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
        return true;
    }
    // Role-based admin session set by Auth::login() (main /login.php)
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Get current user ID
 * @return int|null
 */
function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current admin ID
 * @return int|null
 */
function get_current_admin_id() {
    return $_SESSION['admin_id'] ?? null;
}

/**
 * Redirect to URL
 * @param string $url
 * @return void
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * JSON response
 * @param array $data
 * @return void
 */
function json_response($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get a value from the site_settings table
 * @param string $key Setting key
 * @param mixed $default Fallback when missing
 * @return mixed
 */
function get_site_setting($key, $default = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $db = get_db_connection();
            if ($db) {
                foreach ($db->query("SELECT setting_key, setting_value FROM site_settings") as $row) {
                    $cache[$row['setting_key']] = $row['setting_value'];
                }
            }
        } catch (Exception $e) {
            error_log("get_site_setting error: " . $e->getMessage());
        }
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

/**
 * Insert or update a value in the site_settings table
 * @param string $key Setting key
 * @param string|null $value Setting value
 * @return bool
 */
function set_site_setting($key, $value) {
    try {
        $db = get_db_connection();
        if (!$db) return false;
        $stmt = $db->prepare("
            INSERT INTO site_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        error_log("set_site_setting error: " . $e->getMessage());
        return false;
    }
}

// ------------------------------------------------------------------
// Maintenance mode gate.
// When enabled in Admin > Settings, non-admin visitors see a notice;
// API endpoints receive a JSON response instead. Auth pages and the
// admin area always remain reachable (so admins can disable it).
// Fail-open by design: if settings cannot be read the site stays up.
// ------------------------------------------------------------------
if (php_sapi_name() !== 'cli') {
    $maintenance_script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
    $in_admin_area = strpos($maintenance_script, '/admin/') !== false;
    $exempt_scripts = ['login.php', 'logout.php', 'register.php'];
    $is_exempt = in_array(basename($maintenance_script), $exempt_scripts, true);

    if (!$in_admin_area && !$is_exempt && get_site_setting('maintenance_mode') === '1') {
        http_response_code(503);
        header('Retry-After: 3600');
        if (strpos($maintenance_script, '/api/') !== false || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            json_response(['success' => false, 'message' => 'The site is temporarily down for maintenance. Please try again later.']);
        }
        $site_name_m = function_exists('get_site_setting') ? get_site_setting('site_name', APP_NAME) : APP_NAME;
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>Maintenance - ' . htmlspecialchars($site_name_m) . '</title>'
            . '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f8fafc;color:#1e293b;text-align:center;padding:20px;}'
            . '.box{max-width:480px;}h1{font-size:28px;margin-bottom:12px;}p{color:#64748b;line-height:1.6;}a{color:#6366f1;text-decoration:none;font-weight:600;}</style></head>'
            . '<body><div class="box"><h1>🔧 ' . htmlspecialchars($site_name_m) . ' is under maintenance</h1>'
            . '<p>We are making some improvements. Please check back soon.</p>'
            . '<p><a href="' . app_url('admin/login.php') . '">Admin login</a></p></div></body></html>';
        exit;
    }
}
