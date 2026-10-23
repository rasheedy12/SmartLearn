<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedin'], $_SESSION['user_role'], $_SESSION['user_id'])
    || $_SESSION['loggedin'] !== true
    || $_SESSION['user_role'] !== 'student') {
    header('Location: ../pages/login.php?error=' . urlencode('Please sign in with a student account.'));
    exit;
}

require_once __DIR__ . '/db_config.php';

if (!isset($_SESSION['student_csrf_token'])) {
    $_SESSION['student_csrf_token'] = bin2hex(random_bytes(32));
}

function student_csrf_token()
{
    return $_SESSION['student_csrf_token'];
}

function student_verify_csrf()
{
    return isset($_POST['csrf_token'], $_SESSION['student_csrf_token'])
        && hash_equals($_SESSION['student_csrf_token'], $_POST['csrf_token']);
}

function student_record_lesson_completion(mysqli $mysqli, int $enrollment_id, int $lesson_id, int $course_id): void
{
    $progress_stmt = $mysqli->prepare(
        'INSERT INTO lesson_progress (enrollment_id, lesson_id, is_completed, completed_at)
         VALUES (?, ?, 1, NOW())
         ON DUPLICATE KEY UPDATE is_completed = 1, completed_at = NOW()'
    );
    $progress_stmt->bind_param('ii', $enrollment_id, $lesson_id);
    $progress_stmt->execute();
    $progress_stmt->close();

    $totals_stmt = $mysqli->prepare(
        'SELECT COUNT(l.lesson_id) AS lesson_count,
                COUNT(CASE WHEN lp.is_completed = 1 THEN l.lesson_id END) AS completed_count
         FROM modules m
         JOIN lessons l ON l.module_id = m.module_id
         LEFT JOIN lesson_progress lp ON lp.lesson_id = l.lesson_id AND lp.enrollment_id = ?
         WHERE m.course_id = ?'
    );
    $totals_stmt->bind_param('ii', $enrollment_id, $course_id);
    $totals_stmt->execute();
    $totals = $totals_stmt->get_result()->fetch_assoc();
    $totals_stmt->close();

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
    $enrollment_update->bind_param('iii', $progress_percentage, $is_course_complete, $enrollment_id);
    $enrollment_update->execute();
    $enrollment_update->close();
}
