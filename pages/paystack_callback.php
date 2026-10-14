<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/paystack_helpers.php';

function paystack_student_return($course_id, $user_id)
{
    if (!empty($_SESSION['loggedin']) && ($_SESSION['user_role'] ?? '') === 'student'
        && !empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === (int) $user_id) {
        header('Location: course_detail.php?id=' . (int) $course_id . '&payment_success=1');
    } else {
        header('Location: login.php?success=' . rawurlencode('Payment verified and course enrollment is complete. Sign in to start learning.'));
    }
    exit;
}

$reference = trim((string) ($_GET['reference'] ?? $_GET['trxref'] ?? ''));
$transaction_started = false;
if ($reference === '' || strlen($reference) > 100) {
    header('Location: browse_courses.php?payment_error=' . rawurlencode('The payment reference is missing or invalid.'));
    exit;
}


try {
    $payment_stmt = $mysqli->prepare(
        'SELECT payment_id, user_id, course_id, amount_minor, currency, payment_status
         FROM course_payments WHERE reference = ? LIMIT 1'
    );
    $payment_stmt->bind_param('s', $reference);
    $payment_stmt->execute();
    $payment = $payment_stmt->get_result()->fetch_assoc();
    if (!$payment) {
        header('Location: browse_courses.php?payment_error=' . rawurlencode('This payment could not be found for your account.'));
        exit;
    }

    $course_id = (int) $payment['course_id'];
    if ($payment['payment_status'] === 'completed') {
        paystack_student_return($course_id, $payment['user_id']);
    }

    $verification = paystack_api_request(
        'GET',
        '/transaction/verify/' . rawurlencode($reference)
    );
    if (($verification['status'] ?? '') !== 'success'
        || (string) ($verification['reference'] ?? '') !== $reference
        || (string) ($verification['amount'] ?? '') !== (string) $payment['amount_minor']
        || strtoupper((string) ($verification['currency'] ?? '')) !== strtoupper($payment['currency'])) {
        $failed_stmt = $mysqli->prepare(
            "UPDATE course_payments SET payment_status = 'failed' WHERE payment_id = ? AND payment_status = 'pending'"
        );
        $failed_stmt->bind_param('i', $payment['payment_id']);
        $failed_stmt->execute();
        header('Location: course_detail.php?id=' . $course_id . '&payment_error=' . rawurlencode('Payment was not verified. No enrollment was created.'));
        exit;
    }

    $mysqli->begin_transaction();
    $transaction_started = true;
    $lock_stmt = $mysqli->prepare(
        'SELECT payment_id, user_id, course_id, amount_minor, currency, payment_status
         FROM course_payments WHERE payment_id = ? FOR UPDATE'
    );
    $lock_stmt->bind_param('i', $payment['payment_id']);
    $lock_stmt->execute();
    $locked_payment = $lock_stmt->get_result()->fetch_assoc();
    if (!$locked_payment || $locked_payment['payment_status'] === 'failed'
        || (string) $locked_payment['amount_minor'] !== (string) $payment['amount_minor']
        || $locked_payment['currency'] !== $payment['currency']) {
        throw new RuntimeException('The pending payment changed while verification was in progress.');
    }

    $enrollment_id = null;
    $existing_stmt = $mysqli->prepare(
        'SELECT enrollment_id FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1'
    );
    $existing_stmt->bind_param('ii', $payment['user_id'], $payment['course_id']);
    $existing_stmt->execute();
    $existing_enrollment = $existing_stmt->get_result()->fetch_assoc();
    if ($existing_enrollment) {
        $enrollment_id = (int) $existing_enrollment['enrollment_id'];
    } else {
        $enroll_stmt = $mysqli->prepare(
            'INSERT INTO enrollments (user_id, course_id, enrolled_at, progress_percentage) VALUES (?, ?, NOW(), 0)'
        );
        $enroll_stmt->bind_param('ii', $payment['user_id'], $payment['course_id']);
        $enroll_stmt->execute();
        $enrollment_id = (int) $enroll_stmt->insert_id;

        $count_stmt = $mysqli->prepare(
            'UPDATE courses SET enrolled_students_count = enrolled_students_count + 1 WHERE course_id = ?'
        );
        $count_stmt->bind_param('i', $payment['course_id']);
        $count_stmt->execute();
    }

    $complete_stmt = $mysqli->prepare(
        "UPDATE course_payments
         SET payment_status = 'completed', enrollment_id = ?
         WHERE payment_id = ? AND payment_status = 'pending'"
    );
    $complete_stmt->bind_param('ii', $enrollment_id, $payment['payment_id']);
    $complete_stmt->execute();
    if ($complete_stmt->affected_rows !== 1 && $locked_payment['payment_status'] !== 'completed') {
        throw new RuntimeException('Payment record could not be marked complete.');
    }
    $mysqli->commit();
    $transaction_started = false;
    paystack_student_return($course_id, $payment['user_id']);
} catch (Throwable $exception) {
    if ($transaction_started) {
        $mysqli->rollback();
    }
    error_log('Paystack callback verification failed: ' . $exception->getMessage());
    $course_id = isset($course_id) ? (int) $course_id : 0;
    if ($course_id > 0) {
        header('Location: course_detail.php?id=' . $course_id . '&payment_error=' . rawurlencode('Payment verification is temporarily unavailable. Your enrollment will not be enabled until verification succeeds.'));
    } else {
        header('Location: browse_courses.php?payment_error=' . rawurlencode('Payment verification is temporarily unavailable.'));
    }
    exit;
}
