<?php
require_once __DIR__ . '/../includes/student_helpers.php';
require_once __DIR__ . '/../includes/paystack_helpers.php';
require_once __DIR__ . '/../includes/youtube_helpers.php';

define('BASE_URL', '../');
$course_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$course_id = $course_id && $course_id > 0 ? $course_id : 0;
$user_id = (int) $_SESSION['user_id'];
$error_message = '';
$success_message = '';
$payment_error = trim((string) ($_GET['payment_error'] ?? ''));
$course = null;
$modules = [];
$enrollment = null;
$is_enrolled = false;
$is_wishlisted = false;

if (!isset($_SESSION['student_wishlist'])) {
    $_SESSION['student_wishlist'] = [];
}
$wishlist_key = $user_id . ':' . $course_id;
$is_wishlisted = isset($_SESSION['student_wishlist'][$wishlist_key]);

try {
    if ($course_id > 0) {
        $course_stmt = $mysqli->prepare(
            "SELECT c.course_id, c.category_id, c.title, c.subtitle, c.description, c.skill_level, c.language,
                    c.thumbnail_url, c.video_preview_url, c.price, c.discounted_price, c.is_free,
                    c.average_rating, c.ratings_count, c.enrolled_students_count,
                    cat.name AS category_name, CONCAT(u.first_name, ' ', u.last_name) AS instructor_name
             FROM courses c
             JOIN users u ON u.user_id = c.instructor_id
             LEFT JOIN categories cat ON cat.category_id = c.category_id
             WHERE c.course_id = ? AND c.status = 'published'
             LIMIT 1"
        );
        $course_stmt->bind_param('i', $course_id);
        $course_stmt->execute();
        $course = $course_stmt->get_result()->fetch_assoc();
    }

    if ($course) {
        $enrollment_stmt = $mysqli->prepare(
            'SELECT enrollment_id, progress_percentage FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1'
        );
        $enrollment_stmt->bind_param('ii', $user_id, $course_id);
        $enrollment_stmt->execute();
        $enrollment = $enrollment_stmt->get_result()->fetch_assoc();
        $is_enrolled = $enrollment !== null;

        $modules_stmt = $mysqli->prepare(
            'SELECT m.module_id, m.title AS module_title, m.order_index AS module_order,
                    l.lesson_id, l.title AS lesson_title, l.duration_minutes, l.is_previewable, l.order_index AS lesson_order
             FROM modules m
             LEFT JOIN lessons l ON l.module_id = m.module_id
             WHERE m.course_id = ?
             ORDER BY m.order_index, l.order_index'
        );
        $modules_stmt->bind_param('i', $course_id);
        $modules_stmt->execute();
        $module_rows = $modules_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($module_rows as $row) {
            $module_id = (int) $row['module_id'];
            if (!isset($modules[$module_id])) {
                $modules[$module_id] = ['title' => $row['module_title'], 'lessons' => []];
            }
            if ($row['lesson_id'] !== null) {
                $modules[$module_id]['lessons'][] = $row;
            }
        }
    }
} catch (Throwable $exception) {
    error_log('Course detail query failed: ' . $exception->getMessage());
    $error_message = 'Course information could not be loaded right now.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $course) {
    if (!student_verify_csrf()) {
        http_response_code(400);
        $error_message = 'Your request could not be verified. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'toggle_wishlist') {
        if ($is_wishlisted) {
            unset($_SESSION['student_wishlist'][$wishlist_key]);
            $success_message = 'Removed from your wishlist.';
            $is_wishlisted = false;
        } else {
            $_SESSION['student_wishlist'][$wishlist_key] = true;
            $success_message = 'Saved to your wishlist for this session.';
            $is_wishlisted = true;
        }
    } elseif (($_POST['action'] ?? '') === 'enroll') {
        if ($is_enrolled) {
            $success_message = 'You are already enrolled in this course.';
        } elseif ((int) $course['is_free'] !== 1) {
            $error_message = 'Paid courses require payment before enrollment. Use the Paystack checkout form.';
        } else {
            try {
                $mysqli->begin_transaction();
                $enroll_stmt = $mysqli->prepare(
                    'INSERT INTO enrollments (user_id, course_id, enrolled_at, progress_percentage) VALUES (?, ?, NOW(), 0)'
                );
                $enroll_stmt->bind_param('ii', $user_id, $course_id);
                $enroll_stmt->execute();
                $count_stmt = $mysqli->prepare(
                    'UPDATE courses SET enrolled_students_count = enrolled_students_count + 1 WHERE course_id = ?'
                );
                $count_stmt->bind_param('i', $course_id);
                $count_stmt->execute();
                $mysqli->commit();
                header('Location: course_detail.php?id=' . $course_id . '&enrolled=1');
                exit;
            } catch (Throwable $exception) {
                if ($mysqli->server_status & MYSQLI_SERVER_STATUS_IN_TRANS) {
                    $mysqli->rollback();
                }
                error_log('Free course enrollment failed: ' . $exception->getMessage());
                $error_message = 'Enrollment could not be completed. Please try again.';
            }
        }
    }
}

if ($course && isset($_GET['enrolled']) && $_GET['enrolled'] === '1') {
    $success_message = 'You are enrolled. The course is now available in My Courses.';
}
if ($course && isset($_GET['payment_success']) && $_GET['payment_success'] === '1') {
    $success_message = 'Payment verified. You are enrolled and can start learning.';
}

$page_title = $course ? $course['title'] : 'Course Not Found';
include __DIR__ . '/../includes/dashboard_header.php';

if (!$course):
?>
<div class="container py-5">
    <div class="alert alert-warning">
        <h1 class="h4">Course not found</h1>
        <p class="mb-3"><?php echo htmlspecialchars($error_message ?: 'This course is unavailable or is not published.', ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="browse_courses.php" class="btn btn-primary">Browse courses</a>
    </div>
</div>
<?php
include __DIR__ . '/../includes/dashboard_footer.php';
exit;
endif;

$display_price = (int) $course['is_free'] === 1
    ? 0
    : (float) ($course['discounted_price'] ?? $course['price']);
$thumbnail = trim((string) $course['thumbnail_url']);
if ($thumbnail !== '' && !preg_match('~^(https?://|/)~i', $thumbnail)) {
    $thumbnail = BASE_URL . ltrim($thumbnail, '/');
}
$lesson_count = 0;
foreach ($modules as $module) {
    $lesson_count += count($module['lessons']);
}
?>
<div class="container-fluid bg-primary text-white py-5">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a class="link-light" href="browse_courses.php">Browse courses</a></li>
                <li class="breadcrumb-item"><a class="link-light"
                    href="browse_courses.php?category=<?php echo (int) ($course['category_id'] ?? 0); ?>">
                    <?php echo htmlspecialchars($course['category_name'] ?? 'Courses', ENT_QUOTES, 'UTF-8'); ?>
                </a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">
                    <?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?>
                </li>
            </ol>
        </nav>
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <h1 class="display-5 fw-bold"><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <?php if (!empty($course['subtitle'])): ?>
                    <p class="lead"><?php echo htmlspecialchars($course['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <p>Created by <strong><?php echo htmlspecialchars($course['instructor_name'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                <p class="mb-0">
                    <i class="fas fa-layer-group me-2"></i><?php echo $lesson_count; ?> lessons
                    <span class="mx-2">|</span>
                    <i class="fas fa-signal me-2"></i><?php echo htmlspecialchars($course['skill_level'] ?: 'All levels', ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($course['language'])): ?>
                        <span class="mx-2">|</span><?php echo htmlspecialchars($course['language'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </p>
                <p class="mt-2 mb-0">
                    <i class="fas fa-star text-warning me-1"></i><?php echo number_format((float) $course['average_rating'], 1); ?>
                    (<?php echo (int) $course['ratings_count']; ?> ratings)
                    <span class="mx-2">|</span><?php echo number_format((int) $course['enrolled_students_count']); ?> students
                </p>
            </div>
            <div class="col-lg-4">
                <div class="card text-dark shadow">
                    <?php if (!empty($course['video_preview_url']) && ($preview_video_id = youtube_video_id($course['video_preview_url']))): ?>
                        <div class="ratio ratio-16x9">
                            <iframe src="https://www.youtube-nocookie.com/embed/<?php echo htmlspecialchars($preview_video_id, ENT_QUOTES, 'UTF-8'); ?>"
                                    title="Course video preview" allowfullscreen loading="lazy"></iframe>
                        </div>
                    <?php elseif (!empty($course['video_preview_url'])): ?>
                        <video controls class="w-100" style="max-height: 220px">
                            <source src="<?php echo htmlspecialchars($course['video_preview_url'], ENT_QUOTES, 'UTF-8'); ?>">
                        </video>
                    <?php elseif ($thumbnail !== ''): ?>
                        <img src="<?php echo htmlspecialchars($thumbnail, ENT_QUOTES, 'UTF-8'); ?>"
                             class="card-img-top" alt="<?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?>"
                             style="max-height: 220px; object-fit: cover;">
                    <?php else: ?>
                        <div class="bg-light text-muted text-center py-5">Course preview unavailable</div>
                    <?php endif; ?>
                    <div class="card-body">
                        <h2 class="h3"><?php echo $display_price <= 0 ? 'Free' : '$' . number_format($display_price, 2) . ' USD'; ?></h2>
                        <?php if ($is_enrolled): ?>
                                <?php
                                $first_lesson_id = 0;
                                foreach ($modules as $module) {
                                    if (!empty($module['lessons'])) {
                                        $first_lesson_id = (int) $module['lessons'][0]['lesson_id'];
                                        break;
                                    }
                                }
                                ?>
                                <?php if ($first_lesson_id > 0): ?>
                                    <a href="lesson_view.php?course_id=<?php echo $course_id; ?>&amp;lesson_id=<?php echo $first_lesson_id; ?>"
                                       class="btn btn-success w-100 mb-2">Start learning</a>
                                <?php else: ?>
                                    <a href="../admin/my_courses.php" class="btn btn-success w-100 mb-2">Go to My Courses</a>
                                <?php endif; ?>
                            <?php elseif ((int) $course['is_free'] === 1): ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(student_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="enroll">
                                <button class="btn btn-primary w-100 mb-2" type="submit">Enroll for free</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="initiate_course_payment.php">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(student_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                <label class="form-label small mb-1" for="payment-currency">Pay in your currency</label>
                                <select class="form-select mb-2" id="payment-currency" name="currency" required>
                                    <?php foreach (paystack_supported_currencies() as $currency_code => $currency_name): ?>
                                        <option value="<?php echo htmlspecialchars($currency_code, ENT_QUOTES, 'UTF-8'); ?>"
                                            <?php echo $currency_code === 'USD' ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($currency_name . ' (' . $currency_code . ')', ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-primary w-100 mb-2" type="submit">Pay with Paystack</button>
                                <small class="text-muted d-block text-center">The USD course price is converted using the current exchange rate. Currency availability depends on the merchant's Paystack account.</small>
                            </form>
                        <?php endif; ?>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(student_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="toggle_wishlist">
                            <button class="btn btn-outline-secondary w-100" type="submit">
                                <?php echo $is_wishlisted ? 'Remove from wishlist' : 'Add to wishlist'; ?>
                                <i class="<?php echo $is_wishlisted ? 'fas' : 'far'; ?> fa-heart ms-1"></i>
                            </button>
                        </form>
                        <small class="text-muted d-block text-center mt-2">Wishlist is saved for this session.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<main class="container py-5">
    <?php if ($payment_error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($payment_error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error_message !== ''): ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($success_message !== ''): ?>
        <div class="alert alert-success" role="status"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <section class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h4">About this course</h2>
                    <?php if (!empty($course['description'])): ?>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($course['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                    <?php else: ?>
                        <p class="text-muted mb-0">The instructor has not added a course description yet.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h4 mb-3">Course content</h2>
                    <?php if ($modules === []): ?>
                        <p class="text-muted mb-0">The instructor has not added course lessons yet.</p>
                    <?php else: ?>
                        <div class="accordion" id="courseModules">
                            <?php $module_index = 0; foreach ($modules as $module): $module_index++; ?>
                                <div class="accordion-item">
                                    <h3 class="accordion-header" id="module-heading-<?php echo $module_index; ?>">
                                        <button class="accordion-button <?php echo $module_index > 1 ? 'collapsed' : ''; ?>"
                                                type="button" data-bs-toggle="collapse"
                                                data-bs-target="#module-content-<?php echo $module_index; ?>"
                                                aria-expanded="<?php echo $module_index === 1 ? 'true' : 'false'; ?>">
                                            <?php echo htmlspecialchars($module['title'], ENT_QUOTES, 'UTF-8'); ?>
                                            <span class="ms-auto me-3 small text-muted"><?php echo count($module['lessons']); ?> lessons</span>
                                        </button>
                                    </h3>
                                    <div id="module-content-<?php echo $module_index; ?>"
                                         class="accordion-collapse collapse <?php echo $module_index === 1 ? 'show' : ''; ?>"
                                         data-bs-parent="#courseModules">
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($module['lessons'] as $lesson): ?>
                                                <li class="list-group-item d-flex justify-content-between">
                                                    <span>
                                                        <?php if ($is_enrolled || (int) $lesson['is_previewable'] === 1): ?>
                                                            <a href="lesson_view.php?course_id=<?php echo $course_id; ?>&amp;lesson_id=<?php echo (int) $lesson['lesson_id']; ?>"
                                                               class="text-decoration-none">
                                                                <i class="far fa-play-circle text-primary me-2"></i>
                                                                <?php echo htmlspecialchars($lesson['lesson_title'], ENT_QUOTES, 'UTF-8'); ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <i class="fas fa-lock text-muted me-2"></i>
                                                        <?php echo htmlspecialchars($lesson['lesson_title'], ENT_QUOTES, 'UTF-8'); ?>
                                                        <?php endif; ?>
                                                        <?php if ((int) $lesson['is_previewable'] === 1): ?>
                                                            <span class="badge bg-info text-dark ms-1">Preview</span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <small class="text-muted"><?php echo (int) $lesson['duration_minutes']; ?> min</small>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
        <aside class="col-lg-4">
            <section class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5">Instructor</h2>
                    <p class="mb-1"><?php echo htmlspecialchars($course['instructor_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <small class="text-muted">Course instructor</small>
                </div>
            </section>
            <section class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Study buddies</h2>
                    <p class="small text-muted">Connect with other learners from the Study Buddies page.</p>
                    <a class="btn btn-outline-info w-100"
                       href="../admin/study_buddy.php?course_id=<?php echo $course_id; ?>">Find study buddies</a>
                </div>
            </section>
        </aside>
    </div>
</main>
<?php include __DIR__ . '/../includes/dashboard_footer.php'; ?>
