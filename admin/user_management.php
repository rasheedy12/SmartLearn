<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/admin_helpers.php';
$page_title = 'User Management';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_verify_csrf()) {
        admin_flash('error', 'Your session expired. Refresh the page and try again.');
    } else {
        $user_id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        $actions = [
            'approve' => ['pending_approval', 'active', 'Instructor account approved.'],
            'suspend' => ['active', 'suspended', 'User suspended.'],
            'activate' => ['suspended', 'active', 'User activated.'],
            'delete' => ['*', 'deleted', 'User account deactivated.'],
        ];
        $action = $_POST['action'] ?? '';

        if (!$user_id || $user_id < 1 || !isset($actions[$action])) {
            admin_flash('error', 'Choose a valid user action.');
        } elseif ($user_id === (int) $_SESSION['user_id']) {
            admin_flash('error', 'You cannot change the status of your own administrator account.');
        } else {
            [$required_status, $new_status, $success_message] = $actions[$action];
            $status_sql = $required_status === '*'
                ? 'UPDATE users SET status = ? WHERE user_id = ? AND status <> \'deleted\''
                : 'UPDATE users SET status = ? WHERE user_id = ? AND status = ?';
            $update = $mysqli->prepare($status_sql);
            if ($required_status === '*') {
                $update->bind_param('si', $new_status, $user_id);
            } else {
                $update->bind_param('sis', $new_status, $user_id, $required_status);
            }
            $update->execute();
            if ($update->affected_rows === 1) {
                admin_flash('success', $success_message);
            } else {
                admin_flash('error', 'The user was not found or no longer has a status that allows this action.');
            }
            $update->close();
        }
    }

    header('Location: user_management.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$role = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';
$valid_roles = ['student', 'instructor', 'admin'];
$valid_statuses = ['active', 'pending_approval', 'suspended', 'deleted'];
if (!in_array($role, $valid_roles, true)) {
    $role = '';
}
if (!in_array($status, $valid_statuses, true)) {
    $status = '';
}

$where = [];
$params = [];
$types = '';
if ($search !== '') {
    $where[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
    $types .= 'sss';
}
if ($role !== '') {
    $where[] = 'role = ?';
    $params[] = $role;
    $types .= 's';
}
if ($status !== '') {
    $where[] = 'status = ?';
    $params[] = $status;
    $types .= 's';
}
$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$count_stmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM users' . $where_sql);
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_users = (int) $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$users_stmt = $mysqli->prepare(
    'SELECT user_id, first_name, last_name, email, role, status, avatar_url, created_at
     FROM users' . $where_sql . ' ORDER BY created_at DESC, user_id DESC'
);
if ($params) {
    $users_stmt->bind_param($types, ...$params);
}
$users_stmt->execute();
$users_data = $users_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$users_stmt->close();

include BASE_URL . 'includes/dashboard_header.php';
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">User Management</h1>
    <?php admin_show_flash(); ?>
    <p class="lead mb-4">Oversee platform users and manage instructor approvals and account status.</p>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center">
            <h2 class="h5 mb-0 me-3">All Users <span class="badge bg-secondary"><?php echo number_format($total_users); ?></span></h2>
            <form method="get" class="d-flex flex-wrap gap-2 my-2 my-md-0">
                <input class="form-control form-control-sm" type="search" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search name or email" aria-label="Search users">
                <select class="form-select form-select-sm" name="role" aria-label="Filter by role">
                    <option value="">All Roles</option>
                    <?php foreach ($valid_roles as $option): ?>
                        <option value="<?php echo $option; ?>" <?php echo $role === $option ? 'selected' : ''; ?>><?php echo ucfirst($option); ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="form-select form-select-sm" name="status" aria-label="Filter by status">
                    <option value="">All Statuses</option>
                    <?php foreach ($valid_statuses as $option): ?>
                        <option value="<?php echo $option; ?>" <?php echo $status === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $option)), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
                <?php if ($search !== '' || $role !== '' || $status !== ''): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="user_management.php">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="card-body p-0">
            <?php if (!$users_data): ?>
                <p class="text-center text-muted p-4 mb-0">No users match these filters.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light"><tr><th></th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined Date</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($users_data as $user): ?>
                                <?php
                                $name = $user['first_name'] . ' ' . $user['last_name'];
                                $avatar = !empty($user['avatar_url'])
                                    ? (filter_var($user['avatar_url'], FILTER_VALIDATE_URL) ? $user['avatar_url'] : BASE_URL . ltrim($user['avatar_url'], '/'))
                                    : BASE_URL . 'assets/images/user_avatar_placeholder.png';
                                $is_current_user = (int) $user['user_id'] === (int) $_SESSION['user_id'];
                                ?>
                                <tr>
                                    <td><img src="<?php echo htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;" onerror="this.src='<?php echo BASE_URL; ?>assets/images/user_avatar_placeholder.png'"></td>
                                    <td><strong><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($user['role']), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="badge <?php echo $user['status'] === 'active' ? 'bg-success' : ($user['status'] === 'pending_approval' ? 'bg-warning text-dark' : ($user['status'] === 'suspended' ? 'bg-danger' : 'bg-secondary')); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user['status'])), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($user['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php if ($is_current_user): ?>
                                            <span class="text-muted small">Current account</span>
                                        <?php elseif ($user['status'] === 'deleted'): ?>
                                            <span class="text-muted small">Deactivated</span>
                                        <?php else: ?>
                                            <form method="post" class="d-flex gap-1">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>">
                                                <?php if ($user['role'] === 'instructor' && $user['status'] === 'pending_approval'): ?>
                                                    <button class="btn btn-sm btn-outline-success" name="action" value="approve" type="submit">Approve</button>
                                                <?php endif; ?>
                                                <?php if ($user['status'] === 'active'): ?>
                                                    <button class="btn btn-sm btn-outline-warning" name="action" value="suspend" type="submit">Suspend</button>
                                                <?php elseif ($user['status'] === 'suspended'): ?>
                                                    <button class="btn btn-sm btn-outline-success" name="action" value="activate" type="submit">Activate</button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-danger" name="action" value="delete" type="submit" onclick="return confirm('Deactivate this user account?')">Deactivate</button>
                                            </form>
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
<?php include BASE_URL . 'includes/dashboard_footer.php'; ?>
