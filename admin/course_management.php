<?php
define('BASE_URL', '../');
$page_title = "Course Management";
$_SESSION['user_role'] = 'admin';
include '../includes/dashboard_header.php';

$all_platform_courses = [
    ['id' => 1, 'title' => 'JavaScript Fundamentals', 'instructor' => 'Dr. Sarah Chen', 'category' => 'Web Development', 'students' => 5300, 'status' => 'Published', 'rating' => 4.8],
    ['id' => 2, 'title' => 'UX Design Principles', 'instructor' => 'Mike Davis', 'category' => 'Design', 'students' => 3100, 'status' => 'Published', 'rating' => 4.7],
    ['id' => 7, 'title' => 'Beginner Guitar Lessons', 'instructor' => 'Alex Tone', 'category' => 'Music', 'students' => 50, 'status' => 'Pending Approval', 'rating' => 0],
    ['id' => 8, 'title' => 'Advanced SEO Strategies', 'instructor' => 'Marketing Guru', 'category' => 'Marketing', 'students' => 0, 'status' => 'Draft', 'rating' => 0],
];
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">Course Management</h1>
    <p class="lead mb-4">Manage all courses on the platform. Approve new submissions, edit existing courses, and maintain quality standards.</p>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center">
            <h5 class="mb-0 me-3">All Platform Courses</h5>
            <form class="d-flex my-2 my-md-0" style="min-width: 300px;">
                <input class="form-control form-control-sm me-2" type="search" placeholder="Search courses by title or instructor..." aria-label="Search Courses">
                <select class="form-select form-select-sm me-2" style="max-width: 200px;">
                    <option selected>All Statuses</option>
                    <option value="published">Published</option>
                    <option value="pending">Pending Approval</option>
                    <option value="draft">Draft</option>
                    <option value="rejected">Rejected</option>
                </select>
                <button class="btn btn-sm btn-outline-primary" type="submit"><i class="fas fa-filter"></i></button>
            </form>
        </div>
        <div class="card-body p-0">
            <?php if(empty($all_platform_courses)): ?>
                <p class="text-center text-muted p-4">No courses found on the platform.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Course Title</th>
                            <th>Instructor</th>
                            <th>Category</th>
                            <th>Students</th>
                            <th>Rating</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($all_platform_courses as $course): ?>
                        <tr>
                            <td><?php echo $course['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($course['title']); ?></strong></td>
                            <td><?php echo htmlspecialchars($course['instructor']); ?></td>
                            <td><?php echo htmlspecialchars($course['category']); ?></td>
                            <td><?php echo number_format($course['students']); ?></td>
                            <td><i class="fas fa-star text-warning me-1"></i> <?php echo $course['rating'] > 0 ? $course['rating'] : 'N/A'; ?></td>
                            <td>
                                <?php
                                $status_class = 'bg-secondary';
                                if ($course['status'] == 'Published') $status_class = 'bg-success';
                                elseif ($course['status'] == 'Pending Approval') $status_class = 'bg-warning text-dark';
                                elseif ($course['status'] == 'Draft') $status_class = 'bg-info text-dark';
                                elseif ($course['status'] == 'Rejected') $status_class = 'bg-danger';
                                ?>
                                <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($course['status']); ?></span>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="courseActionsAdmin<?php echo $course['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                        Manage
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="courseActionsAdmin<?php echo $course['id']; ?>">
                                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>course_detail.php?id=<?php echo $course['id']; ?>&admin_view=true"><i class="fas fa-eye fa-fw me-2"></i>View Course</a></li>
                                        <li><a class="dropdown-item" href="#"><i class="fas fa-edit fa-fw me-2"></i>Edit Details</a></li>
                                        <?php if($course['status'] == 'Pending Approval'): ?>
                                        <li><a class="dropdown-item text-success" href="#"><i class="fas fa-check-circle fa-fw me-2"></i>Approve Course</a></li>
                                        <li><a class="dropdown-item text-danger" href="#"><i class="fas fa-times-circle fa-fw me-2"></i>Reject Course</a></li>
                                        <?php elseif($course['status'] == 'Published'): ?>
                                        <li><a class="dropdown-item text-warning" href="#"><i class="fas fa-eye-slash fa-fw me-2"></i>Unpublish Course</a></li>
                                        <?php endif; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="#"><i class="fas fa-tags fa-fw me-2"></i>Manage Categories/Tags</a></li>
                                        <li><a class="dropdown-item text-danger" href="#"><i class="fas fa-trash-alt fa-fw me-2"></i>Delete Course</a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../includes/dashboard_footer.php'; ?>
