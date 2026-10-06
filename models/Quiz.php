<?php
/**
 * Quiz Model
 */

require_once __DIR__ . '/../config/config.php';

class Quiz {
    
    /**
     * Get all quizzes for a user
     * @param int $user_id
     * @param int|null $course_id
     * @return array
     */
    public static function get_user_quizzes($user_id, $course_id = null) {
        $db = get_db_connection();
        if (!$db) {
            return [];
        }
        
        try {
            if ($course_id) {
                $stmt = $db->prepare("SELECT q.*, c.title as course_title FROM quizzes q LEFT JOIN courses c ON q.course_id = c.id WHERE q.user_id = ? AND q.course_id = ? ORDER BY q.created_at DESC");
                $stmt->execute([$user_id, $course_id]);
            } else {
                $stmt = $db->prepare("SELECT q.*, c.title as course_title FROM quizzes q LEFT JOIN courses c ON q.course_id = c.id WHERE q.user_id = ? ORDER BY q.created_at DESC");
                $stmt->execute([$user_id]);
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get user quizzes error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get quiz by ID with questions
     * @param int $quiz_id
     * @param int $user_id
     * @return array|null
     */
    public static function get_quiz($quiz_id, $user_id) {
        $db = get_db_connection();
        if (!$db) {
            return null;
        }
        
        try {
            $stmt = $db->prepare("SELECT q.*, c.title as course_title FROM quizzes q LEFT JOIN courses c ON q.course_id = c.id WHERE q.id = ? AND q.user_id = ? LIMIT 1");
            $stmt->execute([$quiz_id, $user_id]);
            $quiz = $stmt->fetch();
            
            if ($quiz) {
                // Get questions
                $stmt = $db->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id");
                $stmt->execute([$quiz_id]);
                $quiz['questions'] = $stmt->fetchAll();
            }
            
            return $quiz;
        } catch (PDOException $e) {
            error_log("Get quiz error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create new quiz
     * @param int $user_id
     * @param string $title
     * @param string $description
     * @param array $questions
     * @param int|null $course_id
     * @return array
     */
    public static function create_quiz($user_id, $title, $description, $questions, $course_id = null) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            $db->beginTransaction();
            
            // Insert quiz
            $stmt = $db->prepare("INSERT INTO quizzes (title, description, course_id, user_id, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$title, $description, $course_id, $user_id]);
            $quiz_id = $db->lastInsertId();
            
            // Insert questions
            $question_stmt = $db->prepare("INSERT INTO quiz_questions (quiz_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            foreach ($questions as $question) {
                $question_stmt->execute([
                    $quiz_id,
                    $question['question'],
                    $question['option_a'],
                    $question['option_b'],
                    $question['option_c'],
                    $question['option_d'],
                    $question['correct_answer'],
                    $question['explanation'] ?? null
                ]);
            }
            
            $db->commit();
            
            return ['success' => true, 'message' => 'Quiz created successfully', 'quiz_id' => $quiz_id];
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Create quiz error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create quiz'];
        }
    }
    
    /**
     * Save quiz result
     * @param int $quiz_id
     * @param int $user_id
     * @param int $score
     * @param int $total_questions
     * @param array $answers
     * @return array
     */
    public static function save_quiz_result($quiz_id, $user_id, $score, $total_questions, $answers) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        try {
            $percentage = ($score / $total_questions) * 100;
            
            $stmt = $db->prepare("INSERT INTO quiz_results (quiz_id, user_id, score, total_questions, percentage, answers, completed_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$quiz_id, $user_id, $score, $total_questions, $percentage, json_encode($answers)]);
            
            return ['success' => true, 'message' => 'Quiz result saved successfully'];
        } catch (PDOException $e) {
            error_log("Save quiz result error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to save quiz result'];
        }
    }
    
    /**
     * Get a quiz prepared for taking - correct answers stripped
     * @param int $quiz_id
     * @param int $user_id
     * @return array|null
     */
    public static function get_quiz_for_taking($quiz_id, $user_id) {
        $db = get_db_connection();
        if (!$db) {
            return null;
        }

        try {
            $stmt = $db->prepare("SELECT q.id, q.title, q.description, c.title as course_title FROM quizzes q LEFT JOIN courses c ON q.course_id = c.id WHERE q.id = ? AND q.user_id = ? LIMIT 1");
            $stmt->execute([$quiz_id, $user_id]);
            $quiz = $stmt->fetch();

            if ($quiz) {
                $stmt = $db->prepare("SELECT id, question, option_a, option_b, option_c, option_d FROM quiz_questions WHERE quiz_id = ? ORDER BY id");
                $stmt->execute([$quiz_id]);
                $quiz['questions'] = $stmt->fetchAll();
            }

            return $quiz;
        } catch (PDOException $e) {
            error_log("Get quiz for taking error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Grade submitted answers server-side, save the result and trigger achievement checks
     * @param int $quiz_id
     * @param int $user_id
     * @param array $answers Map of question_id => chosen option letter
     * @return array
     */
    public static function grade_and_save_result($quiz_id, $user_id, $answers) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }

        try {
            // Verify ownership
            $stmt = $db->prepare("SELECT id, title FROM quizzes WHERE id = ? AND user_id = ? LIMIT 1");
            $stmt->execute([$quiz_id, $user_id]);
            $quiz = $stmt->fetch();

            if (!$quiz) {
                return ['success' => false, 'message' => 'Quiz not found'];
            }

            $stmt = $db->prepare("SELECT id, question, option_a, option_b, option_c, option_d, correct_answer, explanation FROM quiz_questions WHERE quiz_id = ? ORDER BY id");
            $stmt->execute([$quiz_id]);
            $questions = $stmt->fetchAll();

            if (empty($questions)) {
                return ['success' => false, 'message' => 'This quiz has no questions'];
            }

            // Grade each question
            $score = 0;
            $review = [];
            foreach ($questions as $q) {
                $qid = $q['id'];
                $given = isset($answers[$qid]) ? strtolower(substr(trim((string) $answers[$qid]), 0, 1)) : '';
                $correct_letter = strtolower(substr(trim($q['correct_answer']), 0, 1));
                $is_correct = ($given === $correct_letter);

                if ($is_correct) {
                    $score++;
                }

                $options = ['a' => $q['option_a'], 'b' => $q['option_b'], 'c' => $q['option_c'], 'd' => $q['option_d']];
                $review[] = [
                    'question_id' => $qid,
                    'question' => $q['question'],
                    'your_answer' => $given !== '' ? $options[$given] ?? $given : null,
                    'correct_answer' => $options[$correct_letter] ?? $q['correct_answer'],
                    'is_correct' => $is_correct,
                    'explanation' => $q['explanation'],
                ];
            }

            $total = count($questions);
            $percentage = round(($score / $total) * 100, 1);

            // Store only the letter choices to keep the payload small
            $stored_answers = [];
            foreach ($answers as $qid => $letter) {
                $stored_answers[$qid] = strtolower(substr(trim((string) $letter), 0, 1));
            }

            $stmt = $db->prepare("INSERT INTO quiz_results (quiz_id, user_id, score, total_questions, percentage, answers, completed_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$quiz_id, $user_id, $score, $total, $percentage, json_encode($stored_answers)]);

            // Check for newly unlocked achievements (Quiz Master / Perfect Score)
            require_once __DIR__ . '/Achievement.php';
            $new_achievements = Achievement::check_achievements($user_id);

            return [
                'success' => true,
                'message' => 'Quiz graded successfully',
                'quiz_title' => $quiz['title'],
                'score' => $score,
                'total_questions' => $total,
                'percentage' => $percentage,
                'review' => $review,
                'new_achievements' => $new_achievements,
            ];
        } catch (PDOException $e) {
            error_log("Grade quiz result error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to save quiz result'];
        }
    }

    /**
     * Delete a quiz owned by the user
     * @param int $quiz_id
     * @param int $user_id
     * @return array
     */
    public static function delete_quiz($quiz_id, $user_id) {
        $db = get_db_connection();
        if (!$db) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("DELETE FROM quiz_questions WHERE quiz_id = ? AND quiz_id IN (SELECT id FROM quizzes WHERE id = ? AND user_id = ?)");
            $stmt->execute([$quiz_id, $quiz_id, $user_id]);

            $stmt = $db->prepare("DELETE FROM quiz_results WHERE quiz_id = ? AND quiz_id IN (SELECT id FROM quizzes WHERE id = ? AND user_id = ?)");
            $stmt->execute([$quiz_id, $quiz_id, $user_id]);

            $stmt = $db->prepare("DELETE FROM quizzes WHERE id = ? AND user_id = ?");
            $stmt->execute([$quiz_id, $user_id]);

            $deleted = $stmt->rowCount();
            $db->commit();

            return $deleted > 0
                ? ['success' => true, 'message' => 'Quiz deleted successfully']
                : ['success' => false, 'message' => 'Quiz not found'];
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Delete quiz error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete quiz'];
        }
    }

    /**
     * Get quiz results for user
     * @param int $user_id
     * @return array
     */
    public static function get_user_results($user_id) {
        $db = get_db_connection();
        if (!$db) {
            return [];
        }
        
        try {
            $stmt = $db->prepare("
                SELECT qr.*, q.title as quiz_title 
                FROM quiz_results qr 
                JOIN quizzes q ON qr.quiz_id = q.id 
                WHERE qr.user_id = ? 
                ORDER BY qr.completed_at DESC
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get user results error: " . $e->getMessage());
            return [];
        }
    }
}
