<?php

function smartlearn_start_secure_session()
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $cookie = session_get_cookie_params();
    $secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    session_set_cookie_params([
        'lifetime' => $cookie['lifetime'],
        'path' => $cookie['path'],
        'domain' => $cookie['domain'],
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function smartlearn_complete_login($mysqli, array $user, $base_url = '../', $message = 'Signed in successfully.')
{
    smartlearn_start_secure_session();
    session_regenerate_id(true);

    $_SESSION['loggedin'] = true;
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_avatar'] = !empty($user['avatar_url'])
        ? $user['avatar_url']
        : 'assets/images/user_avatar_placeholder.png';
    $_SESSION['auth_success_message'] = $message;

    $user_id = (int) $user['user_id'];
    if ($update = $mysqli->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE user_id = ?')) {
        $update->bind_param('i', $user_id);
        $update->execute();
        $update->close();
    }

    if ($user['role'] === 'admin') {
        header('Location: ' . $base_url . 'admin/admin_dashboard.php');
    } elseif ($user['role'] === 'instructor') {
        header('Location: ' . $base_url . 'admin/instructor_dashboard.php');
    } else {
        header('Location: ' . $base_url . 'admin/student_dashboard.php');
    }
    exit;
}
