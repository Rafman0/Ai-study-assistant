<?php
/**
 * Admin sidebar — dark fixed sidebar with navigation
 * Expects $admin_active to be set by the including page (e.g. 'dashboard', 'users', etc.)
 */
if (!isset($admin_active)) $admin_active = 'dashboard';
$admin_name = $_SESSION['admin_name'] ?? $_SESSION['user_name'] ?? 'Admin';

$nav = [
    ['key' => 'dashboard',     'icon' => '<i class="bi bi-grid-1x2-fill"></i>', 'label' => 'Dashboard',     'href' => 'admin/dashboard.php'],
    ['key' => 'users',         'icon' => '<i class="bi bi-people-fill"></i>',    'label' => 'User Management', 'href' => 'admin/users.php'],
    ['key' => 'courses',       'icon' => '<i class="bi bi-book-half"></i>',      'label' => 'Course Management', 'href' => 'admin/courses.php'],
    ['key' => 'quizzes',       'icon' => '<i class="bi bi-clipboard2-data-fill"></i>', 'label' => 'Quiz Management', 'href' => 'admin/quizzes.php'],
    ['key' => 'flashcards',    'icon' => '<i class="bi bi-layers-fill"></i>',    'label' => 'Flashcards',    'href' => 'admin/flashcards.php'],
    ['key' => 'analytics',     'icon' => '<i class="bi bi-graph-up-arrow"></i>', 'label' => 'AI Analytics',  'href' => 'admin/analytics.php'],
    ['key' => 'announcements', 'icon' => '<i class="bi bi-megaphone-fill"></i>', 'label' => 'Announcements', 'href' => 'admin/announcements.php'],
    ['key' => 'settings',      'icon' => '<i class="bi bi-gear-fill"></i>',      'label' => 'Settings',      'href' => 'admin/settings.php'],
];
?>
<aside class="admin-sidebar" id="admin-sidebar">
    <div class="admin-sidebar-brand">
        <i class="bi bi-shield-lock-fill"></i>
        <span>Admin Panel</span>
    </div>
    <nav class="admin-sidebar-nav">
        <?php foreach ($nav as $item): ?>
        <a href="<?php echo app_url($item['href']); ?>" class="admin-sidebar-link <?php echo $admin_active === $item['key'] ? 'active' : ''; ?>">
            <?php echo $item['icon']; ?>
            <span><?php echo $item['label']; ?></span>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="admin-sidebar-footer">
        <div class="admin-sidebar-user">
            <i class="bi bi-person-circle"></i>
            <span><?php echo htmlspecialchars($admin_name); ?></span>
        </div>
        <a href="<?php echo app_url('admin/logout.php'); ?>" class="admin-sidebar-link logout">
            <i class="bi bi-box-arrow-left"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>
