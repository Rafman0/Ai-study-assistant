<?php
/**
 * Progress Model
 */

require_once __DIR__ . '/../config/config.php';

class Progress {
    
    /**
     * Get progress data for a user
     * @param int $user_id
     * @param int|null $course_id
     * @return array
     */
    public static function get_user_progress($user_id, $course_id = null) {
        $db = get_db_connection();
        if (!$db) {
            return [];
        }
        
        try {
            if ($course_id) {
                $stmt = $db->prepare("SELECT p.*, c.title as course_title FROM progress p LEFT JOIN courses c ON p.course_id = c.id WHERE p.user_id = ? AND p.course_id = ?");
                $stmt->execute([$user_id, $course_id]);
            } else {
                $stmt = $db->prepare("SELECT p.*, c.title as course_title FROM progress p LEFT JOIN courses c ON p.course_id = c.id WHERE p.user_id = ?");
                $stmt->execute([$user_id]);
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get user progress error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update study session count
     * @param int $user_id
     * @param int|null $course_id
     * @param int $duration
     * @return array
     */
    public static function update_study_session($user_id, $course_id = null, $duration = 0) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            // Check if progress record exists
            if ($course_id) {
                $stmt = $db->prepare("SELECT id FROM progress WHERE user_id = ? AND course_id = ?");
                $stmt->execute([$user_id, $course_id]);
            } else {
                $stmt = $db->prepare("SELECT id FROM progress WHERE user_id = ? AND course_id IS NULL");
                $stmt->execute([$user_id]);
            }
            
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing record
                $stmt = $db->prepare("UPDATE progress SET total_study_time = total_study_time + ?, sessions_completed = sessions_completed + 1, last_study_date = CURDATE(), updated_at = NOW() WHERE id = ?");
                $stmt->execute([$duration, $existing['id']]);
            } else {
                // Create new record
                $stmt = $db->prepare("INSERT INTO progress (user_id, course_id, total_study_time, sessions_completed, last_study_date, updated_at) VALUES (?, ?, ?, 1, CURDATE(), NOW())");
                $stmt->execute([$user_id, $course_id, $duration]);
            }
            
            // Update streak
            self::update_streak($user_id);
            
            return ['success' => true, 'message' => 'Progress updated successfully'];
        } catch (PDOException $e) {
            error_log("Update progress error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update progress'];
        }
    }
    
    /**
     * Update study streak
     * @param int $user_id
     * @return void
     */
    private static function update_streak($user_id) {
        $db = get_db_connection();
        if (!$db) {
            return;
        }
        
        try {
            // Get current streak info
            $stmt = $db->prepare("SELECT current_streak, longest_streak, last_study_date FROM progress WHERE user_id = ? ORDER BY current_streak DESC LIMIT 1");
            $stmt->execute([$user_id]);
            $progress = $stmt->fetch();
            
            if ($progress) {
                $today = date('Y-m-d');
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                
                if ($progress['last_study_date'] === $today || $progress['last_study_date'] === $yesterday) {
                    // Continue or increment streak
                    $new_streak = $progress['last_study_date'] === $today ? $progress['current_streak'] : $progress['current_streak'] + 1;
                    $longest_streak = max($new_streak, $progress['longest_streak']);
                    
                    $stmt = $db->prepare("UPDATE progress SET current_streak = ?, longest_streak = ? WHERE user_id = ?");
                    $stmt->execute([$new_streak, $longest_streak, $user_id]);
                } else {
                    // Reset streak
                    $stmt = $db->prepare("UPDATE progress SET current_streak = 1 WHERE user_id = ?");
                    $stmt->execute([1, $user_id]);
                }
            }
        } catch (PDOException $e) {
            error_log("Update streak error: " . $e->getMessage());
        }
    }
    
    /**
     * Get overall statistics
     * @param int $user_id
     * @return array
     */
    public static function get_overall_stats($user_id) {
        $db = get_db_connection();
        if (!$db) {
            return [];
        }
        
        try {
            $stats = [];
            
            // Total study time
            $stmt = $db->prepare("SELECT SUM(total_study_time) FROM progress WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stats['total_study_time'] = $stmt->fetchColumn() ?: 0;
            
            // Total sessions
            $stmt = $db->prepare("SELECT SUM(sessions_completed) FROM progress WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stats['total_sessions'] = $stmt->fetchColumn() ?: 0;
            
            // Current streak
            $stmt = $db->prepare("SELECT MAX(current_streak) FROM progress WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stats['current_streak'] = $stmt->fetchColumn() ?: 0;
            
            // Longest streak
            $stmt = $db->prepare("SELECT MAX(longest_streak) FROM progress WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stats['longest_streak'] = $stmt->fetchColumn() ?: 0;
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Get overall stats error: " . $e->getMessage());
            return [];
        }
    }
}
