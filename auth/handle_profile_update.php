<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '../');
}

$profile_url = BASE_URL . 'admin/profile.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . $profile_url, true, 303);
    exit;
}

if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'pages/login.php');
    exit;
}

require_once BASE_URL . 'includes/db_config.php';

$user_id = (int) $_SESSION['user_id'];
$first_name = trim((string) ($_POST['first_name'] ?? ''));
$last_name = trim((string) ($_POST['last_name'] ?? ''));
$bio = trim((string) ($_POST['bio'] ?? ''));
$current_password = (string) ($_POST['current_password'] ?? '');
$new_password = (string) ($_POST['new_password'] ?? '');
$confirm_new_password = (string) ($_POST['confirm_new_password'] ?? '');
$avatar_path = null;
$uploaded_path = null;
$errors = [];

$_SESSION['profile_form_data'] = [
    'first_name' => $first_name,
    'last_name' => $last_name,
    'bio' => $bio,
];

$first_name_length = preg_match_all('/./us', $first_name);
$last_name_length = preg_match_all('/./us', $last_name);
$bio_length = preg_match_all('/./us', $bio);

if (
    !isset($_POST['csrf_token'], $_SESSION['profile_csrf_token'])
    || !hash_equals($_SESSION['profile_csrf_token'], (string) $_POST['csrf_token'])
) {
    $errors[] = 'Your request could not be verified. Refresh the page and try again.';
}
if ($first_name === '' || $first_name_length === false || $first_name_length > 50) {
    $errors[] = 'First name is required and must be 50 characters or fewer.';
}
if ($last_name === '' || $last_name_length === false || $last_name_length > 50) {
    $errors[] = 'Last name is required and must be 50 characters or fewer.';
}
if ($bio_length === false || $bio_length > 2000) {
    $errors[] = 'Your bio must be 2,000 characters or fewer.';
}

$change_password = $current_password !== '' || $new_password !== '' || $confirm_new_password !== '';
$password_hash = null;
if ($change_password) {
    if ($current_password === '' || $new_password === '' || $confirm_new_password === '') {
        $errors[] = 'Enter your current password and complete both new password fields.';
    } elseif (strlen($new_password) < 8) {
        $errors[] = 'The new password must be at least 8 characters long.';
    } elseif (!hash_equals($new_password, $confirm_new_password)) {
        $errors[] = 'The new password and confirmation do not match.';
    }
}

$avatar = $_FILES['profile_avatar'] ?? null;
if ($avatar && ($avatar['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    if (($avatar['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'The profile picture could not be uploaded. Please try again.';
    } elseif (($avatar['size'] ?? 0) > 2 * 1024 * 1024) {
        $errors[] = 'The profile picture must be no larger than 2 MB.';
    } else {
        $file_info = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $file_info->file($avatar['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        if (!isset($extensions[$mime_type])) {
            $errors[] = 'The profile picture must be a PNG or JPEG image.';
        } elseif (!is_uploaded_file($avatar['tmp_name'])) {
            $errors[] = 'The uploaded profile picture is invalid.';
        } else {
            $upload_directory = dirname(__DIR__) . '/uploads/avatars';
            if (!is_dir($upload_directory) && !mkdir($upload_directory, 0755, true) && !is_dir($upload_directory)) {
                $errors[] = 'Profile picture storage is unavailable. Please try again later.';
            } else {
                $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime_type];
                $uploaded_path = $upload_directory . '/' . $filename;
                $avatar_path = 'uploads/avatars/' . $filename;
                if (!move_uploaded_file($avatar['tmp_name'], $uploaded_path)) {
                    $errors[] = 'The profile picture could not be saved. Please try again.';
                    $uploaded_path = null;
                    $avatar_path = null;
                }
            }
        }
    }
}

if (!$errors) {
    try {
        $account_stmt = $mysqli->prepare('SELECT password_hash FROM users WHERE user_id = ? LIMIT 1');
        $account_stmt->bind_param('i', $user_id);
        $account_stmt->execute();
        $account = $account_stmt->get_result()->fetch_assoc();
        $account_stmt->close();
        if (!$account) {
            $errors[] = 'Your account could not be found. Please sign in again.';
        } elseif ($change_password && !password_verify($current_password, $account['password_hash'])) {
            $errors[] = 'Your current password is incorrect.';
        } elseif ($change_password) {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        }
    } catch (Throwable $exception) {
        error_log('Profile account validation failed: ' . $exception->getMessage());
        $errors[] = 'Your profile could not be verified. Please try again.';
    }
}

if (!$errors) {
    try {
        $update = $mysqli->prepare(
            'UPDATE users
             SET first_name = ?, last_name = ?, bio = ?,
                 avatar_url = COALESCE(?, avatar_url),
                 password_hash = COALESCE(?, password_hash),
                 updated_at = NOW()
             WHERE user_id = ?'
        );
        $update->bind_param('sssssi', $first_name, $last_name, $bio, $avatar_path, $password_hash, $user_id);
        $update->execute();
        $update->close();

        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
        if ($avatar_path !== null) {
            $_SESSION['user_avatar'] = BASE_URL . $avatar_path;
        }
        unset($_SESSION['profile_form_data']);
        $_SESSION['success_message'] = 'Your profile has been updated successfully.';
        header('Location: ' . $profile_url, true, 303);
        exit;
    } catch (Throwable $exception) {
        error_log('Profile update failed: ' . $exception->getMessage());
        $errors[] = 'Your profile could not be updated. Please try again.';
    }
}

if ($uploaded_path !== null && is_file($uploaded_path)) {
    unlink($uploaded_path);
}
$_SESSION['error_message'] = implode(' ', $errors);
header('Location: ' . $profile_url, true, 303);
exit;
