<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/instructor_helpers.php';
require_once BASE_URL . 'includes/assignment_helpers.php';

$instructor_id = (int) $_SESSION['user_id'];
$submission_id = filter_input(INPUT_GET, 'submission_id', FILTER_VALIDATE_INT);
if (!$submission_id || $submission_id < 1) {
    http_response_code(400);
    exit('A valid assignment submission is required.');
}

$review_stmt = $mysqli->prepare(
    'SELECT s.submission_id, s.enrollment_id, s.lesson_id, s.submission_content,
            s.submitted_at, UNIX_TIMESTAMP(s.submitted_at) AS submitted_at_epoch,
            s.grade, s.instructor_feedback, s.graded_at,
            UNIX_TIMESTAMP(s.graded_at) AS graded_at_epoch,
            u.first_name, u.last_name, u.email,
            c.course_id, c.title AS course_title,
            l.title AS assignment_title, l.text_content AS assignment_instructions, l.due_at
     FROM learning_assignment_submissions s
     INNER JOIN enrollments e ON e.enrollment_id = s.enrollment_id
     INNER JOIN users u ON u.user_id = e.user_id
     INNER JOIN lessons l ON l.lesson_id = s.lesson_id
     INNER JOIN modules m ON m.module_id = l.module_id
     INNER JOIN courses c ON c.course_id = m.course_id
     WHERE s.submission_id = ? AND c.instructor_id = ?
       AND l.content_type = \'assignment\'
       AND s.status = \'submitted\' AND s.submitted_at IS NOT NULL
     LIMIT 1'
);
$review_stmt->bind_param('ii', $submission_id, $instructor_id);
$review_stmt->execute();
$review = $review_stmt->get_result()->fetch_assoc();
$review_stmt->close();
if (!$review) {
    http_response_code(404);
    exit('Assignment submission not found.');
}

$page_title = 'Review Assignment';
$errors = [];
$score_input = (string) ($review['grade'] ?? '');
$feedback_input = (string) ($review['instructor_feedback'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $score_input = trim((string) ($_POST['grade'] ?? ''));
    $feedback_input = trim((string) ($_POST['feedback'] ?? ''));

    if (!instructor_verify_csrf()) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    }
    if (
        !is_numeric($score_input)
        || !preg_match('/\A(?:100(?:\.0{1,2})?|(?:\d{1,2})(?:\.\d{1,2})?)\z/', $score_input)
        || (float) $score_input < 0
        || (float) $score_input > 100
    ) {
        $errors[] = 'Enter a numeric score from 0 to 100, with no more than two decimal places.';
    }
    if (strlen($feedback_input) > 5000) {
        $errors[] = 'Feedback must be 5,000 characters or fewer.';
    }

    if (!$errors) {
        $transaction_started = false;
        try {
            $mysqli->begin_transaction();
            $transaction_started = true;

            $lock_stmt = $mysqli->prepare(
                'SELECT s.submission_id
                 FROM learning_assignment_submissions s
                 INNER JOIN lessons l ON l.lesson_id = s.lesson_id
                 INNER JOIN modules m ON m.module_id = l.module_id
                 INNER JOIN courses c ON c.course_id = m.course_id
                 WHERE s.submission_id = ? AND c.instructor_id = ?
                   AND l.content_type = \'assignment\'
                   AND s.status = \'submitted\' AND s.submitted_at IS NOT NULL
                 FOR UPDATE'
            );
            $lock_stmt->bind_param('ii', $submission_id, $instructor_id);
            $lock_stmt->execute();
            $locked_submission = $lock_stmt->get_result()->fetch_assoc();
            $lock_stmt->close();

            if (!$locked_submission) {
                $mysqli->rollback();
                $transaction_started = false;
                $errors[] = 'This submission is no longer available for review.';
            } else {
                $grade_value = (float) $score_input;
                $update_stmt = $mysqli->prepare(
                    'UPDATE learning_assignment_submissions
                     SET grade = ?, instructor_feedback = ?, graded_at = NOW()
                     WHERE submission_id = ? AND status = \'submitted\'
                       AND submitted_at IS NOT NULL'
                );
                $update_stmt->bind_param('dsi', $grade_value, $feedback_input, $submission_id);
                $update_stmt->execute();
                $update_stmt->close();
                $mysqli->commit();
                $transaction_started = false;

                instructor_flash('success', 'Assignment review saved and grade published.');
                header('Location: assignment_review.php?submission_id=' . $submission_id);
                exit;
            }
        } catch (Throwable $exception) {
            if ($transaction_started) {
                $mysqli->rollback();
            }
            error_log('Assignment review could not be saved: ' . $exception->getMessage());
            $errors[] = 'The review could not be saved right now. Please try again.';
        }
    }
}

$attachment_stmt = $mysqli->prepare(
    'SELECT attachment_id, original_name, file_size
     FROM learning_assignment_attachments
     WHERE submission_id = ? AND status = \'submitted\'
     ORDER BY created_at, attachment_id'
);
$attachment_stmt->bind_param('i', $submission_id);
$attachment_stmt->execute();
$attachments = $attachment_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$attachment_stmt->close();

$submitted_at = assignment_submission_time($review);
$deadline = assignment_format_deadline($review['due_at']);
$is_late = assignment_was_submitted_late($review, $deadline);
$page_title = 'Review Assignment';
include BASE_URL . 'includes/dashboard_header.php';
?>
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="h2 mb-0">Review Assignment</h1>
        <a class="btn btn-outline-secondary" href="assignment_reviews.php">Back to submissions</a>
    </div>
    <?php instructor_show_flash(); ?>
    <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-xl-5">
            <section class="card shadow-sm mb-4" aria-labelledby="student-details-heading">
                <div class="card-header bg-white"><h2 class="h5 mb-0" id="student-details-heading">Student and Assignment</h2></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Student</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt class="col-sm-4">Email</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($review['email'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt class="col-sm-4">Course</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($review['course_title'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt class="col-sm-4">Assignment</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($review['assignment_title'], ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt class="col-sm-4">Submitted</dt>
                        <dd class="col-sm-8">
                            <?php echo $submitted_at ? htmlspecialchars($submitted_at->format('M j, Y g:i a T'), ENT_QUOTES, 'UTF-8') : 'Unavailable'; ?>
                            <?php if ($is_late): ?><span class="badge bg-danger ms-1">Late</span><?php endif; ?>
                        </dd>
                        <?php if ($deadline): ?>
                            <dt class="col-sm-4">Deadline</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($deadline->format('M j, Y g:i a T'), ENT_QUOTES, 'UTF-8'); ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </section>

            <section class="card shadow-sm mb-4" aria-labelledby="instructions-heading">
                <div class="card-header bg-white"><h2 class="h5 mb-0" id="instructions-heading">Assignment Instructions</h2></div>
                <div class="card-body">
                    <?php if (trim((string) $review['assignment_instructions']) === ''): ?>
                        <p class="text-muted mb-0">No instructions were provided.</p>
                    <?php else: ?>
                        <div><?php echo nl2br(htmlspecialchars($review['assignment_instructions'], ENT_QUOTES, 'UTF-8')); ?></div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($review['grade'] !== null || $review['instructor_feedback'] !== null): ?>
                <section class="card shadow-sm" aria-labelledby="previous-review-heading">
                    <div class="card-header bg-white"><h2 class="h5 mb-0" id="previous-review-heading">Previous Review</h2></div>
                    <div class="card-body">
                        <?php if ($review['grade'] !== null): ?>
                            <p><strong>Grade:</strong> <?php echo number_format((float) $review['grade'], 2); ?> / 100</p>
                        <?php endif; ?>
                        <?php if ($review['graded_at_epoch'] !== null): ?>
                            <?php $previous_review_at = (new DateTimeImmutable('@' . (int) $review['graded_at_epoch']))->setTimezone(assignment_app_timezone()); ?>
                            <p><strong>Reviewed:</strong> <?php echo htmlspecialchars($previous_review_at->format('M j, Y g:i a T'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                        <?php if ($review['instructor_feedback'] !== null && $review['instructor_feedback'] !== ''): ?>
                            <div><strong>Feedback:</strong><br><?php echo nl2br(htmlspecialchars($review['instructor_feedback'], ENT_QUOTES, 'UTF-8')); ?></div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <div class="col-xl-7">
            <section class="card shadow-sm mb-4" aria-labelledby="submitted-answer-heading">
                <div class="card-header bg-white"><h2 class="h5 mb-0" id="submitted-answer-heading">Student Submission</h2></div>
                <div class="card-body">
                    <div class="border rounded p-3 bg-light-subtle" style="white-space: pre-wrap; overflow-wrap: anywhere;"><?php echo htmlspecialchars($review['submission_content'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php if ($attachments): ?>
                        <h3 class="h6 mt-4">Submitted Attachments</h3>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($attachments as $attachment): ?>
                                <li class="mb-2">
                                    <i class="fas fa-paperclip me-1" aria-hidden="true"></i>
                                    <form method="post" action="../pages/download_assignment_attachment.php" class="d-inline" data-no-action-feedback="true">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(instructor_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="attachment_id" value="<?php echo (int) $attachment['attachment_id']; ?>">
                                        <button class="btn btn-link btn-sm p-0 align-baseline" type="submit"><?php echo htmlspecialchars($attachment['original_name'], ENT_QUOTES, 'UTF-8'); ?></button>
                                    </form>
                                    <span class="text-muted small">(<?php echo number_format((int) $attachment['file_size'] / 1024, 0); ?> KB)</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted small mt-3 mb-0">No files were attached.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card shadow-sm" aria-labelledby="publish-grade-heading">
                <div class="card-header bg-white"><h2 class="h5 mb-0" id="publish-grade-heading">Save Review / Publish Grade</h2></div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(instructor_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="row g-3">
                            <div class="col-sm-4">
                                <label class="form-label" for="grade">Score (out of 100)</label>
                                <input class="form-control" type="number" id="grade" name="grade" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars($score_input, ENT_QUOTES, 'UTF-8'); ?>" required>
                                <div class="form-text">The existing grading scale is 0–100%.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="feedback">Instructor feedback</label>
                                <textarea class="form-control" id="feedback" name="feedback" rows="6" maxlength="5000"><?php echo htmlspecialchars($feedback_input, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <div class="form-text">Maximum 5,000 characters.</div>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary" type="submit">Save Review / Publish Grade</button>
                            </div>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</div>
<?php include BASE_URL . 'includes/dashboard_footer.php'; ?>
