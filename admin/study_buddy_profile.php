<?php
require_once __DIR__ . '/../includes/student_helpers.php';

define('BASE_URL', '../');
$student_id = (int) $_SESSION['user_id'];
$profile_id = filter_var($_GET['user_id'] ?? null, FILTER_VALIDATE_INT);
$profile_id = $profile_id && $profile_id > 0 ? $profile_id : 0;
$profile = null;
$shared_courses = [];
$connection = null;
$error_message = '';

try {
    if ($profile_id > 0 && $profile_id !== $student_id) {
        $profile_stmt = $mysqli->prepare(
            "SELECT user_id, first_name, last_name, avatar_url, bio, created_at
             FROM users WHERE user_id = ? AND role = 'student' AND status = 'active' LIMIT 1"
        );
        $profile_stmt->bind_param('i', $profile_id);
        $profile_stmt->execute();
        $profile = $profile_stmt->get_result()->fetch_assoc();

        if ($profile) {
            $courses_stmt = $mysqli->prepare(
                'SELECT DISTINCT c.course_id, c.title
                 FROM enrollments their_enrollment
                 JOIN courses c ON c.course_id = their_enrollment.course_id
                 JOIN enrollments own_enrollment
                   ON own_enrollment.course_id = c.course_id AND own_enrollment.user_id = ?
                 WHERE their_enrollment.user_id = ?
                 ORDER BY c.title'
            );
            $courses_stmt->bind_param('ii', $student_id, $profile_id);
            $courses_stmt->execute();
            $shared_courses = $courses_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $low_id = min($student_id, $profile_id);
            $high_id = max($student_id, $profile_id);
            $connection_stmt = $mysqli->prepare(
                'SELECT connection_id, requester_id, recipient_id, status
                 FROM study_buddy_connections WHERE user_low_id = ? AND user_high_id = ? LIMIT 1'
            );
            $connection_stmt->bind_param('ii', $low_id, $high_id);
            $connection_stmt->execute();
            $connection = $connection_stmt->get_result()->fetch_assoc();
        }
    }
} catch (Throwable $exception) {
    error_log('Study buddy profile could not be loaded: ' . $exception->getMessage());
    $error_message = 'This student profile could not be loaded right now.';
}

$page_title = $profile ? trim($profile['first_name'] . ' ' . $profile['last_name']) : 'Student Profile';
include __DIR__ . '/../includes/dashboard_header.php';
?>
<div class="container-fluid">
    <a href="study_buddy.php" class="btn btn-outline-secondary btn-sm mb-3">
        <i class="fas fa-arrow-left me-1"></i>Back to Study Buddies
    </a>
    <?php if ($error_message !== ''): ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if (!$profile): ?>
        <div class="alert alert-warning" role="alert">
            <h1 class="h5">Student profile unavailable</h1>
            <p class="mb-0">This student may no longer have an active account.</p>
        </div>
    <?php else: ?>
        <?php
        $avatar = trim((string) $profile['avatar_url']);
        if ($avatar !== '' && !filter_var($avatar, FILTER_VALIDATE_URL)) {
            $avatar = BASE_URL . ltrim($avatar, '/');
        }
        ?>
        <section class="card shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
                    <img src="<?php echo htmlspecialchars($avatar ?: BASE_URL . 'assets/images/user_avatar_placeholder.png', ENT_QUOTES, 'UTF-8'); ?>"
                         alt="" class="rounded-circle" style="width:88px;height:88px;object-fit:cover"
                         onerror="this.onerror=null;this.src='<?php echo BASE_URL; ?>assets/images/user_avatar_placeholder.png'">
                    <div>
                        <h1 class="h3 mb-1"><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></h1>
                        <p class="text-muted mb-0">SmartLearn student</p>
                    </div>
                    <div class="ms-md-auto">
                        <?php if ($connection && $connection['status'] === 'accepted'): ?>
                            <span class="badge bg-success">Connected</span>
                        <?php elseif ($connection && (int) $connection['requester_id'] === $student_id): ?>
                            <span class="badge bg-secondary">Request pending</span>
                        <?php elseif ($connection): ?>
                            <a class="btn btn-primary" href="study_buddy.php#incoming-requests">Review incoming request</a>
                        <?php else: ?>
                            <form method="post" action="study_buddy.php">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(student_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="connect">
                                <input type="hidden" name="target_id" value="<?php echo $profile_id; ?>">
                                <button class="btn btn-primary" type="submit"><i class="fas fa-user-plus me-1"></i>Connect</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <h2 class="h5">About</h2>
                <p><?php echo !empty($profile['bio'])
                    ? nl2br(htmlspecialchars($profile['bio'], ENT_QUOTES, 'UTF-8'))
                    : '<span class="text-muted">This student has not added a bio yet.</span>'; ?></p>
                <h2 class="h5 mt-4">Courses you share</h2>
                <?php if ($shared_courses === []): ?>
                    <p class="text-muted mb-0">You do not have any shared enrolled courses yet.</p>
                <?php else: ?>
                    <ul class="mb-0">
                        <?php foreach ($shared_courses as $course): ?>
                            <li><a href="../pages/course_detail.php?id=<?php echo (int) $course['course_id']; ?>">
                                <?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/dashboard_footer.php'; ?>
