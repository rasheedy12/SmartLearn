<?php
define('BASE_URL', '../');
$page_title = "Admin Dashboard";
$_SESSION['user_role'] = 'admin'; // Mock role for sidebar
$use_charts = true; // This page will use charts
include '../includes/dashboard_header.php';
?>

<div class="container-fluid">
    <h1 class="h2 mb-4">Admin Dashboard</h1>

    <div class="alert alert-danger alert-system" role="alert">
        <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>System Alerts</h5>
        <ul>
            <li>High server load detected (CPU: 85%) - Consider scaling resources.</li>
            <li>15 courses have completion rates below 40% threshold.</li>
            <li>Database backup failed last night - Manual intervention required.</li>
        </ul>
    </div>

    <h3 class="h4 mb-3 mt-4">Platform Overview</h3>
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <h6>Total Users</h6>
                    <i class="fas fa-users icon-lg text-primary"></i>
                </div>
                <div class="stat-value">24,847</div>
                <div class="stat-meta">
                    Students: 22,156 | Instructors: 2,981 <br>
                    <span class="text-success stat-change"><i class="fas fa-arrow-up"></i> 12%</span> vs last month
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card h-100">
                 <div class="d-flex justify-content-between align-items-center">
                    <h6>Active Courses</h6>
                    <i class="fas fa-book-open icon-lg text-info"></i>
                </div>
                <div class="stat-value">1,247</div>
                <div class="stat-meta">
                    Published: 1,089 | Pending: 158 <br>
                    <span class="text-success stat-change"><i class="fas fa-arrow-up"></i> 50</span> new this month
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <h6>Monthly Revenue</h6>
                    <i class="fas fa-dollar-sign icon-lg text-success"></i>
                </div>
                <div class="stat-value">$127,450</div>
                 <div class="stat-meta">
                    Avg per day: $4,248 <br>
                    <span class="text-success stat-change"><i class="fas fa-arrow-up"></i> 8%</span> vs last month
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <h6>System Health</h6>
                    <i class="fas fa-heartbeat icon-lg text-danger"></i>
                </div>
                <div class="stat-value">98.7%</div>
                <div class="stat-meta">
                    Uptime this month <br>
                    <span class="text-muted">3.2h downtime total</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">User Growth Trend</h5>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary active">Students</button>
                        <button class="btn btn-sm btn-outline-secondary">Instructors</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="userGrowthChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                     <h5 class="mb-0">High-Risk Courses</h5>
                     <span class="badge bg-danger">15 Courses</span>
                </div>
                <div class="list-group list-group-flush">
                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        Advanced Mathematics
                        <small class="text-danger">35% Completion</small>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        Quantum Physics Intro
                        <small class="text-danger">22% Completion</small>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        Organic Chemistry II
                        <small class="text-warning">41% Completion</small>
                    </a>
                     <a href="#" class="list-group-item list-group-item-action text-center text-primary">
                        View All High-Risk Courses
                    </a>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
             <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                     <h5 class="mb-0">Recent Platform Activity</h5>
                </div>
                <div class="list-group list-group-flush">
                    <div class="list-group-item">
                        <p class="mb-1"><strong class="text-primary">New User:</strong> John Doe registered as Student.</p>
                        <small class="text-muted">2 minutes ago</small>
                    </div>
                     <div class="list-group-item">
                        <p class="mb-1"><strong class="text-success">Course Published:</strong> "Intro to Python" by Dr. Jane Smith.</p>
                        <small class="text-muted">15 minutes ago</small>
                    </div>
                    <div class="list-group-item">
                        <p class="mb-1"><strong class="text-info">System Update:</strong> Security patch v1.2 applied.</p>
                        <small class="text-muted">1 hour ago</small>
                    </div>
                    <a href="#" class="list-group-item list-group-item-action text-center text-primary">
                        View Full Activity Log
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>
<?php
// Pass data for charts to JS
$chart_data = [
    'userGrowth' => [
        'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        'datasets' => [
            [
                'label' => 'Students',
                'data' => [500, 650, 800, 750, 900, 1100],
                'borderColor' => 'rgba(111, 66, 193, 1)', // purple
                'backgroundColor' => 'rgba(111, 66, 193, 0.1)',
                'fill' => true,
                'tension' => 0.3
            ]
        ]
    ]
];
echo '<script>const chartData = ' . json_encode($chart_data) . ';</script>';

$extra_js = [BASE_URL . 'assets/js/dashboard_charts.js']; // Ensure this file exists and has chart logic
include '../includes/dashboard_footer.php';
?>
