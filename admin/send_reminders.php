<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/instructor_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !instructor_verify_csrf()) {
    instructor_flash('error', 'The reminder request could not be verified. Please try again.');
    header('Location: instructor_dashboard.php');
    exit;
}

$instructor_id = (int) $_SESSION['user_id'];
$students_stmt = $mysqli->prepare(
    'SELECT DISTINCT u.user_id
     FROM enrollments e
     INNER JOIN courses c ON c.course_id = e.course_id
     INNER JOIN users u ON u.user_id = e.user_id
     WHERE c.instructor_id = ?
       AND (u.last_login_at IS NULL OR u.last_login_at < DATE_SUB(NOW(), INTERVAL 5 DAY))'
);
$students_stmt->bind_param('i', $instructor_id);
$students_stmt->execute();
$students = $students_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$students_stmt->close();

if (!$students) {
    instructor_flash('success', 'There are no at-risk students to remind.');
    header('Location: instructor_dashboard.php');
    exit;
}

$mysqli->begin_transaction();
$insert = $mysqli->prepare(
    'INSERT INTO notifications (user_id, title, message, type)
     VALUES (?, ?, ?, \'info\')'
);
$title = 'Course activity reminder';
$message = 'Your instructor noticed you have not signed in recently. Please continue learning in your course.';
foreach ($students as $student) {
    $user_id = (int) $student['user_id'];
    $insert->bind_param('iss', $user_id, $title, $message);
    $insert->execute();
}
$insert->close();
$mysqli->commit();

instructor_flash('success', 'Reminder sent to ' . count($students) . ' at-risk student' . (count($students) === 1 ? '' : 's') . '.');
header('Location: instructor_dashboard.php');
exit;
?>
