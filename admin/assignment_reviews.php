<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/instructor_helpers.php';
require_once BASE_URL . 'includes/assignment_helpers.php';

$instructor_id = (int) $_SESSION['user_id'];
$page_title = 'Assignment Reviews';
$search = trim((string) ($_GET['q'] ?? ''));
$search = function_exists('mb_substr') ? mb_substr($search, 0, 100) : substr($search, 0, 100);
$filter = (string) ($_GET['status'] ?? 'all');
if (!in_array($filter, ['all', 'pending', 'graded'], true)) {
    $filter = 'all';
}

$where_status = '';
if ($filter === 'pending') {
    $where_status = ' AND s.grade IS NULL';
} elseif ($filter === 'graded') {
    $where_status = ' AND s.grade IS NOT NULL';
}

$search_sql = '';
if ($search !== '') {
    $search_sql = ' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?
                         OR c.title LIKE ? OR l.title LIKE ?)';
}

$review_stmt = $mysqli->prepare(
    'SELECT s.submission_id, s.submitted_at, s.grade, s.instructor_feedback, s.graded_at,
            UNIX_TIMESTAMP(s.submitted_at) AS submitted_at_epoch,
            u.first_name, u.last_name, c.title AS course_title, l.title AS assignment_title,
            l.due_at
     FROM learning_assignment_submissions s
     INNER JOIN enrollments e ON e.enrollment_id = s.enrollment_id
     INNER JOIN users u ON u.user_id = e.user_id
     INNER JOIN lessons l ON l.lesson_id = s.lesson_id
     INNER JOIN modules m ON m.module_id = l.module_id
     INNER JOIN courses c ON c.course_id = m.course_id
     WHERE c.instructor_id = ? AND l.content_type = \'assignment\'
       AND s.status = \'submitted\' AND s.submitted_at IS NOT NULL'
    . $where_status . $search_sql .
    ' ORDER BY s.graded_at IS NULL DESC, s.submitted_at DESC, s.submission_id DESC'
);
if ($search !== '') {
    $like = '%' . $search . '%';
    $review_stmt->bind_param('isssss', $instructor_id, $like, $like, $like, $like, $like);
} else {
    $review_stmt->bind_param('i', $instructor_id);
}
$review_stmt->execute();
$submissions = $review_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$review_stmt->close();

include BASE_URL . 'includes/dashboard_header.php';
?>
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Assignment Reviews</h1>
            <p class="text-muted mb-0">Search, filter, and review submitted student assignments.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?php echo INSTRUCTOR_ADMIN_URL; ?>instructor_dashboard.php">Back to dashboard</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md">
                    <label class="form-label" for="review-search">Search submissions</label>
                    <input class="form-control" type="search" id="review-search" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Student, email, course, or assignment">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="review-status">Review status</label>
                    <select class="form-select" id="review-status" name="status">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All submissions</option>
                        <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending review</option>
                        <option value="graded" <?php echo $filter === 'graded' ? 'selected' : ''; ?>>Graded</option>
                    </select>
                </div>
                <div class="col-md-auto d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Apply filters</button>
                    <a class="btn btn-outline-secondary" href="assignment_reviews.php">Clear</a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Student</th>
                        <th scope="col">Course</th>
                        <th scope="col">Assignment</th>
                        <th scope="col">Submitted</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Action</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$submissions): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No submissions match these filters.</td></tr>
                    <?php else: ?>
                        <?php foreach ($submissions as $submission): ?>
                            <?php
                            $submitted_at = (new DateTimeImmutable('@' . (int) $submission['submitted_at_epoch']))
                                ->setTimezone(assignment_app_timezone());
                            $deadline = assignment_format_deadline($submission['due_at']);
                            $is_late = assignment_was_submitted_late($submission, $deadline);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($submission['course_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($submission['assignment_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($submitted_at->format('M j, Y g:i a T'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php if ($submission['grade'] === null): ?>
                                        <span class="badge bg-warning text-dark">Pending review</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Graded · <?php echo number_format((float) $submission['grade'], 2); ?>%</span>
                                    <?php endif; ?>
                                    <?php if ($is_late): ?><span class="badge bg-danger">Late</span><?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-primary btn-sm" href="assignment_review.php?submission_id=<?php echo (int) $submission['submission_id']; ?>">
                                        Review Assignment
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include BASE_URL . 'includes/dashboard_footer.php'; ?>
