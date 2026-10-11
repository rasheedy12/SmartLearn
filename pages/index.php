<?php
define('BASE_URL', '../');

// session_start(); // Uncomment for session management

// Conceptual: Choose header based on login status
// if (isset($_SESSION['user_id'])) {
//    include '../includes/dashboard_header.php'; // Or a specific logged-in public header
// } else {
    include '../includes/header.php';
// }

include '../sections/hero.php';
include '../sections/how_it_works.php';
include '../sections/powerful_features.php';
include '../sections/built_for_everyone.php';
include '../sections/testimonials.php';
include '../sections/cta.php';

include '../includes/footer.php';
?>