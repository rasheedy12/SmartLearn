<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/admin_helpers.php';
$page_title = 'Course Management';
$status_options = ['draft', 'pending_approval', 'published', 'unpublished', 'rejected'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $actions = [
        'approve' => ['pending_approval', 'published', 'Course approved and published.'],
        'reject' => ['pending_approval', 'rejected', 'Course submission rejected.'],
        'unpublish' => ['published', 'unpublished', 'Course unpublished.'],
        'publish' => ['unpublished', 'published', 'Course published.'],
    ];
    $course_id = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';

    if (!admin_verify_csrf()) {
        admin_flash('error', 'Your session expired. Refresh the page and try again.');
    } elseif (!$course_id || $course_id < 1 || !isset($actions[$action])) {
        admin_flash('error', 'Choose a valid course action.');
    } else {
        [$required_status, $new_status, $success_message] = $actions[$action];
        $update = $mysqli->prepare(
            'UPDATE courses
             SET status = ?,
                 published_at = CASE WHEN ? = \'published\' THEN COALESCE(published_at, CURRENT_TIMESTAMP) ELSE published_at END
             WHERE course_id = ? AND status = ?'
        );
        $update->bind_param('ssis', $new_status, $new_status, $course_id, $required_status);
        $update->execute();
        if ($update->affected_rows === 1) {
            admin_flash('success', $success_message);
        } else {
            admin_flash('error', 'The course was not found or its status no longer allows this action.');
        }
        $update->close();
    }

    header('Location: course_management.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$completion = ($_GET['completion'] ?? '') === 'low';
$course_id = isset($_GET['course_id']) ? filter_var($_GET['course_id'], FILTER_VALIDATE_INT) : null;
if ($status !== '' && !in_array($status, $status_options, true)) {
    $status = '';
}
if (isset($_GET['course_id']) && (!$course_id || $course_id < 1)) {
    http_response_code(400);
    exit('Invalid course.');
}

$where = [];
$params = [];
$types = '';
if ($course_id !== null) {
    $where[] = 'c.course_id = ?';
    $params[] = $course_id;
    $types .= 'i';
}
if ($search !== '') {
    $where[] = '(c.title LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
    $types .= 'sss';
}
if ($status !== '') {
    $where[] = 'c.status = ?';
    $params[] = $status;
    $types .= 's';
}
$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$completion_sql = $completion
    ? ' HAVING COUNT(e.enrollment_id) > 0 AND AVG(e.progress_percentage) < 40'
    : '';
$courses_stmt = $mysqli->prepare(
    'SELECT c.course_id, c.title, c.status, c.average_rating, c.created_at,
            u.first_name, u.last_name, cat.name AS category_name,
            COUNT(DISTINCT e.enrollment_id) AS students,
            COALESCE(AVG(e.progress_percentage), 0) AS completion_rate
     FROM courses c
     INNER JOIN users u ON u.user_id = c.instructor_id
     LEFT JOIN categories cat ON cat.category_id = c.category_id
     LEFT JOIN enrollments e ON e.course_id = c.course_id'
     . $where_sql .
     ' GROUP BY c.course_id, c.title, c.status, c.average_rating, c.created_at,
               u.first_name, u.last_name, cat.name'
     . $completion_sql .
     ' ORDER BY c.created_at DESC, c.course_id DESC'
);
if ($params) {
    $courses_stmt->bind_param($types, ...$params);
}
$courses_stmt->execute();
$all_platform_courses = $courses_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$courses_stmt->close();

include BASE_URL . 'includes/dashboard_header.php';
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">Course Management</h1>
    <?php admin_show_flash(); ?>
    <p class="lead mb-4">Review course submissions, monitor performance, and manage published courses.</p>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center">
            <h2 class="h5 mb-0 me-3">Platform Courses <span class="badge bg-secondary"><?php echo number_format(count($all_platform_courses)); ?></span></h2>
            <form method="get" class="d-flex flex-wrap gap-2 my-2 my-md-0">
                <?php if ($course_id !== null): ?><input type="hidden" name="course_id" value="<?php echo (int) $course_id; ?>"><?php endif; ?>
                <input class="form-control form-control-sm" type="search" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search title or instructor" aria-label="Search courses">
                <select class="form-select form-select-sm" name="status" aria-label="Filter by status">
                    <option value="">All Statuses</option>
                    <?php foreach ($status_options as $option): ?>
                        <option value="<?php echo $option; ?>" <?php echo $status === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $option)), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-check align-self-center">
                    <input class="form-check-input" type="checkbox" name="completion" value="low" id="lowCompletion" <?php echo $completion ? 'checked' : ''; ?>>
                    <label class="form-check-label small" for="lowCompletion">Below 40%</label>
                </div>
                <button class="btn btn-sm btn-outline-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
                <?php if ($search !== '' || $status !== '' || $completion || $course_id !== null): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="course_management.php">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="card-body p-0">
            <?php if (!$all_platform_courses): ?>
                <p class="text-center text-muted p-4 mb-0">No courses match these filters.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light"><tr><th>ID</th><th>Course Title</th><th>Instructor</th><th>Category</th><th>Students</th><th>Completion</th><th>Rating</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($all_platform_courses as $course): ?>
                                <?php
                                $status_class = $course['status'] === 'published' ? 'bg-success'
                                    : ($course['status'] === 'pending_approval' ? 'bg-warning text-dark'
                                        : ($course['status'] === 'rejected' ? 'bg-danger' : 'bg-secondary'));
                                ?>
                                <tr>
                                    <td><?php echo (int) $course['course_id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($course['category_name'] ?: 'Uncategorized', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo number_format((int) $course['students']); ?></td>
                                    <td><?php echo (int) round((float) $course['completion_rate']); ?>%</td>
                                    <td><?php echo (float) $course['average_rating'] > 0 ? number_format((float) $course['average_rating'], 1) : 'N/A'; ?></td>
                                    <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $course['status'])), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td>
                                        <form method="post" class="d-flex flex-wrap gap-1">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="course_id" value="<?php echo (int) $course['course_id']; ?>">
                                            <?php if ($course['status'] === 'pending_approval'): ?>
                                                <button class="btn btn-sm btn-outline-success" type="submit" name="action" value="approve">Approve</button>
                                                <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="reject">Reject</button>
                                            <?php elseif ($course['status'] === 'published'): ?>
                                                <button class="btn btn-sm btn-outline-warning" type="submit" name="action" value="unpublish">Unpublish</button>
                                            <?php elseif ($course['status'] === 'unpublished'): ?>
                                                <button class="btn btn-sm btn-outline-success" type="submit" name="action" value="publish">Publish</button>
                                            <?php else: ?>
                                                <span class="text-muted small">No action available</span>
                                            <?php endif; ?>
                                        </form>
                                    </td>
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
