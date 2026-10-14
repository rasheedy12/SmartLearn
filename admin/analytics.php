<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/instructor_helpers.php';
$instructor_id = (int) $_SESSION['user_id'];
$course_id = isset($_GET['course_id']) ? filter_var($_GET['course_id'], FILTER_VALIDATE_INT) : null;
if ($course_id !== null && (!$course_id || $course_id < 1)) {
    http_response_code(400);
    exit('Invalid course.');
}

$where = ' WHERE c.instructor_id = ?';
$types = 'i';
$params = [$instructor_id];
if ($course_id !== null) {
    instructor_require_course($mysqli, $course_id, $instructor_id);
    $where .= ' AND c.course_id = ?';
    $types .= 'i';
    $params[] = $course_id;
}

$stmt = $mysqli->prepare(
    'SELECT COUNT(DISTINCT e.user_id) AS total_students,
            COALESCE(ROUND(AVG(e.progress_percentage)), 0) AS completion_rate,
            COALESCE(SUM(CASE WHEN e.completed_at IS NOT NULL THEN c.price ELSE 0 END), 0) AS revenue,
            c.course_id, c.title,
            COUNT(DISTINCT e.enrollment_id) AS course_students,
            COALESCE(ROUND(AVG(e.progress_percentage)), 0) AS course_completion
     FROM courses c
     LEFT JOIN enrollments e ON e.course_id = c.course_id'
     . $where .
     ' GROUP BY c.course_id, c.title ORDER BY course_students DESC, c.title'
);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$course_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_students = 0;
$weighted_progress = 0;
$total_revenue = 0.0;
$top_course = null;
foreach ($course_rows as $row) {
    $total_students += (int) $row['course_students'];
    $weighted_progress += (int) $row['course_students'] * (int) $row['course_completion'];
    $total_revenue += (float) $row['revenue'];
    if ($top_course === null && (int) $row['course_students'] > 0) {
        $top_course = $row['title'];
    }
}
$average_completion = $total_students > 0 ? (int) round($weighted_progress / $total_students) : 0;
$page_title = 'Course Analytics';
include BASE_URL . 'includes/dashboard_header.php';
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">Course Analytics</h1>
    <?php instructor_show_flash(); ?>
    <p class="lead mb-4">Enrollment and completion metrics from your courses.</p>
    <form method="get" class="mb-4">
        <label for="analyticsCourse" class="form-label">Filter by course</label>
        <div class="d-flex gap-2">
            <select id="analyticsCourse" name="course_id" class="form-select" style="max-width: 400px;">
                <option value="">All my courses</option>
                <?php foreach ($course_rows as $course): ?>
                    <option value="<?php echo (int) $course['course_id']; ?>" <?php echo (string) $course_id === (string) $course['course_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-outline-primary" type="submit">Apply</button>
        </div>
    </form>
    <div class="row mb-4">
        <div class="col-md-3 mb-3"><div class="stat-card h-100"><h6>Enrollments</h6><div class="stat-value"><?php echo number_format($total_students); ?></div></div></div>
        <div class="col-md-3 mb-3"><div class="stat-card h-100"><h6>Average Completion</h6><div class="stat-value"><?php echo $average_completion; ?>%</div></div></div>
        <div class="col-md-3 mb-3"><div class="stat-card h-100"><h6>Completed-course Revenue</h6><div class="stat-value">$<?php echo number_format($total_revenue, 2); ?></div></div></div>
        <div class="col-md-3 mb-3"><div class="stat-card h-100"><h6>Top Enrolled Course</h6><div class="stat-value fs-5"><?php echo htmlspecialchars($top_course ?: 'No enrollments yet', ENT_QUOTES, 'UTF-8'); ?></div></div></div>
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-white"><h5 class="mb-0">Course Performance</h5></div>
        <div class="card-body p-0">
            <?php if (!$course_rows): ?><p class="p-4 text-muted mb-0">No courses match this filter.</p><?php else: ?>
                <div class="table-responsive"><table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Course</th><th>Enrolled Students</th><th>Completion Rate</th><th>Student Roster</th></tr></thead>
                    <tbody><?php foreach ($course_rows as $course): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo number_format((int) $course['course_students']); ?></td>
                            <td><?php echo (int) $course['course_completion']; ?>%</td>
                            <td><a href="<?php echo BASE_URL; ?>students_list.php?course_id=<?php echo (int) $course['course_id']; ?>" class="btn btn-sm btn-outline-primary">View Students</a></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include BASE_URL . 'includes/dashboard_footer.php'; ?>
