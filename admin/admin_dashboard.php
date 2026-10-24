<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/admin_helpers.php';

$page_title = 'Admin Dashboard';
$use_charts = true;
$current_month = date('Y-m');
$previous_month = date('Y-m', strtotime('first day of last month'));
$activity_limit = isset($_GET['activity']) && $_GET['activity'] === 'all' ? 25 : 5;

$user_counts = ['student' => 0, 'instructor' => 0, 'admin' => 0];
$user_result = $mysqli->query('SELECT role, COUNT(*) AS total FROM users GROUP BY role');
foreach ($user_result->fetch_all(MYSQLI_ASSOC) as $row) {
    $user_counts[$row['role']] = (int) $row['total'];
}
$total_users = array_sum($user_counts);

$course_counts = ['published' => 0, 'pending_approval' => 0];
$course_result = $mysqli->query('SELECT status, COUNT(*) AS total FROM courses GROUP BY status');
foreach ($course_result->fetch_all(MYSQLI_ASSOC) as $row) {
    $course_counts[$row['status']] = (int) $row['total'];
}

$revenue_available = true;
$monthly_revenue = null;
$revenue_change = null;
try {
    $revenue_stmt = $mysqli->prepare(
        'SELECT
            COALESCE(SUM(CASE WHEN DATE_FORMAT(created_at, \'%Y-%m\') = ? THEN base_amount_usd END), 0) AS this_month,
            COALESCE(SUM(CASE WHEN DATE_FORMAT(created_at, \'%Y-%m\') = ? THEN base_amount_usd END), 0) AS last_month
         FROM course_payments
         WHERE payment_status = \'completed\''
    );
    $revenue_stmt->bind_param('ss', $current_month, $previous_month);
    $revenue_stmt->execute();
    $revenue = $revenue_stmt->get_result()->fetch_assoc();
    $revenue_stmt->close();
    $monthly_revenue = (float) $revenue['this_month'];
    $previous_revenue = (float) $revenue['last_month'];
    $revenue_change = $previous_revenue > 0
        ? (int) round((($monthly_revenue - $previous_revenue) / $previous_revenue) * 100)
        : null;
} catch (mysqli_sql_exception $error) {
    $revenue_available = false;
    error_log('Admin dashboard could not load payment metrics: ' . $error->getMessage());
}

$pending_instructors = (int) $mysqli->query(
    "SELECT COUNT(*) AS total FROM users WHERE role = 'instructor' AND status = 'pending_approval'"
)->fetch_assoc()['total'];
$low_completion_result = $mysqli->query(
    'SELECT c.course_id, c.title, AVG(e.progress_percentage) AS completion_rate,
            COUNT(e.enrollment_id) AS enrollment_count
     FROM courses c
     INNER JOIN enrollments e ON e.course_id = c.course_id
     WHERE c.status = \'published\'
     GROUP BY c.course_id, c.title
     HAVING AVG(e.progress_percentage) < 40
     ORDER BY completion_rate, c.title'
);
$low_completion_courses = $low_completion_result->fetch_all(MYSQLI_ASSOC);
$pending_courses = $course_counts['pending_approval'];

$growth_months = [];
$growth_values = [];
$month_cursor = new DateTimeImmutable('first day of this month');
for ($month_offset = 5; $month_offset >= 0; $month_offset--) {
    $month = $month_cursor->modify('-' . $month_offset . ' months');
    $month_key = $month->format('Y-m');
    $growth_months[$month_key] = ['student' => 0, 'instructor' => 0];
    $growth_values[] = $month->format('M Y');
}
$growth_stmt = $mysqli->prepare(
    'SELECT DATE_FORMAT(created_at, \'%Y-%m\') AS month_key, role, COUNT(*) AS total
     FROM users
     WHERE created_at >= DATE_SUB(DATE_FORMAT(CURRENT_DATE, \'%Y-%m-01\'), INTERVAL 5 MONTH)
       AND role IN (\'student\', \'instructor\')
     GROUP BY month_key, role'
);
$growth_stmt->execute();
foreach ($growth_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    if (isset($growth_months[$row['month_key']][$row['role']])) {
        $growth_months[$row['month_key']][$row['role']] = (int) $row['total'];
    }
}
$growth_stmt->close();

$activity_sql =
    'SELECT activity_type, description, occurred_at FROM (
        SELECT \'New User\' AS activity_type,
               CONCAT(u.first_name, \' \', u.last_name, \' registered as \', u.role, \'.\') AS description,
               u.created_at AS occurred_at
        FROM users u
        UNION ALL
        SELECT \'Course Submitted\' AS activity_type,
               CONCAT(\'"\', c.title, \'" submitted by \', u.first_name, \' \', u.last_name, \'.\') AS description,
               c.created_at AS occurred_at
        FROM courses c
        INNER JOIN users u ON u.user_id = c.instructor_id
     ) AS platform_activity
     ORDER BY occurred_at DESC';
$activity = $mysqli->query($activity_sql)->fetch_all(MYSQLI_ASSOC);
if ($revenue_available) {
    try {
        $payment_activity = $mysqli->query(
            'SELECT \'Payment Completed\' AS activity_type,
                    CONCAT(\'Payment of \', p.currency, \' \', FORMAT(p.amount, 2), \' received for "\', c.title, \'".\') AS description,
                    p.created_at AS occurred_at
             FROM course_payments p
             INNER JOIN courses c ON c.course_id = p.course_id
             WHERE p.payment_status = \'completed\'
             ORDER BY p.created_at DESC
             LIMIT ' . $activity_limit
        )->fetch_all(MYSQLI_ASSOC);
        $activity = array_merge($activity, $payment_activity);
    } catch (mysqli_sql_exception $error) {
        $revenue_available = false;
        $monthly_revenue = null;
        $revenue_change = null;
        error_log('Admin dashboard could not load payment activity: ' . $error->getMessage());
    }
}
usort($activity, function ($left, $right) {
    return strcmp($right['occurred_at'], $left['occurred_at']);
});
$activity = array_slice($activity, 0, $activity_limit);

$chart_data = [
    'userGrowth' => [
        'labels' => $growth_values,
        'datasets' => [
            [
                'label' => 'Students',
                'data' => array_column($growth_months, 'student'),
                'borderColor' => 'rgba(111, 66, 193, 1)',
                'backgroundColor' => 'rgba(111, 66, 193, 0.1)',
                'fill' => true,
                'tension' => 0.3,
            ],
            [
                'label' => 'Instructors',
                'data' => array_column($growth_months, 'instructor'),
                'borderColor' => 'rgba(32, 201, 151, 1)',
                'backgroundColor' => 'rgba(32, 201, 151, 0.1)',
                'fill' => true,
                'tension' => 0.3,
            ],
        ],
    ],
];
include BASE_URL . 'includes/dashboard_header.php';
?>

<div class="container-fluid">
    <h1 class="h2 mb-4">Admin Dashboard</h1>

    <section id="system-alerts" aria-labelledby="systemAlertsHeading">
        <?php if ($pending_instructors || $pending_courses || $low_completion_courses || !$revenue_available): ?>
            <div class="alert alert-warning alert-system" role="alert">
                <h2 class="h5 alert-heading" id="systemAlertsHeading"><i class="fas fa-exclamation-triangle me-2"></i>Items Requiring Attention</h2>
                <ul class="mb-0">
                    <?php if ($pending_instructors): ?>
                        <li><a href="./user_management.php?role=instructor&amp;status=pending_approval"><?php echo number_format($pending_instructors); ?> instructor account<?php echo $pending_instructors === 1 ? '' : 's'; ?> waiting for approval.</a></li>
                    <?php endif; ?>
                    <?php if ($pending_courses): ?>
                        <li><a href="./course_management.php?status=pending_approval"><?php echo number_format($pending_courses); ?> course<?php echo $pending_courses === 1 ? '' : 's'; ?> waiting for approval.</a></li>
                    <?php endif; ?>
                    <?php if ($low_completion_courses): ?>
                        <li><a href="./course_management.php?completion=low"><?php echo number_format(count($low_completion_courses)); ?> published course<?php echo count($low_completion_courses) === 1 ? '' : 's'; ?> have completion rates below 40%.</a></li>
                    <?php endif; ?>
                    <?php if (!$revenue_available): ?>
                        <li class="text-danger">Payment metrics and payment activity could not be loaded from the database. Check the server error log.</li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="alert alert-success" role="status">
                <i class="fas fa-check-circle me-2"></i>No pending approvals or low-completion courses need attention.
            </div>
        <?php endif; ?>
    </section>

    <h2 class="h4 mb-3 mt-4">Platform Overview</h2>
    <div class="row">
        <div class="col-xl-4 col-md-6 mb-4">
            <a class="text-decoration-none text-reset" href="./user_management.php">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-center"><h3 class="h6">Total Users</h3><i class="fas fa-users icon-lg text-primary"></i></div>
                    <div class="stat-value"><?php echo number_format($total_users); ?></div>
                    <div class="stat-meta">Students: <?php echo number_format($user_counts['student']); ?> | Instructors: <?php echo number_format($user_counts['instructor']); ?></div>
                </div>
            </a>
        </div>
        <div class="col-xl-4 col-md-6 mb-4">
            <a class="text-decoration-none text-reset" href="./course_management.php">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-center"><h3 class="h6">Active Courses</h3><i class="fas fa-book-open icon-lg text-info"></i></div>
                    <div class="stat-value"><?php echo number_format($course_counts['published']); ?></div>
                    <div class="stat-meta">Published: <?php echo number_format($course_counts['published']); ?> | Pending: <?php echo number_format($pending_courses); ?></div>
                </div>
            </a>
        </div>
        <div class="col-xl-4 col-md-6 mb-4">
            <a class="text-decoration-none text-reset" href="./reports.php">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-center"><h3 class="h6">Monthly Revenue</h3><i class="fas fa-dollar-sign icon-lg text-success"></i></div>
                    <div class="stat-value"><?php echo $revenue_available ? '$' . number_format($monthly_revenue, 2) : 'Unavailable'; ?></div>
                    <div class="stat-meta">
                        <?php echo $revenue_available ? 'Completed payments this month' : 'Payment records could not be read'; ?>
                        <?php if ($revenue_available && $revenue_change !== null): ?>
                            <br><span class="<?php echo $revenue_change >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo $revenue_change >= 0 ? '+' : ''; ?><?php echo $revenue_change; ?>%</span> vs last month
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">User Growth Trend</h2>
                    <div class="btn-group" role="group" aria-label="Choose user role for growth chart">
                        <button type="button" class="btn btn-sm btn-outline-secondary active" data-growth-role="student" aria-pressed="true">Students</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-growth-role="instructor" aria-pressed="false">Instructors</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container"><canvas id="userGrowthChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">High-Risk Courses</h2>
                    <span class="badge bg-danger"><?php echo number_format(count($low_completion_courses)); ?> Courses</span>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (!$low_completion_courses): ?>
                        <p class="list-group-item text-muted mb-0">No published courses with enrolled students are below 40% completion.</p>
                    <?php else: ?>
                        <?php foreach (array_slice($low_completion_courses, 0, 3) as $course): ?>
                            <a href="./course_management.php?course_id=<?php echo (int) $course['course_id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?>
                                <small class="text-danger"><?php echo (int) round((float) $course['completion_rate']); ?>% Completion</small>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <a href="./course_management.php?completion=low" class="list-group-item list-group-item-action text-center text-primary">View All High-Risk Courses</a>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100" id="recent-activity">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Recent Platform Activity</h2></div>
                <div class="list-group list-group-flush">
                    <?php if (!$activity): ?>
                        <p class="list-group-item text-muted mb-0">No platform activity recorded yet.</p>
                    <?php else: ?>
                        <?php foreach ($activity as $event): ?>
                            <div class="list-group-item">
                                <p class="mb-1"><strong><?php echo htmlspecialchars($event['activity_type'], ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars($event['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <small class="text-muted"><?php echo htmlspecialchars(date('M j, Y g:i a', strtotime($event['occurred_at'])), ENT_QUOTES, 'UTF-8'); ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <a href="./admin_dashboard.php?activity=<?php echo $activity_limit === 5 ? 'all' : 'recent'; ?>#recent-activity" class="list-group-item list-group-item-action text-center text-primary">
                        <?php echo $activity_limit === 5 ? 'View Full Activity Log' : 'Show Recent Activity'; ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<script>const chartData = <?php echo json_encode($chart_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;</script>
<?php
$extra_js = [BASE_URL . 'assets/js/dashboard_charts.js'];
include BASE_URL . 'includes/dashboard_footer.php';
?>
