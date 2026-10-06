<?php
$page_title = 'Settings';
$admin_active = 'settings';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Auth.php';

// Require admin login
Auth::require_admin();

$message = '';
$message_type = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Invalid request. Please try again.';
        $message_type = 'danger';
    } else {
        // Get form data
        $site_name = trim($_POST['site_name'] ?? APP_NAME);
        $site_tagline = trim($_POST['site_tagline'] ?? APP_TAGLINE);
        $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';

        // Limit lengths to keep values sane
        if (mb_strlen($site_name) > 100) {
            $site_name = mb_substr($site_name, 0, 100);
        }
        if (mb_strlen($site_tagline) > 200) {
            $site_tagline = mb_substr($site_tagline, 0, 200);
        }

        $ok_name = set_site_setting('site_name', $site_name !== '' ? $site_name : null);
        $ok_tagline = set_site_setting('site_tagline', $site_tagline !== '' ? $site_tagline : null);
        $ok_maintenance = set_site_setting('maintenance_mode', $maintenance_mode);

        if ($ok_name && $ok_tagline && $ok_maintenance) {
            $message = 'Settings saved successfully.'
                . ($maintenance_mode === '1'
                    ? ' Maintenance mode is now ENABLED - non-admin visitors will see the maintenance notice.'
                    : ' Maintenance mode is disabled.');
            $message_type = 'success';
        } else {
            $message = 'Could not save settings. Please try again.';
            $message_type = 'danger';
        }
    }
}

// Load current settings (fall back to config defaults)
$current_site_name = get_site_setting('site_name', APP_NAME);
$current_site_tagline = get_site_setting('site_tagline', APP_TAGLINE);
$current_maintenance = get_site_setting('maintenance_mode', '0');

require_once __DIR__ . '/../views/admin/partials/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <h6 style="font-weight:600;color:var(--color-primary-dark);text-transform:uppercase;letter-spacing:0.04em;font-size:var(--font-size-xs);margin-bottom:var(--spacing-md)">Application Settings</h6>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                    <div class="mb-3">
                        <label for="site_name" class="form-label">Site Name</label>
                        <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo htmlspecialchars($current_site_name); ?>">
                        <div class="form-text">The name of your application</div>
                    </div>

                    <div class="mb-3">
                        <label for="site_tagline" class="form-label">Site Tagline</label>
                        <input type="text" class="form-control" id="site_tagline" name="site_tagline" value="<?php echo htmlspecialchars($current_site_tagline); ?>">
                        <div class="form-text">A short tagline for your application</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Maintenance Mode</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" <?php echo $current_maintenance === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="maintenance_mode">Enable maintenance mode</label>
                        </div>
                        <div class="form-text">When enabled, only admins can access the site. Non-admin visitors will see the maintenance notice.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <h6 style="font-weight:600;color:var(--color-primary-dark);text-transform:uppercase;letter-spacing:0.04em;font-size:var(--font-size-xs);margin-bottom:var(--spacing-md)">Current Configuration</h6>
                <table class="admin-table">
                    <tbody>
                        <tr><td class="text-muted">App Base URL</td><td class="text-end"><?php echo htmlspecialchars(APP_BASE_URL); ?></td></tr>
                        <tr><td class="text-muted">Database</td><td class="text-end"><?php echo htmlspecialchars(DB_NAME); ?></td></tr>
                        <tr><td class="text-muted">PHP Version</td><td class="text-end"><?php echo htmlspecialchars(PHP_VERSION); ?></td></tr>
                        <tr><td class="text-muted">MySQL Version</td><td class="text-end"><?php
                            $db = get_db_connection();
                            if ($db) {
                                $stmt = $db->query("SELECT VERSION()");
                                echo htmlspecialchars($stmt->fetchColumn());
                            } else {
                                echo 'Not connected';
                            }
                        ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h6 style="font-weight:600;color:var(--color-primary-dark);text-transform:uppercase;letter-spacing:0.04em;font-size:var(--font-size-xs);margin-bottom:var(--spacing-md)">System Information</h6>
                <table class="admin-table">
                    <tbody>
                        <tr><td class="text-muted">Server Time</td><td class="text-end"><?php echo htmlspecialchars(date('Y-m-d H:i:s')); ?></td></tr>
                        <tr><td class="text-muted">Timezone</td><td class="text-end"><?php echo htmlspecialchars(date_default_timezone_get()); ?></td></tr>
                        <tr><td class="text-muted">PHP Version</td><td class="text-end"><?php echo htmlspecialchars(PHP_VERSION); ?></td></tr>
                        <tr><td class="text-muted">Server Software</td><td class="text-end"><?php echo htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../views/admin/partials/footer.php'; ?>
