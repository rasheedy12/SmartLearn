<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/instructor_helpers.php';
require_once BASE_URL . 'includes/youtube_helpers.php';
$instructor_id = (int) $_SESSION['user_id'];
$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
if (!$course_id || $course_id < 1) {
    http_response_code(400);
    exit('A valid course is required.');
}
$course = instructor_require_course($mysqli, $course_id, $instructor_id);
$page_title = 'Manage Course Content';
$extra_js = [BASE_URL . 'assets/js/course-content.js'];
$errors = [];
$transaction_started = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!instructor_verify_csrf()) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'add_module') {
        $module_title = trim($_POST['module_title'] ?? '');
        if ($module_title === '' || strlen($module_title) > 255) {
            $errors[] = 'Enter a module title of no more than 255 characters.';
        } else {
            $order_stmt = $mysqli->prepare('SELECT COALESCE(MAX(order_index), 0) + 1 AS next_order FROM modules WHERE course_id = ?');
            $order_stmt->bind_param('i', $course_id);
            $order_stmt->execute();
            $order_index = (int) $order_stmt->get_result()->fetch_assoc()['next_order'];
            $order_stmt->close();

            $insert = $mysqli->prepare('INSERT INTO modules (course_id, title, order_index) VALUES (?, ?, ?)');
            $insert->bind_param('isi', $course_id, $module_title, $order_index);
            $insert->execute();
            $insert->close();
            instructor_flash('success', 'Module added.');
            header('Location: course_content.php?course_id=' . $course_id);
            exit;
        }
    } elseif (($_POST['action'] ?? '') === 'add_lesson') {
        $module_id = filter_var($_POST['module_id'] ?? null, FILTER_VALIDATE_INT);
        $lesson_title = trim($_POST['lesson_title'] ?? '');
        $content_type = $_POST['content_type'] ?? '';
        $content = trim($_POST['lesson_content'] ?? '');
        $is_previewable = isset($_POST['is_previewable']) ? 1 : 0;
        $allowed_types = ['video', 'text', 'quiz', 'assignment', 'external_link'];

        if (!$module_id || $module_id < 1) {
            $errors[] = 'Choose a valid module.';
        }
        if ($lesson_title === '' || strlen($lesson_title) > 255) {
            $errors[] = 'Enter a lesson title of no more than 255 characters.';
        }
        if (!in_array($content_type, $allowed_types, true)) {
            $errors[] = 'Choose a valid lesson type.';
        }
        if (in_array($content_type, ['text', 'video', 'external_link', 'assignment'], true) && $content === '') {
            $errors[] = $content_type === 'assignment'
                ? 'Enter the assignment instructions.'
                : 'Enter the lesson content or URL.';
        }
        if ($content_type === 'video' && youtube_video_id($content) === null) {
            $errors[] = 'Enter a valid YouTube video URL for video lessons.';
        }
        if ($content_type === 'external_link'
            && (!filter_var($content, FILTER_VALIDATE_URL)
                || !in_array(strtolower((string) parse_url($content, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            $errors[] = 'Enter a valid HTTP or HTTPS URL for external-link lessons.';
        }

        if (!$errors) {
            $module_check = $mysqli->prepare('SELECT module_id FROM modules WHERE module_id = ? AND course_id = ?');
            $module_check->bind_param('ii', $module_id, $course_id);
            $module_check->execute();
            $owns_module = $module_check->get_result()->fetch_assoc();
            $module_check->close();
            if (!$owns_module) {
                $errors[] = 'The selected module does not belong to this course.';
            } else {
                $order_stmt = $mysqli->prepare('SELECT COALESCE(MAX(order_index), 0) + 1 AS next_order FROM lessons WHERE module_id = ?');
                $order_stmt->bind_param('i', $module_id);
                $order_stmt->execute();
                $order_index = (int) $order_stmt->get_result()->fetch_assoc()['next_order'];
                $order_stmt->close();

                $video_url = $content_type === 'video' ? $content : null;
                $external_url = $content_type === 'external_link' ? $content : null;
                $text_content = in_array($content_type, ['text', 'assignment', 'quiz'], true) ? $content : null;
                $insert = $mysqli->prepare(
                    'INSERT INTO lessons (module_id, title, content_type, video_url, text_content, external_url, order_index, is_previewable)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $insert->bind_param('issssssi', $module_id, $lesson_title, $content_type, $video_url, $text_content, $external_url, $order_index, $is_previewable);
                $insert->execute();
                $insert->close();
                instructor_flash('success', 'Lesson added.');
                header('Location: course_content.php?course_id=' . $course_id);
                exit;
            }
        }
    } elseif (($_POST['action'] ?? '') === 'add_quiz_question') {
        $lesson_id = filter_var($_POST['lesson_id'] ?? null, FILTER_VALIDATE_INT);
        $question_text = trim((string) ($_POST['question_text'] ?? ''));
        $question_type = (string) ($_POST['question_type'] ?? '');
        $allowed_question_types = ['single_choice', 'multiple_choice', 'true_false'];
        $module_check = $mysqli->prepare(
            'SELECT l.lesson_id
             FROM lessons l
             JOIN modules m ON m.module_id = l.module_id
             WHERE l.lesson_id = ? AND l.content_type = \'quiz\' AND m.course_id = ?
             LIMIT 1'
        );
        $module_check->bind_param('ii', $lesson_id, $course_id);
        $module_check->execute();
        $quiz_lesson = $module_check->get_result()->fetch_assoc();
        $module_check->close();

        if (!$quiz_lesson) {
            $errors[] = 'Choose a quiz lesson in this course.';
        }
        if ($question_text === '' || strlen($question_text) > 4000) {
            $errors[] = 'Enter a question of no more than 4,000 characters.';
        }
        if (!in_array($question_type, $allowed_question_types, true)) {
            $errors[] = 'Choose a valid question type.';
        }

        $option_texts = [];
        if ($question_type === 'true_false') {
            $option_texts = ['True', 'False'];
        } else {
            foreach (array_slice((array) ($_POST['options'] ?? []), 0, 6) as $option_text) {
                $option_text = trim((string) $option_text);
                if ($option_text !== '') {
                    $option_texts[] = $option_text;
                }
            }
            if (count($option_texts) < 2) {
                $errors[] = 'Add at least two answer options.';
            }
            foreach ($option_texts as $option_text) {
                if (strlen($option_text) > 255) {
                    $errors[] = 'Each answer option must be no more than 255 characters.';
                    break;
                }
            }
        }

        $correct_indexes = array_values(array_unique(array_map(
            static fn($index) => filter_var($index, FILTER_VALIDATE_INT),
            (array) ($_POST['correct_options'] ?? [])
        )));
        $correct_indexes = array_values(array_filter(
            $correct_indexes,
            static fn($index) => $index !== false && $index >= 0 && $index < count($option_texts)
        ));
        if ($question_type === 'single_choice' && count($correct_indexes) !== 1) {
            $errors[] = 'Select exactly one correct answer.';
        } elseif ($question_type === 'multiple_choice' && count($correct_indexes) < 1) {
            $errors[] = 'Select at least one correct answer.';
        } elseif ($question_type === 'true_false' && count($correct_indexes) !== 1) {
            $errors[] = 'Select whether True or False is correct.';
        }

        if (!$errors) {
            $transaction_started = false;
            try {
                $mysqli->begin_transaction();
                $transaction_started = true;
                $order_stmt = $mysqli->prepare(
                    'SELECT COALESCE(MAX(order_index), 0) + 1 AS next_order
                     FROM learning_quiz_questions WHERE lesson_id = ?'
                );
                $order_stmt->bind_param('i', $lesson_id);
                $order_stmt->execute();
                $order_index = (int) $order_stmt->get_result()->fetch_assoc()['next_order'];
                $order_stmt->close();

                $question_stmt = $mysqli->prepare(
                    'INSERT INTO learning_quiz_questions (lesson_id, question_text, question_type, order_index)
                     VALUES (?, ?, ?, ?)'
                );
                $question_stmt->bind_param('issi', $lesson_id, $question_text, $question_type, $order_index);
                $question_stmt->execute();
                $question_id = (int) $question_stmt->insert_id;
                $question_stmt->close();

                $option_stmt = $mysqli->prepare(
                    'INSERT INTO learning_quiz_options (question_id, option_text, is_correct) VALUES (?, ?, ?)'
                );
                foreach ($option_texts as $index => $option_text) {
                    $is_correct = in_array($index, $correct_indexes, true) ? 1 : 0;
                    $option_stmt->bind_param('isi', $question_id, $option_text, $is_correct);
                    $option_stmt->execute();
                }
                $option_stmt->close();
                $mysqli->commit();
                $transaction_started = false;
                instructor_flash('success', 'Quiz question added.');
                header('Location: course_content.php?course_id=' . $course_id . '#quiz-' . $lesson_id);
                exit;
            } catch (Throwable $exception) {
                if ($transaction_started) {
                    $mysqli->rollback();
                }
                error_log('Quiz question could not be saved: ' . $exception->getMessage());
                $errors[] = 'The quiz question could not be saved. Please try again.';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'grade_assignment') {
        $submission_id = filter_var($_POST['submission_id'] ?? null, FILTER_VALIDATE_INT);
        $grade = trim((string) ($_POST['grade'] ?? ''));
        $feedback = trim((string) ($_POST['feedback'] ?? ''));
        if (!$submission_id || $submission_id < 1) {
            $errors[] = 'Choose a valid assignment submission.';
        }
        if (!is_numeric($grade) || (float) $grade < 0 || (float) $grade > 100) {
            $errors[] = 'Enter a grade between 0 and 100.';
        }
        if (strlen($feedback) > 5000) {
            $errors[] = 'Feedback must be 5,000 characters or fewer.';
        }

        if (!$errors) {
            $submission_check = $mysqli->prepare(
                'SELECT s.submission_id
                 FROM learning_assignment_submissions s
                 JOIN lessons l ON l.lesson_id = s.lesson_id
                 JOIN modules m ON m.module_id = l.module_id
                 WHERE s.submission_id = ? AND l.content_type = \'assignment\' AND m.course_id = ?
                 LIMIT 1'
            );
            $submission_check->bind_param('ii', $submission_id, $course_id);
            $submission_check->execute();
            $owned_submission = $submission_check->get_result()->fetch_assoc();
            $submission_check->close();

            if (!$owned_submission) {
                $errors[] = 'That submission is not available in this course.';
            }
        }

        if (!$errors) {
            $update = $mysqli->prepare(
                'UPDATE learning_assignment_submissions s
                 JOIN lessons l ON l.lesson_id = s.lesson_id
                 JOIN modules m ON m.module_id = l.module_id
                 JOIN courses c ON c.course_id = m.course_id
                 SET s.grade = ?, s.instructor_feedback = ?, s.graded_at = NOW()
                 WHERE s.submission_id = ? AND l.content_type = \'assignment\' AND c.course_id = ?'
            );
            $grade_value = (float) $grade;
            $update->bind_param('dsii', $grade_value, $feedback, $submission_id, $course_id);
            $update->execute();
            $update->close();
            instructor_flash('success', 'Assignment graded.');
            header('Location: course_content.php?course_id=' . $course_id . '#assignment-reviews');
            exit;
        }
    } else {
        $errors[] = 'Choose a valid content action.';
    }
}

$modules_stmt = $mysqli->prepare(
    'SELECT m.module_id, m.title,
            l.lesson_id, l.title AS lesson_title, l.content_type, l.order_index AS lesson_order
     FROM modules m
     LEFT JOIN lessons l ON l.module_id = m.module_id
     WHERE m.course_id = ?
     ORDER BY m.order_index, m.module_id, l.order_index, l.lesson_id'
);
$modules_stmt->bind_param('i', $course_id);
$modules_stmt->execute();
$module_rows = $modules_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$modules_stmt->close();
$modules = [];
foreach ($module_rows as $row) {
    $module_id = (int) $row['module_id'];
    if (!isset($modules[$module_id])) {
        $modules[$module_id] = ['module_id' => $module_id, 'title' => $row['title'], 'lessons' => []];
    }
    if ($row['lesson_id'] !== null) {
        $modules[$module_id]['lessons'][] = $row;
    }
}

$activity_rows_stmt = $mysqli->prepare(
    'SELECT l.lesson_id, l.title, l.content_type, l.text_content, m.title AS module_title,
            q.question_id, q.question_text, q.question_type, q.order_index AS question_order,
            o.option_text, o.is_correct
     FROM lessons l
     JOIN modules m ON m.module_id = l.module_id
     LEFT JOIN learning_quiz_questions q ON q.lesson_id = l.lesson_id
     LEFT JOIN learning_quiz_options o ON o.question_id = q.question_id
     WHERE m.course_id = ? AND l.content_type = \'quiz\'
     ORDER BY l.order_index, q.order_index, q.question_id, o.option_id'
);
$activity_rows_stmt->bind_param('i', $course_id);
$activity_rows_stmt->execute();
$quiz_rows = $activity_rows_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$activity_rows_stmt->close();
$quiz_lessons = [];
foreach ($quiz_rows as $row) {
    $lesson_id = (int) $row['lesson_id'];
    if (!isset($quiz_lessons[$lesson_id])) {
        $quiz_lessons[$lesson_id] = [
            'lesson_id' => $lesson_id,
            'title' => $row['title'],
            'instructions' => $row['text_content'],
            'questions' => [],
        ];
    }
    if ($row['question_id'] !== null) {
        $question_id = (int) $row['question_id'];
        if (!isset($quiz_lessons[$lesson_id]['questions'][$question_id])) {
            $quiz_lessons[$lesson_id]['questions'][$question_id] = [
                'text' => $row['question_text'],
                'type' => $row['question_type'],
                'options' => [],
            ];
        }
        if ($row['option_text'] !== null) {
            $quiz_lessons[$lesson_id]['questions'][$question_id]['options'][] = [
                'text' => $row['option_text'],
                'is_correct' => (int) $row['is_correct'] === 1,
            ];
        }
    }
}

$review_stmt = $mysqli->prepare(
    'SELECT s.submission_id, s.submission_content, s.submitted_at, s.grade, s.instructor_feedback,
            l.lesson_id, l.title AS assignment_title, c.title AS course_title,
            u.first_name, u.last_name, e.enrollment_id
     FROM learning_assignment_submissions s
     JOIN enrollments e ON e.enrollment_id = s.enrollment_id
     JOIN users u ON u.user_id = e.user_id
     JOIN lessons l ON l.lesson_id = s.lesson_id
     JOIN modules m ON m.module_id = l.module_id
     JOIN courses c ON c.course_id = m.course_id
     WHERE c.course_id = ? AND l.content_type = \'assignment\'
     ORDER BY s.graded_at IS NULL DESC, s.submitted_at DESC'
);
$review_stmt->bind_param('i', $course_id);
$review_stmt->execute();
$assignment_submissions = $review_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$review_stmt->close();

include BASE_URL . 'includes/dashboard_header.php';
?>
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div><h1 class="h2 mb-1">Manage Course Content</h1><p class="lead mb-0"><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></p></div>
        <a href="<?php echo INSTRUCTOR_ADMIN_URL; ?>instructor_courses.php" class="btn btn-outline-secondary">Back to My Courses</a>
    </div>
    <?php instructor_show_flash(); ?>
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <div class="row">
        <div class="col-lg-5 mb-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white"><h5 class="mb-0">Add Module</h5></div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(instructor_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="add_module">
                        <label class="form-label" for="moduleTitle">Module title</label>
                        <input class="form-control mb-3" id="moduleTitle" name="module_title" maxlength="255" required>
                        <button class="btn btn-primary" type="submit">Add Module</button>
                    </form>
                </div>
            </div>
            <?php if ($modules): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-white"><h5 class="mb-0">Add Lesson</h5></div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(instructor_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="add_lesson">
                            <div class="mb-3">
                                <label class="form-label" for="lessonModule">Module</label>
                                <select class="form-select" id="lessonModule" name="module_id" required>
                                    <?php foreach ($modules as $module): ?><option value="<?php echo $module['module_id']; ?>"><?php echo htmlspecialchars($module['title'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3"><label class="form-label" for="lessonTitle">Lesson title</label><input class="form-control" id="lessonTitle" name="lesson_title" maxlength="255" required></div>
                            <div class="mb-3">
                                <label class="form-label" for="contentType">Lesson type</label>
                                <select class="form-select" id="contentType" name="content_type" required>
                                    <option value="video">YouTube video URL</option><option value="text">Text lesson</option><option value="quiz">Quiz</option><option value="assignment">Assignment</option><option value="external_link">External link</option>
                                </select>
                            </div>
                            <div class="mb-3"><label class="form-label" for="lessonContent">Content, activity instructions, or URL</label><textarea class="form-control" id="lessonContent" name="lesson_content" rows="4"></textarea><small class="form-text text-muted">Assignment instructions are required. For quizzes, add questions after creating the lesson.</small></div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="lessonPreviewable" name="is_previewable" value="1">
                                    <label class="form-check-label" for="lessonPreviewable">Allow students to preview this lesson before enrolling</label>
                                </div>
                            <button class="btn btn-primary" type="submit">Add Lesson</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-0">Course Modules and Lessons</h5></div>
                <div class="card-body">
                    <?php if (!$modules): ?><p class="text-muted mb-0">No modules yet. Add your first module to start building this course.</p><?php endif; ?>
                    <?php foreach ($modules as $module): ?>
                        <div class="mb-4">
                            <h5><?php echo htmlspecialchars($module['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                            <?php if (!$module['lessons']): ?><p class="text-muted">No lessons in this module yet.</p><?php else: ?>
                                <ol class="list-group list-group-numbered">
                                    <?php foreach ($module['lessons'] as $lesson): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo htmlspecialchars($lesson['lesson_title'], ENT_QUOTES, 'UTF-8'); ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $lesson['content_type'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($quiz_lessons): ?>
        <section class="card shadow-sm mt-4">
            <div class="card-header bg-white"><h2 class="h5 mb-0">Quiz Questions</h2></div>
            <div class="card-body">
                <?php foreach ($quiz_lessons as $quiz): ?>
                    <article id="quiz-<?php echo $quiz['lesson_id']; ?>" class="border rounded p-3 mb-3">
                        <h3 class="h6"><?php echo htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <?php if (!empty($quiz['instructions'])): ?>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($quiz['instructions'], ENT_QUOTES, 'UTF-8')); ?></p>
                        <?php endif; ?>
                        <?php if ($quiz['questions']): ?>
                            <ol>
                                <?php foreach ($quiz['questions'] as $question): ?>
                                    <li class="mb-2">
                                        <?php echo htmlspecialchars($question['text'], ENT_QUOTES, 'UTF-8'); ?>
                                        <small class="text-muted">(<?php echo htmlspecialchars(str_replace('_', ' ', $question['type']), ENT_QUOTES, 'UTF-8'); ?>)</small>
                                        <ul class="small">
                                            <?php foreach ($question['options'] as $option): ?>
                                                <li><?php echo htmlspecialchars($option['text'], ENT_QUOTES, 'UTF-8'); ?><?php echo $option['is_correct'] ? ' — correct' : ''; ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php else: ?>
                            <p class="text-muted">No questions have been added yet.</p>
                        <?php endif; ?>
                        <form method="post" class="border-top pt-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(instructor_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="add_quiz_question">
                            <input type="hidden" name="lesson_id" value="<?php echo $quiz['lesson_id']; ?>">
                            <label class="form-label" for="question-<?php echo $quiz['lesson_id']; ?>">Question</label>
                            <textarea class="form-control mb-2" id="question-<?php echo $quiz['lesson_id']; ?>" name="question_text" rows="2" maxlength="4000" required></textarea>
                            <div class="row g-2 mb-2">
                                <?php for ($option_index = 0; $option_index < 4; $option_index++): ?>
                                    <div class="col-md-6" data-quiz-option-row>
                                        <label class="form-label" data-quiz-option-label for="option-<?php echo $quiz['lesson_id'] . '-' . $option_index; ?>">Option <?php echo $option_index + 1; ?></label>
                                        <div class="input-group">
                                            <input class="form-control" data-quiz-option-input id="option-<?php echo $quiz['lesson_id'] . '-' . $option_index; ?>" name="options[]" maxlength="255" required>
                                            <span class="input-group-text">
                                                <input type="checkbox" name="correct_options[]" value="<?php echo $option_index; ?>" aria-label="Mark option <?php echo $option_index + 1; ?> as correct">
                                            </span>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            <small class="form-text text-muted d-block mb-2">Mark correct answers. Select one for single choice, one or more for multiple choice.</small>
                            <label class="form-label" for="questionType-<?php echo $quiz['lesson_id']; ?>">Answer type</label>
                            <select class="form-select mb-2" id="questionType-<?php echo $quiz['lesson_id']; ?>" name="question_type">
                                <option value="single_choice">Single choice</option>
                                <option value="multiple_choice">Multiple choice</option>
                                <option value="true_false">True or False</option>
                            </select>
                            <button class="btn btn-sm btn-primary" type="submit">Add question</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section id="assignment-reviews" class="card shadow-sm mt-4">
        <div class="card-header bg-white"><h2 class="h5 mb-0">Assignment Submissions</h2></div>
        <?php if (!$assignment_submissions): ?>
            <div class="card-body"><p class="text-muted mb-0">No assignment submissions have been received for this course.</p></div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($assignment_submissions as $submission): ?>
                    <article class="list-group-item p-3">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <h3 class="h6 mb-1"><?php echo htmlspecialchars($submission['assignment_title'], ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <small class="text-muted">Submitted <?php echo htmlspecialchars(date('M j, Y g:i a', strtotime($submission['submitted_at'])), ENT_QUOTES, 'UTF-8'); ?></small>
                        </div>
                        <p class="my-2"><?php echo nl2br(htmlspecialchars($submission['submission_content'], ENT_QUOTES, 'UTF-8')); ?></p>
                        <form method="post" class="row g-2 align-items-end">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(instructor_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="grade_assignment">
                            <input type="hidden" name="submission_id" value="<?php echo (int) $submission['submission_id']; ?>">
                            <div class="col-sm-2">
                                <label class="form-label" for="grade-<?php echo (int) $submission['submission_id']; ?>">Grade (%)</label>
                                <input class="form-control" type="number" min="0" max="100" step="0.01" id="grade-<?php echo (int) $submission['submission_id']; ?>" name="grade" value="<?php echo htmlspecialchars((string) ($submission['grade'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="col-sm">
                                <label class="form-label" for="feedback-<?php echo (int) $submission['submission_id']; ?>">Feedback</label>
                                <textarea class="form-control" id="feedback-<?php echo (int) $submission['submission_id']; ?>" name="feedback" rows="2" maxlength="5000"><?php echo htmlspecialchars($submission['instructor_feedback'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                            <div class="col-sm-auto"><button class="btn btn-primary" type="submit">Save grade</button></div>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php include BASE_URL . 'includes/dashboard_footer.php'; ?>
