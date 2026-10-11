<?php
define('BASE_URL', '../');
$page_title = "Instructor Dashboard";
$_SESSION['user_role'] = 'instructor'; // Mock role for sidebar
// $use_charts = true; // If charts are needed
include '../includes/dashboard_header.php';
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">Instructor Dashboard</h1>

    <h3 class="h4 mb-3">Teaching Overview</h3>
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <h6>Active Students</h6>
                    <i class="fas fa-users icon-lg text-primary"></i>
                </div>
                <div class="stat-value">247</div>
                <div class="stat-meta"><span class="text-success stat-change"><i class="fas fa-arrow-up"></i> 12%</span> vs last week</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <h6>Avg. Completion Rate</h6>
                    <i class="fas fa-tasks icon-lg text-success"></i>
                </div>
                <div class="stat-value">78%</div>
                <div class="stat-meta"><span class="text-danger stat-change"><i class="fas fa-arrow-down"></i> 2%</span> vs last week</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card h-100">
                 <div class="d-flex justify-content-between align-items-center">
                    <h6>At-Risk Students</h6>
                     <i class="fas fa-user-graduate icon-lg text-warning"></i>
                </div>
                <div class="stat-value">23</div>
                <div class="stat-meta"><a href="#" class="text-warning">Needs attention</a></div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <h6>Unread Messages</h6>
                    <i class="fas fa-envelope icon-lg text-info"></i>
                </div>
                <div class="stat-value">8</div>
                <div class="stat-meta"><a href="#" class="text-info">View all messages</a></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h4 mb-0">My Courses</h3>
                <a href="<?php echo BASE_URL; ?>create_course.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> Create Course</a>
            </div>
            <?php
            $instructor_courses = [
                ["abbr" => "CS", "title" => "Computer Science Fundamentals", "term" => "Fall 2024 - 89 students enrolled", "engagement" => 80, "completion" => 70, "at_risk" => 12, "bg_color" => "bg-purple", "active" => true, "id" => 1],
                ["abbr" => "WD", "title" => "Web Development Bootcamp", "term" => "Fall 2024 - 156 students enrolled", "engagement" => 90, "completion" => 85, "at_risk" => 8, "bg_color" => "bg-success", "active" => true, "id" => 2],
                ["abbr" => "DM", "title" => "Digital Marketing Basics", "term" => "Spring 2024 - 120 students enrolled", "engagement" => 75, "completion" => 65, "at_risk" => 15, "bg_color" => "bg-info", "active" => false, "id" => 3],
            ];
            ?>
            <?php foreach($instructor_courses as $course): ?>
            <div class="instructor-course-card mb-3">
                <div class="course-icon-lg <?php echo htmlspecialchars($course['bg_color']); ?>">
                    <?php echo htmlspecialchars($course['abbr']); ?>
                </div>
                <div class="course-details flex-grow-1">
                    <h5><?php echo htmlspecialchars($course['title']); ?></h5>
                    <p class="course-meta"><?php echo htmlspecialchars($course['term']); ?></p>
                    <div class="course-stats d-flex mb-2">
                        <div class="stat-item">Engagement: <strong><?php echo $course['engagement']; ?>%</strong></div>
                        <div class="stat-item">Completion: <strong><?php echo $course['completion']; ?>%</strong></div>
                        <div class="stat-item text-danger">At Risk: <strong><?php echo $course['at_risk']; ?> students</strong></div>
                    </div>
                    <div>
                        <a href="#" class="btn btn-outline-secondary btn-sm me-1">Manage Content</a>
                        <a href="#" class="btn btn-outline-secondary btn-sm me-1">View Analytics</a>
                        <a href="#" class="btn btn-outline-secondary btn-sm">Student Progress</a>
                    </div>
                </div>
                <div class="course-actions">
                    <?php if($course['active']): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Archived</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm alert-attention mb-4">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-exclamation-circle me-2 text-danger"></i>Students Need Attention</h5>
                    <p class="card-text small">23 students haven't logged in for 5+ days.</p>
                    <a href="#" class="btn btn-danger btn-sm">Send Reminder</a>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Recent Activity</h5>
                </div>
                <div class="list-group list-group-flush quick-action-list">
                    <a href="#" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-signature text-primary"></i> New quiz submission: JavaScript Quiz 4 <small class="text-muted d-block">2 minutes ago</small>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action">
                        <i class="fas fa-comments text-info"></i> New discussion post in "Web Dev" <small class="text-muted d-block">15 min ago</small>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action">
                        <i class="fas fa-check-circle text-success"></i> Course milestone reached: Web Dev course hit 80% completion <small class="text-muted d-block">1 hour ago</small>
                    </a>
                </div>
            </div>
             <div class="card shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="list-group list-group-flush quick-action-list">
                    <a href="#" class="list-group-item list-group-item-action"><i class="fas fa-chart-line"></i> View Analytics Report</a>
                    <a href="#" class="list-group-item list-group-item-action"><i class="fas fa-bullhorn"></i> Send Announcement</a>
                    <a href="#" class="list-group-item list-group-item-action"><i class="fas fa-file-export"></i> Export Student Data</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
include '../includes/dashboard_footer.php';
?>
