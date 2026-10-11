<?php
define('BASE_URL', '../');
$page_title = "My Students";
$_SESSION['user_role'] = 'instructor';
include '../includes/dashboard_header.php';

// Mock: $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : null;
// Fetch students for this instructor, optionally filtered by course_id
$students_data = [
    ['id' => 101, 'name' => 'Alice Johnson', 'avatar' => BASE_URL . 'assets/images/avatars/student_1.png', 'email' => 'alice@example.com', 'enrolled_course' => 'Web Development Bootcamp', 'progress' => 75, 'last_login' => '2025-06-01'],
    ['id' => 102, 'name' => 'Bob Williams', 'avatar' => BASE_URL . 'assets/images/avatars/student_2.png', 'email' => 'bob@example.com', 'enrolled_course' => 'Web Development Bootcamp', 'progress' => 50, 'last_login' => '2025-05-28'],
    ['id' => 103, 'name' => 'Carol Davis', 'avatar' => BASE_URL . 'assets/images/avatars/student_3.png', 'email' => 'carol@example.com', 'enrolled_course' => 'Computer Science Fundamentals', 'progress' => 90, 'last_login' => '2025-06-02'],
    ['id' => 104, 'name' => 'David Miller', 'avatar' => BASE_URL . 'assets/images/user_avatar_placeholder.png', 'email' => 'david@example.com', 'enrolled_course' => 'Web Development Bootcamp', 'progress' => 20, 'last_login' => '2025-05-15'],
];
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">My Students</h1>
    <p class="lead mb-4">View and manage students enrolled in your courses.</p>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center">
            <h5 class="mb-0 me-3">Student Roster</h5>
            <form class="d-flex my-2 my-md-0" style="min-width: 300px;">
                <input class="form-control form-control-sm me-2" type="search" placeholder="Search students by name or email..." aria-label="Search Students">
                <select class="form-select form-select-sm me-2" style="max-width: 200px;">
                    <option selected>All Courses</option>
                    <option value="1">Web Development Bootcamp</option>
                    <option value="2">Computer Science Fundamentals</option>
                </select>
                <button class="btn btn-sm btn-outline-primary" type="submit"><i class="fas fa-filter"></i></button>
            </form>
        </div>
        <div class="card-body p-0">
            <?php if(empty($students_data)): ?>
                <p class="text-center text-muted p-4">No students found matching your criteria.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th></th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Enrolled Course</th>
                            <th>Progress</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($students_data as $student): ?>
                        <tr>
                            <td><img src="<?php echo htmlspecialchars($student['avatar']); ?>" alt="<?php echo htmlspecialchars($student['name']); ?>" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;" onerror="this.src='<?php echo BASE_URL; ?>assets/images/user_avatar_placeholder.png'"></td>
                            <td><strong><?php echo htmlspecialchars($student['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                            <td><?php echo htmlspecialchars($student['enrolled_course']); ?></td>
                            <td>
                                <div class="progress" style="height: 8px; min-width: 80px;">
                                    <div class="progress-bar <?php echo ($student['progress'] < 30 ? 'bg-danger' : ($student['progress'] < 70 ? 'bg-warning' : 'bg-success')); ?>" role="progressbar" style="width: <?php echo $student['progress']; ?>%;" aria-valuenow="<?php echo $student['progress']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <small><?php echo $student['progress']; ?>%</small>
                            </td>
                            <td><?php echo date("M d, Y", strtotime($student['last_login'])); ?></td>
                            <td>
                                <a href="#" class="btn btn-sm btn-outline-primary" title="View Progress"><i class="fas fa-chart-bar"></i></a>
                                <a href="#" class="btn btn-sm btn-outline-secondary" title="Send Message"><i class="fas fa-envelope"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-footer bg-white d-flex justify-content-center">
             <nav aria-label="Student list navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item disabled"><a class="page-link" href="#">Previous</a></li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                    <li class="page-item"><a class="page-link" href="#">Next</a></li>
                </ul>
            </nav>
        </div>
    </div>
</div>
<?php include '../includes/dashboard_footer.php'; ?>

