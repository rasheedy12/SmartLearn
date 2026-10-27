<?php
define('BASE_URL', '../');
$page_title = "My Profile";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedin'], $_SESSION['user_id']) || $_SESSION['loggedin'] !== true) {
    header('Location: ' . BASE_URL . 'pages/login.php');
    exit;
}

require_once BASE_URL . 'includes/db_config.php';

if (empty($_SESSION['profile_csrf_token'])) {
    $_SESSION['profile_csrf_token'] = bin2hex(random_bytes(32));
}
$current_user_id = $_SESSION['user_id'];
$extra_js = [BASE_URL . 'assets/js/profile-picture.js'];
$linked_oauth_providers = [];
$oauth_storage_available = true;
$oauth_links_query = $mysqli->prepare('SELECT provider FROM oauth_identities WHERE user_id = ?');
if ($oauth_links_query) {
    $oauth_links_query->bind_param('i', $current_user_id);
    if ($oauth_links_query->execute()) {
        $oauth_links_result = $oauth_links_query->get_result();
        while ($oauth_links_result && ($oauth_link = $oauth_links_result->fetch_assoc())) {
            $linked_oauth_providers[] = $oauth_link['provider'];
        }
    }
    $oauth_links_query->close();
} else {
    $oauth_storage_available = false;
}

// Fetch current user data from the database
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
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $cookie_params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $cookie_params['path'], $cookie_params['domain'],
                    $cookie_params['secure'], $cookie_params['httponly']
                );
            }
            session_destroy();
            header('Location: ' . BASE_URL . 'pages/login.php?error=' . urlencode('Your account could not be found. Please sign in again.'));
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
$oauth_success_message = ($_GET['oauth'] ?? '') === 'connected' ? 'Sign-in provider connected to your account.' : null;
$oauth_error_message = trim((string) ($_GET['oauth_error'] ?? ''));
$profile_form_data = $_SESSION['profile_form_data'] ?? [];
unset($_SESSION['profile_form_data']);
unset($_SESSION['success_message']); // Clear after displaying
unset($_SESSION['error_message']); // Clear after displaying

include BASE_URL . 'includes/dashboard_header.php';
?>
<div class="container-fluid">
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
    <?php if ($oauth_success_message): ?>
        <div class="alert alert-success" role="status"><?php echo htmlspecialchars($oauth_success_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($oauth_error_message !== ''): ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($oauth_error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h5 class="mb-0">Connected sign-in methods</h5></div>
        <div class="card-body">
            <p class="text-muted">Connect a provider while signed in to this SmartLearn account. Your current role and account details stay in SmartLearn.</p>
            <?php if (!$oauth_storage_available): ?>
                <div class="alert alert-warning mb-0">Run the OAuth identities database migration before connecting a provider.</div>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach (['google' => 'Google', 'microsoft' => 'Microsoft'] as $provider_key => $provider_label): ?>
                        <?php if (in_array($provider_key, $linked_oauth_providers, true)): ?>
                            <span class="btn btn-outline-secondary disabled" aria-disabled="true"><i class="fab fa-<?php echo $provider_key === 'google' ? 'google' : 'microsoft'; ?> me-2"></i><?php echo $provider_label; ?> connected</span>
                        <?php else: ?>
                            <form action="<?php echo BASE_URL; ?>auth/oauth_start.php" method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['profile_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="provider" value="<?php echo htmlspecialchars($provider_key, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="intent" value="link">
                                <button class="btn btn-outline-primary" type="submit"><i class="fab fa-<?php echo $provider_key === 'google' ? 'google' : 'microsoft'; ?> me-2"></i>Connect <?php echo $provider_label; ?></button>
                            </form>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm text-center h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <?php
                    $stored_avatar = trim((string) ($current_user_data['avatar_url'] ?? ''));
                    $avatar_path = BASE_URL . 'assets/images/user_avatar_placeholder.png';
                    if (
                        preg_match('~\Ahttps?://~i', $stored_avatar)
                        && filter_var($stored_avatar, FILTER_VALIDATE_URL)
                    ) {
                        $avatar_path = $stored_avatar;
                    } elseif ($stored_avatar !== '') {
                        $relative_avatar = preg_replace('~^(?:\.\./)+~', '', $stored_avatar);
                        $absolute_avatar = dirname(__DIR__) . '/' . ltrim((string) $relative_avatar, '/');
                        if (
                            !preg_match('~(^|/)\.\.(?:/|$)~', (string) $relative_avatar)
                            && is_file($absolute_avatar)
                        ) {
                            $avatar_path = BASE_URL . ltrim((string) $relative_avatar, '/');
                        }
                    }
                    ?>
                    <img id="profileAvatarPreview" src="<?php echo htmlspecialchars($avatar_path, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($current_user_data['name']); ?>" 
                         class="rounded-circle mb-3 mx-auto" 
                         style="width: 120px; height: 120px; object-fit: cover;">
                    <h4 class="card-title"><?php echo htmlspecialchars($current_user_data['name']); ?></h4>
                    <p class="text-muted mb-1 text-capitalize"><?php echo htmlspecialchars($current_user_data['role']); ?></p>
                    <p class="small text-muted">Joined: <?php echo date("M d, Y", strtotime($current_user_data['joined_date'])); ?></p>
                    <form id="profilePictureForm" action="<?php echo BASE_URL; ?>auth/handle_profile_update.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['profile_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="update_profile_picture">
                        <input type="file" class="visually-hidden" id="profileAvatar" name="profile_avatar"
                               accept="image/jpeg, image/png, image/webp" aria-label="Select profile picture" required>
                        <label for="profileAvatar" class="btn btn-outline-primary mt-2">Change Photo</label>
                        <small class="form-text text-muted d-block">JPEG, PNG, or WebP. Maximum 2 MB.</small>
                        <div id="profileAvatarError" class="text-danger small mt-2" role="alert" aria-live="polite"></div>
                        <div class="d-flex justify-content-center gap-2 mt-3">
                            <button id="saveProfileAvatar" type="submit" class="btn btn-primary btn-sm">Save Photo</button>
                            <button id="cancelProfileAvatar" type="button" class="btn btn-outline-secondary btn-sm" disabled>Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8 mb-4">
            <form action="<?php echo BASE_URL; ?>auth/handle_profile_update.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['profile_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white"><h5 class="mb-0">Account Information</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName" name="first_name" maxlength="50" value="<?php echo htmlspecialchars($profile_form_data['first_name'] ?? $current_user_data['first_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="last_name" maxlength="50" value="<?php echo htmlspecialchars($profile_form_data['last_name'] ?? $current_user_data['last_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="profileEmail" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="profileEmail" name="profile_email" value="<?php echo htmlspecialchars($current_user_data['email']); ?>" readonly>
                        <small class="form-text text-muted">Email cannot be changed here. Contact support if needed.</small>
                    </div>
                    <div class="mb-3">
                        <label for="profileBio" class="form-label">Short Bio / About Me</label>
                        <textarea class="form-control" id="profileBio" name="bio" rows="4" maxlength="2000"><?php echo htmlspecialchars($profile_form_data['bio'] ?? $current_user_data['bio'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
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
             </form>
        </div>
    </div>
</div>
<?php include '../includes/dashboard_footer.php'; ?>
