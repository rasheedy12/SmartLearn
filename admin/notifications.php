<?php
define('BASE_URL', '../');
require_once __DIR__ . '/../includes/auth_session.php';
smartlearn_start_secure_session();

$allowed_roles = ['student', 'instructor', 'admin'];
if (empty($_SESSION['loggedin']) || empty($_SESSION['user_id'])
    || !in_array($_SESSION['user_role'] ?? '', $allowed_roles, true)) {
    header('Location: ' . BASE_URL . 'pages/login.php');
    exit;
}

require_once __DIR__ . '/../includes/db_config.php';
$user_id = (int) $_SESSION['user_id'];
if (empty($_SESSION['notification_csrf_token'])) {
    $_SESSION['notification_csrf_token'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!isset($_POST['csrf_token'], $_SESSION['notification_csrf_token'])
        || !is_string($_POST['csrf_token'])
        || !hash_equals($_SESSION['notification_csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        exit('Your notification request could not be verified. Refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'mark_all_read') {
            $update_stmt = $mysqli->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
            $update_stmt->bind_param('i', $user_id);
            $update_stmt->execute();
            $update_stmt->close();
            $_SESSION['notification_flash'] = ['success', 'All notifications marked as read.'];
        } elseif ($action === 'mark_read') {
            $raw_notification_id = $_POST['notification_id'] ?? null;
            $notification_id = is_string($raw_notification_id) || is_int($raw_notification_id)
                ? filter_var($raw_notification_id, FILTER_VALIDATE_INT)
                : false;
            if ($notification_id === false || $notification_id < 1) {
                http_response_code(400);
                exit('A valid notification is required.');
            }
            $update_stmt = $mysqli->prepare(
                'UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ? AND is_read = 0'
            );
            $update_stmt->bind_param('ii', $notification_id, $user_id);
            $update_stmt->execute();
            $update_stmt->close();
            $_SESSION['notification_flash'] = ['success', 'Notification marked as read.'];
        } else {
            http_response_code(400);
            exit('That notification action is not available.');
        }
    } catch (Throwable $exception) {
        error_log('Notification update failed: ' . $exception->getMessage());
        $_SESSION['notification_flash'] = ['danger', 'Notifications could not be updated. Please try again.'];
    }

    header('Location: notifications.php', true, 303);
    exit;
}

$page_title = 'Notifications';
$notification_flash = $_SESSION['notification_flash'] ?? null;
unset($_SESSION['notification_flash']);
$notifications = [];
$unread_count = 0;
$notification_load_error = '';

try {
    $count_stmt = $mysqli->prepare('SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND is_read = 0');
    $count_stmt->bind_param('i', $user_id);
    $count_stmt->execute();
    $unread_count = (int) $count_stmt->get_result()->fetch_assoc()['unread_count'];
    $count_stmt->close();

    $list_stmt = $mysqli->prepare(
        'SELECT notification_id, title, message, type, is_read, created_at
         FROM notifications
         WHERE user_id = ?
         ORDER BY created_at DESC, notification_id DESC
         LIMIT 50'
    );
    $list_stmt->bind_param('i', $user_id);
    $list_stmt->execute();
    $notifications = $list_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $list_stmt->close();
} catch (Throwable $exception) {
    error_log('Notification list load failed: ' . $exception->getMessage());
    $notification_load_error = 'Notifications are temporarily unavailable. Please try again later.';
}

$notification_type_labels = [
    'info' => 'Update',
    'warning' => 'Reminder',
    'error' => 'Alert',
    'success' => 'Success',
    'course_update' => 'Course update',
    'new_message' => 'Message',
];
$notification_type_classes = [
    'info' => 'text-bg-info',
    'warning' => 'text-bg-warning',
    'error' => 'text-bg-danger',
    'success' => 'text-bg-success',
    'course_update' => 'text-bg-primary',
    'new_message' => 'text-bg-secondary',
];

include __DIR__ . '/../includes/dashboard_header.php';
?>
<div class="container-fluid">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h2 mb-1">Notifications</h1>
            <p class="text-muted mb-0"><?php echo $unread_count; ?> unread</p>
        </div>
        <?php if ($unread_count > 0): ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['notification_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="mark_all_read">
                <button class="btn btn-outline-primary" type="submit">Mark all as read</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (is_array($notification_flash) && isset($notification_flash[0], $notification_flash[1])): ?>
        <div class="alert alert-<?php echo htmlspecialchars($notification_flash[0], ENT_QUOTES, 'UTF-8'); ?>" role="status">
            <?php echo htmlspecialchars($notification_flash[1], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>
    <?php if ($notification_load_error !== ''): ?>
        <div class="alert alert-warning" role="alert"><?php echo htmlspecialchars($notification_load_error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php elseif (!$notifications): ?>
        <div class="card shadow-sm"><div class="card-body text-center py-5">
            <i class="fas fa-bell-slash fa-2x text-muted mb-3" aria-hidden="true"></i>
            <h2 class="h5">You’re all caught up</h2>
            <p class="text-muted mb-0">New messages, course announcements, and reminders will appear here.</p>
        </div></div>
    <?php else: ?>
        <div class="d-flex flex-column gap-3">
            <?php foreach ($notifications as $notification): ?>
                <?php
                $type = (string) ($notification['type'] ?? 'info');
                $type_label = $notification_type_labels[$type] ?? 'Update';
                $type_class = $notification_type_classes[$type] ?? 'text-bg-info';
                $created_at = strtotime((string) $notification['created_at']);
                ?>
                <article class="card shadow-sm<?php echo (int) $notification['is_read'] === 0 ? ' border-primary' : ''; ?>">
                    <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-3">
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <span class="badge <?php echo $type_class; ?>"><?php echo htmlspecialchars($type_label, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ((int) $notification['is_read'] === 0): ?>
                                    <span class="small text-primary fw-semibold">Unread</span>
                                <?php endif; ?>
                            </div>
                            <h2 class="h5 mb-2"><?php echo htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                            <p class="mb-2"><?php echo nl2br(htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8')); ?></p>
                            <time class="small text-muted" datetime="<?php echo htmlspecialchars(date('c', $created_at ?: time()), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars(date('M j, Y g:i a', $created_at ?: time()), ENT_QUOTES, 'UTF-8'); ?>
                            </time>
                        </div>
                        <?php if ((int) $notification['is_read'] === 0): ?>
                            <form method="post" class="align-self-md-center">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['notification_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="notification_id" value="<?php echo (int) $notification['notification_id']; ?>">
                                <button class="btn btn-sm btn-outline-primary" type="submit">Mark as read</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if (count($notifications) >= 50): ?>
            <p class="small text-muted mt-3">Showing the 50 most recent notifications.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/dashboard_footer.php'; ?>
