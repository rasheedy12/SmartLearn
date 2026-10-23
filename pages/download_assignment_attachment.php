<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (
    !isset($_SESSION['loggedin'], $_SESSION['user_role'], $_SESSION['user_id'])
    || $_SESSION['loggedin'] !== true
    || !in_array($_SESSION['user_role'], ['student', 'instructor'], true)
) {
    http_response_code(401);
    exit('Sign in to download this attachment.');
}

$csrf_session_key = $_SESSION['user_role'] === 'student'
    ? 'student_csrf_token'
    : 'instructor_csrf_token';
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !isset($_POST['csrf_token'], $_SESSION[$csrf_session_key])
    || !hash_equals($_SESSION[$csrf_session_key], (string) $_POST['csrf_token'])
) {
    http_response_code(403);
    exit('This download request could not be verified.');
}

require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/assignment_helpers.php';

$attachment_id = filter_input(INPUT_POST, 'attachment_id', FILTER_VALIDATE_INT);
if (!$attachment_id || $attachment_id < 1) {
    http_response_code(404);
    exit('Attachment not found.');
}

$access_sql = 'SELECT a.original_name, a.stored_name, a.mime_type, a.file_size
               FROM learning_assignment_attachments a
               JOIN learning_assignment_submissions s ON s.submission_id = a.submission_id
               JOIN lessons l ON l.lesson_id = s.lesson_id
               JOIN modules m ON m.module_id = l.module_id
               JOIN enrollments e ON e.enrollment_id = s.enrollment_id
               JOIN courses c ON c.course_id = m.course_id
               WHERE a.attachment_id = ? AND l.content_type = \'assignment\'
                 AND ((? = \'student\' AND e.user_id = ? AND s.enrollment_id = e.enrollment_id
                       AND a.status IN (\'draft\', \'submitted\'))
                   OR (? = \'instructor\' AND c.instructor_id = ? AND a.status = \'submitted\'))
               LIMIT 1';
$user_role = (string) $_SESSION['user_role'];
$user_id = (int) $_SESSION['user_id'];
try {
    $access_stmt = $mysqli->prepare($access_sql);
    $access_stmt->bind_param('isisi', $attachment_id, $user_role, $user_id, $user_role, $user_id);
    $access_stmt->execute();
    $attachment = $access_stmt->get_result()->fetch_assoc();
    $access_stmt->close();
} catch (Throwable $exception) {
    error_log('Assignment attachment access could not be checked: ' . $exception->getMessage());
    http_response_code(500);
    exit('The attachment could not be downloaded right now.');
}

if (!$attachment || !preg_match('/\A[a-f0-9]{64}\z/', $attachment['stored_name'])) {
    http_response_code(404);
    exit('Attachment not found.');
}

try {
    $file_path = assignment_storage_directory() . DIRECTORY_SEPARATOR . $attachment['stored_name'];
} catch (Throwable $exception) {
    error_log('Assignment attachment storage is unavailable: ' . $exception->getMessage());
    http_response_code(404);
    exit('Attachment not found.');
}

if (!is_file($file_path) || !is_readable($file_path)) {
    http_response_code(404);
    exit('Attachment not found.');
}

$safe_name = preg_replace('/[^A-Za-z0-9._-]/', '_', $attachment['original_name']);
header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . (string) filesize($file_path));
header('Content-Disposition: attachment; filename="' . $safe_name . '"; filename*=UTF-8\'\'' . rawurlencode($attachment['original_name']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($file_path);
