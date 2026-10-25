<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/admin_helpers.php';
$page_title = 'Platform Reports';
$use_charts = true;

$period = $_GET['period'] ?? '12';
if (!in_array($period, ['6', '12', 'year'], true)) {
    $period = '12';
}
$month_count = $period === '6' ? 6 : 12;
$end = new DateTimeImmutable('first day of next month');
$start = $period === 'year'
    ? new DateTimeImmutable('first day of January this year')
    : $end->modify('-' . $month_count . ' months');
$start_date = $start->format('Y-m-d');
$end_date = $end->format('Y-m-d');
$months = [];
for ($cursor = $start; $cursor < $end; $cursor = $cursor->modify('+1 month')) {
    $months[$cursor->format('Y-m')] = [
        'label' => $cursor->format('M Y'),
        'revenue' => 0.0,
    ];
}

$revenue_available = true;
$report_revenue = 0.0;
$revenue_by_month = [];
try {
    $revenue_stmt = $mysqli->prepare(
        'SELECT DATE_FORMAT(created_at, \'%Y-%m\') AS month_key, SUM(base_amount_usd) AS total
         FROM course_payments
         WHERE payment_status = \'completed\' AND created_at >= ? AND created_at < ?
         GROUP BY month_key
         ORDER BY month_key'
    );
    $revenue_stmt->bind_param('ss', $start_date, $end_date);
    $revenue_stmt->execute();
    $revenue_by_month = $revenue_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $revenue_stmt->close();
    foreach ($revenue_by_month as $row) {
        if (isset($months[$row['month_key']])) {
            $months[$row['month_key']]['revenue'] = (float) $row['total'];
        }
    }

    $ytd_stmt = $mysqli->prepare(
        'SELECT COALESCE(SUM(base_amount_usd), 0) AS total
         FROM course_payments
         WHERE payment_status = \'completed\' AND created_at >= ? AND created_at < ?'
    );
    $year_start = (new DateTimeImmutable('first day of January this year'))->format('Y-m-d');
    $ytd_stmt->bind_param('ss', $year_start, $end_date);
    $ytd_stmt->execute();
    $report_revenue = (float) $ytd_stmt->get_result()->fetch_assoc()['total'];
    $ytd_stmt->close();
} catch (mysqli_sql_exception $error) {
    $revenue_available = false;
    error_log('Admin reports could not load payment metrics: ' . $error->getMessage());
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (!$revenue_available) {
        http_response_code(503);
        exit('Revenue export is unavailable because payment records could not be read. Check the server error log.');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="smartlearn-revenue-' . $period . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Month', 'Completed payment revenue (USD)']);
    foreach ($months as $month) {
        fputcsv($output, [$month['label'], number_format($month['revenue'], 2, '.', '')]);
    }
    fclose($output);
    exit;
}

$this_month_start = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
$next_month_start = (new DateTimeImmutable('first day of next month'))->format('Y-m-d');
$new_users_stmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM users WHERE created_at >= ? AND created_at < ?');
$new_users_stmt->bind_param('ss', $this_month_start, $next_month_start);
$new_users_stmt->execute();
$new_users = (int) $new_users_stmt->get_result()->fetch_assoc()['total'];
$new_users_stmt->close();

$enrollments_stmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM enrollments WHERE enrolled_at >= ? AND enrolled_at < ?');
$enrollments_stmt->bind_param('ss', $this_month_start, $next_month_start);
$enrollments_stmt->execute();
$monthly_enrollments = (int) $enrollments_stmt->get_result()->fetch_assoc()['total'];
$enrollments_stmt->close();

$rating_result = $mysqli->query('SELECT AVG(average_rating) AS rating FROM courses WHERE ratings_count > 0');
$average_rating = $rating_result->fetch_assoc()['rating'];
$role_result = $mysqli->query('SELECT role, COUNT(*) AS total FROM users GROUP BY role');
$role_counts = ['student' => 0, 'instructor' => 0, 'admin' => 0];
foreach ($role_result->fetch_all(MYSQLI_ASSOC) as $row) {
    $role_counts[$row['role']] = (int) $row['total'];
}
$top_courses = $mysqli->query(
    'SELECT c.course_id, c.title, COUNT(e.enrollment_id) AS enrollments
     FROM courses c
     INNER JOIN enrollments e ON e.course_id = c.course_id
     GROUP BY c.course_id, c.title
     ORDER BY enrollments DESC, c.title
     LIMIT 5'
)->fetch_all(MYSQLI_ASSOC);

$chart_data = [
    'userDemographics' => [
        'labels' => ['Students', 'Instructors', 'Admins'],
        'datasets' => [[
            'label' => 'User Roles',
            'data' => array_values($role_counts),
            'backgroundColor' => ['rgba(54, 162, 235, 0.7)', 'rgba(255, 206, 86, 0.7)', 'rgba(255, 99, 132, 0.7)'],
        ]],
    ],
];
if ($revenue_available) {
    $chart_data['revenue'] = [
        'labels' => array_column($months, 'label'),
        'datasets' => [[
            'label' => 'Revenue (USD)',
            'data' => array_column($months, 'revenue'),
            'borderColor' => 'rgba(75, 192, 192, 1)',
            'backgroundColor' => 'rgba(75, 192, 192, 0.12)',
            'fill' => true,
            'tension' => 0.3,
        ]],
    ];
}

include BASE_URL . 'includes/dashboard_header.php';
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">Platform Reports</h1>
    <?php if (!$revenue_available): ?>
        <div class="alert alert-warning" role="alert">Payment data is unavailable from the database. Revenue figures and exports cannot be shown; check the server error log.</div>
    <?php endif; ?>
    <p class="lead mb-4">Current platform activity and financial reports.</p>

    <div class="row mb-4">
        <div class="col-md-3 mb-3"><div class="stat-card h-100"><h2 class="h6">Total Revenue (YTD)</h2><div class="stat-value"><?php echo $revenue_available ? '$' . number_format($report_revenue, 2) : 'Unavailable'; ?></div></div></div>
        <div class="col-md-3 mb-3"><div class="stat-card h-100"><h2 class="h6">New Users (Month)</h2><div class="stat-value"><?php echo number_format($new_users); ?></div></div></div>
        <div class="col-md-3 mb-3"><div class="stat-card h-100"><h2 class="h6">Enrollments (Month)</h2><div class="stat-value"><?php echo number_format($monthly_enrollments); ?></div></div></div>
        <div class="col-md-3 mb-3"><div class="stat-card h-100"><h2 class="h6">Average Course Rating</h2><div class="stat-value"><?php echo $average_rating === null ? 'N/A' : number_format((float) $average_rating, 1) . '/5'; ?></div></div></div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="h5 mb-0">Revenue Over Time</h2>
            <div class="d-flex gap-2">
                <form method="get" action="reports.php">
                    <select class="form-select form-select-sm" name="period" aria-label="Revenue report period" onchange="this.form.submit()">
                        <option value="12" <?php echo $period === '12' ? 'selected' : ''; ?>>Last 12 Months</option>
                        <option value="6" <?php echo $period === '6' ? 'selected' : ''; ?>>Last 6 Months</option>
                        <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>This Year</option>
                    </select>
                </form>
                <?php if ($revenue_available): ?>
                    <a href="reports.php?period=<?php echo urlencode($period); ?>&amp;export=csv" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download me-1"></i>Export CSV</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <?php if ($revenue_available): ?>
                <div class="chart-container" style="height:300px;"><canvas id="revenueChart"></canvas></div>
            <?php else: ?>
                <p class="text-muted text-center mb-0">The payment data source is currently unavailable.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><h2 class="h5 mb-0">Most Enrolled Courses</h2></div>
                <div class="list-group list-group-flush">
                    <?php if (!$top_courses): ?>
                        <p class="list-group-item text-muted mb-0">No course enrollments recorded yet.</p>
                    <?php else: ?>
                        <?php foreach ($top_courses as $course): ?>
                            <a href="course_management.php?course_id=<?php echo (int) $course['course_id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?><span class="badge bg-primary rounded-pill"><?php echo number_format((int) $course['enrollments']); ?> enrollments</span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><h2 class="h5 mb-0">User Demographics</h2></div>
                <div class="card-body"><div class="chart-container" style="height:240px;"><canvas id="userDemographicsChart"></canvas></div></div>
            </div>
        </div>
    </div>
</div>
<script>const chartData = <?php echo json_encode($chart_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;</script>
<?php
$extra_js = [BASE_URL . 'assets/js/dashboard_charts.js'];
include BASE_URL . 'includes/dashboard_footer.php';
?>
