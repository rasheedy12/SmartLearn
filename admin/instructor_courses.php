<?php
define('BASE_URL', '../');
$page_title = "My Courses (Instructor)";
$_SESSION['user_role'] = 'instructor';
include '../includes/dashboard_header.php';

$instructor_courses_list = [
    ["id" => 1, "title" => "Computer Science Fundamentals", "students" => 89, "status" => "Published", "avg_completion" => 70, "img" => BASE_URL . "assets/images/course_placeholders/cs_fundamentals.jpg", "last_updated" => "2025-05-28"],
    ["id" => 2, "title" => "Web Development Bootcamp", "students" => 156, "status" => "Published", "avg_completion" => 85, "img" => BASE_URL . "assets/images/course_placeholders/web_dev_bootcamp.jpg", "last_updated" => "2025-06-01"],
    ["id" => 5, "title" => "Advanced JavaScript Techniques", "students" => 0, "status" => "Draft", "avg_completion" => 0, "img" => BASE_URL . "assets/images/course_placeholders/advanced_js.jpg", "last_updated" => "2025-06-02"],
];
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">My Courses</h1>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <p class="lead mb-0">Manage your courses, view student progress, and create new learning experiences.</p>
        <a href="<?php echo BASE_URL; ?>create_course.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Create New Course</a>
    </div>

    <?php if(empty($instructor_courses_list)): ?>
        <div class="alert alert-info text-center">
            <h4 class="alert-heading">No Courses Created Yet!</h4>
            <p>Ready to share your knowledge? Create your first course and start teaching.</p>
            <hr>
            <a href="<?php echo BASE_URL; ?>create_course.php" class="btn btn-success"><i class="fas fa-chalkboard-teacher me-2"></i>Create Your First Course</a>
        </div>
    <?php else: ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">Your Courses</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th></th>
                            <th>Course Title</th>
                            <th>Students</th>
                            <th>Avg. Completion</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($instructor_courses_list as $course): ?>
                        <tr>
                            <td><img src="<?php echo htmlspecialchars($course['img']); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>" style="width: 60px; height: 40px; object-fit: cover; border-radius: .25rem;" onerror="this.style.display='none'"></td>
                            <td><strong><?php echo htmlspecialchars($course['title']); ?></strong></td>
                            <td><?php echo $course['students']; ?></td>
                            <td><?php echo $course['avg_completion']; ?>%</td>
                            <td>
                                <span class="badge <?php echo ($course['status'] == 'Published') ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                    <?php echo htmlspecialchars($course['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date("M d, Y", strtotime($course['last_updated'])); ?></td>
                            <td>
                                <a href="<?php echo BASE_URL; ?>course_detail.php?id=<?php echo $course['id']; ?>&edit=true" class="btn btn-sm btn-outline-primary" title="Edit Course"><i class="fas fa-edit"></i></a>
                                <a href="<?php echo BASE_URL; ?>analytics.php?course_id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-info" title="View Analytics"><i class="fas fa-chart-line"></i></a>
                                <a href="<?php echo BASE_URL; ?>students_list.php?course_id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Manage Students"><i class="fas fa-users"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php include '../includes/dashboard_footer.php'; ?>
