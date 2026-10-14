<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/instructor_helpers.php';
$instructor_id = (int) $_SESSION['user_id'];
$page_title = 'Send Announcement';
$errors = [];

$courses_stmt = $mysqli->prepare('SELECT course_id, title FROM courses WHERE instructor_id = ? ORDER BY title');
$courses_stmt->bind_param('i', $instructor_id);
$courses_stmt->execute();
$courses = $courses_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$courses_stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!instructor_verify_csrf()) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    }
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $course_id = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
    if ($title === '' || strlen($title) > 235) {
        $errors[] = 'Enter an announcement title of no more than 235 characters.';
    }
    if ($message === '') {
        $errors[] = 'Enter an announcement message.';
    }
    if ($course_id === false || $course_id < 0) {
        $errors[] = 'Choose a valid course.';
    }

    if (!$errors && $course_id > 0 && !instructor_get_course($mysqli, $course_id, $instructor_id)) {
        $errors[] = 'The selected course does not belong to your account.';
    }

    if (!$errors) {
        if ($course_id > 0) {
            $students_stmt = $mysqli->prepare(
                'SELECT DISTINCT e.user_id FROM enrollments e
                 INNER JOIN courses c ON c.course_id = e.course_id
                 WHERE c.course_id = ? AND c.instructor_id = ?'
            );
            $students_stmt->bind_param('ii', $course_id, $instructor_id);
        } else {
            $students_stmt = $mysqli->prepare(
                'SELECT DISTINCT e.user_id FROM enrollments e
                 INNER JOIN courses c ON c.course_id = e.course_id
                 WHERE c.instructor_id = ?'
            );
            $students_stmt->bind_param('i', $instructor_id);
        }
        $students_stmt->execute();
        $students = $students_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $students_stmt->close();

        if (!$students) {
            $errors[] = 'There are no enrolled students to receive this announcement.';
        } else {
            $notification_title = 'Announcement: ' . $title;
            $mysqli->begin_transaction();
            $insert = $mysqli->prepare(
                'INSERT INTO notifications (user_id, title, message, type)
                 VALUES (?, ?, ?, \'course_update\')'
            );
            foreach ($students as $student) {
                $user_id = (int) $student['user_id'];
                $insert->bind_param('iss', $user_id, $notification_title, $message);
                $insert->execute();
            }
            $insert->close();
            $mysqli->commit();

            instructor_flash('success', 'Announcement sent to ' . count($students) . ' student' . (count($students) === 1 ? '' : 's') . '.');
            header('Location: announcements.php');
            exit;
        }
    }
}

include BASE_URL . 'includes/dashboard_header.php';
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">Send Announcement</h1>
    <?php instructor_show_flash(); ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white"><h5 class="mb-0">Notify Enrolled Students</h5></div>
        <div class="card-body">
            <?php if (!$courses): ?>
                <p class="text-muted">Create a course and enroll students before sending an announcement.</p>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(instructor_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="announcementCourse">Recipients</label>
                        <select class="form-select" id="announcementCourse" name="course_id" required>
                            <option value="0">All students in my courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo (int) $course['course_id']; ?>" <?php echo (string) ($course['course_id']) === (string) ($_POST['course_id'] ?? '') ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label" for="announcementTitle">Title</label><input class="form-control" id="announcementTitle" name="title" maxlength="235" required value="<?php echo htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="mb-3"><label class="form-label" for="announcementMessage">Message</label><textarea class="form-control" id="announcementMessage" name="message" rows="5" required><?php echo htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea></div>
                    <button class="btn btn-primary" type="submit">Send Announcement</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include BASE_URL . 'includes/dashboard_footer.php'; ?>
