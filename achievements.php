<?php
$page_title = 'Achievements';
$active_page = 'achievements';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/Auth.php';
require_once __DIR__ . '/models/Achievement.php';

Auth::require_login();

$user_id = get_current_user_id();
$achievements = Achievement::get_user_achievements($user_id);

// Check for new achievements
$new_unlocks = Achievement::check_achievements($user_id);
if (!empty($new_unlocks)) {
    $achievements = Achievement::get_user_achievements($user_id);
}

$layout_app_shell = true;
require_once __DIR__ . '/views/partials/navbar.php';
?>

<header class="page-heading">
    <h1>Achievements</h1>
    <p>View your earned badges and accomplishments</p>
</header>

<?php if (!empty($new_unlocks)): ?>
                    <div class="alert alert-success">
                        <strong>🎉 New Achievement(s) Unlocked:</strong> <?php echo implode(', ', $new_unlocks); ?>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">My Achievements</h3>
                        
                        <?php if (empty($achievements)): ?>
                            <p style="color: var(--color-gray-600);">No achievements yet. Start learning to unlock badges!</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($achievements as $achievement): ?>
                                    <div class="col-12 col-md-6 mb-4">
                                        <div class="card h-100" style="opacity: <?php echo $achievement['unlocked_at'] ? '1' : '0.5'; ?>;">
                                            <div class="card-body">
                                                <div class="d-flex align-items-start">
                                                    <div style="font-size: var(--font-size-4xl); margin-right: var(--spacing-md);">
                                                        <?php echo $achievement['icon'] ?? '🏆'; ?>
                                                    </div>
                                                    <div style="flex: 1;">
                                                        <h4 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-xs);">
                                                            <?php echo htmlspecialchars($achievement['name']); ?>
                                                        </h4>
                                                        <p style="color: var(--color-gray-600); font-size: var(--font-size-sm); margin-bottom: var(--spacing-sm);">
                                                            <?php echo htmlspecialchars($achievement['description']); ?>
                                                        </p>
                                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                                            <span style="background-color: var(--color-accent); color: var(--color-white); padding: 2px 8px; border-radius: 4px; font-size: var(--font-size-sm);">
                                                                <?php echo number_format($achievement['points']); ?> points
                                                            </span>
                                                            <?php if ($achievement['unlocked_at']): ?>
                                                                <span style="font-size: var(--font-size-sm); color: var(--color-success);">
                                                                    ✓ Unlocked <?php echo date('M j, Y', strtotime($achievement['unlocked_at'])); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span style="font-size: var(--font-size-sm); color: var(--color-gray-500);">
                                                                    🔒 Locked
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
