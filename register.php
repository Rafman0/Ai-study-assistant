<?php
$page_title = 'Register';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/Auth.php';
require_once __DIR__ . '/utils/Security.php';
require_once __DIR__ . '/utils/Validator.php';

// Redirect if already logged in
if (is_logged_in()) {
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
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate inputs
        $validation = Validator::validate([
            'name' => ['required', 'min:2', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8'],
            'confirm_password' => ['required']
        ], [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'confirm_password' => $confirm_password
        ]);
        
        if (!$validation['valid']) {
            $error = implode('<br>', $validation['errors']);
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            // Additional password strength check
            $password_check = Security::validate_password($password);
            if (!$password_check['valid']) {
                $error = $password_check['message'];
            } else {
                // Attempt registration
                $result = Auth::register($name, $email, $password);
                if ($result['success']) {
                    $success = 'Registration successful! You can now login.';
                    // Clear form
                    $name = $email = $password = $confirm_password = '';
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
}

require_once __DIR__ . '/views/partials/navbar.php';
?>

<!-- Registration Section -->
<section class="auth-section" style="padding: 80px 0; background-color: var(--color-gray-50);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-6 col-lg-5">
                <div class="card" style="padding: var(--spacing-2xl);">
                    <div class="text-center mb-4">
                        <img src="<?php echo asset_url('images/ai-study-logo.png'); ?>" alt="AI Study Assistant Logo" style="width: 64px; height: 64px; margin-bottom: var(--spacing-md); object-fit: contain;">
                        <h2 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-sm);">Create Account</h2>
                        <p style="color: var(--color-gray-600);">Join AI Study Assistant and start learning smarter</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                            <div class="mt-3">
                                <a href="<?php echo app_url('login.php'); ?>" class="btn btn-primary">Go to Login</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <div class="form-group">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="form-text">Must be at least 8 characters with uppercase, lowercase, and number.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: var(--spacing-md);">Create Account</button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p style="color: var(--color-gray-600);">
                                Already have an account? 
                                <a href="<?php echo app_url('login.php'); ?>" style="color: var(--color-accent);">Login here</a>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/views/partials/footer.php';
?>
