<?php
// includes/sidebar.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$user_role_sidebar = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'student';
$current_page_sidebar = basename($_SERVER['PHP_SELF']);

$current_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/');
$current_dir = str_replace('\\', '/', $current_dir);
$is_admin_dir = $current_dir === '/admin' || $current_dir === 'admin';
$is_pages_dir = $current_dir === '/pages' || $current_dir === 'pages';
$base = $is_admin_dir ? './' : ($is_pages_dir ? '../admin/' : 'admin/');
$browse_courses_url = $is_admin_dir
    ? '../pages/browse_courses.php'
    : ($is_pages_dir ? 'browse_courses.php' : 'pages/browse_courses.php');
$logout_url = ($is_admin_dir || $is_pages_dir) ? '../auth/handle_logout.php' : 'auth/handle_logout.php';

$sidebar_links = [
    'common' => [
        ['label' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'link' => $base . $user_role_sidebar . '_dashboard.php'],
    ],
    'student' => [
        ['heading' => 'Learning'],
        ['label' => 'My Courses', 'icon' => 'fas fa-book', 'link' => $base . 'my_courses.php'],
        ['label' => 'Browse Courses', 'icon' => 'fas fa-search', 'link' => $browse_courses_url],
        ['label' => 'Assignments', 'icon' => 'fas fa-tasks', 'link' => $base . 'assignments.php'],
        ['label' => 'Study Buddies', 'icon' => 'fas fa-user-friends', 'link' => $base . 'study_buddy.php'],
        ['label' => 'My Progress', 'icon' => 'fas fa-chart-line', 'link' => $base . 'progress.php'],
    ],
    'instructor' => [
        ['heading' => 'Teaching'],
        ['label' => 'My Courses', 'icon' => 'fas fa-chalkboard-teacher', 'link' => $base . 'instructor_courses.php'],
        ['label' => 'Create Course', 'icon' => 'fas fa-plus-circle', 'link' => $base . 'create_course.php'],
        ['label' => 'Students', 'icon' => 'fas fa-users', 'link' => $base . 'students_list.php'],
        ['label' => 'Assignment Reviews', 'icon' => 'fas fa-clipboard-check', 'link' => $base . 'assignment_reviews.php'],
        ['label' => 'Analytics', 'icon' => 'fas fa-chart-pie', 'link' => $base . 'analytics.php'],
        ['label' => 'Announcements', 'icon' => 'fas fa-bullhorn', 'link' => $base . 'announcements.php'],
    ],
    'admin' => [
        ['heading' => 'Management'],
        ['label' => 'User Management', 'icon' => 'fas fa-users-cog', 'link' => $base . 'user_management.php'],
        ['label' => 'Course Management', 'icon' => 'fas fa-book-medical', 'link' => $base . 'course_management.php'],
        ['label' => 'System Settings', 'icon' => 'fas fa-cogs', 'link' => $base . 'system_settings.php'],
        ['label' => 'Platform Analytics', 'icon' => 'fas fa-chart-bar', 'link' => $base . 'reports.php'],
        ['label' => 'System Alerts', 'icon' => 'fas fa-exclamation-triangle', 'link' => $base . 'admin_dashboard.php#system-alerts'],
    ],
    'account_common' => [
        ['heading' => 'Account'],
        ['label' => 'Edit Profile', 'icon' => 'fas fa-user-edit', 'link' => $base . 'profile.php'],
        ['label' => 'Logout', 'icon' => 'fas fa-sign-out-alt', 'link' => $logout_url],
    ]
];

function render_sidebar_icon($icon_class) {
    $paths = [
        'fas fa-tachometer-alt' => '<path d="M4 19a9 9 0 1 1 16 0"/><path d="M12 13l4-4"/><path d="M7 19h10"/>',
        'fas fa-book' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v17H6.5A2.5 2.5 0 0 0 4 22z"/><path d="M4 5.5v14A2.5 2.5 0 0 1 6.5 17H20"/>',
        'fas fa-search' => '<circle cx="10.8" cy="10.8" r="6.3"/><path d="m16 16 4.5 4.5"/>',
        'fas fa-tasks' => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="m3.5 6 1.2 1.2L7 4.8M3.5 12l1.2 1.2L7 10.8M3.5 18l1.2 1.2L7 16.8"/>',
        'fas fa-user-friends' => '<circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1z"/><path d="M16 5.2a3 3 0 0 1 0 5.6M18 14a5 5 0 0 1 3 4.6V20h-3"/>',
        'fas fa-chart-line' => '<path d="M3 3v18h18"/><path d="m6 14 5-5 4 3 6-7"/><path d="M16 5h5v5"/>',
        'fas fa-chalkboard-teacher' => '<rect x="3" y="4" width="18" height="13" rx="1"/><path d="M8 21h8M12 17v4M8 10h8M8 13h5"/>',
        'fas fa-plus-circle' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'fas fa-users' => '<circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1z"/><path d="M16 5.2a3 3 0 0 1 0 5.6M18 14a5 5 0 0 1 3 4.6V20h-3"/>',
        'fas fa-clipboard-check' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2M8 12l2.5 2.5L16 9"/>',
        'fas fa-chart-pie' => '<path d="M11 3a9 9 0 1 0 10 10H11z"/><path d="M14 3.5V10h6.5A9 9 0 0 0 14 3.5z"/>',
        'fas fa-bullhorn' => '<path d="M3 11v2a2 2 0 0 0 2 2h2l10 5V4L7 9H5a2 2 0 0 0-2 2z"/><path d="m7 15 2 6h4l-2-4M20 9a5 5 0 0 1 0 6"/>',
        'fas fa-users-cog' => '<circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 10.5-4"/><circle cx="18" cy="17" r="2.5"/><path d="M18 13v1M18 20v1M14 17h1M21 17h1M15.2 14.2l.7.7M20.1 19.1l.7.7M20.8 14.2l-.7.7M15.9 19.1l-.7.7"/>',
        'fas fa-book-medical' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v17H6.5A2.5 2.5 0 0 0 4 22z"/><path d="M4 5.5v14A2.5 2.5 0 0 1 6.5 17H20M13 7v6M10 10h6"/>',
        'fas fa-cogs' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-1.8 3.1-.2-.1a1.7 1.7 0 0 0-1.8.1l-.2.1h-3.6l-.1-.2a1.7 1.7 0 0 0-1.5-1l-.2-.1-1.8-3.1.1-.2a1.7 1.7 0 0 0-.3-1.7l-.1-.2v-3.6l.2-.1a1.7 1.7 0 0 0 1-1.5l.1-.2 3.1-1.8.2.1a1.7 1.7 0 0 0 1.7-.3l.2-.1h3.6l.1.2a1.7 1.7 0 0 0 1.5 1l.2.1 1.8 3.1-.1.2a1.7 1.7 0 0 0 .3 1.7l.1.2v3.6l-.2.1a1.7 1.7 0 0 0-1 1.5"/>',
        'fas fa-chart-bar' => '<path d="M3 3v18h18"/><rect x="7" y="11" width="3" height="7"/><rect x="13" y="7" width="3" height="11"/><rect x="19" y="4" width="2" height="14"/>',
        'fas fa-exclamation-triangle' => '<path d="m12 3 10 18H2z"/><path d="M12 9v5M12 17h.01"/>',
        'fas fa-user-edit' => '<circle cx="10" cy="7" r="4"/><path d="M3 21v-2a7 7 0 0 1 11-5.7M15 19l5-5 2 2-5 5-3 1z"/>',
        'fas fa-sign-out-alt' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/>',
    ];
    $icon_paths = $paths[$icon_class] ?? '<circle cx="12" cy="12" r="8"/>';

    echo '<svg class="sidebar-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . $icon_paths . '</svg>';
}

function render_sidebar_links_recursive($links_array, $current_page_sidebar) {
    foreach ($links_array as $item) {
        if (isset($item['heading'])) {
            echo '<li class="sidebar-heading">' . htmlspecialchars($item['heading']) . '</li>';
        } else {
            $item_path = parse_url($item['link'], PHP_URL_PATH);
            $active_class = ($current_page_sidebar == basename($item_path ?: $item['link'])) ? 'active' : '';
            echo '<li class="nav-item">';
            echo '<a class="nav-link ' . $active_class . '" href="' . htmlspecialchars($item['link']) . '">';
            render_sidebar_icon($item['icon']);
            echo htmlspecialchars($item['label']);
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
