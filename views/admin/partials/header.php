<?php
/**
 * Admin page header — opens the HTML wrapper and topbar
 * Expects $page_title and $admin_active to already be set.
 */
if (!isset($page_title)) $page_title = 'Admin';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/Auth.php';
Auth::require_admin();
$admin_name = $_SESSION['admin_name'] ?? $_SESSION['user_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> — <?php echo APP_NAME; ?> Admin</title>
    <link rel="icon" type="image/png" href="<?php echo asset_url('images/favicon.png'); ?>">
    <link href="<?php echo asset_url('vendor/bootstrap.min.css'); ?>" rel="stylesheet">
    <link href="<?php echo asset_url('vendor/bootstrap-icons.min.css'); ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset_url('css/style.css?v=3'); ?>">
    <?php if (!empty($extra_css)) echo $extra_css; ?>
</head>
<body class="admin-body">
<div class="admin-layout">
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="admin-sidebar-overlay" id="adminSidebarOverlay"></div>
<div class="admin-main">
<header class="admin-topbar">
    <button class="admin-sidebar-toggle d-md-none" id="admin-sidebar-toggle"><i class="bi bi-list fs-4"></i></button>
    <h6 class="admin-topbar-title mb-0"><?php echo htmlspecialchars($page_title); ?></h6>
    <div class="d-flex align-items-center gap-3">
        <a href="<?php echo app_url('index.php'); ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="bi bi-box-arrow-up-right"></i> View Site</a>
        <span class="d-none d-sm-inline text-muted small"><?php echo htmlspecialchars($admin_name); ?></span>
    </div>
</header>
<div class="admin-content">
<?php if (!empty($flash_notice)): ?>
<div class="alert alert-<?php echo htmlspecialchars($flash_notice['type'] ?? 'info'); ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($flash_notice['text'] ?? ''); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>
