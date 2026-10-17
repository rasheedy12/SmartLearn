<?php
require_once __DIR__ . '/../includes/student_helpers.php';

define('BASE_URL', '../');
$student_id = (int) $_SESSION['user_id'];
$page_title = 'Study Buddies';
$search = trim((string) ($_GET['q'] ?? ''));
$course_filter = filter_var($_GET['course_id'] ?? null, FILTER_VALIDATE_INT);
$course_filter = $course_filter && $course_filter > 0 ? $course_filter : 0;
$error_message = '';
$success_message = (string) ($_SESSION['study_buddy_flash'] ?? '');
unset($_SESSION['study_buddy_flash']);
$transaction_started = false;

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!student_verify_csrf()) {
            throw new InvalidArgumentException('Your request could not be verified. Refresh the page and try again.');
        }

        $action = (string) ($_POST['action'] ?? '');
        $target_id = filter_var($_POST['target_id'] ?? null, FILTER_VALIDATE_INT);

        if ($action === 'connect') {
            if (!$target_id || $target_id === $student_id) {
                throw new InvalidArgumentException('Choose a valid student to connect with.');
            }
            $target_stmt = $mysqli->prepare(
                "SELECT user_id FROM users WHERE user_id = ? AND role = 'student' AND status = 'active' LIMIT 1"
            );
            $target_stmt->bind_param('i', $target_id);
            $target_stmt->execute();
            if (!$target_stmt->get_result()->fetch_assoc()) {
                throw new InvalidArgumentException('That student is unavailable.');
            }
            $low_id = min($student_id, $target_id);
            $high_id = max($student_id, $target_id);
            $existing_stmt = $mysqli->prepare(
                'SELECT connection_id, requester_id, recipient_id, status
                 FROM study_buddy_connections WHERE user_low_id = ? AND user_high_id = ? LIMIT 1'
            );
            $existing_stmt->bind_param('ii', $low_id, $high_id);
            $existing_stmt->execute();
            $connection = $existing_stmt->get_result()->fetch_assoc();
            if ($connection && $connection['status'] === 'accepted') {
                $success_message = 'You are already connected with this student.';
            } elseif ($connection && (int) $connection['requester_id'] === $target_id
                && (int) $connection['recipient_id'] === $student_id) {
                $success_message = 'This student has already sent you a request. Accept it from Incoming Requests.';
            } elseif ($connection && (int) $connection['requester_id'] === $student_id
                && $connection['status'] === 'pending') {
                $success_message = 'Your connection request is already pending.';
            } elseif ($connection) {
                $update = $mysqli->prepare(
                    "UPDATE study_buddy_connections
                     SET requester_id = ?, recipient_id = ?, status = 'pending', updated_at = NOW()
                     WHERE connection_id = ?"
                );
                $update->bind_param('iii', $student_id, $target_id, $connection['connection_id']);
                $update->execute();
                $success_message = 'Connection request sent.';
            } else {
                $insert = $mysqli->prepare(
                    "INSERT INTO study_buddy_connections
                        (requester_id, recipient_id, user_low_id, user_high_id, status)
                     VALUES (?, ?, ?, ?, 'pending')"
                );
                $insert->bind_param('iiii', $student_id, $target_id, $low_id, $high_id);
                $insert->execute();
                $success_message = 'Connection request sent.';
            }
        } elseif (in_array($action, ['accept_connection', 'decline_connection', 'remove_connection'], true)) {
            if (!$target_id) {
                throw new InvalidArgumentException('Choose a valid connection request.');
            }
            if ($action === 'accept_connection' || $action === 'decline_connection') {
                if ($action === 'accept_connection') {
                    $stmt = $mysqli->prepare(
                        "UPDATE study_buddy_connections SET status = 'accepted', updated_at = NOW()
                         WHERE connection_id = ? AND recipient_id = ? AND status = 'pending'"
                    );
                    $stmt->bind_param('ii', $target_id, $student_id);
                } else {
                    $stmt = $mysqli->prepare(
                        'DELETE FROM study_buddy_connections
                         WHERE connection_id = ? AND recipient_id = ? AND status = \'pending\''
                    );
                    $stmt->bind_param('ii', $target_id, $student_id);
                }
                $stmt->execute();
                if ($stmt->affected_rows !== 1) {
                    throw new InvalidArgumentException('That request has already changed or is no longer available.');
                }
                $success_message = $action === 'accept_connection'
                    ? 'Connection accepted.'
                    : 'Connection request declined.';
            } else {
                $stmt = $mysqli->prepare(
                    "DELETE FROM study_buddy_connections
                     WHERE connection_id = ? AND status = 'accepted'
                       AND (requester_id = ? OR recipient_id = ?)"
                );
                $stmt->bind_param('iii', $target_id, $student_id, $student_id);
                $stmt->execute();
                if ($stmt->affected_rows !== 1) {
                    throw new InvalidArgumentException('That connection could not be removed.');
                }
                $success_message = 'Connection removed.';
            }
        } elseif ($action === 'create_group') {
            $group_name = trim((string) ($_POST['group_name'] ?? ''));
            $group_description = trim((string) ($_POST['group_description'] ?? ''));
            $group_course_id = filter_var($_POST['group_course_id'] ?? null, FILTER_VALIDATE_INT);
            $group_course_id = $group_course_id && $group_course_id > 0 ? $group_course_id : null;
            if ($group_name === '' || mb_strlen($group_name) > 120) {
                throw new InvalidArgumentException('Enter a group name of 1 to 120 characters.');
            }
            if (mb_strlen($group_description) > 2000) {
                throw new InvalidArgumentException('Group descriptions cannot exceed 2,000 characters.');
            }
            if ($group_course_id !== null) {
                $owned_course = $mysqli->prepare(
                    'SELECT c.course_id FROM courses c
                     JOIN enrollments e ON e.course_id = c.course_id
                     WHERE c.course_id = ? AND e.user_id = ? LIMIT 1'
                );
                $owned_course->bind_param('ii', $group_course_id, $student_id);
                $owned_course->execute();
                if (!$owned_course->get_result()->fetch_assoc()) {
                    throw new InvalidArgumentException('Choose a course you are enrolled in, or leave the course blank.');
                }
            }

            $mysqli->begin_transaction();
            $transaction_started = true;
            $insert_group = $mysqli->prepare(
                'INSERT INTO study_buddy_groups (name, description, course_id, creator_id)
                 VALUES (?, ?, ?, ?)'
            );
            $insert_group->bind_param('ssii', $group_name, $group_description, $group_course_id, $student_id);
            $insert_group->execute();
            $new_group_id = (int) $insert_group->insert_id;
            $add_creator = $mysqli->prepare(
                "INSERT INTO study_buddy_group_members (group_id, user_id, role) VALUES (?, ?, 'admin')"
            );
            $add_creator->bind_param('ii', $new_group_id, $student_id);
            $add_creator->execute();
            $mysqli->commit();
            $transaction_started = false;
            $success_message = 'Study group created. You are its first member.';
        } elseif (in_array($action, ['join_group', 'leave_group'], true)) {
            if (!$target_id) {
                throw new InvalidArgumentException('Choose a valid study group.');
            }
            $group_stmt = $mysqli->prepare(
                'SELECT group_id FROM study_buddy_groups WHERE group_id = ? LIMIT 1'
            );
            $group_stmt->bind_param('i', $target_id);
            $group_stmt->execute();
            if (!$group_stmt->get_result()->fetch_assoc()) {
                throw new InvalidArgumentException('That study group is unavailable.');
            }
            if ($action === 'join_group') {
                $member_stmt = $mysqli->prepare(
                    "INSERT IGNORE INTO study_buddy_group_members (group_id, user_id, role)
                     VALUES (?, ?, 'member')"
                );
                $member_stmt->bind_param('ii', $target_id, $student_id);
                $member_stmt->execute();
                $success_message = $member_stmt->affected_rows === 1
                    ? 'You joined the study group.'
                    : 'You are already a member of this group.';
            } else {
                $membership_stmt = $mysqli->prepare(
                    'SELECT role FROM study_buddy_group_members WHERE group_id = ? AND user_id = ? LIMIT 1'
                );
                $membership_stmt->bind_param('ii', $target_id, $student_id);
                $membership_stmt->execute();
                $membership = $membership_stmt->get_result()->fetch_assoc();
                if (!$membership) {
                    throw new InvalidArgumentException('You are not a member of that group.');
                }

                $mysqli->begin_transaction();
                $transaction_started = true;
                if ($membership['role'] === 'admin') {
                    $next_admin_stmt = $mysqli->prepare(
                        "SELECT group_member_id, user_id FROM study_buddy_group_members
                         WHERE group_id = ? AND user_id <> ?
                         ORDER BY joined_at, group_member_id LIMIT 1"
                    );
                    $next_admin_stmt->bind_param('ii', $target_id, $student_id);
                    $next_admin_stmt->execute();
                    $next_admin = $next_admin_stmt->get_result()->fetch_assoc();
                    if ($next_admin) {
                        $promote_stmt = $mysqli->prepare(
                            "UPDATE study_buddy_group_members SET role = 'admin' WHERE group_member_id = ?"
                        );
                        $promote_stmt->bind_param('i', $next_admin['group_member_id']);
                        $promote_stmt->execute();
                    } else {
                        $delete_group_stmt = $mysqli->prepare('DELETE FROM study_buddy_groups WHERE group_id = ?');
                        $delete_group_stmt->bind_param('i', $target_id);
                        $delete_group_stmt->execute();
                    }
                }
                $remove_member_stmt = $mysqli->prepare(
                    'DELETE FROM study_buddy_group_members WHERE group_id = ? AND user_id = ?'
                );
                $remove_member_stmt->bind_param('ii', $target_id, $student_id);
                $remove_member_stmt->execute();
                $mysqli->commit();
                $transaction_started = false;
                $success_message = $membership['role'] === 'admin' && empty($next_admin)
                    ? 'You left the group and it was closed because no members remained.'
                    : 'You left the study group.';
            }
        } else {
            throw new InvalidArgumentException('Choose a valid Study Buddies action.');
        }
    }
} catch (InvalidArgumentException $exception) {
    if ($transaction_started) {
        try {
            $mysqli->rollback();
            $transaction_started = false;
        } catch (Throwable $rollback_exception) {
            error_log('Study Buddies rollback failed: ' . $rollback_exception->getMessage());
        }
    }
    $error_message = $exception->getMessage();
} catch (Throwable $exception) {
    if ($transaction_started) {
        $mysqli->rollback();
        $transaction_started = false;
    }
    error_log('Study Buddies database operation failed: ' . $exception->getMessage());
    $error_message = 'Study Buddies could not complete that action. Please try again.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $success_message !== '' && $error_message === '') {
    $_SESSION['study_buddy_flash'] = $success_message;
    $query = [];
    if ($search !== '') {
        $query['q'] = $search;
    }
    if ($course_filter > 0) {
        $query['course_id'] = $course_filter;
    }
    header('Location: study_buddy.php' . ($query ? '?' . http_build_query($query) : ''), true, 303);
    exit;
}

$buddies = [];
$incoming_requests = [];
$connections = [];
$groups = [];
$my_courses = [];
$groups_available = true;
try {
    $courses_stmt = $mysqli->prepare(
        'SELECT c.course_id, c.title FROM enrollments e
         JOIN courses c ON c.course_id = e.course_id
         WHERE e.user_id = ? ORDER BY c.title'
    );
    $courses_stmt->bind_param('i', $student_id);
    $courses_stmt->execute();
    $my_courses = $courses_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $buddy_sql = "SELECT u.user_id, u.first_name, u.last_name, u.bio, u.avatar_url,
                         GROUP_CONCAT(DISTINCT CASE WHEN my_enrollment.enrollment_id IS NOT NULL THEN c.title END
                                      ORDER BY c.title SEPARATOR ', ') AS common_courses,
                         MAX(my_enrollment.enrollment_id IS NOT NULL) AS has_shared_courses,
                         (SELECT sbc.connection_id FROM study_buddy_connections sbc
                          WHERE sbc.user_low_id = LEAST(u.user_id, ?)
                            AND sbc.user_high_id = GREATEST(u.user_id, ?)
                          LIMIT 1) AS connection_id,
                         (SELECT sbc.status FROM study_buddy_connections sbc
                          WHERE sbc.user_low_id = LEAST(u.user_id, ?)
                            AND sbc.user_high_id = GREATEST(u.user_id, ?)
                          LIMIT 1) AS connection_status,
                         (SELECT sbc.requester_id FROM study_buddy_connections sbc
                          WHERE sbc.user_low_id = LEAST(u.user_id, ?)
                            AND sbc.user_high_id = GREATEST(u.user_id, ?)
                          LIMIT 1) AS requester_id
                  FROM users u
                  LEFT JOIN enrollments their_enrollment ON their_enrollment.user_id = u.user_id
                  LEFT JOIN courses c ON c.course_id = their_enrollment.course_id
                  LEFT JOIN enrollments my_enrollment ON my_enrollment.course_id = c.course_id AND my_enrollment.user_id = ?
                  WHERE u.role = 'student' AND u.status = 'active' AND u.user_id <> ?";
    $params = [$student_id, $student_id, $student_id, $student_id, $student_id, $student_id, $student_id, $student_id];
    $types = 'iiiiiiii';
    if ($search !== '') {
        $buddy_sql .= ' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.bio LIKE ?
                          OR (my_enrollment.enrollment_id IS NOT NULL AND c.title LIKE ?))';
        $term = '%' . $search . '%';
        array_push($params, $term, $term, $term, $term);
        $types .= 'ssss';
    }
    if ($course_filter > 0) {
        $buddy_sql .= ' AND EXISTS (SELECT 1 FROM enrollments filter_enrollment
                          WHERE filter_enrollment.user_id = u.user_id
                            AND filter_enrollment.course_id = ?)
                       AND EXISTS (SELECT 1 FROM enrollments own_filter
                          WHERE own_filter.user_id = ? AND own_filter.course_id = ?)';
        array_push($params, $course_filter, $student_id, $course_filter);
        $types .= 'iii';
    }
    $buddy_sql .= ' GROUP BY u.user_id, u.first_name, u.last_name, u.bio, u.avatar_url
                    ORDER BY has_shared_courses DESC, u.first_name, u.last_name
                    LIMIT 100';
    $buddy_stmt = $mysqli->prepare($buddy_sql);
    $buddy_stmt->bind_param($types, ...$params);
    $buddy_stmt->execute();
    $buddies = $buddy_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $incoming_stmt = $mysqli->prepare(
        "SELECT sbc.connection_id, u.user_id, u.first_name, u.last_name, u.avatar_url
         FROM study_buddy_connections sbc
         JOIN users u ON u.user_id = sbc.requester_id
         WHERE sbc.recipient_id = ? AND sbc.status = 'pending' AND u.status = 'active'
         ORDER BY sbc.created_at DESC"
    );
    $incoming_stmt->bind_param('i', $student_id);
    $incoming_stmt->execute();
    $incoming_requests = $incoming_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $connections_stmt = $mysqli->prepare(
        "SELECT sbc.connection_id, u.user_id, u.first_name, u.last_name, u.avatar_url
         FROM study_buddy_connections sbc
         JOIN users u ON u.user_id = IF(sbc.requester_id = ?, sbc.recipient_id, sbc.requester_id)
         WHERE (sbc.requester_id = ? OR sbc.recipient_id = ?)
           AND sbc.status = 'accepted' AND u.status = 'active'
         ORDER BY u.first_name, u.last_name"
    );
    $connections_stmt->bind_param('iii', $student_id, $student_id, $student_id);
    $connections_stmt->execute();
    $connections = $connections_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $groups_stmt = $mysqli->prepare(
        'SELECT g.group_id, g.name, g.description, g.course_id, c.title AS course_title,
                creator.first_name AS creator_first_name, creator.last_name AS creator_last_name,
                COUNT(DISTINCT members.user_id) AS member_count,
                MAX(mine.user_id IS NOT NULL) AS is_member
         FROM study_buddy_groups g
         JOIN users creator ON creator.user_id = g.creator_id AND creator.status = \'active\'
         LEFT JOIN courses c ON c.course_id = g.course_id
         LEFT JOIN study_buddy_group_members members ON members.group_id = g.group_id
         LEFT JOIN study_buddy_group_members mine ON mine.group_id = g.group_id AND mine.user_id = ?
         GROUP BY g.group_id, g.name, g.description, g.course_id, c.title, creator.first_name, creator.last_name
         ORDER BY is_member DESC, member_count DESC, g.name
         LIMIT 100'
    );
    $groups_stmt->bind_param('i', $student_id);
    $groups_stmt->execute();
    $groups = $groups_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $exception) {
    error_log('Study Buddies data could not be loaded: ' . $exception->getMessage());
    $groups_available = false;
    $error_message = $error_message ?: 'Study Buddies data could not be loaded. Apply the Study Buddies database migration and try again.';
}

include __DIR__ . '/../includes/dashboard_header.php';
function study_buddy_post_action($action, $target_id = 0)
{
    return '<form method="post" class="d-inline">'
        . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(student_csrf_token(), ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="action" value="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="target_id" value="' . (int) $target_id . '">';
}
?>
<div class="container-fluid">
    <h1 class="h2 mb-2">Study Buddies</h1>
    <p class="lead mb-4">Find fellow learners, manage connections, and build study groups.</p>

    <?php if ($error_message !== ''): ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($success_message !== ''): ?>
        <div class="alert alert-success" role="status"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($groups_available): ?>
        <section class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="h5 mb-0">Find Study Buddies</h2>
                    <form class="row g-2 align-items-center" method="get">
                        <div class="col-auto">
                            <select class="form-select form-select-sm" name="course_id" aria-label="Filter by your course">
                                <option value="">All courses</option>
                                <?php foreach ($my_courses as $my_course): ?>
                                    <option value="<?php echo (int) $my_course['course_id']; ?>"
                                        <?php echo $course_filter === (int) $my_course['course_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($my_course['title'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col">
                        <input class="form-control form-control-sm" type="search" name="q"
                               value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="Search name, bio, or course" aria-label="Search study buddies">
                        </div>
                        <div class="col-auto">
                        <button class="btn btn-sm btn-outline-primary" type="submit">Search</button>
                        </div>
                        <?php if ($search !== '' || $course_filter > 0): ?>
                            <div class="col-auto"><a class="btn btn-sm btn-outline-secondary" href="study_buddy.php">Clear</a></div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <?php if ($buddies === []): ?>
                    <p class="text-muted text-center mb-0">
                        <?php echo $search !== '' || $course_filter > 0
                            ? 'No active students match that search or course.'
                            : 'No other active student accounts are available yet.'; ?>
                    </p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($buddies as $buddy): ?>
                            <?php
                            $buddy_name = trim($buddy['first_name'] . ' ' . $buddy['last_name']);
                            $avatar = trim((string) $buddy['avatar_url']);
                            if ($avatar !== '' && !filter_var($avatar, FILTER_VALIDATE_URL)) {
                                $avatar = BASE_URL . ltrim($avatar, '/');
                            }
                            ?>
                            <div class="list-group-item py-3">
                                <div class="row align-items-center g-3">
                                    <div class="col-auto">
                                        <img src="<?php echo htmlspecialchars($avatar ?: BASE_URL . 'assets/images/user_avatar_placeholder.png', ENT_QUOTES, 'UTF-8'); ?>"
                                             alt="" class="rounded-circle" style="width:52px;height:52px;object-fit:cover"
                                             onerror="this.onerror=null;this.src='<?php echo BASE_URL; ?>assets/images/user_avatar_placeholder.png'">
                                    </div>
                                    <div class="col">
                                        <h3 class="h6 mb-1"><?php echo htmlspecialchars($buddy_name, ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <p class="small text-muted mb-1">
                                            Shared courses: <?php echo htmlspecialchars($buddy['common_courses'] ?: 'None yet', ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                        <?php if (!empty($buddy['bio'])): ?>
                                            <p class="small fst-italic mb-0"><?php echo htmlspecialchars($buddy['bio'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-auto d-flex flex-wrap gap-2">
                                        <?php if ($buddy['connection_status'] === 'accepted'): ?>
                                            <span class="badge bg-success align-self-center">Connected</span>
                                            <?php echo study_buddy_post_action('remove_connection', (int) $buddy['connection_id']); ?>
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button></form>
                                        <?php elseif ($buddy['connection_status'] === 'pending' && (int) $buddy['requester_id'] === $student_id): ?>
                                            <span class="badge bg-secondary align-self-center">Request pending</span>
                                        <?php elseif ($buddy['connection_status'] === 'pending'): ?>
                                            <span class="badge bg-info text-dark align-self-center">Request received</span>
                                        <?php else: ?>
                                            <?php echo study_buddy_post_action('connect', (int) $buddy['user_id']); ?>
                                            <button class="btn btn-sm btn-primary" type="submit"><i class="fas fa-user-plus me-1"></i>Connect</button></form>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-auto">
                                        <a class="btn btn-sm btn-outline-secondary"
                                           href="study_buddy_profile.php?user_id=<?php echo (int) $buddy['user_id']; ?>">
                                            <i class="fas fa-eye me-1"></i>View profile
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <div class="row g-4">
            <section class="col-lg-5" id="incoming-requests">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white"><h2 class="h5 mb-0">Incoming Requests (<?php echo count($incoming_requests); ?>)</h2></div>
                    <div class="list-group list-group-flush">
                        <?php if ($incoming_requests === []): ?>
                            <div class="list-group-item text-muted">No pending requests.</div>
                        <?php else: foreach ($incoming_requests as $request): ?>
                            <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <span><?php echo htmlspecialchars(trim($request['first_name'] . ' ' . $request['last_name']), ENT_QUOTES, 'UTF-8'); ?></span>
                                <div>
                                    <?php echo study_buddy_post_action('accept_connection', (int) $request['connection_id']); ?>
                                    <button class="btn btn-sm btn-success" type="submit">Accept</button></form>
                                    <?php echo study_buddy_post_action('decline_connection', (int) $request['connection_id']); ?>
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Decline</button></form>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </section>
            <section class="col-lg-7">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white"><h2 class="h5 mb-0">My Connections (<?php echo count($connections); ?>)</h2></div>
                    <div class="list-group list-group-flush">
                        <?php if ($connections === []): ?>
                            <div class="list-group-item text-muted">Accepted connections will appear here.</div>
                        <?php else: foreach ($connections as $connection): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars(trim($connection['first_name'] . ' ' . $connection['last_name']), ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php echo study_buddy_post_action('remove_connection', (int) $connection['connection_id']); ?>
                                <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button></form>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </section>
        </div>

        <section class="card shadow-sm mt-4">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 class="h5 mb-0">Study Groups (<?php echo count($groups); ?>)</h2>
                <button class="btn btn-sm btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#createStudyGroup" aria-expanded="false">
                    <i class="fas fa-plus me-1"></i>Create Group
                </button>
            </div>
            <div class="collapse" id="createStudyGroup">
                <div class="card-body border-bottom">
                    <form method="post" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(student_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="create_group">
                        <div class="col-md-5">
                            <label class="form-label" for="group-name">Group name</label>
                            <input class="form-control" id="group-name" name="group_name" maxlength="120" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="group-course">Course focus (optional)</label>
                            <select class="form-select" id="group-course" name="group_course_id">
                                <option value="">No specific course</option>
                                <?php foreach ($my_courses as $my_course): ?>
                                    <option value="<?php echo (int) $my_course['course_id']; ?>"><?php echo htmlspecialchars($my_course['title'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button class="btn btn-primary w-100" type="submit">Create study group</button>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="group-description">Description</label>
                            <textarea class="form-control" id="group-description" name="group_description" rows="2" maxlength="2000"></textarea>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <?php if ($groups === []): ?>
                    <p class="text-muted text-center mb-0">No study groups yet. Create one to get started.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($groups as $group): ?>
                            <div class="list-group-item py-3">
                                <div class="row align-items-center g-3">
                                    <div class="col-auto"><span class="bg-secondary text-white rounded d-flex align-items-center justify-content-center" style="width:48px;height:48px"><i class="fas fa-users"></i></span></div>
                                    <div class="col">
                                        <h3 class="h6 mb-1"><?php echo htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <p class="small text-muted mb-1">
                                            <?php echo $group['course_title'] ? 'Focus: ' . htmlspecialchars($group['course_title'], ENT_QUOTES, 'UTF-8') . ' · ' : ''; ?>
                                            <?php echo (int) $group['member_count']; ?> member<?php echo (int) $group['member_count'] === 1 ? '' : 's'; ?>
                                            · Created by <?php echo htmlspecialchars(trim($group['creator_first_name'] . ' ' . $group['creator_last_name']), ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                        <?php if ($group['description'] !== null && $group['description'] !== ''): ?>
                                            <p class="small mb-0"><?php echo nl2br(htmlspecialchars($group['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-auto">
                                        <?php if ((int) $group['is_member'] === 1): ?>
                                            <?php echo study_buddy_post_action('leave_group', (int) $group['group_id']); ?>
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">Leave group</button></form>
                                        <?php else: ?>
                                            <?php echo study_buddy_post_action('join_group', (int) $group['group_id']); ?>
                                            <button class="btn btn-sm btn-info" type="submit"><i class="fas fa-sign-in-alt me-1"></i>Join group</button></form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/dashboard_footer.php'; ?>
