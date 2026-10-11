
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - SmartLearn' : 'SmartLearn - Stay Engaged, Learn Smarter'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/style.css">
</head>
<body>
    <?php
    $current_page = basename($_SERVER['PHP_SELF']);
    $is_auth_page = in_array($current_page, ['login.php', 'register.php']);
    ?>

    <?php if (!$is_auth_page): ?>
    <nav class="navbar navbar-expand-lg navbar-light bg-light sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand navbar-brand-custom" href="<?php echo BASE_URL; ?>index.php">
                 <i class="fas fa-graduation-cap me-2 text-purple"></i>SmartLearn
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>index.php#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>browse_courses.php">Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>index.php#testimonials">Testimonials</a>
                    </li>
                    <?php
                    // Conceptual: Check if user is logged in (replace with actual session check)
                    // if (isset($_SESSION['user_id'])) {
                    //     $dashboard_link = BASE_URL . htmlspecialchars($_SESSION['user_role']) . '_dashboard.php';
                    //     echo '<li class="nav-item"><a class="nav-link" href="' . $dashboard_link . '">My Dashboard</a></li>';
                    //     echo '<li class="nav-item"><a class="nav-link btn btn-sm btn-outline-secondary ms-2" href="' . BASE_URL . 'auth/handle_logout.php">Logout</a></li>';
                    // } else {
                        echo '<li class="nav-item"><a class="nav-link" href="' . BASE_URL . 'login.php">Login</a></li>';
                        echo '<li class="nav-item ms-lg-2"><a class="nav-link btn btn-custom-primary btn-sm px-3 text-white" href="' . BASE_URL . 'register.php">Sign Up</a></li>';
                    // }
                    ?>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>
