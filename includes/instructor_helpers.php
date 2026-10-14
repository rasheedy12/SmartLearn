<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedin'], $_SESSION['user_role'], $_SESSION['user_id'])
    || $_SESSION['loggedin'] !== true
    || $_SESSION['user_role'] !== 'instructor') {
    header('Location: ../pages/login.php?error=' . urlencode('Please sign in with an instructor account.'));
    exit;
}

require_once __DIR__ . '/db_config.php';

if (!isset($_SESSION['instructor_csrf_token'])) {
    $_SESSION['instructor_csrf_token'] = bin2hex(random_bytes(32));
}

function instructor_csrf_token()
{
    return $_SESSION['instructor_csrf_token'];
}

function instructor_verify_csrf()
{
    return isset($_POST['csrf_token'], $_SESSION['instructor_csrf_token'])
        && hash_equals($_SESSION['instructor_csrf_token'], $_POST['csrf_token']);
}

function instructor_flash($type, $message)
{
    $_SESSION['instructor_flash'] = ['type' => $type, 'message' => $message];
}

function instructor_show_flash()
{
    if (empty($_SESSION['instructor_flash'])) {
        return;
    }

    $flash = $_SESSION['instructor_flash'];
    unset($_SESSION['instructor_flash']);
    $type = $flash['type'] === 'success' ? 'success' : 'danger';
    echo '<div class="alert alert-' . $type . '" role="alert">'
        . htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8')
        . '</div>';
}

function instructor_get_course(mysqli $mysqli, $course_id, $instructor_id)
{
    $stmt = $mysqli->prepare(
        'SELECT course_id, title, subtitle, description, status, thumbnail_url
         FROM courses WHERE course_id = ? AND instructor_id = ?'
    );
    $stmt->bind_param('ii', $course_id, $instructor_id);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $course ?: null;
}

function instructor_require_course(mysqli $mysqli, $course_id, $instructor_id)
{
    $course = instructor_get_course($mysqli, $course_id, $instructor_id);
    if (!$course) {
        http_response_code(404);
        exit('Course not found.');
    }

    return $course;
}

function instructor_student_rows(mysqli $mysqli, $instructor_id, $course_id = null, $search = '')
{
    $sql = 'SELECT u.user_id, u.first_name, u.last_name, u.email, u.avatar_url,
                   u.last_login_at, c.course_id, c.title AS course_title,
                   e.progress_percentage, e.enrolled_at
            FROM enrollments e
            INNER JOIN courses c ON c.course_id = e.course_id
            INNER JOIN users u ON u.user_id = e.user_id
            WHERE c.instructor_id = ?';

    if ($course_id !== null) {
        $sql .= ' AND c.course_id = ?';
    }
    if ($search !== '') {
        $sql .= ' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
    }
    $sql .= ' ORDER BY u.last_name, u.first_name, c.title';

    $stmt = $mysqli->prepare($sql);
    if ($course_id !== null && $search !== '') {
        $like = '%' . $search . '%';
        $stmt->bind_param('iisss', $instructor_id, $course_id, $like, $like, $like);
    } elseif ($course_id !== null) {
        $stmt->bind_param('ii', $instructor_id, $course_id);
    } elseif ($search !== '') {
        $like = '%' . $search . '%';
        $stmt->bind_param('isss', $instructor_id, $like, $like, $like);
    } else {
        $stmt->bind_param('i', $instructor_id);
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}
?>
