-- AI Study Assistant Seed Data
-- Run this after schema.sql to populate with initial data

USE ai_study_assistant;

-- Insert default admin user (password: admin123)
-- Password hash generated with password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO users (name, email, password, role) VALUES
('Admin User', 'admin@studyassistant.com', '$2y$10$NkiQQ12Q7e0lnIiHdL8P..zSJMd/ps4VTXdsrSzM03jliQtz3EVXC', 'admin'),
('John Student', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user'),
('Jane Learner', 'jane@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user');

-- Insert sample courses
INSERT INTO courses (title, description, code, instructor, user_id) VALUES
('Introduction to Computer Science', 'Fundamental concepts of programming and computer science', 'CS101', 'Dr. Smith', 2),
('Data Structures and Algorithms', 'Advanced data structures and algorithm analysis', 'CS201', 'Prof. Johnson', 2),
('Database Management Systems', 'Relational database design and SQL', 'CS301', 'Dr. Williams', 2),
('Web Development', 'HTML, CSS, JavaScript, and PHP development', 'CS401', 'Prof. Brown', 3),
('Artificial Intelligence', 'Machine learning and AI concepts', 'CS501', 'Dr. Davis', 3);

-- Insert sample notes
INSERT INTO notes (title, content, course_id, user_id) VALUES
('Binary Search Basics', 'Binary search is an efficient algorithm for finding an item from a sorted list. It works by repeatedly dividing the search interval in half.', 2, 2),
('SQL Joins Explained', 'INNER JOIN: Returns records that have matching values in both tables. LEFT JOIN: Returns all records from the left table, and matched records from the right table.', 3, 2),
('CSS Flexbox', 'Flexbox is a one-dimensional layout method for laying out items in rows or columns. Items flex to fill additional space and shrink to fit into smaller spaces.', 4, 3);

-- Insert sample flashcards
INSERT INTO flashcards (question, answer, course_id, user_id) VALUES
('What is the time complexity of binary search?', 'O(log n)', 2, 2),
('What does ACID stand for in databases?', 'Atomicity, Consistency, Isolation, Durability', 3, 2),
('What is the difference between GET and POST?', 'GET retrieves data from server, POST sends data to server', 4, 3),
('What is machine learning?', 'A subset of AI that enables systems to learn from data', 5, 3);

-- Insert sample quiz
INSERT INTO quizzes (title, description, course_id, user_id) VALUES
('Data Structures Quiz', 'Test your knowledge of basic data structures', 2, 2),
('Database Fundamentals Quiz', 'Basic database concepts and SQL', 3, 2);

-- Insert sample quiz questions
INSERT INTO quiz_questions (quiz_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation) VALUES
(1, 'Which data structure uses LIFO?', 'Stack', 'Queue', 'Array', 'Linked List', 'a', 'Stack uses Last In First Out principle'),
(1, 'What is the time complexity of array access?', 'O(1)', 'O(n)', 'O(log n)', 'O(nÂ²)', 'a', 'Array access by index is constant time'),
(2, 'Which SQL clause is used to filter data?', 'WHERE', 'GROUP BY', 'ORDER BY', 'HAVING', 'a', 'WHERE clause filters records'),
(2, 'What is a primary key?', 'Unique identifier for each record', 'Foreign key reference', 'Index for searching', 'Data type constraint', 'a', 'Primary key uniquely identifies each row');

-- Insert sample journal entries
INSERT INTO journal_entries (title, content, mood, user_id) VALUES
('Productive Study Session', 'Today I learned about binary search trees. It was challenging but I understand the concept now.', 'motivated', 2),
('Exam Preparation', 'Started preparing for the database exam. Need to practice more SQL queries.', 'stressed', 2),
('Great Progress', 'Completed the web development module ahead of schedule!', 'happy', 3);

-- Insert sample study sessions
INSERT INTO study_sessions (course_id, user_id, duration_minutes, topics_covered, notes) VALUES
(2, 2, 45, 'Binary search, Time complexity', 'Need to practice more problems'),
(3, 2, 60, 'SQL joins, Normalization', 'Normalization is tricky but important'),
(4, 3, 30, 'CSS Flexbox basics', 'Flexbox is powerful for layouts'),
(5, 3, 90, 'Neural networks introduction', 'Complex topic, need more study');

-- Insert sample progress data
INSERT INTO progress (user_id, course_id, total_study_time, sessions_completed, quizzes_taken, average_quiz_score, flashcards_reviewed, notes_created, journal_entries, current_streak, longest_streak, last_study_date) VALUES
(2, 2, 180, 4, 2, 85.50, 12, 5, 3, 5, 7, CURDATE()),
(2, 3, 120, 3, 1, 75.00, 8, 3, 2, 5, 7, CURDATE()),
(3, 4, 90, 2, 0, 0.00, 5, 2, 1, 3, 3, CURDATE()),
(3, 5, 150, 3, 0, 0.00, 10, 4, 2, 3, 3, CURDATE());

-- Insert achievements
INSERT INTO achievements (name, description, icon, requirement, points) VALUES
('First Study Session', 'Complete your first study session', 'ðŸ“š', 'Complete 1 study session', 10),
('Quiz Master', 'Score 90% or higher on a quiz', 'ðŸ†', 'Score 90%+ on any quiz', 25),
('7-Day Streak', 'Study for 7 consecutive days', 'ðŸ”¥', '7 day study streak', 50),
('Course Explorer', 'Enroll in 3 different courses', 'ðŸŽ¯', 'Enroll in 3 courses', 15),
('Note Taker', 'Create 10 notes', 'ðŸ“', 'Create 10 notes', 20),
('Study Champion', 'Complete 50 study sessions', 'ðŸ‘‘', 'Complete 50 study sessions', 100),
('Flashcard Pro', 'Create 50 flashcards', '🃏', 'Create 50 flashcards', 30),
('Journal Keeper', 'Write 20 journal entries', 'ðŸ“”', 'Write 20 journal entries', 25),
('Perfect Score', 'Get 100% on a quiz', 'ðŸ’¯', 'Get 100% on any quiz', 35),
('Consistent Learner', 'Study for 30 days in a month', 'ðŸ“…', 'Study 30 days in a month', 75);

-- Insert sample user achievements
INSERT INTO user_achievements (user_id, achievement_id) VALUES
(2, 1), -- First Study Session
(2, 4), -- Course Explorer
(3, 1), -- First Study Session
(3, 5); -- Note Taker

-- Insert sample study goals
INSERT INTO study_goals (user_id, title, description, target_date, is_completed) VALUES
(2, 'Complete Data Structures Course', 'Finish all modules and pass final exam', DATE_ADD(CURDATE(), INTERVAL 30 DAY), FALSE),
(2, 'Learn Advanced SQL', 'Master complex queries and optimization', DATE_ADD(CURDATE(), INTERVAL 45 DAY), FALSE),
(3, 'Build a Portfolio Website', 'Create a complete website using learned skills', DATE_ADD(CURDATE(), INTERVAL 60 DAY), FALSE),
(3, 'Complete AI Fundamentals', 'Understand basic AI and ML concepts', DATE_ADD(CURDATE(), INTERVAL 90 DAY), FALSE);

