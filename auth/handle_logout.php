<?php
require_once __DIR__ . '/../includes/auth_session.php';
smartlearn_start_secure_session();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Logout must be submitted from the dashboard.');
}

if (!isset($_POST['csrf_token'], $_SESSION['logout_csrf_token'])
    || !hash_equals($_SESSION['logout_csrf_token'], (string) $_POST['csrf_token'])) {
    http_response_code(403);
    exit('Logout request could not be verified.');
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '../'); // Goes up one level to the project root
}

// Unset all of the session variables.
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Start a fresh anonymous session for a one-time logout notice.
smartlearn_start_secure_session();
session_regenerate_id(true);
$_SESSION['logout_success_message'] = 'You have been logged out.';

// Finish the POST with an immediate GET so refresh/back cannot resubmit logout.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Location: ' . BASE_URL . 'pages/login.php', true, 303);
exit;
?>
