<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/instructor_helpers.php';
$instructor_id = (int) $_SESSION['user_id'];
$page_title = 'My Courses';
$stmt = $mysqli->prepare(
    'SELECT c.course_id, c.title, c.status, c.updated_at,
            COUNT(e.enrollment_id) AS students,
            COALESCE(ROUND(AVG(e.progress_percentage)), 0) AS avg_completion
     FROM courses c
     LEFT JOIN enrollments e ON e.course_id = c.course_id
     WHERE c.instructor_id = ?
     GROUP BY c.course_id, c.title, c.status, c.updated_at
     ORDER BY c.updated_at DESC'
);
$stmt->bind_param('i', $instructor_id);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
include BASE_URL . 'includes/dashboard_header.php';
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">My Courses</h1>
    <?php instructor_show_flash(); ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <p class="lead mb-0">Manage your courses, view student progress, and create new learning experiences.</p>
        <a href="<?php echo INSTRUCTOR_ADMIN_URL; ?>create_course.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Create New Course</a>
    </div>

    <?php if (!$courses): ?>
        <div class="alert alert-info text-center">
            <h4 class="alert-heading">No Courses Created Yet</h4>
            <p>Ready to share your knowledge? Create your first course and start teaching.</p>
            <a href="<?php echo INSTRUCTOR_ADMIN_URL; ?>create_course.php" class="btn btn-success">Create Your First Course</a>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white"><h5 class="mb-0">Your Courses</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light"><tr><th>Course Title</th><th>Students</th><th>Avg. Completion</th><th>Status</th><th>Last Updated</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td><?php echo number_format((int) $course['students']); ?></td>
                                    <td><?php echo (int) $course['avg_completion']; ?>%</td>
                                    <td><span class="badge bg-<?php echo $course['status'] === 'published' ? 'success' : ($course['status'] === 'rejected' ? 'danger' : 'secondary'); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $course['status'])), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($course['updated_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-nowrap">
                                        <a href="<?php echo INSTRUCTOR_ADMIN_URL; ?>course_content.php?course_id=<?php echo (int) $course['course_id']; ?>" class="btn btn-sm btn-outline-primary" title="Manage Content"><i class="fas fa-edit"></i></a>
                                        <a href="<?php echo INSTRUCTOR_ADMIN_URL; ?>analytics.php?course_id=<?php echo (int) $course['course_id']; ?>" class="btn btn-sm btn-outline-info" title="View Analytics"><i class="fas fa-chart-line"></i></a>
                                        <a href="<?php echo INSTRUCTOR_ADMIN_URL; ?>students_list.php?course_id=<?php echo (int) $course['course_id']; ?>" class="btn btn-sm btn-outline-secondary" title="Manage Students"><i class="fas fa-users"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include BASE_URL . 'includes/dashboard_footer.php'; ?>
