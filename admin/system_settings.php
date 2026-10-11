<?php
define('BASE_URL', '../');
$page_title = "System Settings";
$_SESSION['user_role'] = 'admin';
include '../includes/dashboard_header.php';

// Mock current settings
$settings = [
    'site_name' => 'SmartLearn Platform',
    'admin_email' => 'admin@smartlearn.com',
    'maintenance_mode' => false,
    'allow_registration' => true,
    'default_user_role' => 'student',
    'smtp_host' => 'smtp.example.com',
    'smtp_port' => '587',
    'homepage_hero_title' => 'Stay Engaged. Learn Smarter. Finish Strong.',
];
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">System Settings</h1> 
    <p class="lead mb-4">Configure various aspects of the SmartLearn platform.</p>

    <form action="#" method="POST"> 
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white"><h5 class="mb-0">General Settings</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="siteName" class="form-label">Site Name</label>
                        <input type="text" class="form-control" id="siteName" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="adminEmail" class="form-label">Administrator Email</label>
                        <input type="email" class="form-control" id="adminEmail" name="admin_email" value="<?php echo htmlspecialchars($settings['admin_email']); ?>">
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="maintenanceMode" name="maintenance_mode" <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="maintenanceMode">Enable Maintenance Mode</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="allowRegistration" name="allow_registration" <?php echo $settings['allow_registration'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="allowRegistration">Allow New User Registrations</label>
                    </div>
                     <div class="mb-3">
                        <label for="defaultUserRole" class="form-label">Default New User Role</label>
                        <select class="form-select" id="defaultUserRole" name="default_user_role">
                            <option value="student" <?php echo ($settings['default_user_role'] == 'student') ? 'selected' : ''; ?>>Student</option>
                            <option value="instructor" <?php echo ($settings['default_user_role'] == 'instructor') ? 'selected' : ''; ?>>Instructor (Pending Approval)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white"><h5 class="mb-0">Email (SMTP) Settings</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="smtpHost" class="form-label">SMTP Host</label>
                            <input type="text" class="form-control" id="smtpHost" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host']); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="smtpPort" class="form-label">SMTP Port</label>
                            <input type="text" class="form-control" id="smtpPort" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port']); ?>">
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary">Test SMTP Connection</button>
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white"><h5 class="mb-0">Homepage Customization</h5></div>
                <div class="card-body">
                     <div class="mb-3">
                        <label for="homepageHeroTitle" class="form-label">Homepage Hero Title</label>
                        <input type="text" class="form-control" id="homepageHeroTitle" name="homepage_hero_title" value="<?php echo htmlspecialchars($settings['homepage_hero_title']); ?>">
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-0">Actions</h5></div>
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">Save All Settings</button>
                    <button type="reset" class="btn btn-outline-danger w-100">Reset to Defaults</button>
                    <hr>
                    <button type="button" class="btn btn-info w-100 mb-2"><i class="fas fa-broom me-2"></i>Clear Cache</button>
                    <button type="button" class="btn btn-secondary w-100"><i class="fas fa-shield-alt me-2"></i>View System Log</button>
                </div>
            </div>
        </div>
    </div>
    </form>
</div>
<?php include '../includes/dashboard_footer.php'; ?>
