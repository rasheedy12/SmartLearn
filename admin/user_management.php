<?php
define('BASE_URL', '../');
$page_title = "User Management";
$_SESSION['user_role'] = 'admin';
include '../includes/dashboard_header.php';

$users_data = [
    ['id' => 1, 'name' => 'John Student', 'avatar' => BASE_URL . 'assets/images/student.jpg', 'email' => 'student@example.com', 'role' => 'Student', 'status' => 'Active', 'joined_date' => '2025-01-15'],
    ['id' => 2, 'name' => 'Dr. Sarah Chen', 'avatar' => BASE_URL . 'assets/images/instructor.jpg', 'email' => 'instructor@example.com', 'role' => 'Instructor', 'status' => 'Active', 'joined_date' => '2024-11-20'],
    ['id' => 3, 'name' => 'Admin User', 'avatar' => BASE_URL . 'assets/images/admin.jpg', 'email' => 'admin@example.com', 'role' => 'Admin', 'status' => 'Active', 'joined_date' => '2024-10-01'],
    ['id' => 104, 'name' => 'Mike Davis (Suspended)', 'avatar' => BASE_URL . 'assets/images/student.jpg', 'email' => 'mike.inactive@example.com', 'role' => 'Student', 'status' => 'Suspended', 'joined_date' => '2025-03-01'],
];
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">User Management</h1> 
    <p class="lead mb-4">Oversee all platform users, manage roles, and ensure a healthy community.</p>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center">
            <h5 class="mb-0 me-3">All Users</h5>
            <form class="d-flex my-2 my-md-0" style="min-width: 300px;">
                <input class="form-control form-control-sm me-2" type="search" placeholder="Search users..." aria-label="Search Users">
                <select class="form-select form-select-sm me-2" style="max-width: 150px;">
                    <option selected>All Roles</option>
                    <option value="student">Student</option>
                    <option value="instructor">Instructor</option>
                    <option value="admin">Admin</option>
                </select>
                <button class="btn btn-sm btn-outline-primary" type="submit"><i class="fas fa-filter"></i></button>
            </form>
        </div>
        <div class="card-body p-0">
            <?php if(empty($users_data)): ?>
                <p class="text-center text-muted p-4">No users found.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th></th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users_data as $user): ?>
                        <tr>
                            <td><img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="<?php echo htmlspecialchars($user['name']); ?>" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;" onerror="this.src='<?php echo BASE_URL; ?>assets/images/user_avatar_placeholder.png'"></td>
                            <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                            <td>
                                <span class="badge <?php echo ($user['status'] == 'Active') ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo htmlspecialchars($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date("M d, Y", strtotime($user['joined_date'])); ?></td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="userActions<?php echo $user['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                        Actions
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="userActions<?php echo $user['id']; ?>">
                                        <li><a class="dropdown-item" href="#"><i class="fas fa-eye fa-fw me-2"></i>View Profile</a></li>
                                        <li><a class="dropdown-item" href="#"><i class="fas fa-edit fa-fw me-2"></i>Edit User</a></li>
                                        <li><a class="dropdown-item" href="#"><i class="fas fa-key fa-fw me-2"></i>Reset Password</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <?php if($user['status'] == 'Active'): ?>
                                        <li><a class="dropdown-item text-warning" href="#"><i class="fas fa-user-slash fa-fw me-2"></i>Suspend User</a></li>
                                        <?php else: ?>
                                        <li><a class="dropdown-item text-success" href="#"><i class="fas fa-user-check fa-fw me-2"></i>Activate User</a></li>
                                        <?php endif; ?>
                                        <li><a class="dropdown-item text-danger" href="#"><i class="fas fa-trash-alt fa-fw me-2"></i>Delete User</a></li>
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
         <div class="card-footer bg-white d-flex justify-content-center">
             <nav aria-label="User list navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item disabled"><a class="page-link" href="#">Previous</a></li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item"><a class="page-link" href="#">Next</a></li>
                </ul>
            </nav>
        </div>
    </div>
</div>
<?php include '../includes/dashboard_footer.php'; ?>
