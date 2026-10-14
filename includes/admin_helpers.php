<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedin'], $_SESSION['user_role'], $_SESSION['user_id'])
    || $_SESSION['loggedin'] !== true
    || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../pages/login.php?error=' . urlencode('Please sign in with an administrator account.'));
    exit;
}

require_once __DIR__ . '/db_config.php';

if (!isset($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

function admin_csrf_token()
{
    return $_SESSION['admin_csrf_token'];
}

function admin_verify_csrf()
{
    return isset($_POST['csrf_token'], $_SESSION['admin_csrf_token'])
        && hash_equals($_SESSION['admin_csrf_token'], $_POST['csrf_token']);
}

function admin_flash($type, $message)
{
    $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
}

function admin_show_flash()
{
    if (empty($_SESSION['admin_flash'])) {
        return;
    }

    $flash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
    $type = $flash['type'] === 'success' ? 'success' : 'danger';
    echo '<div class="alert alert-' . $type . '" role="alert">'
        . htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8')
        . '</div>';
}
