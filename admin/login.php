<?php
$page_title = 'Admin Login';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Security.php';

// Redirect if already logged in as admin
if (is_admin_logged_in()) {
    redirect(app_url('admin/dashboard.php'));
}

// Redirect if logged in as regular user
if (is_logged_in()) {
    // Route based on role so admins go to the admin area, not the student dashboard
    if (is_admin_logged_in()) {
        redirect(app_url('admin/dashboard.php'));
    }
    redirect(app_url('dashboard.php'));
}

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Get form data
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validate inputs
        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            // Check rate limiting
            $rate_limit = Security::check_rate_limit('admin_login_' . md5($email));
            if (!$rate_limit['allowed']) {
                $error = 'Too many login attempts. Please try again in ' . ceil($rate_limit['retry_after'] / 60) . ' minutes.';
            } else {
                // Attempt admin login (check against admin users in database)
                $db = get_db_connection();
                if (!$db) {
                    $error = 'Database connection failed. Please try again.';
                } else {
                    try {
                        $stmt = $db->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? AND role = 'admin' LIMIT 1");
                        $stmt->execute([$email]);
                        $admin = $stmt->fetch();
                        
                        if ($admin && password_verify($password, $admin['password'])) {
                            // Set admin session
                            $_SESSION['admin_id'] = $admin['id'];
                            $_SESSION['admin_name'] = $admin['name'];
                            $_SESSION['admin_email'] = $admin['email'];
                            $_SESSION['admin_role'] = $admin['role'];
                            
                            Security::clear_rate_limit('admin_login_' . md5($email));
                            
                            // Redirect to admin dashboard
                            redirect(app_url('admin/dashboard.php'));
                        } else {
                            Security::record_attempt('admin_login_' . md5($email));
                            $error = 'Invalid email or password';
                        }
                    } catch (PDOException $e) {
                        error_log("Admin login error: " . $e->getMessage());
                        $error = 'Login failed. Please try again.';
                    }
                }
            }
        }
    }
}

require_once __DIR__ . '/../views/partials/navbar.php';
?>

<!-- Admin Login Section -->
<section class="auth-section" style="padding: 80px 0; background-color: var(--color-gray-50);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-6 col-lg-5">
                <div class="card" style="padding: var(--spacing-2xl);">
                    <div class="text-center mb-4">
                        <img src="<?php echo asset_url('images/ai-study-logo.png'); ?>" alt="AI Study Assistant Logo" style="width: 64px; height: 64px; margin-bottom: var(--spacing-md); object-fit: contain;">
                        <h2 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-sm);">Admin Login</h2>
                        <p style="color: var(--color-gray-600);">Access the administrative dashboard</p>
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
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: var(--spacing-md);">Admin Login</button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p style="color: var(--color-gray-600);">
                            <a href="<?php echo app_url('index.php'); ?>" style="color: var(--color-accent);">Back to Home</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/../views/partials/footer.php';
?>
