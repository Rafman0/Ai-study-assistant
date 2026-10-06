<?php
$page_title = 'Home';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/views/partials/navbar.php';
?>

<!-- Hero Section -->
<section class="hero-section" style="background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-primary) 100%); color: var(--color-white); padding: 100px 0; text-align: center;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8">
                <img src="<?php echo asset_url('images/ai-study-logo.png'); ?>" alt="AI Study Assistant Logo" style="width: 120px; height: 120px; margin-bottom: var(--spacing-xl); object-fit: contain;">
                <h1 style="color: var(--color-white); margin-bottom: var(--spacing-xs);">AI Study Assistant</h1>
                <p style="font-size: var(--font-size-base); font-weight: var(--font-weight-normal); font-style: italic; color: var(--color-gray-300); letter-spacing: 0.3px; margin-bottom: var(--spacing-lg); opacity: 0.9;">Learn Smarter, Not Harder.</p>
                <p style="font-size: var(--font-size-lg); color: var(--color-gray-200); margin-bottom: var(--spacing-xl);">
                    Transform your learning experience with AI-powered tools designed to help you study more effectively, 
                    retain information longer, and achieve your academic goals faster.
                </p>
                <div class="d-flex gap-2 justify-content-center" style="flex-wrap: wrap;">
                    <a href="<?php echo app_url('register.php'); ?>" class="btn btn-lg" style="background-color: var(--color-accent); color: var(--color-white); border: none;">Get Started</a>
                    <a href="<?php echo app_url('login.php'); ?>" class="btn btn-lg btn-secondary">Login</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Everything You Need Section -->
<section class="features-section">
    <div class="container">
        <div class="features-header">
            <h2 class="features-heading">Everything You Need to Excel</h2>
            <p class="features-subheading">Comprehensive tools to enhance your learning journey</p>
        </div>
        
        <div class="row">
            <!-- AI Tutor -->
            <div class="col-12 col-md-6 col-lg-3 mb-4">
                <div class="feature-card ai-tutor">
                    <div class="feature-card-icon"><span>🤖</span></div>
                    <h3 class="feature-card-title">AI Tutor</h3>
                    <p class="feature-card-text">Get instant answers to your questions. Our AI tutor helps explain complex concepts in simple terms.</p>
                    <a href="<?php echo app_url('register.php'); ?>" class="btn btn-outline feature-card-btn">Try AI Tutor</a>
                </div>
            </div>
            
            <!-- Course Summarizer -->
            <div class="col-12 col-md-6 col-lg-3 mb-4">
                <div class="feature-card summarizer">
                    <div class="feature-card-icon"><span>📚</span></div>
                    <h3 class="feature-card-title">Course Summarizer</h3>
                    <p class="feature-card-text">Transform lengthy course materials into concise summaries with key points and important definitions.</p>
                    <a href="<?php echo app_url('register.php'); ?>" class="btn btn-outline feature-card-btn">Summarize Course</a>
                </div>
            </div>
            
            <!-- Flashcards -->
            <div class="col-12 col-md-6 col-lg-3 mb-4">
                <div class="feature-card flashcards">
                    <div class="feature-card-icon"><span>🃏</span></div>
                    <h3 class="feature-card-title">Flashcards</h3>
                    <p class="feature-card-text">Create and study with digital flashcards. Organize by course and track your memorization progress.</p>
                    <a href="<?php echo app_url('register.php'); ?>" class="btn btn-outline feature-card-btn">Create Flashcards</a>
                </div>
            </div>
            
            <!-- Quiz Center -->
            <div class="col-12 col-md-6 col-lg-3 mb-4">
                <div class="feature-card quiz">
                    <div class="feature-card-icon"><span>📝</span></div>
                    <h3 class="feature-card-title">Quiz Center</h3>
                    <p class="feature-card-text">Test your knowledge with AI-generated quizzes. Get instant feedback and detailed explanations.</p>
                    <a href="<?php echo app_url('register.php'); ?>" class="btn btn-outline feature-card-btn">Take Quiz</a>
                </div>
            </div>
            
            <!-- Study Journal -->
            <div class="col-12 col-md-6 col-lg-3 mb-4">
                <div class="feature-card journal">
                    <div class="feature-card-icon"><span>📔</span></div>
                    <h3 class="feature-card-title">Study Journal</h3>
                    <p class="feature-card-text">Track your learning journey with personal journal entries. Reflect on progress and set goals.</p>
                    <a href="<?php echo app_url('register.php'); ?>" class="btn btn-outline feature-card-btn">Start Journaling</a>
                </div>
            </div>
            
            <!-- Progress Tracking -->
            <div class="col-12 col-md-6 col-lg-3 mb-4">
                <div class="feature-card progress">
                    <div class="feature-card-icon"><span>📊</span></div>
                    <h3 class="feature-card-title">Progress Tracking</h3>
                    <p class="feature-card-text">Monitor your study habits, track achievements, and visualize your learning growth over time.</p>
                    <a href="<?php echo app_url('register.php'); ?>" class="btn btn-outline feature-card-btn">View Progress</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="how-it-works" style="padding: 80px 0; background-color: var(--color-white);">
    <div class="container">
        <div class="text-center mb-5">
            <h2 style="color: var(--color-primary-dark);">How It Works</h2>
            <p style="color: var(--color-gray-600); font-size: var(--font-size-lg);">Get started in three simple steps</p>
        </div>
        
        <div class="row">
            <div class="col-12 col-md-4 mb-4">
                <div class="text-center">
                    <div style="width: 80px; height: 80px; background-color: var(--color-accent); color: var(--color-white); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: var(--font-size-3xl); font-weight: var(--font-weight-bold); margin: 0 auto var(--spacing-md);">1</div>
                    <h4 style="color: var(--color-primary-dark);">Choose Your Course</h4>
                    <p style="color: var(--color-gray-600);">Select your course or topic from our extensive library or create your own custom courses.</p>
                </div>
            </div>
            
            <div class="col-12 col-md-4 mb-4">
                <div class="text-center">
                    <div style="width: 80px; height: 80px; background-color: var(--color-accent); color: var(--color-white); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: var(--font-size-3xl); font-weight: var(--font-weight-bold); margin: 0 auto var(--spacing-md);">2</div>
                    <h4 style="color: var(--color-primary-dark);">Learn with AI Tools</h4>
                    <p style="color: var(--color-gray-600);">Use our AI-powered tutor, summarizer, flashcards, and quizzes to master the material efficiently.</p>
                </div>
            </div>
            
            <div class="col-12 col-md-4 mb-4">
                <div class="text-center">
                    <div style="width: 80px; height: 80px; background-color: var(--color-accent); color: var(--color-white); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: var(--font-size-3xl); font-weight: var(--font-weight-bold); margin: 0 auto var(--spacing-md);">3</div>
                    <h4 style="color: var(--color-primary-dark);">Track Your Progress</h4>
                    <p style="color: var(--color-gray-600);">Monitor your learning journey with detailed progress tracking and achievement badges.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Feature Highlights Section -->
<section class="feature-highlights" style="padding: 80px 0; background-color: var(--color-gray-50);">
    <div class="container">
        <div class="text-center mb-5">
            <h2 style="color: var(--color-primary-dark);">Feature Highlights</h2>
            <p style="color: var(--color-gray-600); font-size: var(--font-size-lg);">Powerful features designed for academic success</p>
        </div>
        
        <div class="row">
            <div class="col-12 col-md-6 mb-4">
                <div class="d-flex align-items-start gap-2">
                    <div style="font-size: var(--font-size-2xl); margin-right: var(--spacing-md);">✨</div>
                    <div>
                        <h4 style="color: var(--color-primary-dark);">AI-Assisted Learning</h4>
                        <p style="color: var(--color-gray-600);">Get personalized explanations and answers powered by advanced artificial intelligence.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-md-6 mb-4">
                <div class="d-flex align-items-start gap-2">
                    <div style="font-size: var(--font-size-2xl); margin-right: var(--spacing-md);">📖</div>
                    <div>
                        <h4 style="color: var(--color-primary-dark);">Course Summaries</h4>
                        <p style="color: var(--color-gray-600);">Transform lengthy materials into concise, easy-to-understand summaries with key takeaways.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-md-6 mb-4">
                <div class="d-flex align-items-start gap-2">
                    <div style="font-size: var(--font-size-2xl); margin-right: var(--spacing-md);">🎯</div>
                    <div>
                        <h4 style="color: var(--color-primary-dark);">Personalized Study Assistance</h4>
                        <p style="color: var(--color-gray-600);">Tailored learning paths based on your progress, strengths, and areas for improvement.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-md-6 mb-4">
                <div class="d-flex align-items-start gap-2">
                    <div style="font-size: var(--font-size-2xl); margin-right: var(--spacing-md);">🃏</div>
                    <div>
                        <h4 style="color: var(--color-primary-dark);">Interactive Flashcards</h4>
                        <p style="color: var(--color-gray-600);">Digital flashcards with spaced repetition to optimize your memorization and retention.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-md-6 mb-4">
                <div class="d-flex align-items-start gap-2">
                    <div style="font-size: var(--font-size-2xl); margin-right: var(--spacing-md);">🏆</div>
                    <div>
                        <h4 style="color: var(--color-primary-dark);">Smart Quizzes</h4>
                        <p style="color: var(--color-gray-600);">AI-generated quizzes that adapt to your knowledge level with detailed feedback.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-md-6 mb-4">
                <div class="d-flex align-items-start gap-2">
                    <div style="font-size: var(--font-size-2xl); margin-right: var(--spacing-md);">📊</div>
                    <div>
                        <h4 style="color: var(--color-primary-dark);">Progress Tracking</h4>
                        <p style="color: var(--color-gray-600);">Comprehensive analytics to monitor your study habits, achievements, and learning growth.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action Section -->
<section class="cta-section" style="background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%); color: var(--color-white); padding: 80px 0; text-align: center;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8">
                <h2 style="color: var(--color-white); margin-bottom: var(--spacing-md);">Ready to Transform Your Learning?</h2>
                <p style="font-size: var(--font-size-lg); color: var(--color-gray-200); margin-bottom: var(--spacing-xl);">
                    Join thousands of students who are already learning smarter with AI Study Assistant. 
                    Create your free account today and start your journey to academic excellence.
                </p>
                <a href="<?php echo app_url('register.php'); ?>" class="btn btn-lg" style="background-color: var(--color-accent); color: var(--color-white); border: none; padding: var(--spacing-md) var(--spacing-2xl);">Create Free Account</a>
            </div>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/views/partials/footer.php';
?>
