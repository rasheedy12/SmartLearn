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
