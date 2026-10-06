<?php
/**
 * Journal Model
 */

require_once __DIR__ . '/../config/config.php';

class Journal {
    
    /**
     * Get all journal entries for a user
     * @param int $user_id
     * @return array
     */
    public static function get_user_entries($user_id) {
        $db = get_db_connection();
        if (!$db) {
            return [];
        }
        
        try {
            $stmt = $db->prepare("SELECT * FROM journal_entries WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get user journal entries error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get journal entry by ID
     * @param int $entry_id
     * @param int $user_id
     * @return array|null
     */
    public static function get_entry($entry_id, $user_id) {
        $db = get_db_connection();
        if (!$db) {
            return null;
        }
        
        try {
            $stmt = $db->prepare("SELECT * FROM journal_entries WHERE id = ? AND user_id = ? LIMIT 1");
            $stmt->execute([$entry_id, $user_id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get journal entry error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create new journal entry
     * @param int $user_id
     * @param string $title
     * @param string $content
     * @param string $mood
     * @return array
     */
    public static function create_entry($user_id, $title, $content, $mood = 'neutral') {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            $stmt = $db->prepare("INSERT INTO journal_entries (title, content, mood, user_id, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$title, $content, $mood, $user_id]);
            
            return ['success' => true, 'message' => 'Journal entry created successfully', 'entry_id' => $db->lastInsertId()];
        } catch (PDOException $e) {
            error_log("Create journal entry error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create journal entry'];
        }
    }
    
    /**
     * Update journal entry
     * @param int $entry_id
     * @param int $user_id
     * @param string $title
     * @param string $content
     * @param string $mood
     * @return array
     */
    public static function update_entry($entry_id, $user_id, $title, $content, $mood = 'neutral') {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            $stmt = $db->prepare("UPDATE journal_entries SET title = ?, content = ?, mood = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$title, $content, $mood, $entry_id, $user_id]);
            
            return ['success' => true, 'message' => 'Journal entry updated successfully'];
        } catch (PDOException $e) {
            error_log("Update journal entry error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update journal entry'];
        }
    }
    
    /**
     * Delete journal entry
     * @param int $entry_id
     * @param int $user_id
     * @return array
     */
    public static function delete_entry($entry_id, $user_id) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            $stmt = $db->prepare("DELETE FROM journal_entries WHERE id = ? AND user_id = ?");
            $stmt->execute([$entry_id, $user_id]);
            
            return ['success' => true, 'message' => 'Journal entry deleted successfully'];
        } catch (PDOException $e) {
            error_log("Delete journal entry error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete journal entry'];
        }
    }
}
