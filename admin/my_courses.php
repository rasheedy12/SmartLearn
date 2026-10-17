<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/student_helpers.php';
require_once BASE_URL . 'includes/youtube_helpers.php';
$page_title = 'My Courses';
$student_id = (int) $_SESSION['user_id'];

$stmt = $mysqli->prepare(
    'SELECT e.enrollment_id, e.course_id, e.progress_percentage, e.enrolled_at, e.completed_at,
            c.title, c.subtitle, c.description, c.thumbnail_url, c.skill_level,
            CONCAT(u.first_name, \' \', u.last_name) AS instructor_name,
            cat.name AS category_name,
            COUNT(DISTINCT l.lesson_id) AS lesson_count,
            MIN(l.lesson_id) AS first_lesson_id,
            COUNT(DISTINCT CASE WHEN lp.is_completed = 1 THEN l.lesson_id END) AS lessons_completed
     FROM enrollments e
     INNER JOIN courses c ON c.course_id = e.course_id
     INNER JOIN users u ON u.user_id = c.instructor_id
     LEFT JOIN categories cat ON cat.category_id = c.category_id
     LEFT JOIN modules m ON m.course_id = c.course_id
     LEFT JOIN lessons l ON l.module_id = m.module_id
     LEFT JOIN lesson_progress lp ON lp.lesson_id = l.lesson_id AND lp.enrollment_id = e.enrollment_id
     WHERE e.user_id = ?
     GROUP BY e.enrollment_id, e.course_id, e.progress_percentage, e.enrolled_at, e.completed_at,
              c.title, c.subtitle, c.description, c.thumbnail_url, c.skill_level,
              u.first_name, u.last_name, cat.name
     ORDER BY e.completed_at IS NOT NULL, e.enrolled_at DESC'
);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$my_courses_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$watchlist_stmt = $mysqli->prepare(
    'SELECT e.course_id, l.lesson_id, l.title AS lesson_title, l.video_url,
            l.duration_minutes, m.title AS module_title
     FROM enrollments e
     INNER JOIN courses c ON c.course_id = e.course_id
     INNER JOIN modules m ON m.course_id = c.course_id
     INNER JOIN lessons l ON l.module_id = m.module_id
     WHERE e.user_id = ? AND l.content_type = \'video\' AND l.video_url IS NOT NULL
     ORDER BY e.enrolled_at DESC, m.order_index, l.order_index, l.lesson_id'
);
$watchlist_stmt->bind_param('i', $student_id);
$watchlist_stmt->execute();
$watchlist_rows = $watchlist_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$watchlist_stmt->close();

$video_watchlist = [];
foreach ($watchlist_rows as $item) {
    if (youtube_video_id($item['video_url']) !== null) {
        $video_watchlist[(int) $item['course_id']][] = $item;
    }
}

include BASE_URL . 'includes/dashboard_header.php';
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">My Courses</h1>
    <p class="lead mb-4">Your enrolled courses and learning progress.</p>

    <?php if (!$my_courses_data): ?>
        <div class="alert alert-info text-center">
            <h2 class="h4 alert-heading">No Courses Yet</h2>
            <p>You are not enrolled in a course yet. Browse the catalog to find one.</p>
            <a href="../pages/browse_courses.php" class="btn btn-primary"><i class="fas fa-search me-2"></i>Browse Courses</a>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($my_courses_data as $course): ?>
                <?php $progress = (int) $course['progress_percentage']; ?>
                <div class="col" id="course-<?php echo (int) $course['course_id']; ?>">
                    <div class="card h-100 shadow-sm course-listing-card">
                        <?php if (!empty($course['thumbnail_url'])): ?>
                            <?php $thumbnail = filter_var($course['thumbnail_url'], FILTER_VALIDATE_URL) ? $course['thumbnail_url'] : BASE_URL . ltrim($course['thumbnail_url'], '/'); ?>
                            <img src="<?php echo htmlspecialchars($thumbnail, ENT_QUOTES, 'UTF-8'); ?>" class="card-img-top" alt="" style="height: 180px; object-fit: cover;">
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <h2 class="h5 card-title"><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                            <p class="card-text small text-muted mb-2">By <?php echo htmlspecialchars($course['instructor_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="card-text small"><?php echo htmlspecialchars($course['category_name'] ?: ucfirst($course['skill_level']), ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="card-text small mb-1">Progress: <?php echo $progress; ?>% (<?php echo (int) $course['lessons_completed']; ?>/<?php echo (int) $course['lesson_count']; ?> lessons completed)</p>
                            <div class="progress mt-auto" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo $progress; ?>%;" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <?php if ($course['completed_at']): ?>
                                <span class="badge bg-success mt-3 align-self-start">Completed</span>
                            <?php endif; ?>
                            <?php if (!empty($course['first_lesson_id'])): ?>
                                <a class="btn btn-primary mt-3"
                                   href="../pages/lesson_view.php?course_id=<?php echo (int) $course['course_id']; ?>&amp;lesson_id=<?php echo (int) $course['first_lesson_id']; ?>">
                                    Continue learning
                                </a>
                            <?php else: ?>
                                <a class="btn btn-outline-primary mt-3"
                                   href="../pages/course_detail.php?id=<?php echo (int) $course['course_id']; ?>">
                                    View course playlist
                                </a>
                            <?php endif; ?>
                            <?php $course_videos = $video_watchlist[(int) $course['course_id']] ?? []; ?>
                            <?php if ($course_videos): ?>
                                <details class="mt-3">
                                    <summary class="fw-semibold">
                                        Video watchlist (<?php echo count($course_videos); ?>)
                                    </summary>
                                    <div class="list-group list-group-flush mt-2">
                                        <?php foreach ($course_videos as $video): ?>
                                            <a class="list-group-item list-group-item-action px-0"
                                               href="../pages/lesson_view.php?course_id=<?php echo (int) $course['course_id']; ?>&amp;lesson_id=<?php echo (int) $video['lesson_id']; ?>">
                                                <i class="fab fa-youtube text-danger me-2" aria-hidden="true"></i>
                                                <?php echo htmlspecialchars($video['lesson_title'], ENT_QUOTES, 'UTF-8'); ?>
                                                <small class="d-block text-muted ms-4">
                                                    <?php echo htmlspecialchars($video['module_title'], ENT_QUOTES, 'UTF-8'); ?>
                                                    <?php if (!empty($video['duration_minutes'])): ?>
                                                        · <?php echo (int) $video['duration_minutes']; ?> min
                                                    <?php endif; ?>
                                                </small>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php include BASE_URL . 'includes/dashboard_footer.php'; ?>
