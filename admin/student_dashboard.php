<?php
define('BASE_URL', '../');
$page_title = "Student Dashboard";
// $use_charts = true; // Example: if this page needs charts
include '../includes/dashboard_header.php';
?>

<div class="container-fluid">
    <h1 class="h2 mb-4">Your Progress</h1>

    <div class="row">
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="stat-card text-primary h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <h6>Experience Points</h6>
                    <i class="fas fa-star icon-lg text-primary"></i>
                </div>
                <div class="stat-value">2,450 <small class="fs-6 text-muted">XP</small></div>
                <small class="stat-subtext">Level 8 (550 XP to Level 9)</small>
                <div class="progress mt-2">
                    <div class="progress-bar" role="progressbar" style="width: 70%;" aria-valuenow="70" aria-valuemin="0" aria-valuemax="100">70%</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="stat-card text-success h-100">
                 <div class="d-flex justify-content-between align-items-center">
                    <h6>Lessons Completed</h6>
                    <i class="fas fa-check-circle icon-lg text-success"></i>
                </div>
                <div class="stat-value">47/60</div>
                <small class="stat-subtext">78% Complete</small>
                 <div class="progress mt-2">
                    <div class="progress-bar bg-success" role="progressbar" style="width: 78%;" aria-valuenow="78" aria-valuemin="0" aria-valuemax="100">78%</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-12 mb-4">
            <div class="stat-card text-danger h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <h6>Study Streak</h6>
                    <i class="fas fa-fire icon-lg text-danger"></i>
                </div>
                <div class="stat-value">12 <small class="fs-6 text-muted">days</small></div>
                <small class="stat-subtext">Keep it up! You're on fire 🔥</small>
                 <div class="progress mt-2">
                    <div class="progress-bar bg-danger" role="progressbar" style="width: 80%;" aria-valuenow="80" aria-valuemin="0" aria-valuemax="100">80%</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-0 mt-md-3">
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h3 class="h5 mb-0">Active Courses</h3>
                </div>
                <div class="card-body">
                    <?php
                    $active_courses = [
                        ["abbr" => "JS", "title" => "JavaScript Fundamentals", "desc" => "Learn the fundamentals of JavaScript programming", "module" => "Module 4 of 6", "progress" => 65, "next" => "Functions & Scope", "bg_color" => "bg-primary", "link" => BASE_URL . "course_detail.php?id=1"], // Example link
                        ["abbr" => "UX", "title" => "UX Design Principles", "desc" => "Master the principles of user experience design", "module" => "Module 2 of 5", "progress" => 32, "next" => "User Research", "bg_color" => "bg-success", "link" => BASE_URL . "course_detail.php?id=2"],
                    ];
                    ?>
                    <?php if (empty($active_courses)): ?>
                        <p class="text-muted text-center">No active courses at the moment. <a href="<?php echo BASE_URL; ?>browse_courses.php">Browse courses</a> to get started!</p>
                    <?php else: ?>
                        <?php foreach ($active_courses as $course): ?>
                        <a href="<?php echo htmlspecialchars($course['link']); ?>" class="text-decoration-none text-dark">
                            <div class="course-card-sm mb-3">
                                <div class="course-icon <?php echo htmlspecialchars($course['bg_color']); ?>">
                                    <?php echo htmlspecialchars($course['abbr']); ?>
                                </div>
                                <div class="course-info flex-grow-1">
                                    <h6><?php echo htmlspecialchars($course['title']); ?></h6>
                                    <p class="mb-1"><?php echo htmlspecialchars($course['desc']); ?> - <small><?php echo htmlspecialchars($course['module']); ?></small></p>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar <?php echo htmlspecialchars($course['bg_color']); ?>" role="progressbar" style="width: <?php echo $course['progress']; ?>%;" aria-valuenow="<?php echo $course['progress']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-muted d-block mt-1">Next: <?php echo htmlspecialchars($course['next']); ?></small>
                                </div>
                                <div class="course-progress-text ms-3">
                                    <?php echo $course['progress']; ?>%
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white text-center">
                     <a href="<?php echo BASE_URL; ?>my_courses.php" class="btn btn-outline-primary btn-sm">View All My Courses</a>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm mb-4 h-100">
                <div class="card-header bg-white">
                    <h3 class="h5 mb-0">Upcoming Tasks</h3>
                </div>
                <div class="card-body">
                    <?php
                    $upcoming_tasks = [
                        ["icon" => "fas fa-file-alt", "bg" => "bg-warning text-dark", "title" => "JavaScript Quiz 4", "due" => "Due tomorrow", "link" => "#"],
                        ["icon" => "fas fa-book-open", "bg" => "bg-info", "title" => "Read Chapter 5 (UX)", "due" => "Due in 3 days", "link" => "#"],
                    ];
                    ?>
                    <?php if (empty($upcoming_tasks)): ?>
                        <p class="text-muted text-center my-3">No upcoming tasks. Great job!</p>
                    <?php else: ?>
                        <?php foreach ($upcoming_tasks as $task): ?>
                        <a href="<?php echo htmlspecialchars($task['link']); ?>" class="text-decoration-none text-dark">
                            <div class="task-item">
                                <div class="icon-circle <?php echo htmlspecialchars($task['bg']); ?>">
                                    <i class="<?php echo htmlspecialchars($task['icon']); ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0 small fw-bold"><?php echo htmlspecialchars($task['title']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($task['due']); ?></small>
                                </div>
                                <i class="fas fa-chevron-right text-muted small"></i>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                 <div class="card-footer bg-white text-center">
                     <a href="<?php echo BASE_URL; ?>assignments.php" class="btn btn-outline-secondary btn-sm">View All Tasks</a>
                </div>
                <div class="card shadow-sm h-100"> 
                 <div class="card-header bg-white">
                    <h3 class="h5 mb-0">Recent Achievements</h3>
                </div>
                <div class="card-body">
                    <?php
                    $achievements = [
                        ["icon" => "fas fa-trophy", "bg" => "bg-warning text-dark", "title" => "Quiz Master", "desc" => "Completed 10 quizzes"],
                        ["icon" => "fas fa-bolt", "bg" => "bg-success", "title" => "Speed Learner", "desc" => "Completed 5 lessons in one day"],
                    ];
                    ?>
                     <?php if (empty($achievements)): ?>
                        <p class="text-muted text-center my-3">No recent achievements yet.</p>
                    <?php else: ?>
                        <?php foreach ($achievements as $ach): ?>
                        <div class="achievement-item">
                            <div class="icon-circle <?php echo htmlspecialchars($ach['bg']); ?>">
                                <i class="<?php echo htmlspecialchars($ach['icon']); ?>"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 small fw-bold"><?php echo htmlspecialchars($ach['title']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($ach['desc']); ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            </div>
        </div>
    </div>
</div>

<?php
// $extra_js = ['https://cdn.jsdelivr.net/npm/chart.js', BASE_URL . 'assets/js/dashboard_charts.js'];
include '../includes/dashboard_footer.php';
?>
