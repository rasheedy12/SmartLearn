<?php
define('BASE_URL', '../');
$page_title = "Course Analytics";
$_SESSION['user_role'] = 'instructor';
$use_charts = true; // This page will use charts
include '../includes/dashboard_header.php';

// Mock: $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : null;
// Fetch analytics data for this instructor, optionally filtered by course_id
$analytics_summary = [
    'total_students' => 247,
    'avg_completion_rate' => '78%',
    'total_revenue' => '$5,870', // For courses with pricing
    'top_performing_course' => 'Web Development Bootcamp',
];
$course_specific_analytics = [
    'web_dev' => ['name' => 'Web Development Bootcamp', 'students' => 156, 'completion' => 85, 'avg_score' => '88%', 'discussion_posts' => 120],
    'cs_fun' => ['name' => 'CS Fundamentals', 'students' => 89, 'completion' => 70, 'avg_score' => '75%', 'discussion_posts' => 75],
];
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">Course Analytics</h1> 
    <p class="lead mb-4">Gain insights into your course performance and student engagement.</p>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card h-100"><h6>Total Students</h6><div class="stat-value"><?php echo $analytics_summary['total_students']; ?></div></div>
        </div>
        <div class="col-md-3">
            <div class="stat-card h-100"><h6>Avg. Completion</h6><div class="stat-value"><?php echo $analytics_summary['avg_completion_rate']; ?></div></div>
        </div>
        <div class="col-md-3">
            <div class="stat-card h-100"><h6>Total Revenue</h6><div class="stat-value"><?php echo $analytics_summary['total_revenue']; ?></div></div>
        </div>
        <div class="col-md-3">
            <div class="stat-card h-100"><h6>Top Course</h6><div class="stat-value fs-5"><?php echo htmlspecialchars($analytics_summary['top_performing_course']); ?></div></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Student Engagement Overview</h5>
            <select class="form-select form-select-sm" style="max-width: 250px;">
                <option selected>All Courses</option>
                <option value="web_dev">Web Development Bootcamp</option>
                <option value="cs_fun">CS Fundamentals</option>
            </select>
        </div>
        <div class="card-body">
            <div class="chart-container" style="height:300px;">
                <canvas id="studentEngagementChart"></canvas> 
            </div>
             <p class="text-muted text-center small mt-2">Mock chart: Active students vs. time spent.</p>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">Detailed Course Performance</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Course Name</th>
                            <th>Enrolled Students</th>
                            <th>Completion Rate</th>
                            <th>Avg. Quiz Score</th>
                            <th>Discussion Posts</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($course_specific_analytics as $course_analytic): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($course_analytic['name']); ?></strong></td>
                            <td><?php echo $course_analytic['students']; ?></td>
                            <td><?php echo $course_analytic['completion']; ?>%</td>
                            <td><?php echo htmlspecialchars($course_analytic['avg_score']); ?></td>
                            <td><?php echo $course_analytic['discussion_posts']; ?></td>
                            <td><a href="#" class="btn btn-sm btn-outline-primary">View Full Report</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
$chart_data_instructor = [
    'studentEngagement' => [
        'labels' => ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
        'datasets' => [
            ['label' => 'Active Students', 'data' => [150, 180, 165, 200], 'borderColor' => 'rgba(0, 123, 255, 1)', 'tension' => 0.3],
            ['label' => 'Avg. Time Spent (hrs)', 'data' => [2.5, 3, 2.8, 3.5], 'borderColor' => 'rgba(255, 193, 7, 1)', 'tension' => 0.3, 'yAxisID' => 'y1'],
        ]
    ]
];
echo '<script>const chartData = ' . (isset($chartData) ? json_encode(array_merge($chartData, $chart_data_instructor)) : json_encode($chart_data_instructor)) . ';</script>';
$extra_js = [BASE_URL . 'assets/js/dashboard_charts.js'];
include '../includes/dashboard_footer.php';
?>
