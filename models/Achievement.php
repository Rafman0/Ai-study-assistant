<?php
/**
 * Achievement Model
 */

require_once __DIR__ . '/../config/config.php';

class Achievement {
    
    /**
     * Get all achievements
     * @return array
     */
    public static function get_all_achievements() {
        $db = get_db_connection();
        if (!$db) {
            return [];
        }
        
        try {
            $stmt = $db->query("SELECT * FROM achievements ORDER BY points DESC");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get achievements error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user achievements
     * @param int $user_id
     * @return array
     */
    public static function get_user_achievements($user_id) {
        $db = get_db_connection();
        if (!$db) {
            return [];
        }
        
        try {
            $stmt = $db->prepare("
                SELECT a.*, ua.unlocked_at 
                FROM achievements a 
                LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
                ORDER BY a.points DESC
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get user achievements error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Unlock achievement
     * @param int $user_id
     * @param int $achievement_id
     * @return array
     */
    public static function unlock_achievement($user_id, $achievement_id) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            // Check if already unlocked
            $stmt = $db->prepare("SELECT id FROM user_achievements WHERE user_id = ? AND achievement_id = ?");
            $stmt->execute([$user_id, $achievement_id]);
            
            if ($stmt->fetch()) {
                return ['success' => true, 'message' => 'Achievement already unlocked'];
            }
            
            // Unlock achievement
            $stmt = $db->prepare("INSERT INTO user_achievements (user_id, achievement_id, unlocked_at) VALUES (?, ?, NOW())");
            $stmt->execute([$user_id, $achievement_id]);
            
            return ['success' => true, 'message' => 'Achievement unlocked!'];
        } catch (PDOException $e) {
            error_log("Unlock achievement error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to unlock achievement'];
        }
    }
    
    /**
     * Check and unlock achievements based on user activity.
     * Achievement IDs are resolved by name so seed ordering cannot break rules.
     * @param int $user_id
     * @return array Names of newly unlocked achievements
     */
    public static function check_achievements($user_id) {
        $db = get_db_connection();
        if (!$db) {
            return [];
        }

        try {
            // Resolve achievement IDs by name
            $stmt = $db->query("SELECT id, name FROM achievements");
            $id_by_name = [];
            foreach ($stmt->fetchAll() as $row) {
                $id_by_name[$row['name']] = $row['id'];
            }

            if (empty($id_by_name)) {
                return [];
            }

            // Gather activity stats in one pass
            $stats = [];

            $count_queries = [
                'study_sessions' => "SELECT COUNT(*) FROM study_sessions WHERE user_id = ?",
                'courses' => "SELECT COUNT(*) FROM courses WHERE user_id = ?",
                'notes' => "SELECT COUNT(*) FROM notes WHERE user_id = ?",
                'flashcards' => "SELECT COUNT(*) FROM flashcards WHERE user_id = ?",
                'journal_entries' => "SELECT COUNT(*) FROM journal_entries WHERE user_id = ?",
                'quizzes_taken' => "SELECT COUNT(*) FROM quiz_results WHERE user_id = ?",
            ];

            foreach ($count_queries as $key => $sql) {
                $stmt = $db->prepare($sql);
                $stmt->execute([$user_id]);
                $stats[$key] = (int) $stmt->fetchColumn();
            }

            $stmt = $db->prepare("SELECT MAX(current_streak) FROM progress WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stats['best_streak'] = (int) ($stmt->fetchColumn() ?: 0);

            $stmt = $db->prepare("
                SELECT COALESCE(MAX(percentage), 0) AS best_percentage,
                       COALESCE(MAX(CASE WHEN percentage >= 100 THEN 1 ELSE 0 END), 0) AS has_perfect,
                       MAX(DISTINCT YEAR(completed_at) * 100 + MONTH(completed_at)) AS last_active_month,
                       COUNT(DISTINCT YEAR(completed_at) * 100 + MONTH(completed_at)) AS active_month_count
                FROM quiz_results
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $quiz_stats = $stmt->fetch();
            $stats['best_quiz_percentage'] = (int) round((float) $quiz_stats['best_percentage']);
            $stats['has_perfect_score'] = (bool) $quiz_stats['has_perfect'];

            // Study days per calendar month (Consistent Learner: active ~30 days within one month)
            $stmt = $db->prepare("
                SELECT month_key, MAX(days) FROM (
                    SELECT DATE_FORMAT(session_date, '%Y-%m') AS month_key, COUNT(DISTINCT DATE(session_date)) AS days
                    FROM study_sessions WHERE user_id = ? GROUP BY month_key
                ) t
            ");
            $stmt->execute([$user_id]);
            $stats['best_study_days_in_month'] = (int) ($stmt->fetchColumn() ?: 0);

            // Rule definitions: name => condition
            $rules = [
                'First Study Session' => $stats['study_sessions'] >= 1,
                'Quiz Master' => $stats['best_quiz_percentage'] >= 90,
                '7-Day Streak' => $stats['best_streak'] >= 7,
                'Course Explorer' => $stats['courses'] >= 3,
                'Note Taker' => $stats['notes'] >= 10,
                'Study Champion' => $stats['study_sessions'] >= 50,
                'Flashcard Pro' => $stats['flashcards'] >= 50,
                'Journal Keeper' => $stats['journal_entries'] >= 20,
                'Perfect Score' => $stats['has_perfect_score'],
                'Consistent Learner' => $stats['best_study_days_in_month'] >= 25,
            ];

            $unlocked = [];
            foreach ($rules as $achievement_name => $condition) {
                if (!$condition || !isset($id_by_name[$achievement_name])) {
                    continue;
                }
                $result = self::unlock_achievement($user_id, $id_by_name[$achievement_name]);
                if ($result['success'] && strpos($result['message'], 'already') === false) {
                    $unlocked[] = $achievement_name;
                }
            }

            return $unlocked;
        } catch (PDOException $e) {
            error_log("Check achievements error: " . $e->getMessage());
            return [];
        }
    }
}
