<?php
/**
 * Note Model
 */

require_once __DIR__ . '/../config/config.php';

class Note {
    
    /**
     * Get all notes for a user
     * @param int $user_id
     * @param int|null $course_id
     * @return array
     */
    public static function get_user_notes($user_id, $course_id = null) {
        $db = get_db_connection();
        if (!$db) {
            return [];
        }
        
        try {
            if ($course_id) {
                $stmt = $db->prepare("SELECT n.*, c.title as course_title FROM notes n LEFT JOIN courses c ON n.course_id = c.id WHERE n.user_id = ? AND n.course_id = ? ORDER BY n.created_at DESC");
                $stmt->execute([$user_id, $course_id]);
            } else {
                $stmt = $db->prepare("SELECT n.*, c.title as course_title FROM notes n LEFT JOIN courses c ON n.course_id = c.id WHERE n.user_id = ? ORDER BY n.created_at DESC");
                $stmt->execute([$user_id]);
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get user notes error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get note by ID
     * @param int $note_id
     * @param int $user_id
     * @return array|null
     */
    public static function get_note($note_id, $user_id) {
        $db = get_db_connection();
        if (!$db) {
            return null;
        }
        
        try {
            $stmt = $db->prepare("SELECT n.*, c.title as course_title FROM notes n LEFT JOIN courses c ON n.course_id = c.id WHERE n.id = ? AND n.user_id = ? LIMIT 1");
            $stmt->execute([$note_id, $user_id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get note error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create new note
     * @param int $user_id
     * @param string $title
     * @param string $content
     * @param int|null $course_id
     * @return array
     */
    public static function create_note($user_id, $title, $content, $course_id = null) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            $stmt = $db->prepare("INSERT INTO notes (title, content, course_id, user_id, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$title, $content, $course_id, $user_id]);
            
            return ['success' => true, 'message' => 'Note created successfully', 'note_id' => $db->lastInsertId()];
        } catch (PDOException $e) {
            error_log("Create note error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create note'];
        }
    }
    
    /**
     * Update note
     * @param int $note_id
     * @param int $user_id
     * @param string $title
     * @param string $content
     * @param int|null $course_id
     * @return array
     */
    public static function update_note($note_id, $user_id, $title, $content, $course_id = null) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            $stmt = $db->prepare("UPDATE notes SET title = ?, content = ?, course_id = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$title, $content, $course_id, $note_id, $user_id]);
            
            return ['success' => true, 'message' => 'Note updated successfully'];
        } catch (PDOException $e) {
            error_log("Update note error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update note'];
        }
    }
    
    /**
     * Delete note
     * @param int $note_id
     * @param int $user_id
     * @return array
     */
    public static function delete_note($note_id, $user_id) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            $stmt = $db->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
            $stmt->execute([$note_id, $user_id]);
            
            return ['success' => true, 'message' => 'Note deleted successfully'];
        } catch (PDOException $e) {
            error_log("Delete note error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete note'];
        }
    }
    
    /**
     * Search notes
     * @param int $user_id
     * @param string $query
     * @return array
     */
    public static function search_notes($user_id, $query) {
        $db = get_db_connection();
        if (!$db) {
            return [];
        }
        
        try {
            $stmt = $db->prepare("SELECT n.*, c.title as course_title FROM notes n LEFT JOIN courses c ON n.course_id = c.id WHERE n.user_id = ? AND (n.title LIKE ? OR n.content LIKE ?) ORDER BY n.created_at DESC");
            $search_term = "%$query%";
            $stmt->execute([$user_id, $search_term, $search_term]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Search notes error: " . $e->getMessage());
            return [];
        }
    }
}
