<?php
require_once __DIR__ . '/../includes/student_helpers.php';
require_once __DIR__ . '/../includes/youtube_helpers.php';

$course_id = filter_var($_GET['course_id'] ?? null, FILTER_VALIDATE_INT);
$lesson_id = filter_var($_GET['lesson_id'] ?? null, FILTER_VALIDATE_INT);
$course_id = $course_id && $course_id > 0 ? $course_id : 0;
$lesson_id = $lesson_id && $lesson_id > 0 ? $lesson_id : 0;
$user_id = (int) $_SESSION['user_id'];
$error_message = '';
$lesson = null;
$enrollment = null;
$is_enrolled = false;
$playlist = [];
$transaction_started = false;

try {
    if ($course_id > 0 && $lesson_id > 0) {
        $lesson_stmt = $mysqli->prepare(
            'SELECT c.course_id, c.title AS course_title, c.status AS course_status,
                    l.lesson_id, l.title AS lesson_title,
                    l.content_type, l.video_url, l.text_content, l.external_url,
                    l.duration_minutes, l.is_previewable, m.title AS module_title
             FROM lessons l
             JOIN modules m ON m.module_id = l.module_id
             JOIN courses c ON c.course_id = m.course_id
             WHERE c.course_id = ? AND l.lesson_id = ?
             LIMIT 1'
        );
        $lesson_stmt->bind_param('ii', $course_id, $lesson_id);
        $lesson_stmt->execute();
        $lesson = $lesson_stmt->get_result()->fetch_assoc();

        if ($lesson) {
            $enrollment_stmt = $mysqli->prepare(
                'SELECT enrollment_id, progress_percentage FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1'
            );
            $enrollment_stmt->bind_param('ii', $user_id, $course_id);
            $enrollment_stmt->execute();
            $enrollment = $enrollment_stmt->get_result()->fetch_assoc();
            $is_enrolled = $enrollment !== null;
        }
    }

    if ($lesson && ($is_enrolled || (
        $lesson['course_status'] === 'published' && (int) $lesson['is_previewable'] === 1
    ))) {
        $playlist_stmt = $mysqli->prepare(
            'SELECT l.lesson_id, l.title, l.content_type, l.is_previewable, l.duration_minutes,
                    COALESCE(lp.is_completed, 0) AS is_completed
             FROM modules m
             JOIN lessons l ON l.module_id = m.module_id
             LEFT JOIN lesson_progress lp ON lp.lesson_id = l.lesson_id AND lp.enrollment_id = ?
             WHERE m.course_id = ?
             ORDER BY m.order_index, l.order_index, l.lesson_id'
        );
        $enrollment_id = $is_enrolled ? (int) $enrollment['enrollment_id'] : 0;
        $playlist_stmt->bind_param('ii', $enrollment_id, $course_id);
        $playlist_stmt->execute();
        $playlist = $playlist_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $exception) {
    error_log('Course lesson could not be loaded: ' . $exception->getMessage());
    $error_message = 'This lesson could not be loaded right now.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $lesson) {
    if (!student_verify_csrf()) {
        http_response_code(400);
        $error_message = 'Your request could not be verified. Refresh the page and try again.';
    } elseif (!$is_enrolled) {
        http_response_code(403);
        $error_message = 'Enroll in this course before recording lesson progress.';
    } elseif (($_POST['action'] ?? '') === 'complete_lesson') {
        try {
            $mysqli->begin_transaction();
            $transaction_started = true;
            $progress_stmt = $mysqli->prepare(
                'INSERT INTO lesson_progress (enrollment_id, lesson_id, is_completed, completed_at)
                 VALUES (?, ?, 1, NOW())
                 ON DUPLICATE KEY UPDATE is_completed = 1, completed_at = NOW()'
            );
            $progress_stmt->bind_param('ii', $enrollment['enrollment_id'], $lesson_id);
            $progress_stmt->execute();

            $totals_stmt = $mysqli->prepare(
                'SELECT COUNT(l.lesson_id) AS lesson_count,
                        COUNT(CASE WHEN lp.is_completed = 1 THEN l.lesson_id END) AS completed_count
                 FROM modules m
                 JOIN lessons l ON l.module_id = m.module_id
                 LEFT JOIN lesson_progress lp ON lp.lesson_id = l.lesson_id AND lp.enrollment_id = ?
                 WHERE m.course_id = ?'
            );
            $totals_stmt->bind_param('ii', $enrollment['enrollment_id'], $course_id);
            $totals_stmt->execute();
            $totals = $totals_stmt->get_result()->fetch_assoc();
            $lesson_total = (int) $totals['lesson_count'];
            $completed_total = (int) $totals['completed_count'];
            $progress_percentage = $lesson_total > 0
                ? (int) round($completed_total * 100 / $lesson_total)
                : 0;
            $is_course_complete = $lesson_total > 0 && $completed_total >= $lesson_total ? 1 : 0;

            $enrollment_update = $mysqli->prepare(
                'UPDATE enrollments
                 SET progress_percentage = ?,
                     completed_at = IF(? = 1, COALESCE(completed_at, NOW()), NULL)
                 WHERE enrollment_id = ?'
            );
            $enrollment_update->bind_param('iii', $progress_percentage, $is_course_complete, $enrollment['enrollment_id']);
            $enrollment_update->execute();
            $mysqli->commit();
            $transaction_started = false;
            header('Location: lesson_view.php?course_id=' . $course_id . '&lesson_id=' . $lesson_id . '&completed=1');
            exit;
        } catch (Throwable $exception) {
            if ($transaction_started) {
                $mysqli->rollback();
            }
            error_log('Lesson completion could not be saved: ' . $exception->getMessage());
            $error_message = 'Your lesson progress could not be saved. Please try again.';
        }
    }
}

$page_title = $lesson ? $lesson['lesson_title'] : 'Lesson unavailable';
$current_index = null;
foreach ($playlist as $index => $item) {
    if ((int) $item['lesson_id'] === $lesson_id) {
        $current_index = $index;
        break;
    }
}
$video_url = $lesson['video_url'] ?? '';
$youtube_id = youtube_video_id($video_url);
$direct_video = preg_match('~^https://[^\\s]+\\.(?:mp4|webm|ogg)(?:\\?[^\\s]*)?$~i', $video_url) === 1;
$external_link = $lesson['external_url'] ?? '';
include __DIR__ . '/../includes/dashboard_header.php';
?>
<div class="container-fluid">
    <?php if (!$lesson || (!$is_enrolled && (
        $lesson['course_status'] !== 'published' || (int) $lesson['is_previewable'] !== 1
    ))): ?>
        <div class="alert alert-warning">
            <h1 class="h4">Lesson unavailable</h1>
            <p><?php echo htmlspecialchars($error_message ?: 'This lesson does not exist or requires course enrollment.', ENT_QUOTES, 'UTF-8'); ?></p>
            <a class="btn btn-primary" href="course_detail.php?id=<?php echo $course_id; ?>">Back to course</a>
        </div>
    <?php else: ?>
        <?php if ($error_message !== ''): ?>
            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['completed']) && $_GET['completed'] === '1'): ?>
            <div class="alert alert-success" role="status">Lesson marked complete. Your progress has been updated.</div>
        <?php endif; ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div>
                <a href="course_detail.php?id=<?php echo $course_id; ?>" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i><?php echo htmlspecialchars($lesson['course_title'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
                <h1 class="h3 mt-2 mb-0"><?php echo htmlspecialchars($lesson['lesson_title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <small class="text-muted"><?php echo htmlspecialchars($lesson['module_title'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($lesson['duration_minutes'])): ?> · <?php echo (int) $lesson['duration_minutes']; ?> min<?php endif; ?>
                </small>
            </div>
            <?php if (!$is_enrolled): ?><span class="badge bg-info text-dark">Preview lesson</span><?php endif; ?>
        </div>

        <div class="row g-4">
            <main class="col-lg-8">
                <div class="bg-dark rounded p-2 mb-3">
                    <?php if ($youtube_id): ?>
                        <div class="ratio ratio-16x9">
                            <iframe src="https://www.youtube-nocookie.com/embed/<?php echo htmlspecialchars($youtube_id, ENT_QUOTES, 'UTF-8'); ?>"
                                    title="<?php echo htmlspecialchars($lesson['lesson_title'], ENT_QUOTES, 'UTF-8'); ?>"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                    referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                        </div>
                    <?php elseif ($direct_video): ?>
                        <video controls class="w-100">
                            <source src="<?php echo htmlspecialchars($video_url, ENT_QUOTES, 'UTF-8'); ?>">
                            Your browser does not support this video.
                        </video>
                    <?php elseif ($lesson['content_type'] === 'text' && !empty($lesson['text_content'])): ?>
                        <div class="bg-white text-dark rounded p-4">
                            <?php echo nl2br(htmlspecialchars($lesson['text_content'], ENT_QUOTES, 'UTF-8')); ?>
                        </div>
                    <?php elseif ($external_link !== '' && filter_var($external_link, FILTER_VALIDATE_URL)): ?>
                        <div class="text-center text-white py-5">
                            <p>This lesson opens an external resource.</p>
                            <a href="<?php echo htmlspecialchars($external_link, ENT_QUOTES, 'UTF-8'); ?>"
                               class="btn btn-light" target="_blank" rel="noopener noreferrer">Open lesson resource</a>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-white py-5">No video or lesson content has been added.</div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($lesson['text_content']) && $lesson['content_type'] !== 'text'): ?>
                    <section class="card mb-3">
                        <div class="card-body">
                            <h2 class="h5">Lesson notes</h2>
                            <?php echo nl2br(htmlspecialchars($lesson['text_content'], ENT_QUOTES, 'UTF-8')); ?>
                        </div>
                    </section>
                <?php endif; ?>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <?php
                    $previous = $current_index !== null && $current_index > 0 ? $playlist[$current_index - 1] : null;
                    $next = $current_index !== null && isset($playlist[$current_index + 1]) ? $playlist[$current_index + 1] : null;
                    ?>
                    <?php if ($previous && ($is_enrolled || (int) $previous['is_previewable'] === 1)): ?>
                        <a class="btn btn-outline-secondary"
                           href="lesson_view.php?course_id=<?php echo $course_id; ?>&amp;lesson_id=<?php echo (int) $previous['lesson_id']; ?>">
                            <i class="fas fa-chevron-left me-1"></i>Previous
                        </a>
                    <?php else: ?><span></span><?php endif; ?>
                    <?php if ($is_enrolled): ?>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(student_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="complete_lesson">
                            <button class="btn btn-success" type="submit">
                                <i class="fas fa-check-circle me-1"></i>Mark as complete
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if ($next && ($is_enrolled || (int) $next['is_previewable'] === 1)): ?>
                        <a class="btn btn-outline-secondary"
                           href="lesson_view.php?course_id=<?php echo $course_id; ?>&amp;lesson_id=<?php echo (int) $next['lesson_id']; ?>">
                            Next<i class="fas fa-chevron-right ms-1"></i>
                        </a>
                    <?php else: ?><span></span><?php endif; ?>
                </div>
            </main>

            <aside class="col-lg-4">
                <section class="card shadow-sm">
                    <div class="card-header"><h2 class="h5 mb-0">Course video playlist</h2></div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($playlist as $item): ?>
                            <?php
                            $available = $is_enrolled || (int) $item['is_previewable'] === 1;
                            $item_url = 'lesson_view.php?course_id=' . $course_id . '&lesson_id=' . (int) $item['lesson_id'];
                            ?>
                            <?php if ($available): ?>
                                <a class="list-group-item list-group-item-action <?php echo (int) $item['lesson_id'] === $lesson_id ? 'active' : ''; ?>"
                                   href="<?php echo htmlspecialchars($item_url, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php else: ?>
                                <div class="list-group-item text-muted">
                            <?php endif; ?>
                                <div class="d-flex align-items-start gap-2">
                                    <i class="fas <?php echo (int) $item['is_completed'] === 1 ? 'fa-check-circle text-success' : ($available ? 'fa-play-circle' : 'fa-lock'); ?> mt-1"></i>
                                    <span class="flex-grow-1"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ((int) $item['is_previewable'] === 1): ?><small class="d-block">Preview</small><?php endif; ?>
                                    </span>
                                    <?php if (!empty($item['duration_minutes'])): ?><small><?php echo (int) $item['duration_minutes']; ?>m</small><?php endif; ?>
                                </div>
                            <?php if ($available): ?></a><?php else: ?></div><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!$is_enrolled): ?>
                        <div class="card-body">
                            <p class="small text-muted">Enroll in this course to unlock the full playlist.</p>
                            <a class="btn btn-primary w-100" href="course_detail.php?id=<?php echo $course_id; ?>">View course and enroll</a>
                        </div>
                    <?php endif; ?>
                </section>
            </aside>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/dashboard_footer.php'; ?>
