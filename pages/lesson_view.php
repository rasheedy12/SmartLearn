<?php
require_once __DIR__ . '/../includes/student_helpers.php';
require_once __DIR__ . '/../includes/youtube_helpers.php';
require_once __DIR__ . '/../includes/assignment_helpers.php';

$course_id = filter_var($_GET['course_id'] ?? null, FILTER_VALIDATE_INT);
$lesson_id = filter_var($_GET['lesson_id'] ?? null, FILTER_VALIDATE_INT);
$course_id = $course_id && $course_id > 0 ? $course_id : 0;
$lesson_id = $lesson_id && $lesson_id > 0 ? $lesson_id : 0;
$user_id = (int) $_SESSION['user_id'];
$error_message = '';
$lesson = null;
$enrollment = null;
$is_enrolled = false;
$playlist = [];
$quiz_questions = [];
$latest_quiz_attempt = null;
$assignment_submission = null;
$assignment_attachments = [];
$assignment_deadline = null;
$transaction_started = false;

try {
    if ($course_id > 0 && $lesson_id > 0) {
        $lesson_stmt = $mysqli->prepare(
            'SELECT c.course_id, c.title AS course_title, c.status AS course_status,
                    l.lesson_id, l.title AS lesson_title,
                    l.content_type, l.video_url, l.text_content, l.external_url,
                    l.duration_minutes, l.is_previewable, l.due_at, m.title AS module_title
             FROM lessons l
             JOIN modules m ON m.module_id = l.module_id
             JOIN courses c ON c.course_id = m.course_id
             WHERE c.course_id = ? AND l.lesson_id = ?
             LIMIT 1'
        );
        $lesson_stmt->bind_param('ii', $course_id, $lesson_id);
        $lesson_stmt->execute();
        $lesson = $lesson_stmt->get_result()->fetch_assoc();

        if ($lesson) {
            $enrollment_stmt = $mysqli->prepare(
                'SELECT enrollment_id, progress_percentage FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1'
            );
            $enrollment_stmt->bind_param('ii', $user_id, $course_id);
            $enrollment_stmt->execute();
            $enrollment = $enrollment_stmt->get_result()->fetch_assoc();
            $is_enrolled = $enrollment !== null;
        }
    }

    if ($lesson && ($is_enrolled || (
        $lesson['course_status'] === 'published' && (int) $lesson['is_previewable'] === 1
    ))) {
        $playlist_stmt = $mysqli->prepare(
            'SELECT l.lesson_id, l.title, l.content_type, l.is_previewable, l.duration_minutes,
                    COALESCE(lp.is_completed, 0) AS is_completed
             FROM modules m
             JOIN lessons l ON l.module_id = m.module_id
             LEFT JOIN lesson_progress lp ON lp.lesson_id = l.lesson_id AND lp.enrollment_id = ?
             WHERE m.course_id = ?
             ORDER BY m.order_index, l.order_index, l.lesson_id'
        );
        $enrollment_id = $is_enrolled ? (int) $enrollment['enrollment_id'] : 0;
        $playlist_stmt->bind_param('ii', $enrollment_id, $course_id);
        $playlist_stmt->execute();
        $playlist = $playlist_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if ($lesson['content_type'] === 'quiz' && $is_enrolled) {
            $questions_stmt = $mysqli->prepare(
                'SELECT q.question_id, q.question_text, q.question_type,
                        o.option_id, o.option_text, o.is_correct
                 FROM learning_quiz_questions q
                 LEFT JOIN learning_quiz_options o ON o.question_id = q.question_id
                 WHERE q.lesson_id = ?
                 ORDER BY q.order_index, q.question_id, o.option_id'
            );
            $questions_stmt->bind_param('i', $lesson_id);
            $questions_stmt->execute();
            foreach ($questions_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $question_id = (int) $row['question_id'];
                if (!isset($quiz_questions[$question_id])) {
                    $quiz_questions[$question_id] = [
                        'question_id' => $question_id,
                        'question_text' => $row['question_text'],
                        'question_type' => $row['question_type'],
                        'options' => [],
                    ];
                }
                if ($row['option_id'] !== null) {
                    $quiz_questions[$question_id]['options'][] = [
                        'option_id' => (int) $row['option_id'],
                        'option_text' => $row['option_text'],
                        'is_correct' => (int) $row['is_correct'] === 1,
                    ];
                }
            }
            $questions_stmt->close();

            $attempt_stmt = $mysqli->prepare(
                'SELECT score, completed_at
                 FROM learning_quiz_attempts
                 WHERE enrollment_id = ? AND lesson_id = ?
                 ORDER BY completed_at DESC, attempt_id DESC LIMIT 1'
            );
            $attempt_stmt->bind_param('ii', $enrollment['enrollment_id'], $lesson_id);
            $attempt_stmt->execute();
            $latest_quiz_attempt = $attempt_stmt->get_result()->fetch_assoc();
            $attempt_stmt->close();
        } elseif ($lesson['content_type'] === 'assignment' && $is_enrolled) {
            $submission_stmt = $mysqli->prepare(
                'SELECT submission_id, submission_content, status, draft_content, draft_version,
                        submitted_at, UNIX_TIMESTAMP(submitted_at) AS submitted_at_epoch,
                        grade, instructor_feedback, graded_at
                 FROM learning_assignment_submissions
                 WHERE enrollment_id = ? AND lesson_id = ? LIMIT 1'
            );
            $submission_stmt->bind_param('ii', $enrollment['enrollment_id'], $lesson_id);
            $submission_stmt->execute();
            $assignment_submission = $submission_stmt->get_result()->fetch_assoc();
            $submission_stmt->close();
            $assignment_deadline = assignment_format_deadline($lesson['due_at']);

            if ($assignment_submission) {
                $attachments_stmt = $mysqli->prepare(
                    'SELECT attachment_id, original_name, file_size, status, created_at
                     FROM learning_assignment_attachments
                     WHERE submission_id = ? AND status IN (\'draft\', \'submitted\')
                     ORDER BY created_at, attachment_id'
                );
                $attachments_stmt->bind_param('i', $assignment_submission['submission_id']);
                $attachments_stmt->execute();
                $assignment_attachments = $attachments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $attachments_stmt->close();
            }
        }
    }
} catch (Throwable $exception) {
    error_log('Course lesson could not be loaded: ' . $exception->getMessage());
    $error_message = 'This lesson could not be loaded right now.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $lesson) {
    $posted_action = (string) ($_POST['action'] ?? '');
    $is_ajax_draft = $posted_action === 'save_assignment_draft'
        && ($_POST['ajax'] ?? '') === '1';
    $is_ajax_request = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

    if (
        ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && $lesson['content_type'] === 'assignment'
        && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > assignment_parse_ini_bytes((string) ini_get('post_max_size'))
    ) {
        $error_message = 'The uploaded files exceed the server request limit. Your existing work is unchanged.';
        if ($is_ajax_draft || $is_ajax_request) {
            assignment_json_response(['success' => false, 'message' => $error_message], 413);
        }
        http_response_code(413);
    } elseif (!student_verify_csrf()) {
        http_response_code(400);
        $error_message = 'Your request could not be verified. Refresh the page and try again.';
        if ($is_ajax_draft) {
            assignment_json_response(['success' => false, 'message' => $error_message], 400);
        }
    } elseif (!$is_enrolled) {
        http_response_code(403);
        $error_message = 'Enroll in this course before completing this action.';
        if ($is_ajax_draft) {
            assignment_json_response(['success' => false, 'message' => $error_message], 403);
        }
    } elseif (($_POST['action'] ?? '') === 'complete_lesson') {
        try {
            $mysqli->begin_transaction();
            $transaction_started = true;
            student_record_lesson_completion($mysqli, (int) $enrollment['enrollment_id'], $lesson_id, $course_id);
            $mysqli->commit();
            $transaction_started = false;
            header('Location: lesson_view.php?course_id=' . $course_id . '&lesson_id=' . $lesson_id . '&completed=1');
            exit;
        } catch (Throwable $exception) {
            if ($transaction_started) {
                $mysqli->rollback();
            }
            error_log('Lesson completion could not be saved: ' . $exception->getMessage());
            $error_message = 'Your lesson progress could not be saved. Please try again.';
        }
    } elseif ($posted_action === 'save_assignment_draft' && $lesson['content_type'] === 'assignment') {
        $draft_content = $_POST['draft_content'] ?? '';
        $assignment_posted_content = is_string($draft_content) ? $draft_content : '';
        $draft_version = filter_var($_POST['draft_version'] ?? null, FILTER_VALIDATE_INT);
        $moved_paths = [];
        try {
            if (!is_string($draft_content)) {
                throw new InvalidArgumentException('Draft response must be plain text.');
            }
            if (mb_strlen($draft_content) > 20000) {
                throw new InvalidArgumentException('Draft responses may contain no more than 20,000 characters.');
            }
            $uploads = assignment_validate_uploads($_FILES['attachments'] ?? []);
            $storage_directory = $uploads ? assignment_storage_directory() : '';

            $mysqli->begin_transaction();
            $transaction_started = true;
            $submission_stmt = $mysqli->prepare(
                'SELECT submission_id, draft_version, submitted_at
                 FROM learning_assignment_submissions
                 WHERE enrollment_id = ? AND lesson_id = ?
                 FOR UPDATE'
            );
            $submission_stmt->bind_param('ii', $enrollment['enrollment_id'], $lesson_id);
            $submission_stmt->execute();
            $existing_submission = $submission_stmt->get_result()->fetch_assoc();
            $submission_stmt->close();

            if ($existing_submission) {
                if ($draft_version === false || $draft_version !== (int) $existing_submission['draft_version']) {
                    $mysqli->rollback();
                    $transaction_started = false;
                    $error_message = 'This draft changed in another tab. Reload the page before saving again.';
                    if ($is_ajax_draft) {
                        assignment_json_response([
                            'success' => false,
                            'message' => $error_message,
                            'conflict' => true,
                        ], 409);
                    }
                    throw new RuntimeException($error_message);
                }

                $submission_id = (int) $existing_submission['submission_id'];
                $update_stmt = $mysqli->prepare(
                    'UPDATE learning_assignment_submissions
                     SET draft_content = ?, draft_version = draft_version + 1
                     WHERE submission_id = ?'
                );
                $update_stmt->bind_param('si', $draft_content, $submission_id);
                $update_stmt->execute();
                $update_stmt->close();
            } else {
                if ($draft_version === false || $draft_version !== 0) {
                    $mysqli->rollback();
                    $transaction_started = false;
                    $error_message = 'This draft changed in another tab. Reload the page before saving again.';
                    if ($is_ajax_draft) {
                        assignment_json_response([
                            'success' => false,
                            'message' => $error_message,
                            'conflict' => true,
                        ], 409);
                    }
                    throw new RuntimeException($error_message);
                }
                $insert_stmt = $mysqli->prepare(
                    'INSERT INTO learning_assignment_submissions
                        (enrollment_id, lesson_id, submission_content, status, draft_content, draft_version, submitted_at)
                     VALUES (?, ?, \'\', \'draft\', ?, 1, NULL)'
                );
                $insert_stmt->bind_param('iis', $enrollment['enrollment_id'], $lesson_id, $draft_content);
                $insert_stmt->execute();
                $submission_id = (int) $insert_stmt->insert_id;
                $insert_stmt->close();
            }

            $draft_attachment_count_stmt = $mysqli->prepare(
                'SELECT COUNT(*) AS attachment_count
                 FROM learning_assignment_attachments
                 WHERE submission_id = ? AND status = \'draft\''
            );
            $draft_attachment_count_stmt->bind_param('i', $submission_id);
            $draft_attachment_count_stmt->execute();
            $draft_attachment_count = (int) $draft_attachment_count_stmt->get_result()->fetch_assoc()['attachment_count'];
            $draft_attachment_count_stmt->close();
            if ($draft_attachment_count + count($uploads) > 5) {
                throw new InvalidArgumentException('An assignment draft can have no more than five unsubmitted attachments.');
            }
            if ($uploads) {
                assignment_store_attachment_records(
                    $mysqli,
                    $submission_id,
                    $uploads,
                    $storage_directory,
                    $moved_paths
                );
            }

            $mysqli->commit();
            $transaction_started = false;
            if ($is_ajax_draft) {
                assignment_json_response([
                    'success' => true,
                    'message' => 'Draft saved',
                    'draft_version' => $existing_submission
                        ? (int) $existing_submission['draft_version'] + 1
                        : 1,
                    'display_status' => $existing_submission && !empty($existing_submission['submitted_at'])
                        ? null
                        : 'Draft',
                ]);
            }

            header('Location: lesson_view.php?course_id=' . $course_id . '&lesson_id=' . $lesson_id . '&draft_saved=1');
            exit;
        } catch (InvalidArgumentException $exception) {
            if ($transaction_started) {
                $mysqli->rollback();
                $transaction_started = false;
            }
            foreach ($moved_paths as $moved_path) {
                if (is_file($moved_path)) {
                    unlink($moved_path);
                }
            }
            $error_message = $exception->getMessage();
            if ($is_ajax_draft) {
                assignment_json_response(['success' => false, 'message' => $error_message], 422);
            }
        } catch (Throwable $exception) {
            if ($transaction_started) {
                $mysqli->rollback();
            }
            foreach ($moved_paths as $moved_path) {
                if (is_file($moved_path)) {
                    unlink($moved_path);
                }
            }
            error_log('Assignment draft could not be saved: ' . $exception->getMessage());
            $error_message = str_contains($exception->getMessage(), 'changed in another tab')
                ? $exception->getMessage()
                : 'Your assignment draft could not be saved. Your existing submission is unchanged.';
            if ($is_ajax_draft) {
                assignment_json_response(['success' => false, 'message' => $error_message], 500);
            }
        }
    } elseif ($posted_action === 'submit_assignment' && $lesson['content_type'] === 'assignment') {
        $submission_content = $_POST['submission_content'] ?? '';
        if (!is_string($submission_content)) {
            $submission_content = '';
        }
        $submission_content = trim($submission_content);
        $assignment_posted_content = $submission_content;
        if ($submission_content === '' || mb_strlen($submission_content) > 20000) {
            $error_message = 'Enter an assignment response of 1 to 20,000 characters.';
        } else {
            $moved_paths = [];
            try {
                $uploads = assignment_validate_uploads($_FILES['attachments'] ?? []);
                $storage_directory = $uploads ? assignment_storage_directory() : '';
                $mysqli->begin_transaction();
                $transaction_started = true;
                $existing_stmt = $mysqli->prepare(
                    'SELECT submission_id, grade, draft_version
                     FROM learning_assignment_submissions
                     WHERE enrollment_id = ? AND lesson_id = ?
                     FOR UPDATE'
                );
                $existing_stmt->bind_param('ii', $enrollment['enrollment_id'], $lesson_id);
                $existing_stmt->execute();
                $existing_submission = $existing_stmt->get_result()->fetch_assoc();
                $existing_stmt->close();

                if ($existing_submission && $existing_submission['grade'] !== null
                    && ($_POST['confirm_resubmission'] ?? '') !== '1') {
                    throw new InvalidArgumentException(
                        'Confirm that you understand resubmitting replaces the current grade and feedback.'
                    );
                }

                if ($existing_submission) {
                    $submission_id = (int) $existing_submission['submission_id'];
                    $update_stmt = $mysqli->prepare(
                        'UPDATE learning_assignment_submissions
                         SET submission_content = ?, status = \'submitted\', submitted_at = NOW(),
                             draft_content = NULL, draft_version = draft_version + 1,
                             grade = NULL, instructor_feedback = NULL, graded_at = NULL
                         WHERE submission_id = ?'
                    );
                    $update_stmt->bind_param('si', $submission_content, $submission_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                } else {
                    $insert_stmt = $mysqli->prepare(
                        'INSERT INTO learning_assignment_submissions
                            (enrollment_id, lesson_id, submission_content, status, submitted_at)
                         VALUES (?, ?, ?, \'submitted\', NOW())'
                    );
                    $insert_stmt->bind_param('iis', $enrollment['enrollment_id'], $lesson_id, $submission_content);
                    $insert_stmt->execute();
                    $submission_id = (int) $insert_stmt->insert_id;
                    $insert_stmt->close();
                }

                $draft_attachments_stmt = $mysqli->prepare(
                    'SELECT COUNT(*) AS attachment_count
                     FROM learning_assignment_attachments
                     WHERE submission_id = ? AND status = \'draft\''
                );
                $draft_attachments_stmt->bind_param('i', $submission_id);
                $draft_attachments_stmt->execute();
                $draft_attachment_count = (int) $draft_attachments_stmt->get_result()->fetch_assoc()['attachment_count'];
                $draft_attachments_stmt->close();
                if ($draft_attachment_count + count($uploads) > 5) {
                    throw new InvalidArgumentException('An assignment can have no more than five submitted attachments.');
                }
                if ($uploads) {
                    assignment_store_attachment_records(
                        $mysqli,
                        $submission_id,
                        $uploads,
                        $storage_directory,
                        $moved_paths
                    );
                }
                if ($draft_attachment_count + count($uploads) > 0) {
                    $supersede_stmt = $mysqli->prepare(
                        'UPDATE learning_assignment_attachments
                         SET status = \'superseded\'
                         WHERE submission_id = ? AND status = \'submitted\''
                    );
                    $supersede_stmt->bind_param('i', $submission_id);
                    $supersede_stmt->execute();
                    $supersede_stmt->close();
                    $submit_attachments_stmt = $mysqli->prepare(
                        'UPDATE learning_assignment_attachments
                         SET status = \'submitted\'
                         WHERE submission_id = ? AND status = \'draft\''
                    );
                    $submit_attachments_stmt->bind_param('i', $submission_id);
                    $submit_attachments_stmt->execute();
                    $submit_attachments_stmt->close();
                }

                student_record_lesson_completion($mysqli, (int) $enrollment['enrollment_id'], $lesson_id, $course_id);
                $mysqli->commit();
                $transaction_started = false;
                header('Location: lesson_view.php?course_id=' . $course_id . '&lesson_id=' . $lesson_id . '&assignment_submitted=1');
                exit;
            } catch (InvalidArgumentException $exception) {
                if ($transaction_started) {
                    $mysqli->rollback();
                    $transaction_started = false;
                }
                foreach ($moved_paths as $moved_path) {
                    if (is_file($moved_path)) {
                        unlink($moved_path);
                    }
                }
                $error_message = $exception->getMessage();
            } catch (Throwable $exception) {
                if ($transaction_started) {
                    $mysqli->rollback();
                }
                foreach ($moved_paths as $moved_path) {
                    if (is_file($moved_path)) {
                        unlink($moved_path);
                    }
                }
                error_log('Assignment submission could not be saved: ' . $exception->getMessage());
                $error_message = 'Your assignment could not be submitted. Please try again.';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'submit_quiz' && $lesson['content_type'] === 'quiz') {
        $posted_answers = $_POST['answers'] ?? [];
        $validated_answers = [];
        $correct_count = 0;
        $answer_error = '';

        if ($quiz_questions === []) {
            $answer_error = 'This quiz is not ready yet because it has no questions.';
        } elseif (!is_array($posted_answers) || count($posted_answers) !== count($quiz_questions)) {
            $answer_error = 'Answer every question before submitting the quiz.';
        } else {
            foreach ($quiz_questions as $question_id => $question) {
                $selected = $posted_answers[$question_id] ?? null;
                $selected_ids = is_array($selected) ? $selected : [$selected];
                $selected_ids = array_values(array_unique(array_filter(
                    array_map(static fn($id) => filter_var($id, FILTER_VALIDATE_INT), $selected_ids),
                    static fn($id) => $id !== false && $id > 0
                )));
                $valid_option_ids = array_column($question['options'], 'option_id');
                if (
                    $selected_ids === []
                    || count(array_diff($selected_ids, $valid_option_ids)) > 0
                    || ($question['question_type'] !== 'multiple_choice' && count($selected_ids) !== 1)
                ) {
                    $answer_error = 'Choose a valid answer for every question.';
                    break;
                }

                $correct_ids = array_column(
                    array_filter($question['options'], static fn($option) => $option['is_correct']),
                    'option_id'
                );
                sort($selected_ids);
                sort($correct_ids);
                $is_correct = $selected_ids === $correct_ids;
                $correct_count += $is_correct ? 1 : 0;
                $validated_answers[$question_id] = [
                    'selected_ids' => $selected_ids,
                    'is_correct' => $is_correct,
                ];
            }
        }

        if ($answer_error !== '') {
            $error_message = $answer_error;
        } else {
            $score = round($correct_count * 100 / count($quiz_questions), 2);
            try {
                $mysqli->begin_transaction();
                $transaction_started = true;
                $attempt_stmt = $mysqli->prepare(
                    'INSERT INTO learning_quiz_attempts (enrollment_id, lesson_id, score)
                     VALUES (?, ?, ?)'
                );
                $attempt_stmt->bind_param('iid', $enrollment['enrollment_id'], $lesson_id, $score);
                $attempt_stmt->execute();
                $attempt_id = (int) $attempt_stmt->insert_id;
                $attempt_stmt->close();

                $answer_stmt = $mysqli->prepare(
                    'INSERT INTO learning_quiz_attempt_answers
                        (attempt_id, question_id, option_id, is_correct)
                     VALUES (?, ?, ?, ?)'
                );
                foreach ($validated_answers as $question_id => $answer) {
                    foreach ($answer['selected_ids'] as $option_id) {
                        $is_correct = $answer['is_correct'] ? 1 : 0;
                        $answer_stmt->bind_param('iiii', $attempt_id, $question_id, $option_id, $is_correct);
                        $answer_stmt->execute();
                    }
                }
                $answer_stmt->close();
                student_record_lesson_completion($mysqli, (int) $enrollment['enrollment_id'], $lesson_id, $course_id);
                $mysqli->commit();
                $transaction_started = false;
                header('Location: lesson_view.php?course_id=' . $course_id . '&lesson_id=' . $lesson_id . '&quiz_submitted=1');
                exit;
            } catch (Throwable $exception) {
                if ($transaction_started) {
                    $mysqli->rollback();
                }
                error_log('Quiz attempt could not be saved: ' . $exception->getMessage());
                $error_message = 'Your quiz could not be submitted. Please try again.';
            }
        }
    }
}

$page_title = $lesson ? $lesson['lesson_title'] : 'Lesson unavailable';
$current_index = null;
foreach ($playlist as $index => $item) {
    if ((int) $item['lesson_id'] === $lesson_id) {
        $current_index = $index;
        break;
    }
}
$video_url = $lesson['video_url'] ?? '';
$youtube_id = youtube_video_id($video_url);
$direct_video = preg_match('~^https://[^\\s]+\\.(?:mp4|webm|ogg)(?:\\?[^\\s]*)?$~i', $video_url) === 1;
$external_link = $lesson['external_url'] ?? '';
$assignment_status = $assignment_submission
    ? assignment_display_status($assignment_submission, $assignment_deadline)
    : 'Not submitted';
$assignment_editor_content = isset($assignment_posted_content)
    ? $assignment_posted_content
    : (string) ($assignment_submission['draft_content'] ?? $assignment_submission['submission_content'] ?? '');
$extra_js = ['../assets/js/assignment-submission.js'];
include __DIR__ . '/../includes/dashboard_header.php';
?>
<div class="container-fluid">
    <?php if (!$lesson || (!$is_enrolled && (
        $lesson['course_status'] !== 'published' || (int) $lesson['is_previewable'] !== 1
    ))): ?>
        <div class="alert alert-warning">
            <h1 class="h4">Lesson unavailable</h1>
            <p><?php echo htmlspecialchars($error_message ?: 'This lesson does not exist or requires course enrollment.', ENT_QUOTES, 'UTF-8'); ?></p>
            <a class="btn btn-primary" href="course_detail.php?id=<?php echo $course_id; ?>">Back to course</a>
        </div>
    <?php else: ?>
        <?php if ($error_message !== ''): ?>
            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['completed']) && $_GET['completed'] === '1'): ?>
            <div class="alert alert-success" role="status">Lesson marked complete. Your progress has been updated.</div>
        <?php endif; ?>
        <?php if (isset($_GET['assignment_submitted']) && $_GET['assignment_submitted'] === '1'): ?>
            <div class="alert alert-success" role="status">Assignment submitted successfully. Your instructor will review it.</div>
        <?php endif; ?>
        <?php if (isset($_GET['draft_saved']) && $_GET['draft_saved'] === '1'): ?>
            <div class="alert alert-success" role="status">Draft saved. It has not been submitted or marked complete.</div>
        <?php endif; ?>
        <?php if (isset($_GET['quiz_submitted']) && $_GET['quiz_submitted'] === '1' && $latest_quiz_attempt): ?>
            <div class="alert alert-success" role="status">
                Quiz submitted. Your score is <?php echo number_format((float) $latest_quiz_attempt['score'], 2); ?>%.
            </div>
        <?php endif; ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div>
                <a href="course_detail.php?id=<?php echo $course_id; ?>" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i><?php echo htmlspecialchars($lesson['course_title'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
                <h1 class="h3 mt-2 mb-0"><?php echo htmlspecialchars($lesson['lesson_title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <small class="text-muted"><?php echo htmlspecialchars($lesson['module_title'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($lesson['duration_minutes'])): ?> · <?php echo (int) $lesson['duration_minutes']; ?> min<?php endif; ?>
                </small>
            </div>
            <?php if (!$is_enrolled): ?><span class="badge bg-info text-dark">Preview lesson</span><?php endif; ?>
        </div>

        <div class="row g-4">
            <main class="col-lg-8">
                <div class="bg-dark rounded p-2 mb-3">
                    <?php if ($youtube_id): ?>
                        <div class="ratio ratio-16x9">
                            <iframe src="https://www.youtube-nocookie.com/embed/<?php echo htmlspecialchars($youtube_id, ENT_QUOTES, 'UTF-8'); ?>"
                                    title="<?php echo htmlspecialchars($lesson['lesson_title'], ENT_QUOTES, 'UTF-8'); ?>"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                    referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                        </div>
                    <?php elseif ($direct_video): ?>
                        <video controls class="w-100">
                            <source src="<?php echo htmlspecialchars($video_url, ENT_QUOTES, 'UTF-8'); ?>">
                            Your browser does not support this video.
                        </video>
                    <?php elseif ($lesson['content_type'] === 'text' && !empty($lesson['text_content'])): ?>
                        <div class="bg-white text-dark rounded p-4">
                            <?php echo nl2br(htmlspecialchars($lesson['text_content'], ENT_QUOTES, 'UTF-8')); ?>
                        </div>
                    <?php elseif ($external_link !== '' && filter_var($external_link, FILTER_VALIDATE_URL)): ?>
                        <div class="text-center text-white py-5">
                            <p>This lesson opens an external resource.</p>
                            <a href="<?php echo htmlspecialchars($external_link, ENT_QUOTES, 'UTF-8'); ?>"
                               class="btn btn-light" target="_blank" rel="noopener noreferrer">Open lesson resource</a>
                        </div>
                    <?php elseif ($lesson['content_type'] === 'assignment'): ?>
                        <div class="text-center text-white py-5">
                            <i class="fas fa-file-pen fa-3x mb-3" aria-hidden="true"></i>
                            <p class="mb-0">Assignment activity</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-white py-5">No video or lesson content has been added.</div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($lesson['text_content']) && !in_array($lesson['content_type'], ['text', 'assignment'], true)): ?>
                    <section class="card mb-3">
                        <div class="card-body">
                            <h2 class="h5"><?php echo $lesson['content_type'] === 'assignment' ? 'Assignment instructions' : ($lesson['content_type'] === 'quiz' ? 'Quiz instructions' : 'Lesson notes'); ?></h2>
                            <?php echo nl2br(htmlspecialchars($lesson['text_content'], ENT_QUOTES, 'UTF-8')); ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($lesson['content_type'] === 'assignment' && $is_enrolled): ?>
                    <?php
                    $assignment_status_class = match ($assignment_status) {
                        'Late' => 'bg-danger',
                        'Graded', 'Returned' => 'bg-success',
                        'Submitted' => 'bg-primary',
                        'Draft' => 'bg-secondary',
                        default => 'bg-light text-dark border',
                    };
                    $deadline_iso = $assignment_deadline
                        ? $assignment_deadline->format(DateTimeInterface::ATOM)
                        : '';
                    $submission_date = $assignment_submission
                        ? assignment_submission_time($assignment_submission)
                        : null;
                    $draft_version = (int) ($assignment_submission['draft_version'] ?? 0);
                    ?>
                    <section class="card shadow-sm mb-3 assignment-panel"
                             <?php if ($deadline_iso !== ''): ?>data-assignment-deadline="<?php echo htmlspecialchars($deadline_iso, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <h2 class="h5 mb-0">Assignment submission</h2>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge <?php echo $assignment_status_class; ?>" data-assignment-status><?php echo htmlspecialchars($assignment_status, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($assignment_submission && assignment_was_submitted_late($assignment_submission, $assignment_deadline)): ?>
                                    <span class="badge bg-danger">Late submission</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <h3 class="h6"><?php echo htmlspecialchars($lesson['lesson_title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <?php if (!empty($lesson['text_content'])): ?>
                                <div class="assignment-instructions mb-3">
                                    <h4 class="small fw-bold">Instructions</h4>
                                    <div><?php echo nl2br(htmlspecialchars($lesson['text_content'], ENT_QUOTES, 'UTF-8')); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if ($assignment_deadline): ?>
                                <div class="alert alert-warning py-2" role="status">
                                    <strong>Deadline:</strong>
                                    <time datetime="<?php echo htmlspecialchars($deadline_iso, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($assignment_deadline->format('M j, Y g:i a T'), ENT_QUOTES, 'UTF-8'); ?>
                                    </time>
                                    <?php $remaining_seconds = $assignment_deadline->getTimestamp() - time(); ?>
                                    <span class="d-block small" data-deadline-countdown aria-live="polite">
                                        <?php if ($remaining_seconds > 0): ?>
                                            Time remaining: <?php echo (int) floor($remaining_seconds / 86400); ?>d
                                            <?php echo (int) floor(($remaining_seconds % 86400) / 3600); ?>h
                                            <?php echo (int) floor(($remaining_seconds % 3600) / 60); ?>m
                                        <?php else: ?>
                                            Deadline passed. Late submissions are accepted and will be marked Late.
                                        <?php endif; ?>
                                    </span>
                                    <span class="d-block small">Late submissions are accepted and marked Late.</span>
                                </div>
                            <?php else: ?>
                                <p class="small text-muted">No deadline has been set for this assignment.</p>
                            <?php endif; ?>

                            <?php if ($submission_date): ?>
                                <p class="small text-muted">
                                    Submitted <?php echo htmlspecialchars($submission_date->format('M j, Y g:i a T'), ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($assignment_submission && !empty($assignment_submission['submitted_at']) && $assignment_submission['draft_content'] !== null): ?>
                                <p class="small text-info">You have a saved draft. Your previous submitted response remains unchanged until you submit again.</p>
                            <?php endif; ?>

                            <?php if ($assignment_submission && $assignment_submission['grade'] !== null): ?>
                                <div class="alert alert-info" role="status">
                                    <strong><?php echo $assignment_status === 'Returned' ? 'Grade and feedback' : 'Grade'; ?>:</strong>
                                    <?php echo number_format((float) $assignment_submission['grade'], 2); ?>%
                                    <?php if (!empty($assignment_submission['instructor_feedback'])): ?>
                                        <div class="mt-2"><?php echo nl2br(htmlspecialchars($assignment_submission['instructor_feedback'], ENT_QUOTES, 'UTF-8')); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($assignment_submission && !empty($assignment_submission['submitted_at'])): ?>
                                <p class="text-muted">Your submission is awaiting instructor review.</p>
                            <?php endif; ?>

                            <?php if ($assignment_attachments): ?>
                                <div class="mb-3">
                                    <h4 class="small fw-bold">Attached files</h4>
                                    <ul class="list-unstyled mb-0">
                                        <?php foreach ($assignment_attachments as $attachment): ?>
                                            <li class="mb-1">
                                                <i class="fas fa-paperclip me-1" aria-hidden="true"></i>
                                                <form method="post" action="download_assignment_attachment.php" class="d-inline" data-no-action-feedback="true">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(student_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="attachment_id" value="<?php echo (int) $attachment['attachment_id']; ?>">
                                                    <button class="btn btn-link btn-sm p-0 align-baseline" type="submit">
                                                        <?php echo htmlspecialchars($attachment['original_name'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </button>
                                                </form>
                                                <span class="badge <?php echo $attachment['status'] === 'submitted' ? 'bg-primary' : 'bg-secondary'; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($attachment['status']), ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <form method="post" enctype="multipart/form-data" data-assignment-form>
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(student_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="draft_version" value="<?php echo $draft_version; ?>" data-draft-version>
                                <label class="form-label" for="assignmentResponse">Your response</label>
                                <div class="assignment-toolbar mb-2" role="group" aria-label="Basic response formatting">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-wrap-text="**" aria-label="Bold selected text"><strong>B</strong></button>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-wrap-text="_" aria-label="Italicize selected text"><em>I</em></button>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-wrap-text="`" aria-label="Mark selected text as code"><code>&lt;/&gt;</code></button>
                                </div>
                                <textarea class="form-control assignment-response-editor mb-2"
                                          id="assignmentResponse"
                                          name="submission_content"
                                          rows="9"
                                          maxlength="20000"
                                          aria-describedby="assignmentWordCount assignmentDraftStatus assignmentResponseHelp"
                                          data-assignment-response><?php echo htmlspecialchars($assignment_editor_content, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                                    <small class="text-muted" id="assignmentResponseHelp">Plain text response, up to 20,000 characters.</small>
                                    <small class="text-muted" id="assignmentWordCount" data-word-count aria-live="polite">0 words</small>
                                </div>

                                <div class="assignment-drop-zone mb-3" data-assignment-drop-zone>
                                    <i class="fas fa-cloud-arrow-up fa-lg mb-2" aria-hidden="true"></i>
                                    <label for="assignmentAttachments" class="form-label fw-semibold">Attach project files</label>
                                    <p class="small text-muted mb-2">Drop files here or browse. ZIP, PDF, HTML, CSS, JavaScript, PNG, and JPEG; up to five files, <?php echo number_format(assignment_max_file_bytes() / 1024 / 1024, 1); ?> MB each and <?php echo number_format(assignment_max_total_upload_bytes() / 1024 / 1024, 1); ?> MB total.</p>
                                    <input class="form-control" type="file" id="assignmentAttachments" name="attachments[]" multiple
                                           accept=".zip,.pdf,.html,.htm,.css,.js,.png,.jpg,.jpeg"
                                           data-max-file-bytes="<?php echo assignment_max_file_bytes(); ?>"
                                           data-max-total-bytes="<?php echo assignment_max_total_upload_bytes(); ?>"
                                           aria-describedby="assignmentFileList">
                                    <ul class="small text-muted mt-2 mb-0" id="assignmentFileList" data-selected-files aria-live="polite"></ul>
                                </div>

                                <p class="small mb-3" id="assignmentDraftStatus" data-draft-status role="status" aria-live="polite">
                                    <?php echo $assignment_submission && $assignment_submission['draft_content'] !== null ? 'Draft saved.' : 'Draft is not saved yet.'; ?>
                                </p>

                                <?php if ($assignment_submission && $assignment_submission['grade'] !== null): ?>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="confirmResubmission" name="confirm_resubmission" value="1">
                                        <label class="form-check-label" for="confirmResubmission">
                                            I understand that submitting this response will replace my previous submission and remove its grade and feedback.
                                        </label>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-outline-primary" type="submit" name="action" value="save_assignment_draft" data-save-draft>
                                        <i class="fas fa-floppy-disk me-1" aria-hidden="true"></i>Save Draft
                                    </button>
                                    <button class="btn btn-primary" type="submit" name="action" value="submit_assignment" data-submit-assignment>
                                        <i class="fas fa-paper-plane me-1" aria-hidden="true"></i><?php echo $assignment_submission && !empty($assignment_submission['submitted_at']) ? 'Submit Assignment Again' : 'Submit Assignment'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </section>
                <?php elseif ($lesson['content_type'] === 'quiz' && $is_enrolled): ?>
                    <section class="card mb-3">
                        <div class="card-header"><h2 class="h5 mb-0">Quiz</h2></div>
                        <div class="card-body">
                            <?php if ($latest_quiz_attempt): ?>
                                <p class="text-muted">Latest score: <strong><?php echo number_format((float) $latest_quiz_attempt['score'], 2); ?>%</strong>. You may retake this quiz.</p>
                            <?php endif; ?>
                            <?php if (!$quiz_questions): ?>
                                <p class="text-muted mb-0">Your instructor has not added quiz questions yet.</p>
                            <?php else: ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(student_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="submit_quiz">
                                    <?php foreach ($quiz_questions as $question): ?>
                                        <fieldset class="mb-4">
                                            <legend class="h6"><?php echo htmlspecialchars($question['question_text'], ENT_QUOTES, 'UTF-8'); ?></legend>
                                            <?php foreach ($question['options'] as $option): ?>
                                                <?php
                                                $multiple = $question['question_type'] === 'multiple_choice';
                                                $input_name = 'answers[' . $question['question_id'] . ']' . ($multiple ? '[]' : '');
                                                $input_id = 'answer-' . $question['question_id'] . '-' . $option['option_id'];
                                                ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="<?php echo $multiple ? 'checkbox' : 'radio'; ?>"
                                                           name="<?php echo htmlspecialchars($input_name, ENT_QUOTES, 'UTF-8'); ?>"
                                                           id="<?php echo $input_id; ?>"
                                                           value="<?php echo $option['option_id']; ?>"
                                                           <?php echo !$multiple ? 'required' : ''; ?>
                                                           <?php
                                                           $posted = $_POST['answers'][$question['question_id']] ?? [];
                                                           $posted = is_array($posted) ? $posted : [$posted];
                                                           echo in_array((string) $option['option_id'], array_map('strval', $posted), true) ? 'checked' : '';
                                                           ?>>
                                                    <label class="form-check-label" for="<?php echo $input_id; ?>"><?php echo htmlspecialchars($option['option_text'], ENT_QUOTES, 'UTF-8'); ?></label>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if ($question['question_type'] === 'multiple_choice'): ?>
                                                <small class="form-text text-muted">Select all correct answers.</small>
                                            <?php endif; ?>
                                        </fieldset>
                                    <?php endforeach; ?>
                                    <button class="btn btn-primary" type="submit">Submit quiz</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <?php
                    $previous = $current_index !== null && $current_index > 0 ? $playlist[$current_index - 1] : null;
                    $next = $current_index !== null && isset($playlist[$current_index + 1]) ? $playlist[$current_index + 1] : null;
                    ?>
                    <?php if ($previous && ($is_enrolled || (int) $previous['is_previewable'] === 1)): ?>
                        <a class="btn btn-outline-secondary"
                           href="lesson_view.php?course_id=<?php echo $course_id; ?>&amp;lesson_id=<?php echo (int) $previous['lesson_id']; ?>">
                            <i class="fas fa-chevron-left me-1"></i>Previous
                        </a>
                    <?php else: ?><span></span><?php endif; ?>
                    <?php if ($is_enrolled && !in_array($lesson['content_type'], ['quiz', 'assignment'], true)): ?>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(student_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="complete_lesson">
                            <button class="btn btn-success" type="submit">
                                <i class="fas fa-check-circle me-1"></i>Mark as complete
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if ($next && ($is_enrolled || (int) $next['is_previewable'] === 1)): ?>
                        <a class="btn btn-outline-secondary"
                           href="lesson_view.php?course_id=<?php echo $course_id; ?>&amp;lesson_id=<?php echo (int) $next['lesson_id']; ?>">
                            Next<i class="fas fa-chevron-right ms-1"></i>
                        </a>
                    <?php else: ?><span></span><?php endif; ?>
                </div>
            </main>

            <aside class="col-lg-4">
                <section class="card shadow-sm">
                    <div class="card-header"><h2 class="h5 mb-0">Course video playlist</h2></div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($playlist as $item): ?>
                            <?php
                            $available = $is_enrolled || (int) $item['is_previewable'] === 1;
                            $item_url = 'lesson_view.php?course_id=' . $course_id . '&lesson_id=' . (int) $item['lesson_id'];
                            ?>
                            <?php if ($available): ?>
                                <a class="list-group-item list-group-item-action <?php echo (int) $item['lesson_id'] === $lesson_id ? 'active' : ''; ?>"
                                   href="<?php echo htmlspecialchars($item_url, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php else: ?>
                                <div class="list-group-item text-muted">
                            <?php endif; ?>
                                <div class="d-flex align-items-start gap-2">
                                    <i class="fas <?php echo (int) $item['is_completed'] === 1 ? 'fa-check-circle text-success' : ($available ? 'fa-play-circle' : 'fa-lock'); ?> mt-1"></i>
                                    <span class="flex-grow-1"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ((int) $item['is_previewable'] === 1): ?><small class="d-block">Preview</small><?php endif; ?>
                                    </span>
                                    <?php if (!empty($item['duration_minutes'])): ?><small><?php echo (int) $item['duration_minutes']; ?>m</small><?php endif; ?>
                                </div>
                            <?php if ($available): ?></a><?php else: ?></div><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!$is_enrolled): ?>
                        <div class="card-body">
                            <p class="small text-muted">Enroll in this course to unlock the full playlist.</p>
                            <a class="btn btn-primary w-100" href="course_detail.php?id=<?php echo $course_id; ?>">View course and enroll</a>
                        </div>
                    <?php endif; ?>
                </section>
            </aside>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/dashboard_footer.php'; ?>
