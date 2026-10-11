<?php
define('BASE_URL', '../');
$page_title = "Login";
include '../includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-container">
        <div class="auth-graphic-side d-none d-md-flex">
            <div>
                <div class="logo"><i class="fas fa-graduation-cap me-2"></i>SmartLearn</div>
                <h2>Welcome back to your learning journey</h2>
                <p>Continue where you left off and keep building towards your goals. Your personalized dashboard is waiting for you.</p>
                <ul class="mt-3">
                    <li><i class="fas fa-check-circle"></i> Track your daily milestones</li>
                    <li><i class="fas fa-star"></i> Earn XP and unlock achievements</li>
                    <li><i class="fas fa-users"></i> Connect with study buddies</li>
                </ul>
            </div>
        </div>
        <div class="auth-form-side">
            <h1>Sign in to your account</h1>
            <p class="form-text text-muted">Enter your credentials to access your dashboard</p>

            <?php
            // if (isset($_GET['error'])) {
            //     echo '<div class="alert alert-danger">' . htmlspecialchars($_GET['error']) . '</div>';
            // }
            // if (isset($_GET['success'])) {
            //     echo '<div class="alert alert-success">' . htmlspecialchars($_GET['success']) . '</div>';
            // }
            ?>

            <form action="<?php echo BASE_URL; ?>auth/handle_login.php" method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
                        <label class="form-check-label" for="rememberMe">Remember me</label>
                    </div>
                    <div class="text-end">
                        <a href="#">Forgot password?</a>
                    </div>
                </div>
                <button type="submit" class="btn btn-auth-primary w-100">Sign In</button>
            </form>
            <div class="divider-text my-4">or</div>
            <button class="btn btn-social w-100 mb-2"><i class="fab fa-google"></i> Continue with Google</button>
            <button class="btn btn-social w-100"><i class="fab fa-microsoft"></i> Continue with Microsoft</button>
            <p class="mt-4 text-center">Don't have an account? <a href="<?php echo BASE_URL; ?>register.php">Sign up for free</a></p>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
