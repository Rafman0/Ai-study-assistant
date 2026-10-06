<?php
/**
 * Study Session Model
 */

require_once __DIR__ . '/../config/config.php';

class StudySession {
    
    /**
     * Get all study sessions for a user
     * @param int $user_id
     * @param int|null $course_id
     * @return array
     */
    public static function get_user_sessions($user_id, $course_id = null) {
        $db = get_db_connection();
        if (!$db) {
            return [];
        }
        
        try {
            if ($course_id) {
                $stmt = $db->prepare("SELECT s.*, c.title as course_title FROM study_sessions s LEFT JOIN courses c ON s.course_id = c.id WHERE s.user_id = ? AND s.course_id = ? ORDER BY s.session_date DESC");
                $stmt->execute([$user_id, $course_id]);
            } else {
                $stmt = $db->prepare("SELECT s.*, c.title as course_title FROM study_sessions s LEFT JOIN courses c ON s.course_id = c.id WHERE s.user_id = ? ORDER BY s.session_date DESC");
                $stmt->execute([$user_id]);
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get user sessions error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get session by ID
     * @param int $session_id
     * @param int $user_id
     * @return array|null
     */
    public static function get_session($session_id, $user_id) {
        $db = get_db_connection();
        if (!$db) {
            return null;
        }
        
        try {
            $stmt = $db->prepare("SELECT s.*, c.title as course_title FROM study_sessions s LEFT JOIN courses c ON s.course_id = c.id WHERE s.id = ? AND s.user_id = ? LIMIT 1");
            $stmt->execute([$session_id, $user_id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get session error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create new study session
     * @param int $user_id
     * @param int $duration_minutes
     * @param string $topics_covered
     * @param string $notes
     * @param int|null $course_id
     * @return array
     */
    public static function create_session($user_id, $duration_minutes, $topics_covered = null, $notes = null, $course_id = null) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            $stmt = $db->prepare("INSERT INTO study_sessions (course_id, user_id, duration_minutes, topics_covered, notes, session_date) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$course_id, $user_id, $duration_minutes, $topics_covered, $notes]);
            
            return ['success' => true, 'message' => 'Study session recorded successfully', 'session_id' => $db->lastInsertId()];
        } catch (PDOException $e) {
            error_log("Create session error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to record study session'];
        }
    }
    
    /**
     * Update study session
     * @param int $session_id
     * @param int $user_id
     * @param int $duration_minutes
     * @param string $topics_covered
     * @param string $notes
     * @param int|null $course_id
     * @return array
     */
    public static function update_session($session_id, $user_id, $duration_minutes, $topics_covered = null, $notes = null, $course_id = null) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            $stmt = $db->prepare("UPDATE study_sessions SET course_id = ?, duration_minutes = ?, topics_covered = ?, notes = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$course_id, $duration_minutes, $topics_covered, $notes, $session_id, $user_id]);
            
            return ['success' => true, 'message' => 'Study session updated successfully'];
        } catch (PDOException $e) {
            error_log("Update session error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update study session'];
        }
    }
    
    /**
     * Delete study session
     * @param int $session_id
     * @param int $user_id
     * @return array
     */
    public static function delete_session($session_id, $user_id) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            $stmt = $db->prepare("DELETE FROM study_sessions WHERE id = ? AND user_id = ?");
            $stmt->execute([$session_id, $user_id]);
            
            return ['success' => true, 'message' => 'Study session deleted successfully'];
        } catch (PDOException $e) {
            error_log("Delete session error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete study session'];
        }
    }
    
    /**
     * Get total study time for user
     * @param int $user_id
     * @return int
     */
    public static function get_total_study_time($user_id) {
        $db = get_db_connection();
        if (!$db) {
            return 0;
        }
        
        try {
            $stmt = $db->prepare("SELECT SUM(duration_minutes) FROM study_sessions WHERE user_id = ?");
            $stmt->execute([$user_id]);
            return $stmt->fetchColumn() ?: 0;
        } catch (PDOException $e) {
            error_log("Get total study time error: " . $e->getMessage());
            return 0;
        }
    }
}
