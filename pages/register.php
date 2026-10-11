<?php
define('BASE_URL', '../');
$page_title = "Register";
include '../includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-container">
        <div class="auth-graphic-side d-none d-md-flex">
             <div>
                <div class="logo"><i class="fas fa-graduation-cap me-2"></i>SmartLearn</div>
                <h2>Join thousands of learners worldwide</h2>
                <p>Create your account, path, gamified challenges, and AI-powered insights.</p>
                <ul class="mt-3">
                    <li><i class="fas fa-check-circle"></i> Personalized learning paths</li>
                    <li><i class="fas fa-trophy"></i> Gamified rewards and achievements</li>
                    <li><i class="fas fa-user-friends"></i> Study buddy connections</li>
                    <li><i class="fas fa-chart-line"></i> Real-time progress tracking</li>
                </ul>
            </div>
        </div>
        <div class="auth-form-side">
            <h1>Create your account</h1>
            <p class="form-text text-muted">Start your learning journey in less than 2 minutes</p>

            <?php
            // if (isset($_GET['error'])) {
            //     echo '<div class="alert alert-danger">' . htmlspecialchars($_GET['error']) . '</div>';
            // }
            ?>

            <form action="<?php echo BASE_URL; ?>auth/handle_register.php" method="POST">
                <div class="d-grid gap-2 mb-3">
                    <button type="button" class="btn btn-social"><i class="fab fa-google"></i> Sign up with Google</button>
                    <button type="button" class="btn btn-social"><i class="fab fa-microsoft"></i> Sign up with Microsoft</button>
                </div>
                <div class="divider-text my-3">or</div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="firstName" class="form-label">First name</label>
                        <input type="text" class="form-control" id="firstName" name="first_name" placeholder="John" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="lastName" class="form-label">Last name</label>
                        <input type="text" class="form-control" id="lastName" name="last_name" placeholder="Doe" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="john@example.com" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Create a strong password" required>
                    <small class="form-text text-muted">Must be at least 8 characters.</small>
                </div>
                 <div class="mb-3">
                    <label class="form-label d-block">I am a...</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="role" id="roleStudent" value="student" checked>
                        <label class="form-check-label" for="roleStudent">Student</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="role" id="roleInstructor" value="instructor">
                        <label class="form-check-label" for="roleInstructor">Instructor</label>
                    </div>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="agreeTerms" name="agree_terms" required>
                    <label class="form-check-label" for="agreeTerms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></label>
                </div>
                <button type="submit" class="btn btn-auth-primary w-100">Create Account</button>
            </form>
            <p class="mt-4 text-center">Already have an account? <a href="<?php echo BASE_URL; ?>login.php">Sign In</a></p>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
