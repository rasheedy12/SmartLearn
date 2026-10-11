<?php
define('BASE_URL', '../');
$page_title = "My Courses";
$_SESSION['user_role'] = 'student';
include '../includes/dashboard_header.php';

$my_courses_data = [
    ["id" => 1, "abbr" => "JS", "title" => "JavaScript Fundamentals", "instructor" => "Dr. Sarah Chen", "progress" => 65, "bg_color" => "bg-primary", "next_lesson" => "Functions & Scope", "img" => BASE_URL . "assets/images/js.png"],
    ["id" => 2, "abbr" => "UX", "title" => "UX Design Principles", "instructor" => "Mike Davis", "progress" => 32, "bg_color" => "bg-success", "next_lesson" => "User Research", "img" => BASE_URL . "assets/images/ux.jpg"],
    ["id" => 3, "abbr" => "PY", "title" => "Python for Data Science", "instructor" => "Dr. Emily White", "progress" => 88, "bg_color" => "bg-info", "next_lesson" => "Final Project Submission", "img" => BASE_URL . "assets/images/py.jpeg"],
    ["id" => 4, "abbr" => "MK", "title" => "Digital Marketing Essentials", "instructor" => "Johnathan Lee", "progress" => 15, "bg_color" => "bg-warning text-dark", "next_lesson" => "Intro to SEO", "img" => BASE_URL . "assets/images/dme.jpeg"],
];
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">My Courses</h1>
    <p class="lead mb-4">Here are all the courses you are currently enrolled in. Keep up the great work!</p>

    <?php if(empty($my_courses_data)): ?>
        <div class="alert alert-info text-center">
            <h4 class="alert-heading">No Courses Yet!</h4>
            <p>You haven't enrolled in any courses. Ready to start learning something new?</p>
            <hr>
            <a href="<?php echo BASE_URL; ?>browse_courses.php" class="btn btn-primary"><i class="fas fa-search me-2"></i>Browse Courses</a>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach($my_courses_data as $course): ?>
            <div class="col">
                <div class="card h-100 shadow-sm course-listing-card">
                    <img src="<?php echo htmlspecialchars($course['img']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($course['title']); ?>" style="height: 180px; object-fit: cover;" onerror="this.src='https://placehold.co/600x400/EAEAEA/<?php echo substr(htmlspecialchars($course['bg_color']),3); ?>?text=<?php echo htmlspecialchars($course['abbr']); ?>&font=montserrat'">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                        <p class="card-text small text-muted mb-2">By <?php echo htmlspecialchars($course['instructor']); ?></p>
                        <div class="mt-auto">
                             <p class="card-text small mb-1">Progress: <?php echo $course['progress']; ?>%</p>
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar <?php echo htmlspecialchars($course['bg_color']); ?>" role="progressbar" style="width: <?php echo $course['progress']; ?>%;" aria-valuenow="<?php echo $course['progress']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <p class="card-text small text-muted mb-2">Next: <?php echo htmlspecialchars($course['next_lesson']); ?></p>
                            <a href="<?php echo BASE_URL; ?>course_detail.php?id=<?php echo $course['id']; ?>" class="btn btn-sm <?php echo htmlspecialchars($course['bg_color']); ?> text-white w-100">Continue Learning</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php include '../includes/dashboard_footer.php'; ?>
