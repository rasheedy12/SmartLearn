<?php
require_once __DIR__ . '/../includes/auth_session.php';
smartlearn_start_secure_session();

if (!defined('BASE_URL')) {
    define('BASE_URL', '../');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!isset($_POST['csrf_token'], $_SESSION['login_csrf_token'])
        || !is_string($_POST['csrf_token'])
        || !hash_equals($_SESSION['login_csrf_token'], $_POST['csrf_token'])) {
        header('Location: ' . BASE_URL . 'pages/login.php?error=' . urlencode('Your sign-in request could not be verified. Refresh the page and try again.'));
        exit;
    }

    require_once BASE_URL . 'includes/db_config.php';

    $email_input = $_POST['email'] ?? '';
    $password_input = $_POST['password'] ?? '';
    $email = is_string($email_input) ? trim($email_input) : '';
    $password_attempt = is_string($password_input) ? $password_input : '';

    if (empty($email) || empty($password_attempt)) {
        header('Location: ' . BASE_URL . 'pages/login.php?error=' . urlencode('Email and password are required.'));
        exit;
    }

    $sql = 'SELECT user_id, first_name, last_name, email, password_hash, role, avatar_url, status FROM users WHERE email = ?';
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param('s', $param_email);
        $param_email = $email;

        if ($stmt->execute()) {
            $stmt->store_result();
            if ($stmt->num_rows === 1) {
                $stmt->bind_result($user_id, $first_name, $last_name, $db_email, $hashed_password, $role, $avatar_url, $status);
                if ($stmt->fetch()) {
                    if ($status === 'suspended' || $status === 'deleted') {
                        header('Location: ' . BASE_URL . 'pages/login.php?error=' . urlencode('Your account is ' . $status . '. Please contact support.'));
                        exit;
                    }
                    if ($status === 'pending_approval' && $role !== 'student') {
                        header('Location: ' . BASE_URL . 'pages/login.php?error=' . urlencode('Your account is pending approval. Please wait or contact support.'));
                        exit;
                    }

                    if (password_verify($password_attempt, $hashed_password)) {
                        smartlearn_complete_login($mysqli, [
                            'user_id' => $user_id,
                            'first_name' => $first_name,
                            'last_name' => $last_name,
                            'email' => $db_email,
                            'role' => $role,
                            'avatar_url' => $avatar_url,
                            'status' => $status,
                        ], BASE_URL, 'Signed in successfully.');
                    }

                    header('Location: ' . BASE_URL . 'pages/login.php?error=' . urlencode('Invalid email or password.'));
                    exit;
                }
            } else {
                header('Location: ' . BASE_URL . 'pages/login.php?error=' . urlencode('Invalid email or password.'));
                exit;
            }
        } else {
            error_log('Auth Error: Statement execution failed. ' . $stmt->error);
            header('Location: ' . BASE_URL . 'pages/login.php?error=' . urlencode('An error occurred. Please try again.'));
            exit;
        }

        $stmt->close();
    } else {
        error_log('Auth Error: Statement preparation failed. ' . $mysqli->error);
        header('Location: ' . BASE_URL . 'pages/login.php?error=' . urlencode('An error occurred. Please try again.'));
        exit;
    }

    $mysqli->close();
} else {
    header('Location: ' . BASE_URL . 'pages/login.php');
    exit;
}
