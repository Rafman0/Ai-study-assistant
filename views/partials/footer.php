<?php if (!empty($layout_app_shell)): ?>
    </main>
</div>
<?php elseif (empty($layout_shell_only)): ?>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-12 col-md-4">
                    <div class="footer-brand">
                        <img src="<?php echo asset_url('images/ai-study-logo.png'); ?>" alt="AI Study Assistant Logo">
                        <div>
                            <h5><?php echo APP_NAME; ?></h5>
                        </div>
                    </div>
                    <p class="text-white-50">
                        Your intelligent companion for academic success. Learn smarter, not harder.
                    </p>
                </div>
                
                <div class="col-12 col-md-4">
                    <h5 class="text-white mb-3">Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="<?php echo app_url('index.php'); ?>">Home</a></li>
                        <li><a href="<?php echo app_url('login.php'); ?>">Login</a></li>
                        <li><a href="<?php echo app_url('register.php'); ?>">Register</a></li>
                        <?php if (is_logged_in()): ?>
                            <li><a href="<?php echo app_url('dashboard.php'); ?>">Dashboard</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="col-12 col-md-4">
                    <h5 class="text-white mb-3">Features</h5>
                    <ul class="footer-links">
                        <li><a href="<?php echo app_url('ai-tutor.php'); ?>">AI Tutor</a></li>
                        <li><a href="<?php echo app_url('course-summarizer.php'); ?>">Course Summarizer</a></li>
                        <li><a href="<?php echo app_url('flashcards.php'); ?>">Flashcards</a></li>
                        <li><a href="<?php echo app_url('quiz-center.php'); ?>">Quiz Center</a></li>
                        <li><a href="<?php echo app_url('journal.php'); ?>">Study Journal</a></li>
                        <li><a href="<?php echo app_url('progress.php'); ?>">Progress Tracking</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <!-- Bootstrap JS (served locally to avoid CDN latency) -->
    <script src="<?php echo asset_url('vendor/bootstrap.bundle.min.js'); ?>"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo asset_url('js/app.js'); ?>"></script>
    
    <?php if (isset($extra_js)): ?>
        <?php echo $extra_js; ?>
    <?php endif; ?>
</body>
</html>
