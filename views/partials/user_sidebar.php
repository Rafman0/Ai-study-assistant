<?php
$active_page = $active_page ?? 'dashboard';
$menu_items = [
    'dashboard' => ['📊', 'Dashboard', 'dashboard.php'],
    'notifications' => ['🔔', 'Notifications', 'notifications.php'],
    'ai-tutor' => ['🤖', 'AI Tutor', 'ai-tutor.php'],
    'course-summarizer' => ['📚', 'Course Summarizer', 'course-summarizer.php'],
    'courses' => ['📚', 'Manage Courses', 'courses.php'],
    'study-session' => ['⏱️', 'Study Session', 'study-session.php'],
    'flashcards' => ['🃏', 'Flashcards', 'flashcards.php'],
    'quiz-center' => ['📝', 'Quiz Center', 'quiz-center.php'],
    'notes' => ['📝', 'Notes', 'notes.php'],
    'journal' => ['📔', 'Journal', 'journal.php'],
    'progress' => ['📈', 'Progress', 'progress.php'],
    'achievements' => ['🏆', 'Achievements', 'achievements.php'],
    'settings' => ['⚙️', 'Settings', 'settings.php'],
];
?>
<div class="sidebar-header">
    <a href="<?php echo app_url('index.php'); ?>" class="sidebar-home-link" aria-label="AI Study Assistant - Back to Home">
        <img src="<?php echo asset_url('images/ai-study-logo.png'); ?>" alt="AI Study Assistant" class="sidebar-logo">
        <span class="sidebar-brand">AI Study Assistant</span>
    </a>
</div>

<nav class="sidebar-nav">
    <?php foreach ($menu_items as $key => $item): ?>
        <a href="<?php echo app_url($item[2]); ?>" class="sidebar-link <?php echo $active_page === $key ? 'active' : ''; ?>">
            <span class="sidebar-icon"><?php echo $item[0]; ?></span>
            <span class="sidebar-text"><?php echo $item[1]; ?></span>
        </a>
    <?php endforeach; ?>
    <a href="<?php echo app_url('logout.php'); ?>" class="sidebar-link logout-link">
        <span class="sidebar-icon">🚪</span>
        <span class="sidebar-text">Logout</span>
    </a>
</nav>

<button class="mobile-menu-toggle" id="mobile-menu-toggle" onclick="toggleMobileSidebar()" aria-label="Toggle menu">☰</button>

<script>
(function () {
    var backdrop = null;

    function getBackdrop() {
        if (backdrop && document.body.contains(backdrop)) {
            return backdrop;
        }
        backdrop = document.createElement('div');
        backdrop.className = 'sidebar-backdrop';
        backdrop.addEventListener('click', function () {
            toggleMobileSidebar();
        });
        document.body.appendChild(backdrop);
        return backdrop;
    }

    function toggleMobileSidebar() {
        var sidebar = document.querySelector('.modern-sidebar');
        if (!sidebar) { return; }
        var open = sidebar.classList.toggle('show');
        if (open) {
            getBackdrop().classList.add('show');
            document.body.classList.add('sidebar-open');
        } else {
            if (backdrop) { backdrop.classList.remove('show'); }
            document.body.classList.remove('sidebar-open');
        }
    }

    window.toggleMobileSidebar = toggleMobileSidebar;

    // Close the drawer on mobile once a navigation link is chosen
    document.addEventListener('click', function (e) {
        var link = e.target.closest ? e.target.closest('.sidebar-link') : null;
        if (link && window.innerWidth <= 992) {
            var sidebar = document.querySelector('.modern-sidebar');
            if (sidebar) { sidebar.classList.remove('show'); }
            if (backdrop) { backdrop.classList.remove('show'); }
            document.body.classList.remove('sidebar-open');
        }
    });
})();
</script>
