<?php
define('BASE_URL', '../');
$page_title = "Platform Reports";
$_SESSION['user_role'] = 'admin';
$use_charts = true; // This page will use charts
include '../includes/dashboard_header.php';

// Mock data for reports
$report_summary = [
    'total_revenue_ytd' => '$250,600',
    'new_users_this_month' => 1203,
    'courses_sold_this_month' => 850,
    'avg_user_rating' => '4.7/5',
];
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">Platform Reports</h1> 
    <p class="lead mb-4">Access detailed reports on platform activity, financials, and user engagement.</p>

    <div class="row mb-4">
        <div class="col-md-3"><div class="stat-card h-100"><h6>Total Revenue (YTD)</h6><div class="stat-value"><?php echo $report_summary['total_revenue_ytd']; ?></div></div></div>
        <div class="col-md-3"><div class="stat-card h-100"><h6>New Users (Month)</h6><div class="stat-value"><?php echo $report_summary['new_users_this_month']; ?></div></div></div>
        <div class="col-md-3"><div class="stat-card h-100"><h6>Courses Sold (Month)</h6><div class="stat-value"><?php echo $report_summary['courses_sold_this_month']; ?></div></div></div>
        <div class="col-md-3"><div class="stat-card h-100"><h6>Avg. User Rating</h6><div class="stat-value"><?php echo $report_summary['avg_user_rating']; ?></div></div></div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Revenue Over Time</h5>
            <div>
                <select class="form-select form-select-sm d-inline-block" style="width: auto;">
                    <option selected>Last 12 Months</option>
                    <option>Last 6 Months</option>
                    <option>This Year</option>
                </select>
                <button class="btn btn-sm btn-outline-secondary ms-2"><i class="fas fa-download me-1"></i>Export</button>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-container" style="height:300px;">
                <canvas id="revenueChart"></canvas> 
            </div>
            <p class="text-muted text-center small mt-2">Mock chart: Monthly revenue.</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><h5 class="mb-0">Top Selling Courses</h5></div>
                <div class="list-group list-group-flush">
                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Web Development Bootcamp <span class="badge bg-primary rounded-pill">350 sales</span></a>
                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Python for Data Science <span class="badge bg-primary rounded-pill">280 sales</span></a>
                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">JavaScript Fundamentals <span class="badge bg-primary rounded-pill">220 sales</span></a>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><h5 class="mb-0">User Demographics (Mock)</h5></div>
                <div class="card-body">
                    <div class="chart-container" style="height:200px;">
                        <canvas id="userDemographicsChart"></canvas>
                    </div>
                    <p class="text-muted text-center small mt-2">Mock chart: User distribution by role.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$chart_data_admin_reports = [
    'revenue' => [
        'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'], // Last 6 months
        'datasets' => [['label' => 'Revenue ($)', 'data' => [15000, 18000, 22000, 20000, 25000, 28000], 'borderColor' => 'rgba(75, 192, 192, 1)', 'tension' => 0.3]]
    ],
    'userDemographics' => [
        'labels' => ['Students', 'Instructors', 'Admins'],
        'datasets' => [['label' => 'User Roles', 'data' => [22156, 2981, 15], 'backgroundColor' => ['rgba(54, 162, 235, 0.7)', 'rgba(255, 206, 86, 0.7)', 'rgba(255, 99, 132, 0.7)']]]
    ]
];
echo '<script>const chartData = ' . (isset($chartData) ? json_encode(array_merge($chartData, $chart_data_admin_reports)) : json_encode($chart_data_admin_reports)) . ';</script>';
$extra_js = [BASE_URL . 'assets/js/dashboard_charts.js'];
include '../includes/dashboard_footer.php';
?>

