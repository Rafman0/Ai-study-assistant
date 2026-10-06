<?php
$page_title = 'Courses';
$active_page = 'courses';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/Auth.php';
require_once __DIR__ . '/models/Course.php';

Auth::require_login();

$user_id = get_current_user_id();
$courses = Course::get_user_courses($user_id);

$message = '';
$message_type = '';

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['message'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// Handle course creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $message = 'Invalid request. Please try again.';
        $message_type = 'danger';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $instructor = trim($_POST['instructor'] ?? '');
        
        if (empty($title)) {
            $message = 'Course title is required.';
            $message_type = 'danger';
        } else {
            $result = Course::create_course($user_id, $title, $description, $code ?: null, $instructor ?: null);
            if ($result['success']) {
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Course created successfully!'];
                header('Location: ' . app_url('courses.php'));
                exit;
            } else {
                $message = $result['message'];
                $message_type = 'danger';
            }
        }
    }
}

$layout_app_shell = true;
require_once __DIR__ . '/views/partials/navbar.php';
?>

<header class="page-heading">
    <h1>Courses</h1>
    <p>Manage your courses and materials</p>
</header>

<?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">Create New Course</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="create">
                            
                            <div class="form-group">
                                <label for="title" class="form-label">Course Title *</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="code" class="form-label">Course Code</label>
                                <input type="text" class="form-control" id="code" name="code" placeholder="e.g., CS101">
                            </div>
                            
                            <div class="form-group">
                                <label for="instructor" class="form-label">Instructor</label>
                                <input type="text" class="form-control" id="instructor" name="instructor">
                            </div>
                            
                            <div class="form-group">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Create Course</button>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <h3 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-lg);">My Courses</h3>
                        
                        <?php if (empty($courses)): ?>
                            <p style="color: var(--color-gray-600);">No courses yet. Create your first course above!</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($courses as $course): ?>
                                    <div class="col-12 col-md-6 mb-4">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h4 style="color: var(--color-primary-dark); margin-bottom: var(--spacing-sm);">
                                                    <?php echo htmlspecialchars($course['title']); ?>
                                                </h4>
                                                <?php if ($course['code']): ?>
                                                    <span style="background-color: var(--color-accent); color: var(--color-white); padding: 2px 8px; border-radius: 4px; font-size: var(--font-size-sm);">
                                                        <?php echo htmlspecialchars($course['code']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($course['instructor']): ?>
                                                    <p style="color: var(--color-gray-600); font-size: var(--font-size-sm); margin-top: var(--spacing-sm);">
                                                        Instructor: <?php echo htmlspecialchars($course['instructor']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <?php if ($course['description']): ?>
                                                    <p style="color: var(--color-gray-600); margin-top: var(--spacing-sm);">
                                                        <?php echo htmlspecialchars(substr($course['description'], 0, 100)); ?>...
                                                    </p>
                                                <?php endif; ?>
                                                <div style="margin-top: var(--spacing-md);">
                                                    <button type="button"
                                                        class="btn btn-sm btn-outline btn-view-course"
                                                        data-id="<?php echo (int)$course['id']; ?>"
                                                        data-title="<?php echo htmlspecialchars($course['title'], ENT_QUOTES); ?>"
                                                        data-code="<?php echo htmlspecialchars($course['code'] ?? '', ENT_QUOTES); ?>"
                                                        data-instructor="<?php echo htmlspecialchars($course['instructor'] ?? '', ENT_QUOTES); ?>"
                                                        data-description="<?php echo htmlspecialchars($course['description'] ?? '', ENT_QUOTES); ?>"
                                                        data-created="<?php echo htmlspecialchars($course['created_at'] ?? '', ENT_QUOTES); ?>">View</button>
                                                    <button type="button"
                                                        class="btn btn-sm btn-outline btn-edit-course"
                                                        data-id="<?php echo (int)$course['id']; ?>"
                                                        data-title="<?php echo htmlspecialchars($course['title'], ENT_QUOTES); ?>"
                                                        data-code="<?php echo htmlspecialchars($course['code'] ?? '', ENT_QUOTES); ?>"
                                                        data-instructor="<?php echo htmlspecialchars($course['instructor'] ?? '', ENT_QUOTES); ?>"
                                                        data-description="<?php echo htmlspecialchars($course['description'] ?? '', ENT_QUOTES); ?>">Edit</button>
                                                    <button type="button"
                                                        class="btn btn-sm btn-outline btn-delete-course"
                                                        style="color: var(--color-error); border-color: var(--color-error);"
                                                        data-id="<?php echo (int)$course['id']; ?>"
                                                        data-title="<?php echo htmlspecialchars($course['title'], ENT_QUOTES); ?>">Delete</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Hidden CSRF token for course API calls -->
                <input type="hidden" id="courses-csrf" value="<?php echo generate_csrf_token(); ?>">

                <!-- View Course Modal -->
                <div class="modal fade" id="viewCourseModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="viewCourseTitle">Course Details</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body" id="viewCourseBody"></div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-primary btn-view-open-edit">Edit Course</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Course Modal -->
                <div class="modal fade" id="editCourseModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Course</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form id="edit-course-form">
                                <input type="hidden" name="course_id" id="edit-course-id">
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label for="edit-course-title" class="form-label">Course Title *</label>
                                        <input type="text" class="form-control" id="edit-course-title" name="title" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit-course-code" class="form-label">Course Code</label>
                                        <input type="text" class="form-control" id="edit-course-code" name="code" placeholder="e.g., CS101">
                                    </div>
                                    <div class="form-group">
                                        <label for="edit-course-instructor" class="form-label">Instructor</label>
                                        <input type="text" class="form-control" id="edit-course-instructor" name="instructor">
                                    </div>
                                    <div class="form-group">
                                        <label for="edit-course-description" class="form-label">Description</label>
                                        <textarea class="form-control" id="edit-course-description" name="description" rows="3"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary" id="edit-course-save-btn">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <script>
                (function () {
                    'use strict';

                    document.addEventListener('DOMContentLoaded', function () {
                    var apiUrl = <?php echo json_encode(app_url('api/courses.php')); ?>;
                    var csrfToken = document.getElementById('courses-csrf').value;
                    var viewModalEl = document.getElementById('viewCourseModal');
                    var editModalEl = document.getElementById('editCourseModal');
                    var viewModal = new bootstrap.Modal(viewModalEl);
                    var editModal = new bootstrap.Modal(editModalEl);

                    function openView(data) {
                        var code = data.code ? '<span style="background-color: var(--color-accent); color: var(--color-white); padding: 2px 8px; border-radius: 4px; font-size: var(--font-size-sm); display: inline-block; margin-bottom: var(--spacing-md);">' + data.code + '</span>' : '';
                        var instructor = data.instructor ? '<p style="margin-bottom: var(--spacing-sm);"><strong>Instructor:</strong> ' + data.instructor + '</p>' : '';
                        var created = data.created ? '<p style="color: var(--color-gray-500); font-size: var(--font-size-sm); margin-bottom: 0;">Created: ' + data.created + '</p>' : '';
                        var description = data.description ? '<p style="color: var(--color-gray-600); line-height: 1.7; white-space: pre-wrap; margin-top: var(--spacing-md);">' + data.description + '</p>' : '<p style="color: var(--color-gray-400); font-style: italic;">No description provided.</p>';
                        document.getElementById('viewCourseTitle').textContent = data.title;
                        document.getElementById('viewCourseBody').innerHTML = code + instructor + description + created;

                        var openEditBtn = viewModalEl.querySelector('.btn-view-open-edit');
                        openEditBtn.onclick = function () {
                            viewModal.hide();
                            openEdit(data);
                        };
                        viewModal.show();
                    }

                    function openEdit(data) {
                        document.getElementById('edit-course-id').value = data.id;
                        document.getElementById('edit-course-title').value = data.title;
                        document.getElementById('edit-course-code').value = data.code;
                        document.getElementById('edit-course-instructor').value = data.instructor;
                        document.getElementById('edit-course-description').value = data.description;
                        editModal.show();
                    }

                    document.addEventListener('click', function (e) {
                        var viewBtn = e.target.closest ? e.target.closest('.btn-view-course') : null;
                        var editBtn = e.target.closest ? e.target.closest('.btn-edit-course') : null;
                        var delBtn = e.target.closest ? e.target.closest('.btn-delete-course') : null;

                        if (viewBtn) {
                            openView({
                                id: viewBtn.getAttribute('data-id'),
                                title: viewBtn.getAttribute('data-title'),
                                code: viewBtn.getAttribute('data-code'),
                                instructor: viewBtn.getAttribute('data-instructor'),
                                description: viewBtn.getAttribute('data-description'),
                                created: viewBtn.getAttribute('data-created')
                            });
                        } else if (editBtn) {
                            openEdit({
                                id: editBtn.getAttribute('data-id'),
                                title: editBtn.getAttribute('data-title'),
                                code: editBtn.getAttribute('data-code'),
                                instructor: editBtn.getAttribute('data-instructor'),
                                description: editBtn.getAttribute('data-description')
                            });
                        } else if (delBtn) {
                            var title = delBtn.getAttribute('data-title');
                            var id = delBtn.getAttribute('data-id');
                            if (!window.confirm('Delete course "' + title + '"? This cannot be undone.')) {
                                return;
                            }
                            fetch(apiUrl + '?action=delete', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                body: JSON.stringify({ csrf_token: csrfToken, course_id: id })
                            })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                if (data && data.success) {
                                    window.location.href = window.location.pathname;
                                } else {
                                    window.alert((data && data.message) ? data.message : 'Could not delete the course. Please try again.');
                                }
                            })
                            .catch(function () {
                                window.alert('Could not reach the server. Please try again.');
                            });
                        }
                    });

                    document.getElementById('edit-course-form').addEventListener('submit', function (e) {
                        e.preventDefault();
                        var saveBtn = document.getElementById('edit-course-save-btn');
                        saveBtn.disabled = true;
                        fetch(apiUrl + '?action=update', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            body: JSON.stringify({
                                csrf_token: csrfToken,
                                course_id: document.getElementById('edit-course-id').value,
                                title: document.getElementById('edit-course-title').value,
                                code: document.getElementById('edit-course-code').value,
                                instructor: document.getElementById('edit-course-instructor').value,
                                description: document.getElementById('edit-course-description').value
                            })
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data && data.success) {
                                window.location.href = window.location.pathname;
                            } else {
                                window.alert((data && data.message) ? data.message : 'Could not update the course. Please try again.');
                            }
                        })
                        .catch(function () {
                            window.alert('Could not reach the server. Please try again.');
                        })
                        .finally(function () {
                            saveBtn.disabled = false;
                        });
                    });
                    });
                })();
                </script>

<?php require_once __DIR__ . '/views/partials/footer.php'; ?>
