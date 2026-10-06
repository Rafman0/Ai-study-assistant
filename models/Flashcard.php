<?php
/**
 * Flashcard Model
 */

require_once __DIR__ . '/../config/config.php';

class Flashcard {
    
    /**
     * Get all flashcards for a user
     * @param int $user_id
     * @param int|null $course_id
     * @return array
     */
    public static function get_user_flashcards($user_id, $course_id = null) {
        $db = get_db_connection();
        if (!$db) {
            return [];
        }
        
        try {
            if ($course_id) {
                $stmt = $db->prepare("SELECT f.*, c.title as course_title FROM flashcards f LEFT JOIN courses c ON f.course_id = c.id WHERE f.user_id = ? AND f.course_id = ? ORDER BY f.created_at DESC");
                $stmt->execute([$user_id, $course_id]);
            } else {
                $stmt = $db->prepare("SELECT f.*, c.title as course_title FROM flashcards f LEFT JOIN courses c ON f.course_id = c.id WHERE f.user_id = ? ORDER BY f.created_at DESC");
                $stmt->execute([$user_id]);
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get user flashcards error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get flashcard by ID
     * @param int $flashcard_id
     * @param int $user_id
     * @return array|null
     */
    public static function get_flashcard($flashcard_id, $user_id) {
        $db = get_db_connection();
        if (!$db) {
            return null;
        }
        
        try {
            $stmt = $db->prepare("SELECT f.*, c.title as course_title FROM flashcards f LEFT JOIN courses c ON f.course_id = c.id WHERE f.id = ? AND f.user_id = ? LIMIT 1");
            $stmt->execute([$flashcard_id, $user_id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get flashcard error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create new flashcard
     * @param int $user_id
     * @param string $question
     * @param string $answer
     * @param int|null $course_id
     * @return array
     */
    public static function create_flashcard($user_id, $question, $answer, $course_id = null) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            $stmt = $db->prepare("INSERT INTO flashcards (question, answer, course_id, user_id, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$question, $answer, $course_id, $user_id]);
            
            return ['success' => true, 'message' => 'Flashcard created successfully', 'flashcard_id' => $db->lastInsertId()];
        } catch (PDOException $e) {
            error_log("Create flashcard error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create flashcard'];
        }
    }
    
    /**
     * Update flashcard
     * @param int $flashcard_id
     * @param int $user_id
     * @param string $question
     * @param string $answer
     * @param int|null $course_id
     * @return array
     */
    public static function update_flashcard($flashcard_id, $user_id, $question, $answer, $course_id = null) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            $stmt = $db->prepare("UPDATE flashcards SET question = ?, answer = ?, course_id = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$question, $answer, $course_id, $flashcard_id, $user_id]);
            
            return ['success' => true, 'message' => 'Flashcard updated successfully'];
        } catch (PDOException $e) {
            error_log("Update flashcard error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update flashcard'];
        }
    }
    
    /**
     * Delete flashcard
     * @param int $flashcard_id
     * @param int $user_id
     * @return array
     */
    public static function delete_flashcard($flashcard_id, $user_id) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            $stmt = $db->prepare("DELETE FROM flashcards WHERE id = ? AND user_id = ?");
            $stmt->execute([$flashcard_id, $user_id]);
            
            return ['success' => true, 'message' => 'Flashcard deleted successfully'];
        } catch (PDOException $e) {
            error_log("Delete flashcard error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete flashcard'];
        }
    }
}
