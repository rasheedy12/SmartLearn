<?php
require_once __DIR__ . '/../includes/oauth_helpers.php';
smartlearn_start_secure_session();
$registration = $_SESSION['smartlearn_oauth_registration'] ?? null;
if (!is_array($registration) || !isset($registration['started_at']) || time() - (int) $registration['started_at'] > 900) {
    unset($_SESSION['smartlearn_oauth_registration']);
    header('Location: login.php?error=' . urlencode('Your sign-in session expired. Please try again.'));
    exit;
}
define('BASE_URL', '../');
$page_title = 'Complete your account';
$completion_error = trim((string) ($_GET['error'] ?? ''));
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrapper">
    <div class="auth-container">
        <div class="auth-graphic-side d-none d-md-flex">
            <div>
                <div class="logo"><i class="fas fa-graduation-cap me-2"></i>SmartLearn</div>
                <h2>One more step</h2>
                <p>Choose how you’ll use SmartLearn and finish setting up your account.</p>
            </div>
        </div>
        <div class="auth-form-side">
            <h1>Complete your account</h1>
            <p class="form-text text-muted">Your role is selected here and is never taken from the sign-in provider.</p>
            <?php if ($completion_error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($completion_error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form action="<?php echo BASE_URL; ?>auth/handle_oauth_registration.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($registration['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="oauthFirstName" class="form-label">First name</label>
                        <input type="text" class="form-control" id="oauthFirstName" name="first_name" maxlength="50" value="<?php echo htmlspecialchars($registration['first_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="oauthLastName" class="form-label">Last name</label>
                        <input type="text" class="form-control" id="oauthLastName" name="last_name" maxlength="50" value="<?php echo htmlspecialchars($registration['last_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="oauthEmail" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="oauthEmail" name="email" maxlength="255" value="<?php echo htmlspecialchars($registration['email'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo !empty($registration['email_verified']) ? 'readonly' : ''; ?> required>
                    <?php if (!empty($registration['email_verified'])): ?>
                        <small class="form-text text-muted">This email was verified by Google.</small>
                    <?php else: ?>
                        <small class="form-text text-muted">Use an email address you can access. Microsoft may not provide a verified email.</small>
                    <?php endif; ?>
                </div>
                <fieldset class="mb-3">
                    <legend class="form-label">I am a...</legend>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="role" id="oauthRoleStudent" value="student" checked>
                        <label class="form-check-label" for="oauthRoleStudent">Student</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="role" id="oauthRoleInstructor" value="instructor">
                        <label class="form-check-label" for="oauthRoleInstructor">Instructor</label>
                    </div>
                </fieldset>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="oauthAgreeTerms" name="agree_terms" required>
                    <label class="form-check-label" for="oauthAgreeTerms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.</label>
                </div>
                <button type="submit" class="btn btn-auth-primary w-100">Finish creating account</button>
            </form>
            <p class="mt-4 text-center"><a href="<?php echo BASE_URL; ?>pages/login.php">Back to sign in</a></p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
