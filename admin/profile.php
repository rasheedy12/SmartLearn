<?php
define('BASE_URL', '../');
$page_title = "My Profile";
// The dashboard header will start the session and can include db_config.php
include '../includes/dashboard_header.php';

// Check if user is logged in, if not, redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: " . BASE_URL . "login.php");
    exit;
}

// Fetch current user data from the database
$current_user_id = $_SESSION['user_id'];
$current_user_data = [];
$sql_fetch_user = "SELECT first_name, last_name, email, role, avatar_url, bio, created_at FROM users WHERE user_id = ?";

if($stmt = $mysqli->prepare($sql_fetch_user)){
    $stmt->bind_param("i", $current_user_id);
    if($stmt->execute()){
        $result = $stmt->get_result();
        if($result->num_rows === 1){
            $current_user_data = $result->fetch_assoc();
            // Combine first and last name for display
            $current_user_data['name'] = $current_user_data['first_name'] . ' ' . $current_user_data['last_name'];
            $current_user_data['joined_date'] = $current_user_data['created_at']; // Use created_at for joined date
        } else {
            // Should not happen if user is logged in
            $_SESSION['error_message'] = "Error: User data not found. Please log in again.";
            header("location: " . BASE_URL . "auth/handle_logout.php");
            exit;
        }
    } else {
        die("Error executing query. Please try again later.");
    }
    $stmt->close();
} else {
    die("Error preparing statement. Please try again later.");
}

// Display success/error messages passed back from the handler
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;
unset($_SESSION['success_message']); // Clear after displaying
unset($_SESSION['error_message']); // Clear after displaying
?>
<div class="container-fluid">
    {/* The H1 title "My Profile" is now part of the dashboard_header.php logic */}
    <p class="lead mb-4">Manage your account details, preferences, and security settings.</p>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form action="<?php echo BASE_URL; ?>auth/handle_profile_update.php" method="POST" enctype="multipart/form-data">
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm text-center h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <?php
                    // Robust check for avatar path
                    $avatar_path = BASE_URL . 'assets/images/user_avatar_placeholder.png'; // Default
                    if (!empty($current_user_data['avatar_url']) && file_exists(BASE_URL . $current_user_data['avatar_url'])) {
                        $avatar_path = BASE_URL . htmlspecialchars($current_user_data['avatar_url']);
                    }
                    ?>
                    <img src="<?php echo $avatar_path; ?>" 
                         alt="<?php echo htmlspecialchars($current_user_data['name']); ?>" 
                         class="rounded-circle mb-3 mx-auto" 
                         style="width: 120px; height: 120px; object-fit: cover;">
                    <h4 class="card-title"><?php echo htmlspecialchars($current_user_data['name']); ?></h4>
                    <p class="text-muted mb-1 text-capitalize"><?php echo htmlspecialchars($current_user_data['role']); ?></p>
                    <p class="small text-muted">Joined: <?php echo date("M d, Y", strtotime($current_user_data['joined_date'])); ?></p>
                    <input type="file" class="form-control form-control-sm mt-2" id="profileAvatar" name="profile_avatar" accept="image/png, image/jpeg">
                    <small class="form-text text-muted d-block">Upload a new profile picture. (Max 2MB)</small>
                </div>
            </div>
        </div>
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white"><h5 class="mb-0">Account Information</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName" name="first_name" value="<?php echo htmlspecialchars($current_user_data['first_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="last_name" value="<?php echo htmlspecialchars($current_user_data['last_name']); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="profileEmail" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="profileEmail" name="profile_email" value="<?php echo htmlspecialchars($current_user_data['email']); ?>" readonly>
                        <small class="form-text text-muted">Email cannot be changed here. Contact support if needed.</small>
                    </div>
                    <div class="mb-3">
                        <label for="profileBio" class="form-label">Short Bio / About Me</label>
                        <textarea class="form-control" id="profileBio" name="bio" rows="4"><?php echo htmlspecialchars($current_user_data['bio'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-0">Change Password</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="currentPassword" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="currentPassword" name="current_password" placeholder="Enter current password to change it">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="newPassword" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="newPassword" name="new_password" placeholder="Enter new password">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirmNewPassword" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirmNewPassword" name="confirm_new_password" placeholder="Confirm new password">
                        </div>
                    </div>
                    <small class="form-text text-muted">Leave all three password fields blank to keep your current password.</small>
                </div>
            </div>
             <div class="text-end mt-4">
                <button type="submit" class="btn btn-primary px-4">Save Changes</button>
            </div>
        </div>
    </div>
    </form>
</div>
<?php include '../includes/dashboard_footer.php'; ?>
