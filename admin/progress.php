<?php
define('BASE_URL', '../');
$page_title = "My Progress";
$_SESSION['user_role'] = 'student';
$use_charts = true; // This page will use charts
include '../includes/dashboard_header.php';

// Mock data for progress
$overall_progress = 72; // Percentage
$courses_completed = 3;
$total_courses_enrolled = 5;
$xp_earned = 2450;
$badges_earned = [
    ['name' => 'JavaScript Novice', 'icon' => 'fab fa-js-square text-warning', 'date' => '2025-05-10'],
    ['name' => 'Quiz Master', 'icon' => 'fas fa-graduation-cap text-primary', 'date' => '2025-05-20'],
    ['name' => 'UX Explorer', 'icon' => 'fas fa-lightbulb text-info', 'date' => '2025-06-01'],
];
$course_specific_progress = [
    ['name' => 'JavaScript Fundamentals', 'progress' => 65, 'grade' => 'B+'],
    ['name' => 'UX Design Principles', 'progress' => 32, 'grade' => 'In Progress'],
    ['name' => 'Python for Data Science', 'progress' => 88, 'grade' => 'A-'],
];
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">My Progress</h1> 
    <p class="lead mb-4">Track your learning journey, view achievements, and identify areas for growth.</p>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="stat-card text-center h-100">
                <i class="fas fa-chart-pie fa-3x text-primary mb-3"></i>
                <h6>Overall Progress</h6>
                <div class="display-4 fw-bold"><?php echo $overall_progress; ?>%</div>
                <div class="progress mt-2" style="height:10px;">
                    <div class="progress-bar" role="progressbar" style="width: <?php echo $overall_progress; ?>%;" aria-valuenow="<?php echo $overall_progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="stat-card text-center h-100">
                <i class="fas fa-book-reader fa-3x text-success mb-3"></i>
                <h6>Courses Completed</h6>
                <div class="display-4 fw-bold"><?php echo $courses_completed; ?> <span class="fs-5 text-muted">/ <?php echo $total_courses_enrolled; ?></span></div>
                <small class="text-muted">Keep learning!</small>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="stat-card text-center h-100">
                <i class="fas fa-star fa-3x text-warning mb-3"></i>
                <h6>XP Earned</h6>
                <div class="display-4 fw-bold"><?php echo number_format($xp_earned); ?></div>
                <small class="text-muted">Level 8</small>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Course Specific Progress</h5>
                </div>
                <div class="card-body">
                    <?php if(empty($course_specific_progress)): ?>
                        <p class="text-muted">No course progress to display yet.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                        <?php foreach($course_specific_progress as $course_prog): ?>
                            <li class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($course_prog['name']); ?></h6>
                                    <small class="text-muted">Grade: <?php echo htmlspecialchars($course_prog['grade']); ?></small>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $course_prog['progress']; ?>%;" aria-valuenow="<?php echo $course_prog['progress']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <small><?php echo $course_prog['progress']; ?>% Complete</small>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Badges & Achievements</h5>
                </div>
                <div class="card-body">
                    <?php if(empty($badges_earned)): ?>
                        <p class="text-muted">No badges earned yet. Keep learning to unlock them!</p>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach($badges_earned as $badge): ?>
                        <div class="col-4 text-center mb-3">
                            <i class="<?php echo htmlspecialchars($badge['icon']); ?> fa-3x mb-1"></i>
                            <p class="small mb-0 fw-bold"><?php echo htmlspecialchars($badge['name']); ?></p>
                            <small class="text-muted d-block" style="font-size:0.7em;">Earned: <?php echo date("M d, Y", strtotime($badge['date'])); ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Learning Activity Over Time</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height:250px;">
                        <canvas id="learningActivityChart"></canvas> 
                    </div>
                    <p class="text-muted text-center small mt-2">Mock chart: Lessons completed per week.</p>
                </div>
            </div>
        </div>
    </div>

</div>
<?php
// Pass data for charts to JS
$chart_data_progress = [
    'learningActivity' => [
        'labels' => ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6'],
        'datasets' => [
            [
                'label' => 'Lessons Completed',
                'data' => [5, 7, 4, 8, 6, 9],
                'borderColor' => 'rgba(25, 135, 84, 1)', // green
                'backgroundColor' => 'rgba(25, 135, 84, 0.1)',
                'fill' => true,
                'tension' => 0.3
            ]
        ]
    ]
];
echo '<script>const chartData = ' . (isset($chartData) ? json_encode(array_merge($chartData, $chart_data_progress)) : json_encode($chart_data_progress)) . ';</script>';

$extra_js = [BASE_URL . 'assets/js/dashboard_charts.js'];
include '../includes/dashboard_footer.php';
?>

