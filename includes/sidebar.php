<?php
// includes/sidebar.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$user_role_sidebar = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'student';
$current_page_sidebar = basename($_SERVER['PHP_SELF']);

// Define correct base paths for admin pages.
$current_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/');
$current_dir = str_replace('\\', '/', $current_dir);
$base = ($current_dir === '/admin' || $current_dir === 'admin') ? './' : '../';
$logout_url = ($current_dir === '/admin' || $current_dir === 'admin') ? '../auth/handle_logout.php' : 'auth/handle_logout.php';

$sidebar_links = [
    'common' => [
        ['label' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'link' => $base . $user_role_sidebar . '_dashboard.php'],
    ],
    'student' => [
        ['heading' => 'Learning'],
        ['label' => 'My Courses', 'icon' => 'fas fa-book', 'link' => $base . 'my_courses.php'],
        ['label' => 'Browse Courses', 'icon' => 'fas fa-search', 'link' => $base . 'browse_courses.php'],
        ['label' => 'Assignments', 'icon' => 'fas fa-tasks', 'link' => $base . 'assignments.php'],
        ['label' => 'Study Buddies', 'icon' => 'fas fa-user-friends', 'link' => $base . 'study_buddy.php'],
        ['label' => 'My Progress', 'icon' => 'fas fa-chart-line', 'link' => $base . 'progress.php'],
    ],
    'instructor' => [
        ['heading' => 'Teaching'],
        ['label' => 'My Courses', 'icon' => 'fas fa-chalkboard-teacher', 'link' => $base . 'instructor_courses.php'],
        ['label' => 'Create Course', 'icon' => 'fas fa-plus-circle', 'link' => $base . 'create_course.php'],
        ['label' => 'Students', 'icon' => 'fas fa-users', 'link' => $base . 'students_list.php'],
        ['label' => 'Analytics', 'icon' => 'fas fa-chart-pie', 'link' => $base . 'analytics.php'],
        ['label' => 'Announcements', 'icon' => 'fas fa-bullhorn', 'link' => $base . 'announcements.php'],
    ],
    'admin' => [
        ['heading' => 'Management'],
        ['label' => 'User Management', 'icon' => 'fas fa-users-cog', 'link' => $base . 'user_management.php'],
        ['label' => 'Course Management', 'icon' => 'fas fa-book-medical', 'link' => $base . 'course_management.php'],
        ['label' => 'System Settings', 'icon' => 'fas fa-cogs', 'link' => $base . 'system_settings.php'],
        ['label' => 'Platform Analytics', 'icon' => 'fas fa-chart-bar', 'link' => $base . 'reports.php'],
        ['label' => 'System Alerts', 'icon' => 'fas fa-exclamation-triangle', 'link' => '#'],
    ],
    'account_common' => [
        ['heading' => 'Account'],
        ['label' => 'Edit Profile', 'icon' => 'fas fa-user-edit', 'link' => $base . 'profile.php'],
        ['label' => 'Logout', 'icon' => 'fas fa-sign-out-alt', 'link' => $logout_url],
    ]
];

function render_sidebar_links_recursive($links_array, $current_page_sidebar) {
    foreach ($links_array as $item) {
        if (isset($item['heading'])) {
            echo '<li class="sidebar-heading">' . htmlspecialchars($item['heading']) . '</li>';
        } else {
            $active_class = ($current_page_sidebar == basename($item['link'])) ? 'active' : '';
            echo '<li class="nav-item">';
            echo '<a class="nav-link ' . $active_class . '" href="' . htmlspecialchars($item['link']) . '">';
            echo '<i class="' . htmlspecialchars($item['icon']) . ' fa-fw"></i>' . htmlspecialchars($item['label']);
            echo '</a>';
            echo '</li>';
        }
    }
}
?>

<div class="offcanvas offcanvas-start bg-dark text-white d-lg-none" tabindex="-1" id="sidebarMenuOffcanvas" aria-labelledby="sidebarMenuOffcanvasLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="sidebarMenuOffcanvasLabel">
        <a class="sidebar-brand" href="<?php echo $base . $user_role_sidebar; ?>_dashboard.php" style="color:white; text-decoration:none;">
            <span class="logo-abbr">SL</span> SmartLearn
        </a>
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <ul class="nav flex-column">
        <?php
        render_sidebar_links_recursive($sidebar_links['common'], $current_page_sidebar);
        if (isset($sidebar_links[$user_role_sidebar])) {
            render_sidebar_links_recursive($sidebar_links[$user_role_sidebar], $current_page_sidebar);
        }
        render_sidebar_links_recursive($sidebar_links['account_common'], $current_page_sidebar);
        ?>
    </ul>
  </div>
</div>

<nav id="sidebarMenu" class="sidebar d-none d-lg-block">
    <a class="sidebar-brand" href="<?php echo $base . $user_role_sidebar; ?>_dashboard.php">
        <span class="logo-abbr">SL</span> SmartLearn
    </a>

    <ul class="nav flex-column">
        <?php
        render_sidebar_links_recursive($sidebar_links['common'], $current_page_sidebar);
        if (isset($sidebar_links[$user_role_sidebar])) {
            render_sidebar_links_recursive($sidebar_links[$user_role_sidebar], $current_page_sidebar);
        }
        render_sidebar_links_recursive($sidebar_links['account_common'], $current_page_sidebar);
        ?>
    </ul>
</nav>

