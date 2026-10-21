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

if (($_POST['action'] ?? '') === 'update_profile_picture') {
    $errors = [];
    $avatar = $_FILES['profile_avatar'] ?? null;

    if (
        !isset($_POST['csrf_token'], $_SESSION['profile_csrf_token'])
        || !hash_equals($_SESSION['profile_csrf_token'], (string) $_POST['csrf_token'])
    ) {
        $errors[] = 'Your request could not be verified. Refresh the page and try again.';
    }
    if (!$avatar || ($avatar['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Choose a profile picture before saving.';
    } elseif (($avatar['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_INI_SIZE
        || ($avatar['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_FORM_SIZE
        || ($avatar['size'] ?? 0) > 2 * 1024 * 1024
    ) {
        $errors[] = 'The profile picture must be no larger than 2 MB.';
    } elseif (($avatar['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !is_uploaded_file($avatar['tmp_name'] ?? '')
    ) {
        $errors[] = 'The profile picture could not be uploaded. Please try again.';
    }

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!$errors) {
        $file_info = new finfo(FILEINFO_MIME_TYPE);
        $detected_mime = $file_info->file($avatar['tmp_name']);
        $image_info = @getimagesize($avatar['tmp_name']);
        if (
            !isset($extensions[$detected_mime])
            || $image_info === false
            || ($image_info['mime'] ?? '') !== $detected_mime
        ) {
            $errors[] = 'Choose a valid JPEG, PNG, or WebP image.';
        }
    }

    $upload_directory = dirname(__DIR__) . '/uploads/avatars';
    $uploaded_path = null;
    $avatar_path = null;
    $previous_avatar_path = null;

    if (!$errors) {
        if (!is_dir($upload_directory) && !mkdir($upload_directory, 0755, true) && !is_dir($upload_directory)) {
            $errors[] = 'Profile picture storage is unavailable. Please try again later.';
        } else {
            try {
                $account_stmt = $mysqli->prepare('SELECT avatar_url FROM users WHERE user_id = ? LIMIT 1');
                $account_stmt->bind_param('i', $user_id);
                $account_stmt->execute();
                $account = $account_stmt->get_result()->fetch_assoc();
                $account_stmt->close();

                if (!$account) {
                    $errors[] = 'Your account could not be found. Please sign in again.';
                } else {
                    $previous_avatar_path = (string) ($account['avatar_url'] ?? '');
                    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$detected_mime];
                    $uploaded_path = $upload_directory . '/' . $filename;
                    $avatar_path = 'uploads/avatars/' . $filename;

                    if (!move_uploaded_file($avatar['tmp_name'], $uploaded_path)) {
                        $errors[] = 'The profile picture could not be saved. Please try again.';
                        $uploaded_path = null;
                        $avatar_path = null;
                    } elseif (!chmod($uploaded_path, 0644)) {
                        if (!unlink($uploaded_path)) {
                            error_log('Unable to remove a profile picture upload with unsafe permissions.');
                        }
                        $errors[] = 'The profile picture could not be saved. Please try again.';
                        $uploaded_path = null;
                        $avatar_path = null;
                    } else {
                        $update = $mysqli->prepare('UPDATE users SET avatar_url = ?, updated_at = NOW() WHERE user_id = ?');
                        $update->bind_param('si', $avatar_path, $user_id);
                        $update->execute();
                        if ($update->affected_rows !== 1) {
                            throw new RuntimeException('Profile picture update did not affect the authenticated account.');
                        }
                        $update->close();
                    }
                }
            } catch (Throwable $exception) {
                error_log('Profile picture update failed: ' . $exception->getMessage());
                $errors[] = 'Your profile picture could not be updated. Please try again.';
            }
        }
    }

    if ($errors) {
        if ($uploaded_path !== null && is_file($uploaded_path) && !unlink($uploaded_path)) {
            error_log('Unable to remove an uncommitted profile picture upload.');
        }
        $_SESSION['error_message'] = implode(' ', array_unique($errors));
    } else {
        $_SESSION['user_avatar'] = $avatar_path;
        $_SESSION['success_message'] = 'Profile picture updated successfully.';

        if (preg_match('~\Auploads/avatars/[a-f0-9]{32}\.(?:jpg|png|webp)\z~D', $previous_avatar_path)) {
            $previous_file = dirname(__DIR__) . '/' . $previous_avatar_path;
            $managed_directory = realpath($upload_directory);
            if (
                $managed_directory !== false
                && dirname($previous_file) === $managed_directory
                && is_file($previous_file)
                && !unlink($previous_file)
            ) {
                error_log('Unable to remove a replaced managed profile picture.');
            }
        }
    }

    header('Location: ' . $profile_url, true, 303);
    exit;
}

$first_name = trim((string) ($_POST['first_name'] ?? ''));
$last_name = trim((string) ($_POST['last_name'] ?? ''));
$bio = trim((string) ($_POST['bio'] ?? ''));
$current_password = (string) ($_POST['current_password'] ?? '');
$new_password = (string) ($_POST['new_password'] ?? '');
$confirm_new_password = (string) ($_POST['confirm_new_password'] ?? '');
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
                 password_hash = COALESCE(?, password_hash),
                 updated_at = NOW()
             WHERE user_id = ?'
        );
        $update->bind_param('ssssi', $first_name, $last_name, $bio, $password_hash, $user_id);
        $update->execute();
        $update->close();

        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
        unset($_SESSION['profile_form_data']);
        $_SESSION['success_message'] = 'Your profile has been updated successfully.';
        header('Location: ' . $profile_url, true, 303);
        exit;
    } catch (Throwable $exception) {
        error_log('Profile update failed: ' . $exception->getMessage());
        $errors[] = 'Your profile could not be updated. Please try again.';
    }
}

$_SESSION['error_message'] = implode(' ', $errors);
header('Location: ' . $profile_url, true, 303);
exit;
