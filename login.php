<?php
$page_title = 'Login';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/Auth.php';
require_once __DIR__ . '/utils/Security.php';

// Redirect if already logged in
if (is_logged_in()) {
    // Route already-authenticated users to their own dashboard based on role
    if (is_admin_logged_in()) {
        redirect(app_url('admin/dashboard.php'));
    }
    redirect(app_url('dashboard.php'));
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Get form data
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        // Validate inputs
        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            // Check rate limiting
            $rate_limit = Security::check_rate_limit('login_' . md5($email));
            if (!$rate_limit['allowed']) {
                $error = 'Too many login attempts. Please try again in ' . ceil($rate_limit['retry_after'] / 60) . ' minutes.';
            } else {
                // Attempt login
                $result = Auth::login($email, $password, $remember);
                if ($result['success']) {
                    Security::clear_rate_limit('login_' . md5($email));
                    
                    // Update last login
                    $db = get_db_connection();
                    if ($db) {
                        try {
                            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                            $stmt->execute([get_current_user_id()]);
                        } catch (PDOException $e) {
                            error_log("Failed to update last login: " . $e->getMessage());
                        }
                    }
                    
                    // Redirect to the correct dashboard based on role
                    if (is_admin_logged_in()) {
                        redirect(app_url('admin/dashboard.php'));
                    }
                    redirect(app_url('dashboard.php'));
                } else {
                    Security::record_attempt('login_' . md5($email));
                    $error = $result['message'];
                }
            }
        }
    }
}

require_once __DIR__ . '/views/partials/navbar.php';
?>

<!-- Login Section -->
<section class="auth-section" style="padding: 80px 0; background-color: var(--color-gray-50);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-6 col-lg-5">
                <div class="card" style="padding: var(--spacing-2xl);">
                    <div class="text-center mb-4">
                        <img src="<?php echo asset_url('images/ai-study-logo.png'); ?>" alt="AI Study Assistant Logo" style="width: 64px; height: 64px; margin-bottom: var(--spacing-md); object-fit: contain;">
                        <h2 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-sm);">Welcome Back</h2>
                        <p style="color: var(--color-gray-600);">Login to continue your learning journey</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="form-group d-flex align-items-center justify-content-between">
                            <div>
                                <input type="checkbox" id="remember" name="remember" style="margin-right: var(--spacing-sm);">
                                <label for="remember" style="color: var(--color-gray-600);">Remember me</label>
                            </div>
                            <a href="#" style="color: var(--color-accent); font-size: var(--font-size-sm);">Forgot password?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: var(--spacing-md);">Login</button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p style="color: var(--color-gray-600);">
                            Don't have an account? 
                            <a href="<?php echo app_url('register.php'); ?>" style="color: var(--color-accent);">Create one here</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/views/partials/footer.php';
?>
