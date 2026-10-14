<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/instructor_helpers.php';
require_once BASE_URL . 'includes/youtube_helpers.php';
$instructor_id = (int) $_SESSION['user_id'];
$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
if (!$course_id || $course_id < 1) {
    http_response_code(400);
    exit('A valid course is required.');
}
$course = instructor_require_course($mysqli, $course_id, $instructor_id);
$page_title = 'Manage Course Content';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!instructor_verify_csrf()) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'add_module') {
        $module_title = trim($_POST['module_title'] ?? '');
        if ($module_title === '' || strlen($module_title) > 255) {
            $errors[] = 'Enter a module title of no more than 255 characters.';
        } else {
            $order_stmt = $mysqli->prepare('SELECT COALESCE(MAX(order_index), 0) + 1 AS next_order FROM modules WHERE course_id = ?');
            $order_stmt->bind_param('i', $course_id);
            $order_stmt->execute();
            $order_index = (int) $order_stmt->get_result()->fetch_assoc()['next_order'];
            $order_stmt->close();

            $insert = $mysqli->prepare('INSERT INTO modules (course_id, title, order_index) VALUES (?, ?, ?)');
            $insert->bind_param('isi', $course_id, $module_title, $order_index);
            $insert->execute();
            $insert->close();
            instructor_flash('success', 'Module added.');
            header('Location: course_content.php?course_id=' . $course_id);
            exit;
        }
    } elseif (($_POST['action'] ?? '') === 'add_lesson') {
        $module_id = filter_var($_POST['module_id'] ?? null, FILTER_VALIDATE_INT);
        $lesson_title = trim($_POST['lesson_title'] ?? '');
        $content_type = $_POST['content_type'] ?? '';
        $content = trim($_POST['lesson_content'] ?? '');
        $is_previewable = isset($_POST['is_previewable']) ? 1 : 0;
        $allowed_types = ['video', 'text', 'quiz', 'assignment', 'external_link'];

        if (!$module_id || $module_id < 1) {
            $errors[] = 'Choose a valid module.';
        }
        if ($lesson_title === '' || strlen($lesson_title) > 255) {
            $errors[] = 'Enter a lesson title of no more than 255 characters.';
        }
        if (!in_array($content_type, $allowed_types, true)) {
            $errors[] = 'Choose a valid lesson type.';
        }
        if (in_array($content_type, ['text', 'video', 'external_link'], true) && $content === '') {
            $errors[] = 'Enter the lesson content or URL.';
        }
        if ($content_type === 'video' && youtube_video_id($content) === null) {
            $errors[] = 'Enter a valid YouTube video URL for video lessons.';
        }
        if ($content_type === 'external_link'
            && (!filter_var($content, FILTER_VALIDATE_URL)
                || !in_array(strtolower((string) parse_url($content, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            $errors[] = 'Enter a valid HTTP or HTTPS URL for external-link lessons.';
        }

        if (!$errors) {
            $module_check = $mysqli->prepare('SELECT module_id FROM modules WHERE module_id = ? AND course_id = ?');
            $module_check->bind_param('ii', $module_id, $course_id);
            $module_check->execute();
            $owns_module = $module_check->get_result()->fetch_assoc();
            $module_check->close();
            if (!$owns_module) {
                $errors[] = 'The selected module does not belong to this course.';
            } else {
                $order_stmt = $mysqli->prepare('SELECT COALESCE(MAX(order_index), 0) + 1 AS next_order FROM lessons WHERE module_id = ?');
                $order_stmt->bind_param('i', $module_id);
                $order_stmt->execute();
                $order_index = (int) $order_stmt->get_result()->fetch_assoc()['next_order'];
                $order_stmt->close();

                $video_url = $content_type === 'video' ? $content : null;
                $external_url = $content_type === 'external_link' ? $content : null;
                $text_content = $content_type === 'text' ? $content : null;
                $insert = $mysqli->prepare(
                    'INSERT INTO lessons (module_id, title, content_type, video_url, text_content, external_url, order_index, is_previewable)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $insert->bind_param('issssssi', $module_id, $lesson_title, $content_type, $video_url, $text_content, $external_url, $order_index, $is_previewable);
                $insert->execute();
                $insert->close();
                instructor_flash('success', 'Lesson added.');
                header('Location: course_content.php?course_id=' . $course_id);
                exit;
            }
        }
    } else {
        $errors[] = 'Choose a valid content action.';
    }
}

$modules_stmt = $mysqli->prepare(
    'SELECT m.module_id, m.title,
            l.lesson_id, l.title AS lesson_title, l.content_type, l.order_index AS lesson_order
     FROM modules m
     LEFT JOIN lessons l ON l.module_id = m.module_id
     WHERE m.course_id = ?
     ORDER BY m.order_index, m.module_id, l.order_index, l.lesson_id'
);
$modules_stmt->bind_param('i', $course_id);
$modules_stmt->execute();
$module_rows = $modules_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$modules_stmt->close();
$modules = [];
foreach ($module_rows as $row) {
    $module_id = (int) $row['module_id'];
    if (!isset($modules[$module_id])) {
        $modules[$module_id] = ['module_id' => $module_id, 'title' => $row['title'], 'lessons' => []];
    }
    if ($row['lesson_id'] !== null) {
        $modules[$module_id]['lessons'][] = $row;
    }
}

include BASE_URL . 'includes/dashboard_header.php';
?>
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div><h1 class="h2 mb-1">Manage Course Content</h1><p class="lead mb-0"><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></p></div>
        <a href="<?php echo INSTRUCTOR_ADMIN_URL; ?>instructor_courses.php" class="btn btn-outline-secondary">Back to My Courses</a>
    </div>
    <?php instructor_show_flash(); ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <div class="row">
        <div class="col-lg-5 mb-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white"><h5 class="mb-0">Add Module</h5></div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(instructor_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="add_module">
                        <label class="form-label" for="moduleTitle">Module title</label>
                        <input class="form-control mb-3" id="moduleTitle" name="module_title" maxlength="255" required>
                        <button class="btn btn-primary" type="submit">Add Module</button>
                    </form>
                </div>
            </div>
            <?php if ($modules): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-white"><h5 class="mb-0">Add Lesson</h5></div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(instructor_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="add_lesson">
                            <div class="mb-3">
                                <label class="form-label" for="lessonModule">Module</label>
                                <select class="form-select" id="lessonModule" name="module_id" required>
                                    <?php foreach ($modules as $module): ?><option value="<?php echo $module['module_id']; ?>"><?php echo htmlspecialchars($module['title'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3"><label class="form-label" for="lessonTitle">Lesson title</label><input class="form-control" id="lessonTitle" name="lesson_title" maxlength="255" required></div>
                            <div class="mb-3">
                                <label class="form-label" for="contentType">Lesson type</label>
                                <select class="form-select" id="contentType" name="content_type" required>
                                    <option value="video">YouTube video URL</option><option value="text">Text lesson</option><option value="quiz">Quiz</option><option value="assignment">Assignment</option><option value="external_link">External link</option>
                                </select>
                            </div>
                            <div class="mb-3"><label class="form-label" for="lessonContent">Content or URL</label><textarea class="form-control" id="lessonContent" name="lesson_content" rows="4"></textarea></div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="lessonPreviewable" name="is_previewable" value="1">
                                    <label class="form-check-label" for="lessonPreviewable">Allow students to preview this lesson before enrolling</label>
                                </div>
                            <button class="btn btn-primary" type="submit">Add Lesson</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-0">Course Modules and Lessons</h5></div>
                <div class="card-body">
                    <?php if (!$modules): ?><p class="text-muted mb-0">No modules yet. Add your first module to start building this course.</p><?php endif; ?>
                    <?php foreach ($modules as $module): ?>
                        <div class="mb-4">
                            <h5><?php echo htmlspecialchars($module['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                            <?php if (!$module['lessons']): ?><p class="text-muted">No lessons in this module yet.</p><?php else: ?>
                                <ol class="list-group list-group-numbered">
                                    <?php foreach ($module['lessons'] as $lesson): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo htmlspecialchars($lesson['lesson_title'], ENT_QUOTES, 'UTF-8'); ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $lesson['content_type'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include BASE_URL . 'includes/dashboard_footer.php'; ?>
