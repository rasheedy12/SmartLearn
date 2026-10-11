<?php
define('BASE_URL', '../');
$page_title = "My Assignments";
$_SESSION['user_role'] = 'student';
include '../includes/dashboard_header.php';

$assignments_data = [
    ['course' => 'JavaScript Fundamentals', 'title' => 'Quiz 4: Functions', 'due_date' => '2025-06-10', 'status' => 'Pending', 'type' => 'Quiz', 'link' => '#'],
    ['course' => 'UX Design Principles', 'title' => 'Project Milestone 1: User Personas', 'due_date' => '2025-06-15', 'status' => 'Pending', 'type' => 'Project', 'link' => '#'],
    ['course' => 'JavaScript Fundamentals', 'title' => 'Exercise: DOM Manipulation', 'due_date' => '2025-06-05', 'status' => 'Submitted', 'type' => 'Exercise', 'grade' => 'A', 'link' => '#'],
    ['course' => 'Python for Data Science', 'title' => 'Lab 2: Data Cleaning', 'due_date' => '2025-06-12', 'status' => 'Pending', 'type' => 'Lab', 'link' => '#'],
    ['course' => 'UX Design Principles', 'title' => 'Reading: Chapter 3 - Usability Testing', 'due_date' => '2025-06-08', 'status' => 'Completed', 'type' => 'Reading', 'link' => '#'],
];
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">My Assignments</h1> 
    <p class="lead mb-4">Stay on top of your coursework. Here are your pending and completed assignments.</p>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h4 class="h5 mb-0">Assignment List</h4>
            <div>
                <button class="btn btn-sm btn-outline-secondary active">All</button>
                <button class="btn btn-sm btn-outline-secondary">Pending</button>
                <button class="btn btn-sm btn-outline-secondary">Completed</button>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if(empty($assignments_data)): ?>
                <div class="text-center p-4">
                    <i class="fas fa-check-double fa-3x text-success mb-3"></i>
                    <h4>All Caught Up!</h4>
                    <p class="text-muted">You have no pending assignments at the moment.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Course</th>
                            <th>Assignment Title</th>
                            <th>Type</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Grade</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($assignments_data as $assignment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($assignment['course']); ?></td>
                            <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                            <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($assignment['type']); ?></span></td>
                            <td><?php echo date("M d, Y", strtotime($assignment['due_date'])); ?></td>
                            <td>
                                <?php 
                                $status_badge = 'bg-secondary';
                                if ($assignment['status'] == 'Pending') $status_badge = 'bg-warning text-dark';
                                if ($assignment['status'] == 'Submitted') $status_badge = 'bg-primary';
                                if ($assignment['status'] == 'Completed') $status_badge = 'bg-success';
                                if ($assignment['status'] == 'Graded') $status_badge = 'bg-success';
                                ?>
                                <span class="badge <?php echo $status_badge; ?>"><?php echo htmlspecialchars($assignment['status']); ?></span>
                            </td>
                            <td><?php echo isset($assignment['grade']) ? htmlspecialchars($assignment['grade']) : 'N/A'; ?></td>
                            <td>
                                <?php if($assignment['status'] == 'Pending'): ?>
                                <a href="<?php echo htmlspecialchars($assignment['link']); ?>" class="btn btn-sm btn-primary">Start</a>
                                <?php else: ?>
                                <a href="<?php echo htmlspecialchars($assignment['link']); ?>" class="btn btn-sm btn-outline-secondary">View</a>
                                <?php endif; ?>
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
