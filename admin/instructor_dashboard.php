<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/instructor_helpers.php';
require_once BASE_URL . 'includes/assignment_helpers.php';
$instructor_id = (int) $_SESSION['user_id'];
$page_title = 'Instructor Dashboard';

$summary_stmt = $mysqli->prepare(
    'SELECT COUNT(DISTINCT CASE
                WHEN u.last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN u.user_id
            END) AS active_students,
            COALESCE(ROUND(AVG(e.progress_percentage)), 0) AS completion_rate,
            COUNT(DISTINCT CASE
                WHEN u.last_login_at IS NULL OR u.last_login_at < DATE_SUB(NOW(), INTERVAL 5 DAY)
                THEN u.user_id
            END) AS at_risk_students
     FROM courses c
     LEFT JOIN enrollments e ON e.course_id = c.course_id
     LEFT JOIN users u ON u.user_id = e.user_id
     WHERE c.instructor_id = ?'
);
$summary_stmt->bind_param('i', $instructor_id);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();
$summary_stmt->close();

$unread_stmt = $mysqli->prepare(
    'SELECT COUNT(*) AS unread_count FROM notifications
     WHERE user_id = ? AND is_read = 0 AND type = \'new_message\''
);
$unread_stmt->bind_param('i', $instructor_id);
$unread_stmt->execute();
$unread_messages = (int) $unread_stmt->get_result()->fetch_assoc()['unread_count'];
$unread_stmt->close();

$assignment_review_stmt = $mysqli->prepare(
    'SELECT COUNT(*) AS pending_reviews
     FROM learning_assignment_submissions s
     INNER JOIN lessons l ON l.lesson_id = s.lesson_id
     INNER JOIN modules m ON m.module_id = l.module_id
     INNER JOIN courses c ON c.course_id = m.course_id
     WHERE c.instructor_id = ? AND l.content_type = \'assignment\'
       AND s.status = \'submitted\' AND s.submitted_at IS NOT NULL
       AND s.grade IS NULL'
);
$assignment_review_stmt->bind_param('i', $instructor_id);
$assignment_review_stmt->execute();
$pending_assignment_reviews = (int) $assignment_review_stmt->get_result()->fetch_assoc()['pending_reviews'];
$assignment_review_stmt->close();

$recent_assignment_reviews_stmt = $mysqli->prepare(
    'SELECT s.submission_id, s.submitted_at, s.grade,
            UNIX_TIMESTAMP(s.submitted_at) AS submitted_at_epoch,
            u.first_name, u.last_name, c.title AS course_title, l.title AS assignment_title
     FROM learning_assignment_submissions s
     INNER JOIN enrollments e ON e.enrollment_id = s.enrollment_id
     INNER JOIN users u ON u.user_id = e.user_id
     INNER JOIN lessons l ON l.lesson_id = s.lesson_id
     INNER JOIN modules m ON m.module_id = l.module_id
     INNER JOIN courses c ON c.course_id = m.course_id
     WHERE c.instructor_id = ? AND l.content_type = \'assignment\'
       AND s.status = \'submitted\' AND s.submitted_at IS NOT NULL
     ORDER BY s.submitted_at DESC, s.submission_id DESC
     LIMIT 5'
);
$recent_assignment_reviews_stmt->bind_param('i', $instructor_id);
$recent_assignment_reviews_stmt->execute();
$recent_assignment_reviews = $recent_assignment_reviews_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_assignment_reviews_stmt->close();

$courses_stmt = $mysqli->prepare(
    'SELECT c.course_id, c.title, c.status, c.created_at, cat.name AS category_name,
            COUNT(DISTINCT e.enrollment_id) AS enrolled_students,
            COALESCE(ROUND(AVG(e.progress_percentage)), 0) AS completion_rate,
            COUNT(DISTINCT CASE
                WHEN u.last_login_at IS NULL OR u.last_login_at < DATE_SUB(NOW(), INTERVAL 5 DAY)
                THEN u.user_id
            END) AS at_risk_students,
            COUNT(DISTINCT CASE
                WHEN u.last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN u.user_id
            END) AS recently_active_students
     FROM courses c
     LEFT JOIN categories cat ON cat.category_id = c.category_id
     LEFT JOIN enrollments e ON e.course_id = c.course_id
     LEFT JOIN users u ON u.user_id = e.user_id
     WHERE c.instructor_id = ?
     GROUP BY c.course_id, c.title, c.status, c.created_at, cat.name
     ORDER BY c.updated_at DESC'
);
$courses_stmt->bind_param('i', $instructor_id);
$courses_stmt->execute();
$courses = $courses_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$courses_stmt->close();

$activity = [];
$quiz_stmt = $mysqli->prepare(
    'SELECT CONCAT(u.first_name, \' \', u.last_name) AS student_name,
            c.title AS course_title, qa.completed_at AS happened_at
     FROM learning_quiz_attempts qa
     INNER JOIN lessons l ON l.lesson_id = qa.lesson_id
     INNER JOIN modules m ON m.module_id = l.module_id
     INNER JOIN courses c ON c.course_id = m.course_id
     INNER JOIN enrollments e ON e.enrollment_id = qa.enrollment_id
     INNER JOIN users u ON u.user_id = e.user_id
     WHERE c.instructor_id = ? AND qa.completed_at IS NOT NULL
     ORDER BY qa.completed_at DESC LIMIT 5'
);
$quiz_stmt->bind_param('i', $instructor_id);
$quiz_stmt->execute();
foreach ($quiz_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $event) {
    $event['description'] = $event['student_name'] . ' completed a quiz in ' . $event['course_title'];
    $event['icon'] = 'fas fa-file-signature text-primary';
    $activity[] = $event;
}
$quiz_stmt->close();

$discussion_stmt = $mysqli->prepare(
    'SELECT CONCAT(u.first_name, \' \', u.last_name) AS student_name,
            c.title AS course_title, t.title AS thread_title, t.created_at AS happened_at
     FROM discussion_threads t
     INNER JOIN discussion_forums f ON f.forum_id = t.forum_id
     INNER JOIN courses c ON c.course_id = f.course_id
     INNER JOIN users u ON u.user_id = t.user_id
     WHERE c.instructor_id = ?
     ORDER BY t.created_at DESC LIMIT 5'
);
$discussion_stmt->bind_param('i', $instructor_id);
$discussion_stmt->execute();
foreach ($discussion_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $event) {
    $event['description'] = $event['student_name'] . ' started a discussion in ' . $event['course_title'] . ': ' . $event['thread_title'];
    $event['icon'] = 'fas fa-comments text-info';
    $activity[] = $event;
}
$discussion_stmt->close();

usort($activity, function ($left, $right) {
    return strcmp($right['happened_at'], $left['happened_at']);
});
$activity = array_slice($activity, 0, 5);

include BASE_URL . 'includes/dashboard_header.php';
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">Instructor Dashboard</h1>
    <?php instructor_show_flash(); ?>

    <h3 class="h4 mb-3">Teaching Overview</h3>
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <a class="text-decoration-none text-reset" href="<?php echo INSTRUCTOR_ADMIN_URL; ?>students_list.php">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6>Active Students</h6><i class="fas fa-users icon-lg text-primary"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format((int) $summary['active_students']); ?></div>
                    <div class="stat-meta">Logged in during the last 7 days</div>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <a class="text-decoration-none text-reset" href="<?php echo INSTRUCTOR_ADMIN_URL; ?>analytics.php">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6>Avg. Completion Rate</h6><i class="fas fa-tasks icon-lg text-success"></i>
                    </div>
                    <div class="stat-value"><?php echo (int) $summary['completion_rate']; ?>%</div>
                    <div class="stat-meta">Across your enrolled students</div>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <a class="text-decoration-none text-reset" href="<?php echo INSTRUCTOR_ADMIN_URL; ?>students_list.php?at_risk=1">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6>At-Risk Students</h6><i class="fas fa-user-graduate icon-lg text-warning"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format((int) $summary['at_risk_students']); ?></div>
                    <div class="stat-meta"><span class="text-warning">No login in 5+ days</span></div>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <a class="text-decoration-none text-reset" href="<?php echo INSTRUCTOR_ADMIN_URL; ?>students_list.php">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6>Unread Messages</h6><i class="fas fa-envelope icon-lg text-info"></i>
                    </div>
                    <div class="stat-value"><?php echo $unread_messages; ?></div>
                    <div class="stat-meta"><span class="text-info">View your student roster</span></div>
                </div>
            </a>
        </div>
    </div>

    <section class="card shadow-sm mb-4" aria-labelledby="assignment-reviews-heading">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="h5 mb-0" id="assignment-reviews-heading">Assignment Reviews</h2>
            <a class="btn btn-outline-primary btn-sm" href="<?php echo INSTRUCTOR_ADMIN_URL; ?>assignment_reviews.php">
                Search and filter submissions
            </a>
        </div>
        <div class="card-body">
            <p class="mb-3">
                <strong><?php echo number_format($pending_assignment_reviews); ?></strong>
                pending review<?php echo $pending_assignment_reviews === 1 ? '' : 's'; ?>
            </p>
            <?php if (!$recent_assignment_reviews): ?>
                <p class="text-muted mb-0">No submitted assignments are available for review yet.</p>
            <?php else: ?>
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
                            <?php foreach ($recent_assignment_reviews as $assignment_review): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($assignment_review['first_name'] . ' ' . $assignment_review['last_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($assignment_review['course_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($assignment_review['assignment_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php
                                        $submitted_at = assignment_submission_time($assignment_review);
                                        echo $submitted_at
                                            ? htmlspecialchars($submitted_at->format('M j, Y g:i a T'), ENT_QUOTES, 'UTF-8')
                                            : 'Unavailable';
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($assignment_review['grade'] === null): ?>
                                            <span class="badge bg-warning text-dark">Pending review</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Graded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-primary btn-sm" href="<?php echo INSTRUCTOR_ADMIN_URL; ?>assignment_review.php?submission_id=<?php echo (int) $assignment_review['submission_id']; ?>">
                                            Review Assignment
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h4 mb-0">My Courses</h3>
                <a href="<?php echo INSTRUCTOR_ADMIN_URL; ?>create_course.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus me-1"></i> Create Course
                </a>
            </div>
            <?php if (!$courses): ?>
                <div class="alert alert-info">
                    You have not created any courses yet.
                    <a href="<?php echo INSTRUCTOR_ADMIN_URL; ?>create_course.php" class="alert-link">Create your first course.</a>
                </div>
            <?php endif; ?>
            <?php foreach ($courses as $course): ?>
                <?php
                $engagement = $course['enrolled_students'] > 0
                    ? (int) round(100 * $course['recently_active_students'] / $course['enrolled_students'])
                    : 0;
                $status_labels = [
                    'published' => ['Active', 'bg-success'],
                    'pending_approval' => ['Pending Approval', 'bg-warning text-dark'],
                    'draft' => ['Draft', 'bg-secondary'],
                    'unpublished' => ['Unpublished', 'bg-secondary'],
                    'rejected' => ['Rejected', 'bg-danger'],
                ];
                [$status_label, $status_class] = $status_labels[$course['status']] ?? ['Unknown', 'bg-secondary'];
                ?>
                <div class="instructor-course-card mb-3">
                    <div class="course-icon-lg bg-primary">
                        <?php echo htmlspecialchars(strtoupper(substr($course['title'], 0, 2)), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="course-details flex-grow-1">
                        <h5><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                        <p class="course-meta">
                            <?php echo htmlspecialchars($course['category_name'] ?: 'Uncategorized', ENT_QUOTES, 'UTF-8'); ?>
                            &middot; <?php echo number_format((int) $course['enrolled_students']); ?> students enrolled
                        </p>
                        <div class="course-stats d-flex mb-2">
                            <div class="stat-item">Engagement: <strong><?php echo $engagement; ?>%</strong></div>
                            <div class="stat-item">Completion: <strong><?php echo (int) $course['completion_rate']; ?>%</strong></div>
                            <div class="stat-item text-danger">At Risk: <strong><?php echo number_format((int) $course['at_risk_students']); ?> students</strong></div>
                        </div>
                        <div>
                            <a href="<?php echo INSTRUCTOR_ADMIN_URL; ?>course_content.php?course_id=<?php echo (int) $course['course_id']; ?>" class="btn btn-outline-secondary btn-sm me-1">Manage Content</a>
                            <a href="<?php echo INSTRUCTOR_ADMIN_URL; ?>analytics.php?course_id=<?php echo (int) $course['course_id']; ?>" class="btn btn-outline-secondary btn-sm me-1">View Analytics</a>
                            <a href="<?php echo INSTRUCTOR_ADMIN_URL; ?>students_list.php?course_id=<?php echo (int) $course['course_id']; ?>" class="btn btn-outline-secondary btn-sm">Student Progress</a>
                        </div>
                    </div>
                    <div class="course-actions"><span class="badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm alert-attention mb-4">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-exclamation-circle me-2 text-danger"></i>Students Need Attention</h5>
                    <p class="card-text small">
                        <?php echo number_format((int) $summary['at_risk_students']); ?> enrolled students have not logged in for 5+ days.
                    </p>
                    <form action="<?php echo INSTRUCTOR_ADMIN_URL; ?>send_reminders.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(instructor_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn btn-danger btn-sm" <?php echo (int) $summary['at_risk_students'] === 0 ? 'disabled' : ''; ?>>Send Reminder</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-0">Recent Activity</h5></div>
                <div class="list-group list-group-flush quick-action-list">
                    <?php if (!$activity): ?>
                        <div class="list-group-item text-muted">No recent student activity.</div>
                    <?php else: ?>
                        <?php foreach ($activity as $event): ?>
                            <div class="list-group-item">
                                <i class="<?php echo htmlspecialchars($event['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                <?php echo htmlspecialchars($event['description'], ENT_QUOTES, 'UTF-8'); ?>
                                <small class="text-muted d-block"><?php echo htmlspecialchars(date('M j, Y g:i a', strtotime($event['happened_at'])), ENT_QUOTES, 'UTF-8'); ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white"><h5 class="mb-0">Quick Actions</h5></div>
                <div class="list-group list-group-flush quick-action-list">
                    <a href="<?php echo INSTRUCTOR_ADMIN_URL; ?>analytics.php" class="list-group-item list-group-item-action"><i class="fas fa-chart-line"></i> View Analytics Report</a>
                    <a href="<?php echo INSTRUCTOR_ADMIN_URL; ?>announcements.php" class="list-group-item list-group-item-action"><i class="fas fa-bullhorn"></i> Send Announcement</a>
                    <a href="<?php echo INSTRUCTOR_ADMIN_URL; ?>students_list.php?export=csv" class="list-group-item list-group-item-action"><i class="fas fa-file-export"></i> Export Student Data</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include BASE_URL . 'includes/dashboard_footer.php'; ?>
