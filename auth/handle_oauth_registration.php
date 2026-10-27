<?php
require_once __DIR__ . '/../includes/oauth_helpers.php';
smartlearn_start_secure_session();
require_once __DIR__ . '/../includes/db_config.php';

$registration = $_SESSION['smartlearn_oauth_registration'] ?? null;
$complete_url = '../pages/oauth_complete.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_array($registration)
    || !isset($_POST['csrf_token'], $registration['csrf_token'])
    || !hash_equals((string) $registration['csrf_token'], (string) $_POST['csrf_token'])
    || time() - (int) ($registration['started_at'] ?? 0) > 900) {
    header('Location: ../pages/login.php?error=' . urlencode('Your sign-in session expired or could not be verified. Please try again.'));
    exit;
}

$first_name = trim((string) ($_POST['first_name'] ?? ''));
$last_name = trim((string) ($_POST['last_name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$role = (string) ($_POST['role'] ?? '');
if ($registration['provider'] === 'google' && !empty($registration['email_verified'])) {
    if (strcasecmp($email, (string) $registration['email']) !== 0) {
        header('Location: ' . $complete_url . '?error=' . urlencode('Use the Google verified email shown on this page.'));
        exit;
    }
    $email = (string) $registration['email'];
}
$email = strtolower($email);
$errors = [];
$first_name_length = function_exists('mb_strlen') ? mb_strlen($first_name, 'UTF-8') : strlen($first_name);
$last_name_length = function_exists('mb_strlen') ? mb_strlen($last_name, 'UTF-8') : strlen($last_name);
if ($first_name === '' || $first_name_length > 50) {
    $errors[] = 'Enter a first name up to 50 characters.';
}
if ($last_name === '' || $last_name_length > 50) {
    $errors[] = 'Enter a last name up to 50 characters.';
}
if (strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errors[] = 'Enter a valid email address.';
}
if (!in_array($role, ['student', 'instructor'], true)) {
    $errors[] = 'Choose a valid account type.';
}
if (empty($_POST['agree_terms'])) {
    $errors[] = 'You must agree to the Terms of Service and Privacy Policy.';
}
if ($errors) {
    header('Location: ' . $complete_url . '?error=' . urlencode(implode(' ', $errors)));
    exit;
}

$existing_user = smartlearn_oauth_find_user_by_email($mysqli, $email);
if ($existing_user) {
    if ($registration['provider'] === 'google' && !empty($registration['email_verified'])
        && smartlearn_oauth_user_can_sign_in($existing_user)) {
        $mysqli->begin_transaction();
        try {
            smartlearn_oauth_insert_identity(
                $mysqli,
                $existing_user['user_id'],
                $registration['provider'],
                $registration['issuer'],
                $registration['subject']
            );
            $mysqli->commit();
        } catch (Throwable $exception) {
            $mysqli->rollback();
            error_log('OAuth account linking failed: ' . $exception->getMessage());
            header('Location: ' . $complete_url . '?error=' . urlencode('This provider could not be connected. Sign in to your existing account and connect it from your profile.'));
            exit;
        }
        unset($_SESSION['smartlearn_oauth_registration']);
        smartlearn_oauth_login(
            $mysqli,
            $existing_user,
            $registration['provider'],
            $registration['issuer'],
            $registration['subject']
        );
    }
    header('Location: ' . $complete_url . '?error=' . urlencode('This email already has a SmartLearn account. Sign in to that account, then connect this provider from your profile.'));
    exit;
}

$password_hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
$status = $role === 'instructor' ? 'pending_approval' : 'active';
$verified_at = $registration['provider'] === 'google' && !empty($registration['email_verified'])
    ? date('Y-m-d H:i:s')
    : null;
$mysqli->begin_transaction();
try {
    $insert_user = $mysqli->prepare(
        'INSERT INTO users (first_name, last_name, email, password_hash, role, status, email_verified_at) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$insert_user) {
        throw new RuntimeException('Could not create account.');
    }
    $insert_user->bind_param('sssssss', $first_name, $last_name, $email, $password_hash, $role, $status, $verified_at);
    if (!$insert_user->execute()) {
        $insert_user->close();
        throw new RuntimeException('Could not create account.');
    }
    $user_id = (int) $insert_user->insert_id;
    $insert_user->close();
    smartlearn_oauth_insert_identity(
        $mysqli,
        $user_id,
        $registration['provider'],
        $registration['issuer'],
        $registration['subject']
    );
    $mysqli->commit();
} catch (Throwable $exception) {
    $mysqli->rollback();
    error_log('OAuth account creation failed: ' . $exception->getMessage());
    header('Location: ' . $complete_url . '?error=' . urlencode('Could not finish creating your account. Try again or sign in to an existing account.'));
    exit;
}

unset($_SESSION['smartlearn_oauth_registration']);
if ($role === 'instructor') {
    session_regenerate_id(true);
    header('Location: ../pages/login.php?success=' . urlencode('Account created. Instructor access will be available after approval.'));
    exit;
}
$new_user = [
    'user_id' => $user_id,
    'first_name' => $first_name,
    'last_name' => $last_name,
    'email' => $email,
    'role' => $role,
    'avatar_url' => null,
    'status' => $status,
];
smartlearn_oauth_login(
    $mysqli,
    $new_user,
    $registration['provider'],
    $registration['issuer'],
    $registration['subject'],
    '../'
);
