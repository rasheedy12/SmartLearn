<?php
define('BASE_URL', '../');
$page_title = "Browse Courses";

// Conceptual: Determine if user is logged in to show appropriate header/footer
$is_logged_in_browse = true; // Mock this
// if ($is_logged_in_browse) {
    $_SESSION['user_role'] = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'student';
    include '../includes/dashboard_header.php';
// } else {
//    include '../includes/header.php';
// }

$all_courses_data = [
    ["id" => 1, "abbr" => "JS", "title" => "JavaScript Fundamentals", "instructor" => "Dr. Sarah Chen", "category" => "Web Development", "price" => "$49.99", "rating" => 4.8, "img" => BASE_URL . "assets/images/js.png", "bg_color"=>"bg-primary"],
    ["id" => 2, "abbr" => "UX", "title" => "UX Design Principles", "instructor" => "Mike Davis", "category" => "Design", "price" => "$39.99", "rating" => 4.7, "img" => BASE_URL . "assets/images/ux.jpg", "bg_color"=>"bg-success"],
    ["id" => 3, "abbr" => "PY", "title" => "Python for Data Science", "instructor" => "Dr. Emily White", "category" => "Data Science", "price" => "$59.99", "rating" => 4.9, "img" => BASE_URL . "assets/images/py.jpeg", "bg_color"=>"bg-info"],
    ["id" => 4, "abbr" => "MK", "title" => "Digital Marketing Essentials", "instructor" => "Johnathan Lee", "category" => "Marketing", "price" => "$29.99", "rating" => 4.6, "img" => BASE_URL . "assets/images/dme.jpeg", "bg_color"=>"bg-warning text-dark"],
    ["id" => 5, "abbr" => "AI", "title" => "Introduction to Artificial Intelligence", "instructor" => "Dr. Alan Turing Jr.", "category" => "Computer Science", "price" => "$79.99", "rating" => 4.9, "img" => BASE_URL . "assets/images/ai.jpg", "bg_color"=>"bg-danger"],
    ["id" => 6, "abbr" => "GD", "title" => "Graphic Design Masterclass", "instructor" => "Laura Ipsum", "category" => "Design", "price" => "$69.99", "rating" => 4.7, "img" => BASE_URL . "assets/images/gd.jpeg", "bg_color"=>"bg-secondary"],
];
$categories = array_unique(array_column($all_courses_data, 'category'));
?>
<div class="container-fluid"> 
    <h1 class="mb-4">Browse All Courses</h1>
    <p class="lead mb-4">Discover new skills and knowledge. Find the perfect course for you!</p>

    <div class="row mb-4">
        <div class="col-md-8">
            <form class="d-flex">
                <input class="form-control me-2" type="search" placeholder="Search for courses (e.g., Python, UX Design)" aria-label="Search">
                <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
        <div class="col-md-4 mt-2 mt-md-0">
            <select class="form-select" aria-label="Filter by category">
                <option selected>All Categories</option>
                <?php foreach($categories as $category): ?>
                <option value="<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $category))); ?>"><?php echo htmlspecialchars($category); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <?php if(empty($all_courses_data)): ?>
         <div class="alert alert-warning text-center">
            <h4 class="alert-heading">No Courses Available</h4>
            <p>We are working on adding new courses. Please check back soon!</p>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">
            <?php foreach($all_courses_data as $course): ?>
            <div class="col">
                <div class="card h-100 shadow-sm course-listing-card">
                    <img src="<?php echo htmlspecialchars($course['img']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($course['title']); ?>" style="height: 180px; object-fit: cover;" onerror="this.src='https://placehold.co/600x400/EAEAEA/<?php echo substr(htmlspecialchars($course['bg_color']),3); ?>?text=<?php echo htmlspecialchars($course['abbr']); ?>&font=montserrat'">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title fs-6"><?php echo htmlspecialchars($course['title']); ?></h5>
                        <p class="card-text small text-muted mb-1">By <?php echo htmlspecialchars($course['instructor']); ?></p>
                        <p class="card-text small text-muted mb-2">Category: <?php echo htmlspecialchars($course['category']); ?></p>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-primary"><?php echo htmlspecialchars($course['price']); ?></span>
                            <span class="small"><i class="fas fa-star text-warning"></i> <?php echo htmlspecialchars($course['rating']); ?></span>
                        </div>
                        <div class="mt-auto">
                            <a href="course_detail.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary w-100">View Details</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
         <nav aria-label="Page navigation example" class="mt-4 d-flex justify-content-center">
            <ul class="pagination">
                <li class="page-item disabled"><a class="page-link" href="#">Previous</a></li>
                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                <li class="page-item"><a class="page-link" href="#">2</a></li>
                <li class="page-item"><a class="page-link" href="#">3</a></li>
                <li class="page-item"><a class="page-link" href="#">Next</a></li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
<?php
// if ($is_logged_in_browse) {
    include '../includes/dashboard_footer.php';
// } else {
//    include '../includes/footer.php';
// }
?>
