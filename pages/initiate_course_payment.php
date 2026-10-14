<?php
require_once __DIR__ . '/../includes/student_helpers.php';
require_once __DIR__ . '/../includes/paystack_helpers.php';

function payment_redirect($course_id, $message)
{
    header('Location: course_detail.php?id=' . (int) $course_id . '&payment_error=' . rawurlencode($message));
    exit;
}

$course_id = filter_var($_POST['course_id'] ?? null, FILTER_VALIDATE_INT);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}
if (!student_verify_csrf()) {
    payment_redirect($course_id ?: 0, 'Your request could not be verified. Refresh the page and try again.');
}

$currency = strtoupper(trim((string) ($_POST['currency'] ?? '')));
$reference = null;
$transaction_started = false;
if (!$course_id || $course_id < 1 || !isset(paystack_supported_currencies()[$currency])) {
    payment_redirect($course_id ?: 0, 'Choose a valid course and payment currency.');
}

try {
    $course_stmt = $mysqli->prepare(
        "SELECT c.course_id, c.title,
                COALESCE(c.discounted_price, c.price) AS price_usd,
                c.is_free, u.email
         FROM courses c
         JOIN users u ON u.user_id = ?
         WHERE c.course_id = ? AND c.status = 'published'
         LIMIT 1"
    );
    $user_id = (int) $_SESSION['user_id'];
    $course_stmt->bind_param('ii', $user_id, $course_id);
    $course_stmt->execute();
    $course = $course_stmt->get_result()->fetch_assoc();
    if (!$course) {
        payment_redirect($course_id, 'This course is no longer available.');
    }
    if ((int) $course['is_free'] === 1 || (float) $course['price_usd'] <= 0) {
        payment_redirect($course_id, 'This course is free. Use the free enrollment button instead.');
    }

    $enrollment_stmt = $mysqli->prepare('SELECT enrollment_id FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
    $enrollment_stmt->bind_param('ii', $user_id, $course_id);
    $enrollment_stmt->execute();
    if ($enrollment_stmt->get_result()->fetch_assoc()) {
        header('Location: course_detail.php?id=' . $course_id);
        exit;
    }

    $callback_url = trim((string) getenv('PAYSTACK_CALLBACK_URL'));
    if ($callback_url === '' || filter_var($callback_url, FILTER_VALIDATE_URL) === false
        || !in_array(strtolower((string) parse_url($callback_url, PHP_URL_SCHEME)), ['https'], true)) {
        throw new RuntimeException('Paystack callback URL is not configured as a valid HTTPS URL.');
    }

    $exchange_rate = paystack_usd_exchange_rate($currency);
    $minor_units = paystack_currency_minor_units($currency);
    $amount_minor = (int) round((float) $course['price_usd'] * $exchange_rate * $minor_units);
    if ($amount_minor < 1) {
        throw new RuntimeException('Converted payment amount is too small for the selected currency.');
    }
    $amount_major = $amount_minor / $minor_units;
    $amount_minor_string = (string) $amount_minor;
    $reference = 'SL-' . bin2hex(random_bytes(20));

    $mysqli->begin_transaction();
    $transaction_started = true;
    $payment_stmt = $mysqli->prepare(
        "INSERT INTO course_payments
            (user_id, course_id, amount, base_amount_usd, exchange_rate, currency, amount_minor,
             payment_gateway, reference, payment_status)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'paystack', ?, 'pending')"
    );
    $base_amount_usd = (float) $course['price_usd'];
    $payment_stmt->bind_param(
        'iidddsss',
        $user_id,
        $course_id,
        $amount_major,
        $base_amount_usd,
        $exchange_rate,
        $currency,
        $amount_minor_string,
        $reference
    );
    $payment_stmt->execute();
    $mysqli->commit();
    $transaction_started = false;

    $gateway_result = paystack_api_request('POST', '/transaction/initialize', [
        'email' => $course['email'],
        'amount' => $amount_minor,
        'currency' => $currency,
        'reference' => $reference,
        'callback_url' => $callback_url,
        'metadata' => [
            'course_id' => $course_id,
            'student_id' => $user_id,
            'custom_fields' => [
                ['display_name' => 'Course', 'variable_name' => 'course_title', 'value' => $course['title']],
            ],
        ],
    ]);
    $authorization_url = $gateway_result['authorization_url'] ?? '';
    $authorization_host = parse_url($authorization_url, PHP_URL_HOST);
    if (!is_string($authorization_url) || !filter_var($authorization_url, FILTER_VALIDATE_URL)
        || strtolower((string) $authorization_host) !== 'checkout.paystack.com'
        || strtolower((string) parse_url($authorization_url, PHP_URL_SCHEME)) !== 'https') {
        throw new RuntimeException('Paystack did not return a valid secure checkout URL.');
    }

    header('Location: ' . $authorization_url, true, 303);
    exit;
} catch (Throwable $exception) {
    if ($transaction_started) {
        try {
            $mysqli->rollback();
        } catch (Throwable $rollback_exception) {
            error_log('Could not roll back payment initialization: ' . $rollback_exception->getMessage());
        }
    }
    if ($reference !== null) {
        try {
            $failed_stmt = $mysqli->prepare(
                "UPDATE course_payments SET payment_status = 'failed'
                 WHERE reference = ? AND payment_status = 'pending'"
            );
            $failed_stmt->bind_param('s', $reference);
            $failed_stmt->execute();
        } catch (Throwable $status_exception) {
            error_log('Could not mark failed Paystack initialization: ' . $status_exception->getMessage());
        }
    }
    error_log('Paystack course payment initialization failed: ' . $exception->getMessage());
    payment_redirect($course_id ?: 0, 'Payment could not be started. Please try again or contact support.');
}
