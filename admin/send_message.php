<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/instructor_helpers.php';
$instructor_id = (int) $_SESSION['user_id'];
$student_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$student_id || $student_id < 1) {
    http_response_code(400);
    exit('A valid student is required.');
}

$student_stmt = $mysqli->prepare(
    'SELECT u.user_id, u.first_name, u.last_name
     FROM users u
     INNER JOIN enrollments e ON e.user_id = u.user_id
     INNER JOIN courses c ON c.course_id = e.course_id
     WHERE u.user_id = ? AND c.instructor_id = ?
     LIMIT 1'
);
$student_stmt->bind_param('ii', $student_id, $instructor_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();
$student_stmt->close();
if (!$student) {
    http_response_code(404);
    exit('Student not found in your courses.');
}

$page_title = 'Send Student Message';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!instructor_verify_csrf()) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    }
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($title === '' || strlen($title) > 255) {
        $errors[] = 'Enter a subject of no more than 255 characters.';
    }
    if ($message === '') {
        $errors[] = 'Enter a message.';
    }
    if (!$errors) {
        $insert = $mysqli->prepare(
            'INSERT INTO notifications (user_id, title, message, type)
             VALUES (?, ?, ?, \'new_message\')'
        );
        $insert->bind_param('iss', $student_id, $title, $message);
        $insert->execute();
        $insert->close();
        instructor_flash('success', 'Message sent to ' . $student['first_name'] . ' ' . $student['last_name'] . '.');
        header('Location: students_list.php');
        exit;
    }
}

include BASE_URL . 'includes/dashboard_header.php';
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">Send Message</h1>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white"><h5 class="mb-0">To: <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'], ENT_QUOTES, 'UTF-8'); ?></h5></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(instructor_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="mb-3"><label class="form-label" for="messageTitle">Subject</label><input class="form-control" id="messageTitle" name="title" maxlength="255" required value="<?php echo htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
                <div class="mb-3"><label class="form-label" for="messageBody">Message</label><textarea class="form-control" id="messageBody" name="message" rows="5" required><?php echo htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea></div>
                <button class="btn btn-primary" type="submit">Send Message</button>
                <a href="<?php echo BASE_URL; ?>students_list.php" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>
<?php include BASE_URL . 'includes/dashboard_footer.php'; ?>
