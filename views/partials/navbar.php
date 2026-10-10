<?php
require_once __DIR__ . '/../../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo APP_TAGLINE; ?>">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo asset_url('images/favicon.png'); ?>">
    
    <!-- Bootstrap CSS (served locally to avoid CDN latency/blocking) -->
    <link href="<?php echo asset_url('vendor/bootstrap.min.css'); ?>" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo asset_url('css/style.css?v=6'); ?>">
    
    <?php if (isset($extra_css)): ?>
        <?php echo $extra_css; ?>
    <?php endif; ?>
</head>
<body>
    <?php if (!empty($layout_app_shell)): ?>
    <!-- Mobile top bar with hamburger menu (visible only on small screens) -->
    <div class="app-topbar">
        <button class="app-hamburger" type="button" onclick="toggleMobileSidebar()" aria-label="Toggle navigation menu" aria-expanded="false">☰</button>
        <img src="<?php echo asset_url('images/ai-study-logo.png'); ?>" alt="" class="app-topbar-logo">
        <span class="app-topbar-brand"><?php echo APP_NAME; ?></span>
    </div>

    <!-- App shell: single full-height sidebar + main content column -->
    <div class="modern-dashboard">
        <aside class="modern-sidebar">
            <?php include __DIR__ . '/user_sidebar.php'; ?>
        </aside>
        <main class="modern-main-content">
    <?php elseif (empty($layout_shell_only)): ?>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a href="<?php echo app_url(); ?>" class="navbar-brand">
                <img src="<?php echo asset_url('images/ai-study-logo.png'); ?>" alt="AI Study Assistant Logo">
                <span><?php echo APP_NAME; ?></span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (is_admin_logged_in()): ?>
                        <!-- Admin Navigation -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo app_url('admin/dashboard.php'); ?>">Admin Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo app_url('admin/users.php'); ?>">Users</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo app_url('admin/courses.php'); ?>">Courses</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo app_url('admin/logout.php'); ?>">Logout</a>
                        </li>
                    <?php elseif (is_logged_in()): ?>
                        <!-- User Navigation -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo app_url('dashboard.php'); ?>">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo app_url('ai-tutor.php'); ?>">AI Tutor</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo app_url('courses.php'); ?>">Courses</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="<?php echo app_url('settings.php'); ?>">Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo app_url('logout.php'); ?>">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- Public Navigation -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo app_url('index.php'); ?>">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo app_url('login.php'); ?>">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-primary" href="<?php echo app_url('register.php'); ?>">Get Started</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
    <?php else: ?>
    <!-- Shell-only layout: page renders its own full-height app shell (e.g. dashboard sidebar + content) -->
    <?php if (is_logged_in() && !is_admin_logged_in()): ?>
    <!-- Mobile top bar with hamburger menu (visible only on small screens) -->
    <div class="app-topbar">
        <button class="app-hamburger" type="button" onclick="toggleMobileSidebar()" aria-label="Toggle navigation menu" aria-expanded="false">☰</button>
        <img src="<?php echo asset_url('images/ai-study-logo.png'); ?>" alt="" class="app-topbar-logo">
        <span class="app-topbar-brand"><?php echo APP_NAME; ?></span>
    </div>
    <?php endif; ?>
    <?php endif; ?>