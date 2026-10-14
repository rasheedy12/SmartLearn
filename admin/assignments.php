<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/student_helpers.php';
$page_title = 'My Assignments';
$student_id = (int) $_SESSION['user_id'];
$filter = $_GET['status'] ?? 'all';
if (!in_array($filter, ['all', 'pending', 'completed'], true)) {
    $filter = 'all';
}

$stmt = $mysqli->prepare(
    'SELECT e.enrollment_id, c.course_id, c.title AS course_title,
            l.lesson_id, l.title, l.content_type,
            CASE WHEN lp.is_completed = 1 THEN 1 ELSE 0 END AS is_completed
     FROM enrollments e
     INNER JOIN courses c ON c.course_id = e.course_id
     INNER JOIN modules m ON m.course_id = c.course_id
     INNER JOIN lessons l ON l.module_id = m.module_id
     LEFT JOIN lesson_progress lp
         ON lp.lesson_id = l.lesson_id AND lp.enrollment_id = e.enrollment_id
     WHERE e.user_id = ? AND l.content_type IN (\'quiz\', \'assignment\')
     ORDER BY is_completed, c.title, m.order_index, l.order_index'
);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$all_assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$assignments_data = array_values(array_filter($all_assignments, function ($assignment) use ($filter) {
    if ($filter === 'pending') {
        return !(bool) $assignment['is_completed'];
    }
    if ($filter === 'completed') {
        return (bool) $assignment['is_completed'];
    }
    return true;
}));

include BASE_URL . 'includes/dashboard_header.php';
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">My Assignments</h1>
    <p class="lead mb-4">Quizzes and assignments in your enrolled courses. Due dates are not available in the current course data.</p>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h5 mb-0">Course Activities</h2>
            <div class="btn-group" role="group" aria-label="Filter course activities">
                <a class="btn btn-sm btn-outline-secondary <?php echo $filter === 'all' ? 'active' : ''; ?>" href="assignments.php?status=all">All</a>
                <a class="btn btn-sm btn-outline-secondary <?php echo $filter === 'pending' ? 'active' : ''; ?>" href="assignments.php?status=pending">Not Completed</a>
                <a class="btn btn-sm btn-outline-secondary <?php echo $filter === 'completed' ? 'active' : ''; ?>" href="assignments.php?status=completed">Completed</a>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (!$assignments_data): ?>
                <div class="text-center p-4">
                    <i class="fas fa-check-double fa-3x text-success mb-3"></i>
                    <h2 class="h4"><?php echo $filter === 'completed' ? 'No Completed Activities Yet' : 'No Activities Found'; ?></h2>
                    <p class="text-muted mb-0"><?php echo $all_assignments ? 'There are no activities matching this filter.' : 'Quizzes and assignments from enrolled courses will appear here.'; ?></p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light"><tr><th>Course</th><th>Activity</th><th>Type</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($assignments_data as $assignment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($assignment['course_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="badge bg-info text-dark"><?php echo ucfirst(htmlspecialchars($assignment['content_type'], ENT_QUOTES, 'UTF-8')); ?></span></td>
                                    <td><span class="badge <?php echo $assignment['is_completed'] ? 'bg-success' : 'bg-warning text-dark'; ?>"><?php echo $assignment['is_completed'] ? 'Completed' : 'Not completed'; ?></span></td>
                                    <td><a href="my_courses.php#course-<?php echo (int) $assignment['course_id']; ?>" class="btn btn-sm btn-outline-primary">View Course</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include BASE_URL . 'includes/dashboard_footer.php'; ?>
