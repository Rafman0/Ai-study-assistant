<?php
/**
 * Course Model
 */

require_once __DIR__ . '/../config/config.php';

class Course {
    
    /**
     * Get all courses for a user
     * @param int $user_id
     * @return array
     */
    public static function get_user_courses($user_id) {
        $db = get_db_connection();
        if (!$db) {
            return [];
        }
        
        try {
            $stmt = $db->prepare("SELECT * FROM courses WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get user courses error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get course by ID
     * @param int $course_id
     * @param int $user_id
     * @return array|null
     */
    public static function get_course($course_id, $user_id) {
        $db = get_db_connection();
        if (!$db) {
            return null;
        }
        
        try {
            $stmt = $db->prepare("SELECT * FROM courses WHERE id = ? AND user_id = ? LIMIT 1");
            $stmt->execute([$course_id, $user_id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get course error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create new course
     * @param int $user_id
     * @param string $title
     * @param string $description
     * @param string $code
     * @param string $instructor
     * @return array
     */
    public static function create_course($user_id, $title, $description, $code = null, $instructor = null) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            $stmt = $db->prepare("INSERT INTO courses (title, description, code, instructor, user_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$title, $description, $code, $instructor, $user_id]);
            
            return ['success' => true, 'message' => 'Course created successfully', 'course_id' => $db->lastInsertId()];
        } catch (PDOException $e) {
            error_log("Create course error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create course'];
        }
    }
    
    /**
     * Update course
     * @param int $course_id
     * @param int $user_id
     * @param string $title
     * @param string $description
     * @param string $code
     * @param string $instructor
     * @return array
     */
    public static function update_course($course_id, $user_id, $title, $description, $code = null, $instructor = null) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            $stmt = $db->prepare("UPDATE courses SET title = ?, description = ?, code = ?, instructor = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$title, $description, $code, $instructor, $course_id, $user_id]);
            
            return ['success' => true, 'message' => 'Course updated successfully'];
        } catch (PDOException $e) {
            error_log("Update course error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update course'];
        }
    }
    
    /**
     * Delete course
     * @param int $course_id
     * @param int $user_id
     * @return array
     */
    public static function delete_course($course_id, $user_id) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            $stmt = $db->prepare("DELETE FROM courses WHERE id = ? AND user_id = ?");
            $stmt->execute([$course_id, $user_id]);
            
            return ['success' => true, 'message' => 'Course deleted successfully'];
        } catch (PDOException $e) {
            error_log("Delete course error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete course'];
        }
    }
}
