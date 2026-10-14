<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/student_helpers.php';
$page_title = 'Student Dashboard';
$student_id = (int) $_SESSION['user_id'];

$courses_stmt = $mysqli->prepare(
    'SELECT e.enrollment_id, e.course_id, e.progress_percentage, e.completed_at,
            c.title, c.subtitle, c.thumbnail_url, c.status,
            COUNT(DISTINCT l.lesson_id) AS lesson_count,
            COUNT(DISTINCT CASE WHEN lp.is_completed = 1 THEN lp.lesson_id END) AS lessons_completed,
            (
                SELECT l_next.lesson_id
                FROM modules m_next
                INNER JOIN lessons l_next ON l_next.module_id = m_next.module_id
                LEFT JOIN lesson_progress lp_next
                    ON lp_next.lesson_id = l_next.lesson_id
                    AND lp_next.enrollment_id = e.enrollment_id
                    AND lp_next.is_completed = 1
                WHERE m_next.course_id = c.course_id AND lp_next.progress_id IS NULL
                ORDER BY m_next.order_index, l_next.order_index, l_next.lesson_id
                LIMIT 1
            ) AS next_lesson_id,
            (
                SELECT l_next.title
                FROM modules m_next
                INNER JOIN lessons l_next ON l_next.module_id = m_next.module_id
                LEFT JOIN lesson_progress lp_next
                    ON lp_next.lesson_id = l_next.lesson_id
                    AND lp_next.enrollment_id = e.enrollment_id
                    AND lp_next.is_completed = 1
                WHERE m_next.course_id = c.course_id AND lp_next.progress_id IS NULL
                ORDER BY m_next.order_index, l_next.order_index, l_next.lesson_id
                LIMIT 1
            ) AS next_lesson_title
     FROM enrollments e
     INNER JOIN courses c ON c.course_id = e.course_id
     LEFT JOIN modules m ON m.course_id = c.course_id
     LEFT JOIN lessons l ON l.module_id = m.module_id
     LEFT JOIN lesson_progress lp ON lp.lesson_id = l.lesson_id AND lp.enrollment_id = e.enrollment_id
     WHERE e.user_id = ?
     GROUP BY e.enrollment_id, e.course_id, e.progress_percentage, e.completed_at,
              c.title, c.subtitle, c.thumbnail_url, c.status
     ORDER BY e.completed_at IS NOT NULL, e.enrolled_at DESC'
);
$courses_stmt->bind_param('i', $student_id);
$courses_stmt->execute();
$active_courses = $courses_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$courses_stmt->close();

$lesson_stats_stmt = $mysqli->prepare(
    'SELECT COUNT(DISTINCT l.lesson_id) AS total_lessons,
            COUNT(DISTINCT CASE WHEN lp.is_completed = 1 THEN l.lesson_id END) AS completed_lessons
     FROM enrollments e
     INNER JOIN courses c ON c.course_id = e.course_id
     INNER JOIN modules m ON m.course_id = c.course_id
     INNER JOIN lessons l ON l.module_id = m.module_id
     LEFT JOIN lesson_progress lp ON lp.lesson_id = l.lesson_id AND lp.enrollment_id = e.enrollment_id
     WHERE e.user_id = ?'
);
$lesson_stats_stmt->bind_param('i', $student_id);
$lesson_stats_stmt->execute();
$lesson_stats = $lesson_stats_stmt->get_result()->fetch_assoc();
$lesson_stats_stmt->close();
$total_lessons = (int) $lesson_stats['total_lessons'];
$completed_lessons = (int) $lesson_stats['completed_lessons'];
$lesson_completion = $total_lessons > 0 ? (int) round($completed_lessons / $total_lessons * 100) : 0;

$activity_stmt = $mysqli->prepare(
    'SELECT DISTINCT DATE(lp.completed_at) AS activity_day
     FROM lesson_progress lp
     INNER JOIN enrollments e ON e.enrollment_id = lp.enrollment_id
     WHERE e.user_id = ? AND lp.is_completed = 1 AND lp.completed_at IS NOT NULL
     ORDER BY activity_day DESC'
);
$activity_stmt->bind_param('i', $student_id);
$activity_stmt->execute();
$activity_days = array_column($activity_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'activity_day');
$activity_stmt->close();
$study_streak = 0;
$expected_day = new DateTimeImmutable('today');
if ($activity_days && $activity_days[0] === $expected_day->modify('-1 day')->format('Y-m-d')) {
    $expected_day = $expected_day->modify('-1 day');
}
foreach ($activity_days as $activity_day) {
    if ($activity_day !== $expected_day->format('Y-m-d')) {
        break;
    }
    $study_streak++;
    $expected_day = $expected_day->modify('-1 day');
}

$upcoming_tasks_stmt = $mysqli->prepare(
    'SELECT l.lesson_id, l.title, l.content_type, c.course_id, c.title AS course_title
     FROM enrollments e
     INNER JOIN courses c ON c.course_id = e.course_id
     INNER JOIN modules m ON m.course_id = c.course_id
     INNER JOIN lessons l ON l.module_id = m.module_id
     LEFT JOIN lesson_progress lp
         ON lp.lesson_id = l.lesson_id AND lp.enrollment_id = e.enrollment_id AND lp.is_completed = 1
     WHERE e.user_id = ?
       AND l.content_type IN (\'quiz\', \'assignment\')
       AND lp.progress_id IS NULL
     ORDER BY c.title, m.order_index, l.order_index
     LIMIT 5'
);
$upcoming_tasks_stmt->bind_param('i', $student_id);
$upcoming_tasks_stmt->execute();
$upcoming_tasks = $upcoming_tasks_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$upcoming_tasks_stmt->close();

$achievements_available = true;
$achievements = [];
$experience_points = 0;
try {
    $achievements_stmt = $mysqli->prepare(
        'SELECT a.name, a.description, a.icon_class, a.xp_reward, ua.earned_at
         FROM user_achievements ua
         INNER JOIN achievements a ON a.achievement_id = ua.achievement_id
         WHERE ua.user_id = ?
         ORDER BY ua.earned_at DESC'
    );
    $achievements_stmt->bind_param('i', $student_id);
    $achievements_stmt->execute();
    $achievements = $achievements_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $achievements_stmt->close();
    foreach ($achievements as $achievement) {
        $experience_points += (int) $achievement['xp_reward'];
    }
} catch (mysqli_sql_exception $error) {
    $achievements_available = false;
    error_log('Student dashboard could not load achievements: ' . $error->getMessage());
}

include BASE_URL . 'includes/dashboard_header.php';
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">Your Progress</h1>
    <?php if (!$achievements_available): ?>
        <div class="alert alert-warning" role="alert">Achievement and XP data is temporarily unavailable. Check the server error log.</div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-4 col-md-6 mb-4">
            <a href="./progress.php" class="text-decoration-none text-reset">
                <div class="stat-card text-primary h-100">
                    <div class="d-flex justify-content-between align-items-center"><h2 class="h6">Experience Points</h2><i class="fas fa-star icon-lg text-primary"></i></div>
                    <div class="stat-value"><?php echo $achievements_available ? number_format($experience_points) : 'Unavailable'; ?><?php if ($achievements_available): ?> <small class="fs-6 text-muted">XP</small><?php endif; ?></div>
                    <small class="stat-subtext"><?php echo $achievements_available ? count($achievements) . ' recent achievements' : 'Achievement records are unavailable'; ?></small>
                </div>
            </a>
        </div>
        <div class="col-lg-4 col-md-6 mb-4">
            <a href="./progress.php" class="text-decoration-none text-reset">
                <div class="stat-card text-success h-100">
                    <div class="d-flex justify-content-between align-items-center"><h2 class="h6">Lessons Completed</h2><i class="fas fa-check-circle icon-lg text-success"></i></div>
                    <div class="stat-value"><?php echo number_format($completed_lessons); ?>/<?php echo number_format($total_lessons); ?></div>
                    <small class="stat-subtext"><?php echo $lesson_completion; ?>% complete across your courses</small>
                    <div class="progress mt-2"><div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $lesson_completion; ?>%;" aria-valuenow="<?php echo $lesson_completion; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $lesson_completion; ?>%</div></div>
                </div>
            </a>
        </div>
        <div class="col-lg-4 col-md-12 mb-4">
            <a href="./progress.php" class="text-decoration-none text-reset">
                <div class="stat-card text-danger h-100">
                    <div class="d-flex justify-content-between align-items-center"><h2 class="h6">Study Streak</h2><i class="fas fa-fire icon-lg text-danger"></i></div>
                    <div class="stat-value"><?php echo number_format($study_streak); ?> <small class="fs-6 text-muted">days</small></div>
                    <small class="stat-subtext"><?php echo $study_streak > 0 ? 'Consecutive days with completed lessons' : 'Complete a lesson today to start your streak'; ?></small>
                </div>
            </a>
        </div>
    </div>

    <div class="row mt-0 mt-md-3">
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Active Courses</h2></div>
                <div class="card-body">
                    <?php if (!$active_courses): ?>
                        <div class="text-center py-4">
                            <p class="text-muted">You are not enrolled in any courses yet.</p>
                            <a href="../pages/browse_courses.php" class="btn btn-primary"><i class="fas fa-search me-2"></i>Browse Courses</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($active_courses as $course): ?>
                            <?php $progress = (int) $course['progress_percentage']; ?>
                            <a href="./my_courses.php#course-<?php echo (int) $course['course_id']; ?>" class="text-decoration-none text-dark">
                                <div class="course-card-sm mb-3">
                                    <div class="course-icon bg-primary"><?php echo htmlspecialchars(strtoupper(substr($course['title'], 0, 2)), ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="course-info flex-grow-1">
                                        <h3 class="h6"><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <p class="mb-1"><?php echo htmlspecialchars($course['subtitle'] ?: 'Continue learning at your own pace.', ENT_QUOTES, 'UTF-8'); ?> <small><?php echo (int) $course['lessons_completed']; ?>/<?php echo (int) $course['lesson_count']; ?> lessons</small></p>
                                        <div class="progress" style="height: 6px;"><div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $progress; ?>%;" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div></div>
                                        <small class="text-muted d-block mt-1">Next: <?php echo htmlspecialchars($course['next_lesson_title'] ?: 'All available lessons completed', ENT_QUOTES, 'UTF-8'); ?></small>
                                    </div>
                                    <div class="course-progress-text ms-3"><?php echo $progress; ?>%</div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white text-center"><a href="./my_courses.php" class="btn btn-outline-primary btn-sm">View All My Courses</a></div>
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Upcoming Tasks</h2></div>
                <div class="card-body">
                    <?php if (!$upcoming_tasks): ?>
                        <p class="text-muted text-center my-3"><?php echo $active_courses ? 'No incomplete quizzes or assignments in your courses.' : 'Enroll in a course to see its quizzes and assignments here.'; ?></p>
                    <?php else: ?>
                        <?php foreach ($upcoming_tasks as $task): ?>
                            <a href="./my_courses.php#course-<?php echo (int) $task['course_id']; ?>" class="text-decoration-none text-dark">
                                <div class="task-item">
                                    <div class="icon-circle <?php echo $task['content_type'] === 'quiz' ? 'bg-warning text-dark' : 'bg-info text-white'; ?>"><i class="<?php echo $task['content_type'] === 'quiz' ? 'fas fa-file-alt' : 'fas fa-book-open'; ?>"></i></div>
                                    <div class="flex-grow-1">
                                        <h3 class="h6 mb-0 small fw-bold"><?php echo htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <small class="text-muted"><?php echo htmlspecialchars($task['course_title'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo ucfirst($task['content_type']); ?></small>
                                    </div>
                                    <i class="fas fa-chevron-right text-muted small"></i>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white text-center"><a href="./assignments.php" class="btn btn-outline-secondary btn-sm">View All Tasks</a></div>
            </div>
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Recent Achievements</h2></div>
                <div class="card-body">
                    <?php if (!$achievements_available): ?>
                        <p class="text-muted mb-0">Achievement data is unavailable.</p>
                    <?php elseif (!$achievements): ?>
                        <p class="text-muted mb-0">No achievements earned yet. Keep learning to unlock them.</p>
                    <?php else: ?>
                        <?php foreach (array_slice($achievements, 0, 2) as $achievement): ?>
                            <div class="achievement-item">
                                <div class="icon-circle bg-warning text-dark"><i class="<?php echo htmlspecialchars($achievement['icon_class'] ?: 'fas fa-trophy', ENT_QUOTES, 'UTF-8'); ?>"></i></div>
                                <div>
                                    <h3 class="h6 mb-0 small fw-bold"><?php echo htmlspecialchars($achievement['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <small class="text-muted"><?php echo htmlspecialchars($achievement['description'], ENT_QUOTES, 'UTF-8'); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include BASE_URL . 'includes/dashboard_footer.php'; ?>
