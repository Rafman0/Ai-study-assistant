<?php
$page_title = 'Progress';
$active_page = 'progress';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/Auth.php';
require_once __DIR__ . '/models/Progress.php';
require_once __DIR__ . '/models/StudySession.php';

Auth::require_login();

$user_id = get_current_user_id();
$progress_data = Progress::get_user_progress($user_id);
$overall_stats = Progress::get_overall_stats($user_id);
$total_study_time = StudySession::get_total_study_time($user_id);

$layout_app_shell = true;
require_once __DIR__ . '/views/partials/navbar.php';
?>

<header class="page-heading">
    <h1>Progress</h1>
    <p>Track your learning growth and achievements</p>
</header>

<!-- Overall Statistics -->
<div class="card mb-4">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">Overall Statistics</h3>
                        
                        <div class="row">
                            <div class="col-12 col-md-3 mb-3">
                                <div style="text-align: center; padding: var(--spacing-md); background-color: var(--color-gray-50); border-radius: var(--border-radius-md);">
                                    <div style="font-size: var(--font-size-3xl); font-weight: var(--font-weight-bold); color: var(--color-accent);">
                                        <?php echo floor($total_study_time / 60); ?>h <?php echo $total_study_time % 60; ?>m
                                    </div>
                                    <div style="color: var(--color-gray-600); font-size: var(--font-size-sm);">Total Study Time</div>
                                </div>
                            </div>
                            
                            <div class="col-12 col-md-3 mb-3">
                                <div style="text-align: center; padding: var(--spacing-md); background-color: var(--color-gray-50); border-radius: var(--border-radius-md);">
                                    <div style="font-size: var(--font-size-3xl); font-weight: var(--font-weight-bold); color: var(--color-success);">
                                        <?php echo number_format($overall_stats['total_sessions'] ?? 0); ?>
                                    </div>
                                    <div style="color: var(--color-gray-600); font-size: var(--font-size-sm);">Study Sessions</div>
                                </div>
                            </div>
                            
                            <div class="col-12 col-md-3 mb-3">
                                <div style="text-align: center; padding: var(--spacing-md); background-color: var(--color-gray-50); border-radius: var(--border-radius-md);">
                                    <div style="font-size: var(--font-size-3xl); font-weight: var(--font-weight-bold); color: var(--color-flashcards);">
                                        <?php echo number_format($overall_stats['current_streak'] ?? 0); ?>
                                    </div>
                                    <div style="color: var(--color-gray-600); font-size: var(--font-size-sm);">Current Streak</div>
                                </div>
                            </div>
                            
                            <div class="col-12 col-md-3 mb-3">
                                <div style="text-align: center; padding: var(--spacing-md); background-color: var(--color-gray-50); border-radius: var(--border-radius-md);">
                                    <div style="font-size: var(--font-size-3xl); font-weight: var(--font-weight-bold); color: var(--color-progress);">
                                        <?php echo number_format($overall_stats['longest_streak'] ?? 0); ?>
                                    </div>
                                    <div style="color: var(--color-gray-600); font-size: var(--font-size-sm);">Longest Streak</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Course Progress -->
                <div class="card">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">Course Progress</h3>
                        
                        <?php if (empty($progress_data)): ?>
                            <p style="color: var(--color-gray-600);">No progress data yet. Start studying to track your progress!</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table" style="width: 100%;">
                                    <thead>
                                        <tr style="background-color: var(--color-gray-100);">
                                            <th style="padding: var(--spacing-sm);">Course</th>
                                            <th style="padding: var(--spacing-sm);">Study Time</th>
                                            <th style="padding: var(--spacing-sm);">Sessions</th>
                                            <th style="padding: var(--spacing-sm);">Avg Quiz Score</th>
                                            <th style="padding: var(--spacing-sm);">Last Study</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($progress_data as $progress): ?>
                                            <tr style="border-bottom: 1px solid var(--color-gray-200);">
                                                <td style="padding: var(--spacing-sm);">
                                                    <?php echo $progress['course_title'] ? htmlspecialchars($progress['course_title']) : 'General'; ?>
                                                </td>
                                                <td style="padding: var(--spacing-sm);">
                                                    <?php echo floor($progress['total_study_time'] / 60); ?>h <?php echo $progress['total_study_time'] % 60; ?>m
                                                </td>
                                                <td style="padding: var(--spacing-sm);"><?php echo number_format($progress['sessions_completed']); ?></td>
                                                <td style="padding: var(--spacing-sm);"><?php echo $progress['average_quiz_score'] ? number_format($progress['average_quiz_score'], 1) . '%' : 'N/A'; ?></td>
                                                <td style="padding: var(--spacing-sm);">
                                                    <?php echo $progress['last_study_date'] ? date('M j, Y', strtotime($progress['last_study_date'])) : 'Never'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
