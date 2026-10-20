<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/student_helpers.php';
$page_title = 'My Progress';
$student_id = (int) $_SESSION['user_id'];
$use_charts = true;

$courses_stmt = $mysqli->prepare(
    'SELECT c.title, e.progress_percentage, e.completed_at,
            COUNT(DISTINCT l.lesson_id) AS total_lessons,
            COUNT(DISTINCT CASE WHEN lp.is_completed = 1 THEN l.lesson_id END) AS lessons_completed
     FROM enrollments e
     INNER JOIN courses c ON c.course_id = e.course_id
     LEFT JOIN modules m ON m.course_id = c.course_id
     LEFT JOIN lessons l ON l.module_id = m.module_id
     LEFT JOIN lesson_progress lp ON lp.lesson_id = l.lesson_id AND lp.enrollment_id = e.enrollment_id
     WHERE e.user_id = ?
     GROUP BY e.enrollment_id, c.title, e.progress_percentage, e.completed_at
     ORDER BY e.completed_at IS NOT NULL, c.title'
);
$courses_stmt->bind_param('i', $student_id);
$courses_stmt->execute();
$course_specific_progress = $courses_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$courses_stmt->close();

$total_courses_enrolled = count($course_specific_progress);
$courses_completed = count(array_filter($course_specific_progress, function ($course) {
    return $course['completed_at'] !== null;
}));
$progress_total = 0;
foreach ($course_specific_progress as $course) {
    $progress_total += (int) $course['progress_percentage'];
}
$overall_progress = $total_courses_enrolled > 0 ? (int) round($progress_total / $total_courses_enrolled) : 0;

$completion_stmt = $mysqli->prepare(
    'SELECT COUNT(DISTINCT lp.lesson_id) AS total FROM lesson_progress lp
     INNER JOIN enrollments e ON e.enrollment_id = lp.enrollment_id
     WHERE e.user_id = ? AND lp.is_completed = 1'
);
$completion_stmt->bind_param('i', $student_id);
$completion_stmt->execute();
$completed_lessons = (int) $completion_stmt->get_result()->fetch_assoc()['total'];
$completion_stmt->close();

$achievements_available = true;
$badges_earned = [];
$xp_earned = $completed_lessons * 10;
try {
    $achievements_stmt = $mysqli->prepare(
        'SELECT a.name, a.description, a.icon_class, ua.earned_at
         FROM user_achievements ua
         INNER JOIN achievements a ON a.achievement_id = ua.achievement_id
         WHERE ua.user_id = ?
         ORDER BY ua.earned_at DESC'
    );
    $achievements_stmt->bind_param('i', $student_id);
    $achievements_stmt->execute();
    $badges_earned = $achievements_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $achievements_stmt->close();
} catch (mysqli_sql_exception $error) {
    $achievements_available = false;
    error_log('Student progress could not load achievements: ' . $error->getMessage());
}

$weekly_labels = [];
$weekly_counts = [];
$week_start = new DateTimeImmutable('monday this week');
for ($offset = 5; $offset >= 0; $offset--) {
    $week = $week_start->modify('-' . $offset . ' weeks');
    $weekly_labels[$week->format('Y-m-d')] = $week->format('M j');
    $weekly_counts[$week->format('Y-m-d')] = 0;
}
$activity_stmt = $mysqli->prepare(
    'SELECT DATE_SUB(DATE(lp.completed_at), INTERVAL WEEKDAY(lp.completed_at) DAY) AS week_start,
            COUNT(*) AS completed
     FROM lesson_progress lp
     INNER JOIN enrollments e ON e.enrollment_id = lp.enrollment_id
     WHERE e.user_id = ? AND lp.is_completed = 1
       AND lp.completed_at >= DATE_SUB(DATE_SUB(CURRENT_DATE, INTERVAL WEEKDAY(CURRENT_DATE) DAY), INTERVAL 5 WEEK)
     GROUP BY week_start ORDER BY week_start'
);
$activity_stmt->bind_param('i', $student_id);
$activity_stmt->execute();
foreach ($activity_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    if (isset($weekly_counts[$row['week_start']])) {
        $weekly_counts[$row['week_start']] = (int) $row['completed'];
    }
}
$activity_stmt->close();
$chart_data = [
    'learningActivity' => [
        'labels' => array_values($weekly_labels),
        'datasets' => [[
            'label' => 'Lessons Completed',
            'data' => array_values($weekly_counts),
            'borderColor' => 'rgba(25, 135, 84, 1)',
            'backgroundColor' => 'rgba(25, 135, 84, 0.1)',
            'fill' => true,
            'tension' => 0.3,
        ]],
    ],
];

include BASE_URL . 'includes/dashboard_header.php';
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">My Progress</h1>
    <p class="lead mb-4">Your course completion, lesson activity, and earned achievements.</p>
    <?php if (!$achievements_available): ?>
        <div class="alert alert-warning" role="alert">Achievement badge data is temporarily unavailable. Your lesson XP is still up to date.</div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="stat-card text-center h-100">
                <i class="fas fa-chart-pie fa-3x text-primary mb-3"></i>
                <h2 class="h6">Overall Progress</h2>
                <div class="display-4 fw-bold"><?php echo $overall_progress; ?>%</div>
                <div class="progress mt-2" style="height:10px;"><div class="progress-bar" role="progressbar" style="width: <?php echo $overall_progress; ?>%;" aria-valuenow="<?php echo $overall_progress; ?>" aria-valuemin="0" aria-valuemax="100"></div></div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="stat-card text-center h-100">
                <i class="fas fa-book-reader fa-3x text-success mb-3"></i>
                <h2 class="h6">Courses Completed</h2>
                <div class="display-4 fw-bold"><?php echo $courses_completed; ?> <span class="fs-5 text-muted">/ <?php echo $total_courses_enrolled; ?></span></div>
                <small class="text-muted"><?php echo number_format($completed_lessons); ?> lessons completed</small>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="stat-card text-center h-100">
                <i class="fas fa-star fa-3x text-warning mb-3"></i>
                <h2 class="h6">XP Earned</h2>
                <div class="display-4 fw-bold"><?php echo number_format($xp_earned); ?></div>
                <small class="text-muted">10 XP per completed lesson</small>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Course Specific Progress</h2></div>
                <div class="card-body">
                    <?php if (!$course_specific_progress): ?>
                        <p class="text-muted mb-0">Enroll in a course to see your progress here.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($course_specific_progress as $course): ?>
                                <?php $progress = (int) $course['progress_percentage']; ?>
                                <li class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h3 class="h6 mb-1"><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <small class="text-muted"><?php echo $course['completed_at'] ? 'Completed' : 'In Progress'; ?></small>
                                    </div>
                                    <div class="progress" style="height: 8px;"><div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $progress; ?>%;" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div></div>
                                    <small><?php echo $progress; ?>% Complete · <?php echo (int) $course['lessons_completed']; ?>/<?php echo (int) $course['total_lessons']; ?> lessons</small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Badges &amp; Achievements</h2></div>
                <div class="card-body">
                    <?php if (!$achievements_available): ?>
                        <p class="text-muted mb-0">Achievement records are unavailable.</p>
                    <?php elseif (!$badges_earned): ?>
                        <p class="text-muted mb-0">No badges earned yet. Keep learning to unlock them.</p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($badges_earned as $badge): ?>
                                <div class="col-4 text-center mb-3">
                                    <i class="<?php echo htmlspecialchars($badge['icon_class'] ?: 'fas fa-award', ENT_QUOTES, 'UTF-8'); ?> fa-3x mb-1"></i>
                                    <p class="small mb-0 fw-bold"><?php echo htmlspecialchars($badge['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <small class="text-muted d-block"><?php echo htmlspecialchars(date('M d, Y', strtotime($badge['earned_at'])), ENT_QUOTES, 'UTF-8'); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h2 class="h5 mb-0">Learning Activity Over Time</h2></div>
        <div class="card-body">
            <div class="chart-container" style="height:250px;"><canvas id="learningActivityChart"></canvas></div>
            <p class="text-muted text-center small mt-2">Completed lessons per week, for the last six weeks.</p>
        </div>
    </div>
</div>
<script>const chartData = <?php echo json_encode($chart_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;</script>
<?php
$extra_js = [BASE_URL . 'assets/js/dashboard_charts.js'];
include BASE_URL . 'includes/dashboard_footer.php';
?>
