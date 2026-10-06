# AI Study Assistant

**Learn Smarter, Not Harder.**

A complete, production-quality academic web application built with PHP 8+, MySQL 8, HTML5, CSS3, Vanilla JavaScript, and Bootstrap 5.

## CRITICAL REQUIREMENT – GRADUATED HINT SYSTEM

This application is NOT a standard AI chatbot that immediately gives answers.

The core purpose of the AI Study Assistant is to help students learn through guided discovery.

The AI Tutor must:

1. Encourage critical thinking.
2. Provide graduated hints instead of immediate answers.
3. Guide students step-by-step toward solving problems.
4. Adapt hint difficulty based on user progress.
5. Only provide the complete answer when:

   * The student explicitly requests it, OR
   * The student has exhausted all hint levels.

Hint Levels:

Level 1:

* Ask guiding questions.
* Point the student toward relevant concepts.

Level 2:

* Provide conceptual clues.
* Suggest formulas, methods, or approaches.

Level 3:

* Give stronger hints and partial solutions.

Level 4:

* Explain the full solution and reasoning process.

The AI should act like a tutor, mentor, or teacher rather than an answer generator.

Example:

Student:
"What is the output of this code?"

Bad Behavior:
Immediately provide the answer.

Desired Behavior:
First ask the student what they think the code does.
Then guide them through the logic.
Provide hints progressively.
Reveal the final answer only when necessary.

This graduated hint system is a primary project requirement and must be reflected throughout the AI Tutor experience.


## 🚀 Features

- **AI Tutor**: Get graduated hint based AI-powered explanations for academic questions
- **Course Summarizer**: Transform lengthy course materials into concise summaries
- **Flashcards**: Create and study with digital flashcards
- **Quiz Center**: Test knowledge with AI-generated quizzes
- **Study Journal**: Track learning journey with mood tracking
- **Progress Tracking**: Monitor study habits, streaks, and achievements
- **Achievement System**: Unlock badges based on learning activities
- **Admin Dashboard**: Manage users, courses, and application settings

## 📋 Tech Stack

- **Frontend**: HTML5, CSS3, Vanilla JavaScript, Bootstrap 5
- **Backend**: PHP 8+
- **Database**: MySQL 8
- **Architecture**: Clean modular PHP with MVC-like structure

## 🗂️ Project Structure

```
AI study assistant/
├── index.php                    # Landing page
├── login.php                    # User login
├── register.php                 # User registration
├── logout.php                   # User logout
├── dashboard.php                # User dashboard
├── ai-tutor.php                 # AI Tutor interface
├── course-summarizer.php        # Course summarizer
├── courses.php                  # Course management
├── study-session.php            # Study session tracking
├── flashcards.php               # Flashcard management
├── quiz-center.php              # Quiz center
├── notes.php                    # Note management
├── journal.php                  # Study journal
├── progress.php                 # Progress tracking
├── achievements.php             # Achievement badges
├── settings.php                 # User settings
├── admin/                       # Admin section
│   ├── login.php
│   ├── dashboard.php
│   ├── users.php
│   ├── courses.php
│   └── settings.php
├── api/                         # API endpoints
│   ├── auth.php
│   ├── ai.php
│   ├── courses.php
│   ├── notes.php
│   ├── flashcards.php
│   ├── quizzes.php
│   ├── journal.php
│   ├── progress.php
│   └── achievements.php
├── config/
│   └── config.php               # Centralized configuration
├── database/
│   ├── schema.sql               # Database schema
│   └── seed.sql                 # Sample data
├── models/                      # Data models
│   ├── User.php
│   ├── Course.php
│   ├── Note.php
│   ├── Flashcard.php
│   ├── Quiz.php
│   ├── Journal.php
│   ├── Progress.php
│   └── Achievement.php
├── utils/                       # Utility classes
│   ├── Auth.php
│   ├── Security.php
│   └── Validator.php
├── views/                       # View components
│   ├── auth/
│   ├── partials/
│   └── components/
└── assets/                      # Static assets
    ├── css/
    │   └── style.css
    ├── js/
    │   └── app.js
    ├── images/
    │   ├── ai-study-logo.png
    │   └── favicon.png
    └── uploads/
```

## 🔧 Installation

### Prerequisites
- Apache web server
- PHP 8.0 or higher
- MySQL 8.0 or higher
- AppServ (or similar PHP development environment)

### Setup Instructions

1. **Copy the project to your web directory:**
   ```
   C:\AppServ\www\AI study assistant\
   ```

2. **Create the database:**
   - Run `database/schema.sql` in MySQL to create the database structure
   - Run `database/seed.sql` to populate with sample data

3. **Configure the application:**
   - Edit `config/config.php` to set your database credentials
   - Configure AI API keys if you want real AI responses

4. **Access the application:**
   ```
   http://localhost/AI%20study%20assistant/
   ```

### Default Users

**Admin:**
- Email: admin@studyassistant.com
- Password: admin123

**Test Users:**
- Email: john@example.com
- Password: password
- Email: jane@example.com
- Password: password

## 🔐 Security Features

- Password hashing with `password_hash()`
- CSRF protection on all forms
- Prepared SQL statements to prevent SQL injection
- Input validation and sanitization
- Session security
- Rate limiting on login attempts
- Role-based access control
- No exposed API keys

## 🎨 Design System

The application uses a centralized design system with:
- Deep navy / blue primary colors
- Bright blue accents
- White and soft neutral backgrounds
- Purple/indigo accents where appropriate
- CSS variables for consistent theming
- Distinct colors for feature cards

## 📱 Responsive Design

The application is fully responsive and works on:
- Desktop computers
- Laptops
- Tablets
- Mobile devices

## 🤖 AI Integration

The application includes AI Tutor and Course Summarizer features:
- **Demo Mode**: Works without API keys with simulated responses
- **Production Mode**: Configure API keys in `config/config.php` for real AI responses
- **Easy Integration**: Isolated AI API code for easy provider replacement

## 🧪 Testing

A comprehensive testing checklist is available in `TESTING_CHECKLIST.md` covering:
- Landing page functionality
- Authentication flows
- Admin system
- All user features
- Technical validation
- Responsive design
- Path/routing verification

## 📝 Configuration

### Database Configuration (config/config.php)
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ai_study_assistant');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### AI API Configuration (config/config.php)
```php
define('AI_API_KEY', '');
define('AI_API_URL', '');
define('AI_MODEL', '');
```

The app ships in **demo mode** (empty AI constants): all AI features work offline
using a built-in topic-detection engine, so the project is fully demonstrable
without any API key.

To switch to **real AI mode**, set an OpenAI-compatible chat-completions endpoint:

```php
// Example: OpenAI
define('AI_API_KEY', 'sk-your-key-here');
define('AI_API_URL', 'https://api.openai.com/v1/chat/completions');
define('AI_MODEL', 'gpt-4o-mini');

// Example: any OpenAI-compatible provider or local server (Ollama, LM Studio...)
define('AI_API_KEY', 'ollama');                       // any non-empty string
define('AI_API_URL', 'http://localhost:11434/v1/chat/completions');
define('AI_MODEL', 'llama3.1');
```

Requirements for the endpoint:
- Accepts `POST {model, messages: [{role, content}, ...]}`
- Returns `{"choices": [{"message": {"content": "..."}}]}` with HTTP 200

Once configured, every AI feature uses live responses automatically:
- **AI Tutor** (`ai-tutor.php`) - graduated hint levels 1-4 via level-engineered prompts;
  answers are only revealed after level 4 or on explicit student request
- **Course Summarizer** (`course-summarizer.php`) - summary, key points,
  definitions, exam questions (expects JSON output from the model)
- **Quiz Generator** (`quiz-center.php`) - generates multiple-choice questions
  as JSON; falls back to simulated questions if the response is unparseable

## 🚀 Deployment Notes

For production deployment:
1. Update database credentials
2. Configure AI API keys
3. Set up proper error logging
4. Configure email settings
5. Enable HTTPS
6. Set appropriate file permissions
7. Configure production-timezone
8. Review and update security settings

## 📄 License

This is a final-year Computer Science project built for educational purposes.

## 👥 Development

Built following a phased development approach:
1. Project structure + configuration
2. Database schema + seed data
3. Shared components + design system
4. Landing page
5. User authentication
6. Admin system
7. User dashboard
8. Core features (courses, notes, study sessions)
9. AI integration
10. Advanced features (flashcards, quizzes)
11. Gamification (journal, progress, achievements)
12. Responsive design + UI polish
13. Testing and bug fixing

## 🎯 Key Features Implemented

✅ Complete user authentication system
✅ Admin dashboard with user management
✅ AI Tutor with real-time responses
✅ Course Summarizer with structured output
✅ Flashcard system with flip animations
✅ Quiz Center with result tracking
✅ Study session tracking with progress
✅ Journal with mood tracking
✅ Progress monitoring with statistics
✅ Achievement system with auto-unlocking
✅ Responsive design for all devices
✅ Centralized configuration and routing
✅ Security best practices throughout

---

**Tagline:** Learn Smarter, Not Harder.

**Official Logo:** Brain + Open Book concept
