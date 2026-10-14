<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/instructor_helpers.php';
$instructor_id = (int) $_SESSION['user_id'];
$student_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$student_id || $student_id < 1) {
    http_response_code(400);
    exit('A valid student is required.');
}

$stmt = $mysqli->prepare(
    'SELECT u.user_id, u.first_name, u.last_name, u.email, c.course_id, c.title,
            e.progress_percentage, e.enrolled_at, e.completed_at
     FROM enrollments e
     INNER JOIN courses c ON c.course_id = e.course_id
     INNER JOIN users u ON u.user_id = e.user_id
     WHERE c.instructor_id = ? AND u.user_id = ?
     ORDER BY c.title'
);
$stmt->bind_param('ii', $instructor_id, $student_id);
$stmt->execute();
$enrollments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
if (!$enrollments) {
    http_response_code(404);
    exit('Student not found in your courses.');
}

$student = $enrollments[0];
$page_title = 'Student Progress';
include BASE_URL . 'includes/dashboard_header.php';
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2 mb-0">Student Progress</h1>
        <a href="<?php echo INSTRUCTOR_ADMIN_URL; ?>students_list.php" class="btn btn-outline-secondary">Back to Students</a>
    </div>
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h4><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
            <p class="text-muted mb-0"><?php echo htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-white"><h5 class="mb-0">Course Enrollments</h5></div>
        <div class="card-body">
            <?php foreach ($enrollments as $enrollment): ?>
                <div class="mb-4">
                    <div class="d-flex justify-content-between">
                        <strong><?php echo htmlspecialchars($enrollment['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span><?php echo (int) $enrollment['progress_percentage']; ?>%</span>
                    </div>
                    <div class="progress my-2" style="height: 10px;">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo (int) $enrollment['progress_percentage']; ?>%;" aria-valuenow="<?php echo (int) $enrollment['progress_percentage']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <small class="text-muted">
                        Enrolled <?php echo htmlspecialchars(date('M d, Y', strtotime($enrollment['enrolled_at'])), ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($enrollment['completed_at']): ?> · Completed <?php echo htmlspecialchars(date('M d, Y', strtotime($enrollment['completed_at'])), ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                    </small>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php include BASE_URL . 'includes/dashboard_footer.php'; ?>
