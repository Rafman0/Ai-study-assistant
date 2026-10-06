<?php
/**
 * Security utility class
 */

require_once __DIR__ . '/../config/config.php';

class Security {
    
    /**
     * Validate password strength
     * @param string $password
     * @return array
     */
    public static function validate_password($password) {
        if (strlen($password) < 8) {
            return ['valid' => false, 'message' => 'Password must be at least 8 characters'];
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one uppercase letter'];
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one lowercase letter'];
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one number'];
        }
        
        return ['valid' => true, 'message' => 'Password is valid'];
    }
    
    /**
     * Validate email
     * @param string $email
     * @return array
     */
    public static function validate_email($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'Invalid email format'];
        }
        
        return ['valid' => true, 'message' => 'Email is valid'];
    }
    
    /**
     * Sanitize and validate input
     * @param string $input
     * @param string $type
     * @return array
     */
    public static function sanitize_validate($input, $type = 'text') {
        $input = trim($input);
        
        switch ($type) {
            case 'email':
                $result = self::validate_email($input);
                if (!$result['valid']) {
                    return $result;
                }
                return ['valid' => true, 'value' => filter_var($input, FILTER_SANITIZE_EMAIL)];
                
            case 'password':
                return self::validate_password($input);
                
            case 'text':
            default:
                if (empty($input)) {
                    return ['valid' => false, 'message' => 'This field is required'];
                }
                return ['valid' => true, 'value' => sanitize_input($input)];
        }
    }
    
    /**
     * Check for rate limiting (basic implementation)
     * @param string $identifier
     * @param int $max_attempts
     * @param int $time_window
     * @return array
     */
    public static function check_rate_limit($identifier, $max_attempts = 5, $time_window = 300) {
        // This is a basic implementation
        // In production, you'd use Redis or database-based rate limiting
        
        if (!isset($_SESSION['rate_limits'][$identifier])) {
            $_SESSION['rate_limits'][$identifier] = [
                'attempts' => 0,
                'first_attempt' => time()
            ];
        }
        
        $rate_data = $_SESSION['rate_limits'][$identifier];
        
        // Reset if time window has passed
        if (time() - $rate_data['first_attempt'] > $time_window) {
            $_SESSION['rate_limits'][$identifier] = [
                'attempts' => 0,
                'first_attempt' => time()
            ];
            return ['allowed' => true, 'attempts' => 0, 'remaining' => $max_attempts];
        }
        
        // Check if limit exceeded
        if ($rate_data['attempts'] >= $max_attempts) {
            return [
                'allowed' => false,
                'attempts' => $rate_data['attempts'],
                'remaining' => 0,
                'retry_after' => $time_window - (time() - $rate_data['first_attempt'])
            ];
        }
        
        return [
            'allowed' => true,
            'attempts' => $rate_data['attempts'],
            'remaining' => $max_attempts - $rate_data['attempts']
        ];
    }
    
    /**
     * Record rate limit attempt
     * @param string $identifier
     * @return void
     */
    public static function record_attempt($identifier) {
        if (!isset($_SESSION['rate_limits'][$identifier])) {
            $_SESSION['rate_limits'][$identifier] = [
                'attempts' => 0,
                'first_attempt' => time()
            ];
        }
        
        $_SESSION['rate_limits'][$identifier]['attempts']++;
    }
    
    /**
     * Clear rate limit for identifier
     * @param string $identifier
     * @return void
     */
    public static function clear_rate_limit($identifier) {
        unset($_SESSION['rate_limits'][$identifier]);
    }
    
    /**
     * Generate secure random token
     * @param int $length
     * @return string
     */
    public static function generate_token($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * XSS protection
     * @param string $string
     * @return string
     */
    public static function xss_clean($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}
