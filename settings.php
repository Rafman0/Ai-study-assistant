<?php
$page_title = 'Settings';
$active_page = 'settings';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/Auth.php';

Auth::require_login();

$db = get_db_connection();
$user_id = get_current_user_id();
$user = Auth::get_current_user();

$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = sanitize_input($_POST['name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');

    if ($name === '' || $email === '') {
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($db) {
        try {
            // Ensure email is not taken by another account
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $error = 'That email is already in use by another account.';
            } else {
                $stmt = $db->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $stmt->execute([$name, $email, $user_id]);
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $user = Auth::get_current_user();
                $success = 'Profile updated successfully.';
            }
        } catch (PDOException $e) {
            error_log("Settings profile error: " . $e->getMessage());
            $error = 'Failed to update profile. Please try again.';
        }
    } else {
        $error = 'Database connection failed.';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($current === '' || $new === '' || $confirm === '') {
        $error = 'All password fields are required.';
    } elseif (strlen($new) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } elseif ($db) {
        try {
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $hash = $stmt->fetchColumn();

            if (!$hash || !password_verify($current, $hash)) {
                $error = 'Your current password is incorrect.';
            } else {
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user_id]);
                $success = 'Password changed successfully.';
            }
        } catch (PDOException $e) {
            error_log("Settings password error: " . $e->getMessage());
            $error = 'Failed to change password. Please try again.';
        }
    } else {
        $error = 'Database connection failed.';
    }
}

$layout_app_shell = true;
require_once __DIR__ . '/views/partials/navbar.php';
?>

<header class="page-heading">
    <h1>Settings</h1>
    <p>Manage your account preferences</p>
</header>

<?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <!-- Profile Settings -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">Profile</h3>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                            <div class="form-group">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name"
                                       value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required maxlength="255">
                            </div>

                            <div class="form-group">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required maxlength="255">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Member Since</label>
                                <p style="margin: 0; color: var(--color-gray-600);">
                                    <?php echo date('F j, Y', strtotime($user['created_at'] ?? 'now')); ?>
                                </p>
                            </div>

                            <button type="submit" name="update_profile" value="1" class="btn btn-primary">Save Profile</button>
                        </form>
                    </div>
                </div>

                <!-- Password Settings -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">Change Password</h3>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                            <div class="form-group">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
                            </div>

                            <div class="form-group">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6" autocomplete="new-password">
                                <small class="form-text">Minimum 6 characters.</small>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password">
                            </div>

                            <button type="submit" name="change_password" value="1" class="btn btn-primary">Update Password</button>
                        </form>
                    </div>
                </div>

                <!-- Account Info -->
                <div class="card">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">Account</h3>
                        <p style="color: var(--color-gray-600);">
                            Signed in as <strong><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></strong>
                            (<?php echo htmlspecialchars(ucfirst($_SESSION['user_role'] ?? 'user')); ?>)
                        </p>
                        <a href="<?php echo app_url('logout.php'); ?>" class="btn btn-outline" style="border-color: var(--color-error); color: var(--color-error);">
                            Sign Out
                        </a>
                    </div>
                </div>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
