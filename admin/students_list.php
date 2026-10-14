<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/instructor_helpers.php';
$instructor_id = (int) $_SESSION['user_id'];
$course_id = isset($_GET['course_id']) ? filter_var($_GET['course_id'], FILTER_VALIDATE_INT) : null;
$search = trim($_GET['search'] ?? '');
$at_risk_only = isset($_GET['at_risk']) && $_GET['at_risk'] === '1';

if ($course_id !== null && (!$course_id || $course_id < 1)) {
    http_response_code(400);
    exit('Invalid course.');
}
if ($course_id) {
    instructor_require_course($mysqli, $course_id, $instructor_id);
}

$students = instructor_student_rows($mysqli, $instructor_id, $course_id, $search);
if ($at_risk_only) {
    $risk_cutoff = strtotime('-5 days');
    $students = array_values(array_filter($students, function ($student) use ($risk_cutoff) {
        return empty($student['last_login_at']) || strtotime($student['last_login_at']) < $risk_cutoff;
    }));
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="instructor-students.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Student ID', 'First name', 'Last name', 'Email', 'Course', 'Progress (%)', 'Last login']);
    foreach ($students as $student) {
        fputcsv($output, [
            $student['user_id'],
            $student['first_name'],
            $student['last_name'],
            $student['email'],
            $student['course_title'],
            $student['progress_percentage'],
            $student['last_login_at'] ?: 'Never',
        ]);
    }
    fclose($output);
    exit;
}

$courses_stmt = $mysqli->prepare('SELECT course_id, title FROM courses WHERE instructor_id = ? ORDER BY title');
$courses_stmt->bind_param('i', $instructor_id);
$courses_stmt->execute();
$courses = $courses_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$courses_stmt->close();
$page_title = 'My Students';
include BASE_URL . 'includes/dashboard_header.php';
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">My Students</h1>
    <?php instructor_show_flash(); ?>
    <p class="lead mb-4">View enrollment, course progress, and recent sign-in activity for students in your courses.</p>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center">
            <h5 class="mb-0 me-3">Student Roster</h5>
            <form method="get" class="d-flex flex-wrap gap-2 my-2 my-md-0">
                <?php if ($at_risk_only): ?><input type="hidden" name="at_risk" value="1"><?php endif; ?>
                <input class="form-control form-control-sm" type="search" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search students" aria-label="Search students">
                <select class="form-select form-select-sm" name="course_id" aria-label="Filter by course">
                    <option value="">All Courses</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo (int) $course['course_id']; ?>" <?php echo (string) $course_id === (string) $course['course_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-primary" type="submit">Filter</button>
                <?php if ($search !== '' || $course_id || $at_risk_only): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo BASE_URL; ?>students_list.php">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="card-body p-0">
            <?php if (!$students): ?>
                <p class="text-center text-muted p-4">No enrolled students match these filters.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Enrolled Course</th><th>Progress</th><th>Last Login</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($student['course_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <div class="progress" style="height: 8px; min-width: 80px;">
                                            <div class="progress-bar <?php echo (int) $student['progress_percentage'] < 30 ? 'bg-danger' : ((int) $student['progress_percentage'] < 70 ? 'bg-warning' : 'bg-success'); ?>" role="progressbar" style="width: <?php echo (int) $student['progress_percentage']; ?>%;" aria-valuenow="<?php echo (int) $student['progress_percentage']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <small><?php echo (int) $student['progress_percentage']; ?>%</small>
                                    </td>
                                    <td><?php echo $student['last_login_at'] ? htmlspecialchars(date('M d, Y', strtotime($student['last_login_at'])), ENT_QUOTES, 'UTF-8') : 'Never'; ?></td>
                                    <td class="text-nowrap">
                                        <a href="<?php echo BASE_URL; ?>student_progress.php?user_id=<?php echo (int) $student['user_id']; ?>" class="btn btn-sm btn-outline-primary" title="View Progress"><i class="fas fa-chart-bar"></i></a>
                                        <a href="<?php echo BASE_URL; ?>send_message.php?user_id=<?php echo (int) $student['user_id']; ?>" class="btn btn-sm btn-outline-secondary" title="Send Message"><i class="fas fa-envelope"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer bg-white"><?php echo count($students); ?> enrollment<?php echo count($students) === 1 ? '' : 's'; ?></div>
    </div>
</div>
<?php include BASE_URL . 'includes/dashboard_footer.php'; ?>
