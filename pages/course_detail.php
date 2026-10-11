<?php
define('BASE_URL', '../');
// In a real app, course ID would come from GET param: $course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
// Then fetch course data from DB. For now, mock data.
$mock_course_id = 1; // Example
$course_data = [
    1 => [
        'title' => 'JavaScript Fundamentals',
        'tagline' => 'Master the building blocks of modern web development with hands-on projects and real-world examples.',
        'instructor_name' => 'Dr. Sarah Chen',
        'instructor_title' => 'Senior Web Developer & Educator',
        'instructor_avatar' => BASE_URL . 'assets/images/avatars/instructor.png', // Placeholder
        'lessons_count' => 48,
        'skill_level' => 'Beginner',
        'category' => 'Web Development',
        'rating' => 4.8,
        'reviews_count' => 1250,
        'enrolled_count' => 5300,
        'video_preview_url' => 'https://www.w3schools.com/html/mov_bbb.mp4', // Placeholder video
        'modules' => [
            ['title' => 'Module 1: Getting Started', 'lessons' => ['Introduction to JavaScript (Completed)', 'Setting Up Your Environment (Current)', 'Your First JavaScript Program']],
            ['title' => 'Module 2: Variables and Data Types', 'lessons' => ['Understanding Variables', 'Primitive Data Types', 'Type Conversion']],
            ['title' => 'Module 3: Operators and Expressions', 'lessons' => ['Arithmetic Operators', 'Comparison Operators', 'Logical Operators']],
        ],
        'about' => 'This comprehensive course will take you from complete beginner to a proficient JavaScript developer. You\'ll learn the fundamentals of programming, modern JavaScript syntax, and how to build interactive web applications. Through hands-on projects, you\'ll tackle real-world scenarios, equipping you with the skills needed to advance your career in web development.',
        'what_you_learn' => [
            'Master JavaScript fundamental concepts and syntax.',
            'Build interactive web applications and websites.',
            'Understand modern ES6+ JavaScript features.',
            'Work with the DOM to manipulate web pages dynamically.',
            'Learn asynchronous JavaScript with Promises and Async/Await.'
        ],
        'user_progress' => 65 // Example user progress if logged in
    ],
    // Add more mock courses if needed
];

if (!isset($course_data[$mock_course_id])) {
    // Handle course not found, redirect or show error
    $page_title = "Course Not Found";
    include '../includes/header.php'; // Public header for error
    echo '<div class="container py-5 text-center"><h1>Course Not Found</h1><p>The course you are looking for does not exist or has been moved.</p><a href="'.BASE_URL.'browse_courses.php" class="btn btn-primary">Browse Courses</a></div>';
    include '../includes/footer.php';
    exit;
}
$current_course = $course_data[$mock_course_id];
$page_title = htmlspecialchars($current_course['title']);

// Determine if user is logged in (conceptual)
$is_logged_in = true; // Assume user is logged in for full view
// if ($is_logged_in) {
    $_SESSION['user_role'] = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'student'; // Mock role for sidebar
    include '../includes/dashboard_header.php';
// } else {
//    include '../includes/header.php'; // Public header for non-logged-in users
// }
?>

<div class="container-fluid course-detail-page" style="background-color: #6f42c1; color: white; padding-top:2rem; padding-bottom:2rem;">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb" style="--bs-breadcrumb-divider-color: rgba(255,255,255,0.7); --bs-breadcrumb-item-active-color: white;">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>browse_courses.php" style="color: rgba(255,255,255,0.7);">Courses</a></li>
                        <li class="breadcrumb-item"><a href="#" style="color: rgba(255,255,255,0.7);"><?php echo htmlspecialchars($current_course['category']); ?></a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($current_course['title']); ?></li>
                    </ol>
                </nav>
                <h1 class="display-5 fw-bold"><?php echo htmlspecialchars($current_course['title']); ?></h1>
                <p class="lead mb-4"><?php echo htmlspecialchars($current_course['tagline']); ?></p>
                <div class="d-flex align-items-center mb-3">
                    <img src="<?php echo htmlspecialchars($current_course['instructor_avatar']); ?>" alt="<?php echo htmlspecialchars($current_course['instructor_name']); ?>" class="rounded-circle me-2" style="width:40px; height:40px; object-fit:cover;">
                    <div>
                        <small>Created by</small>
                        <div class="fw-semibold"><?php echo htmlspecialchars($current_course['instructor_name']); ?></div>
                        <small class="opacity-75"><?php echo htmlspecialchars($current_course['instructor_title']); ?></small>
                    </div>
                </div>
                <p class="mb-1"><i class="fas fa-layer-group me-2"></i><?php echo $current_course['lessons_count']; ?> Lessons <span class="mx-2">|</span> <i class="fas fa-signal me-2"></i><?php echo htmlspecialchars($current_course['skill_level']); ?> <span class="mx-2">|</span> <i class="fas fa-certificate me-2"></i>Certificate</p>
                <p><i class="fas fa-star text-warning me-1"></i> <?php echo $current_course['rating']; ?> (<?php echo $current_course['reviews_count']; ?> ratings) <span class="mx-2">|</span> <?php echo number_format($current_course['enrolled_count']); ?> students</p>
            </div>
            <div class="col-lg-4 mt-4 mt-lg-0">
                <div class="card shadow">
                    <div class="card-img-top embed-responsive embed-responsive-16by9 bg-dark d-flex align-items-center justify-content-center" style="min-height: 200px;">
                        <?php if(!empty($current_course['video_preview_url'])): ?>
                        <video controls class="embed-responsive-item w-100" poster="<?php echo BASE_URL; ?>assets/images/course_placeholders/js_fundamentals.jpg">
                            <source src="<?php echo htmlspecialchars($current_course['video_preview_url']); ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                        <?php else: ?>
                        <img src="<?php echo BASE_URL; ?>assets/images/course_placeholders/js_fundamentals.jpg" class="img-fluid" alt="Course Preview Image">
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <h3 class="card-title fs-4 mb-3"><sup>$</sup>49.99 <del class="fs-6 text-muted ms-1">$99.99</del></h3>
                        <a href="<?php echo BASE_URL; ?>lesson_view.php?course_id=<?php echo $mock_course_id; ?>&lesson_id=1" class="btn btn-primary w-100 mb-2">Go to Course / Enroll Now</a>
                        <button class="btn btn-outline-secondary w-100 mb-2">Add to Wishlist <i class="far fa-heart ms-1"></i></button>
                        <small class="text-muted d-block text-center">30-Day Money-Back Guarantee</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <h3 class="mb-3">What you'll learn</h3>
                    <ul class="list-unstyled row">
                        <?php foreach($current_course['what_you_learn'] as $item): ?>
                        <li class="col-md-6 mb-2"><i class="fas fa-check text-success me-2"></i><?php echo htmlspecialchars($item); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <h3 class="mb-3">Course Content</h3>
            <div class="accordion" id="courseModulesAccordion">
                <?php foreach($current_course['modules'] as $index => $module): ?>
                <div class="accordion-item mb-2 shadow-sm">
                    <h2 class="accordion-header" id="headingModule<?php echo $index; ?>">
                        <button class="accordion-button <?php echo ($index > 0) ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapseModule<?php echo $index; ?>" aria-expanded="<?php echo ($index == 0) ? 'true' : 'false'; ?>" aria-controls="collapseModule<?php echo $index; ?>">
                            <strong><?php echo htmlspecialchars($module['title']); ?></strong> <span class="ms-auto text-muted small"><?php echo count($module['lessons']); ?> lessons</span>
                        </button>
                    </h2>
                    <div id="collapseModule<?php echo $index; ?>" class="accordion-collapse collapse <?php echo ($index == 0) ? 'show' : ''; ?>" aria-labelledby="headingModule<?php echo $index; ?>" data-bs-parent="#courseModulesAccordion">
                        <div class="accordion-body">
                            <ul class="list-group list-group-flush">
                                <?php foreach($module['lessons'] as $lesson_idx => $lesson_title): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="far fa-play-circle me-2 text-primary"></i> <?php echo htmlspecialchars($lesson_title); ?></span>
                                    <small class="text-muted">
                                        <?php if(str_contains($lesson_title, '(Completed)')): echo '<i class="fas fa-check-circle text-success me-1"></i>Completed';
                                              elseif(str_contains($lesson_title, '(Current)')): echo '<i class="fas fa-spinner fa-spin me-1 text-primary"></i>Current';
                                              else: echo '10:30'; /* Mock duration */
                                        endif; ?>
                                    </small>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

             <div class="card shadow-sm my-4">
                <div class="card-body p-4">
                    <h3 class="mb-3">About This Course</h3>
                    <p><?php echo nl2br(htmlspecialchars($current_course['about'])); ?></p>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <?php if($is_logged_in && isset($current_course['user_progress'])): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title">Your Progress</h5>
                    <div class="progress mb-2" style="height: 20px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $current_course['user_progress']; ?>%;" aria-valuenow="<?php echo $current_course['user_progress']; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $current_course['user_progress']; ?>%</div>
                    </div>
                    <a href="#" class="btn btn-sm btn-outline-primary">Continue Learning</a>
                </div>
            </div>
            <?php endif; ?>

            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title">Instructor</h5>
                    <div class="d-flex align-items-center">
                        <img src="<?php echo htmlspecialchars($current_course['instructor_avatar']); ?>" alt="<?php echo htmlspecialchars($current_course['instructor_name']); ?>" class="rounded-circle me-3" style="width:60px; height:60px; object-fit:cover;">
                        <div>
                            <h6 class="mb-0"><?php echo htmlspecialchars($current_course['instructor_name']); ?></h6>
                            <small class="text-muted"><?php echo htmlspecialchars($current_course['instructor_title']); ?></small>
                        </div>
                    </div>
                    <p class="small mt-2">Dr. Chen is a passionate web developer with over 10 years of experience building scalable applications and teaching coding bootcamps.</p>
                    <a href="#" class="btn btn-sm btn-outline-secondary">View Profile</a>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Study Buddies for this Course</h5>
                     <p class="small text-muted">Connect with others taking this course.</p>
                    <?php for($i=1; $i<=2; $i++): ?>
                    <div class="d-flex align-items-center mb-2">
                        <img src="<?php echo BASE_URL; ?>assets/images/avatars/student_<?php echo $i; ?>.png" alt="Study Buddy" class="rounded-circle me-2" style="width:30px; height:30px; object-fit:cover;" onerror="this.src='<?php echo BASE_URL; ?>assets/images/user_avatar_placeholder.png'">
                        <small>User <?php echo $i; ?> Name</small> <a href="#" class="ms-auto btn btn-sm btn-outline-info py-0">Connect</a>
                    </div>
                    <?php endfor; ?>
                    <a href="<?php echo BASE_URL; ?>study_buddy.php?course_id=<?php echo $mock_course_id; ?>" class="btn btn-sm btn-light w-100 mt-2">Find More Buddies</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// if ($is_logged_in) {
    include '../includes/dashboard_footer.php';
// } else {
//    include '../includes/footer.php';
// }
