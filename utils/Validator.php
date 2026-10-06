<?php
/**
 * Validator utility class
 */

require_once __DIR__ . '/../config/config.php';

class Validator {
    
    /**
     * Validate required field
     * @param mixed $value
     * @param string $field_name
     * @return array
     */
    public static function required($value, $field_name = 'Field') {
        if (empty($value) && $value !== '0') {
            return ['valid' => false, 'message' => "$field_name is required"];
        }
        return ['valid' => true];
    }
    
    /**
     * Validate minimum length
     * @param string $value
     * @param int $min
     * @param string $field_name
     * @return array
     */
    public static function min_length($value, $min, $field_name = 'Field') {
        if (strlen($value) < $min) {
            return ['valid' => false, 'message' => "$field_name must be at least $min characters"];
        }
        return ['valid' => true];
    }
    
    /**
     * Validate maximum length
     * @param string $value
     * @param int $max
     * @param string $field_name
     * @return array
     */
    public static function max_length($value, $max, $field_name = 'Field') {
        if (strlen($value) > $max) {
            return ['valid' => false, 'message' => "$field_name must not exceed $max characters"];
        }
        return ['valid' => true];
    }
    
    /**
     * Validate email format
     * @param string $email
     * @param string $field_name
     * @return array
     */
    public static function email($email, $field_name = 'Email') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => "$field_name is not valid"];
        }
        return ['valid' => true];
    }
    
    /**
     * Validate match (e.g., password confirmation)
     * @param string $value1
     * @param string $value2
     * @param string $field_name
     * @return array
     */
    public static function match($value1, $value2, $field_name = 'Field') {
        if ($value1 !== $value2) {
            return ['valid' => false, 'message' => "$field_name does not match"];
        }
        return ['valid' => true];
    }
    
    /**
     * Validate numeric
     * @param mixed $value
     * @param string $field_name
     * @return array
     */
    public static function numeric($value, $field_name = 'Field') {
        if (!is_numeric($value)) {
            return ['valid' => false, 'message' => "$field_name must be a number"];
        }
        return ['valid' => true];
    }
    
    /**
     * Validate integer
     * @param mixed $value
     * @param string $field_name
     * @return array
     */
    public static function integer($value, $field_name = 'Field') {
        if (!filter_var($value, FILTER_VALIDATE_INT)) {
            return ['valid' => false, 'message' => "$field_name must be an integer"];
        }
        return ['valid' => true];
    }
    
    /**
     * Validate in array
     * @param mixed $value
     * @param array $allowed
     * @param string $field_name
     * @return array
     */
    public static function in_array($value, $allowed, $field_name = 'Field') {
        if (!in_array($value, $allowed)) {
            return ['valid' => false, 'message' => "$field_name is not valid"];
        }
        return ['valid' => true];
    }
    
    /**
     * Run multiple validations
     * @param array $data
     * @param array $rules
     * @return array
     */
    public static function validate($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $field_rules) {
            foreach ($field_rules as $rule) {
                $rule_parts = explode(':', $rule);
                $rule_name = $rule_parts[0];
                $rule_params = array_slice($rule_parts, 1);
                
                $value = $data[$field] ?? null;
                
                switch ($rule_name) {
                    case 'required':
                        $result = self::required($value, ucfirst($field));
                        break;
                    case 'min':
                        $result = self::min_length($value, $rule_params[0] ?? 0, ucfirst($field));
                        break;
                    case 'max':
                        $result = self::max_length($value, $rule_params[0] ?? 255, ucfirst($field));
                        break;
                    case 'email':
                        $result = self::email($value, ucfirst($field));
                        break;
                    case 'match':
                        $match_field = $rule_params[0] ?? '';
                        $result = self::match($value, $data[$match_field] ?? '', ucfirst($field));
                        break;
                    case 'numeric':
                        $result = self::numeric($value, ucfirst($field));
                        break;
                    case 'integer':
                        $result = self::integer($value, ucfirst($field));
                        break;
                    default:
                        $result = ['valid' => true];
                }
                
                if (!$result['valid']) {
                    $errors[$field] = $result['message'];
                    break; // Stop at first error for this field
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
