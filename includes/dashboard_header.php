<?php
// includes/dashboard_header.php
if (session_status() == PHP_SESSION_NONE) { // Start session if not already started
    session_start();
}

define('BASE_URL_INC', file_exists(dirname(__FILE__) . '/../index.php') ? '../' : './'); // Determine the correct project root from the current include location

// Use session data if available, otherwise mock data for direct testing
$user_name = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Guest User';
$user_avatar_path = trim((string) ($_SESSION['user_avatar'] ?? ''));
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'student'; // Default to student for testing

if (
    preg_match('~\Ahttps?://~i', $user_avatar_path)
    && filter_var($user_avatar_path, FILTER_VALIDATE_URL)
) {
    $user_avatar = $user_avatar_path;
} else {
    $avatar_relative_path = preg_replace('~^(?:\.\./)+~', '', $user_avatar_path);
    $avatar_relative_path = ltrim((string) $avatar_relative_path, '/');
    $avatar_file_path = dirname(__DIR__) . '/' . $avatar_relative_path;
    if (
        $avatar_relative_path !== ''
        && !preg_match('~(^|/)\.\.(?:/|$)~', $avatar_relative_path)
        && is_file($avatar_file_path)
    ) {
        $user_avatar = BASE_URL_INC . $avatar_relative_path;
    } else {
        $user_avatar = BASE_URL_INC . 'assets/images/user_avatar_placeholder.png';
    }
}

$current_page_for_title = basename($_SERVER['PHP_SELF']);
$page_title_auto = isset($page_title) ? htmlspecialchars($page_title) : ucfirst(str_replace(['_','.php'], [' ',''], $current_page_for_title));

$current_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/');
$current_dir = str_replace('\\', '/', $current_dir);
$is_admin_dir = $current_dir === '/admin' || $current_dir === 'admin';
$is_pages_dir = $current_dir === '/pages' || $current_dir === 'pages';
$profile_url = $is_admin_dir ? 'profile.php' : ($is_pages_dir ? '../admin/profile.php' : 'admin/profile.php');
$logout_url = ($is_admin_dir || $is_pages_dir) ? '../auth/handle_logout.php' : 'auth/handle_logout.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title_auto; ?> - SmartLearn Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL_INC; ?>assets/style.css">
    <?php if (isset($extra_css) && is_array($extra_css)): foreach ($extra_css as $css_file): ?>
        <link rel="stylesheet" href="<?php echo $css_file; ?>">
    <?php endforeach; endif; ?>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'sidebar.php'; // Include the sidebar ?>
        <div class="main-content">
            <nav class="navbar navbar-expand-lg navbar-light dashboard-header-custom">
                <div class="container-fluid">
                    <button class="btn btn-light d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenuOffcanvas" aria-controls="sidebarMenuOffcanvas">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <h5 class="d-none d-lg-block mb-0 me-auto"><?php echo $page_title_auto; ?></h5>


                    <form class="d-none d-sm-flex ms-auto ms-lg-0 me-lg-3 my-2 my-lg-0" style="max-width: 350px; min-width:200px;">
                        <input class="form-control form-control-sm" type="search" placeholder="Search courses, lessons..." aria-label="Search">
                    </form>

                    <ul class="navbar-nav align-items-center">
                        <li class="nav-item">
                            <a class="nav-link" href="#" title="Notifications">
                                <i class="fas fa-bell fs-5"></i>
                                <span class="badge rounded-pill bg-danger" style="position: relative; top: -10px; left: -5px; font-size: 0.65em;">3</span>
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userActionsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="d-none d-sm-inline me-2"><?php echo $user_name; ?></span>
                                <img src="<?php echo htmlspecialchars($user_avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $user_name; ?>" class="user-avatar" onerror="this.onerror=null; this.src='<?php echo BASE_URL_INC; ?>assets/images/user_avatar_placeholder.png';">
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userActionsDropdown">
                                <li><a class="dropdown-item" href="<?php echo $profile_url; ?>"><i class="fas fa-user-circle fa-fw me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="#"><i class="fas fa-cog fa-fw me-2"></i>Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="<?php echo htmlspecialchars($logout_url, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="m-0">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['logout_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="dropdown-item"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Logout</button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>
            <main class="page-content">
            <?php if (!empty($_SESSION['auth_success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="status">
                    <?php echo htmlspecialchars((string) $_SESSION['auth_success_message'], ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['auth_success_message']); ?>
            <?php endif; ?>
            <?php // Main page content starts here and ends in dashboard_footer.php ?>
