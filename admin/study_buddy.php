<?php
define('BASE_URL', '../');
$page_title = "Study Buddies";
$_SESSION['user_role'] = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'student';
include '../includes/dashboard_header.php';

$mock_buddies = [
    ['name' => 'Sarah Chen', 'avatar' => BASE_URL . 'assets/images/avatars/student_1.png', 'courses' => ['JavaScript', 'UX Design'], 'status' => 'Online', 'bio' => 'Eager learner, loves JS & Python. Looking for project partners!'],
    ['name' => 'Mike Rodriguez', 'avatar' => BASE_URL . 'assets/images/avatars/student_2.png', 'courses' => ['JavaScript', 'Data Science'], 'status' => 'Offline', 'bio' => 'Data enthusiast, enjoys collaborative problem solving.'],
    ['name' => 'Anna Ivanova', 'avatar' => BASE_URL . 'assets/images/avatars/student_3.png', 'courses' => ['UX Design', 'Graphic Design'], 'status' => 'Online', 'bio' => 'Creative designer, passionate about user-centered solutions.'],
];
$mock_groups = [
    ['name' => 'JavaScript Study Group', 'members' => 15, 'activity' => 'Active daily', 'course_focus' => 'JavaScript Fundamentals'],
    ['name' => 'UX Design Project Team', 'members' => 8, 'activity' => 'Weekly meetups', 'course_focus' => 'UX Design Principles'],
];
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">Study Buddies</h1>
    <p class="lead mb-4">Connect with fellow learners, share progress, and stay motivated together!</p>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h4 class="h5 mb-0">Find Study Buddies</h4>
            <form class="d-flex">
                <input class="form-control form-control-sm me-2" type="search" placeholder="Search by name or course..." aria-label="Search Buddies">
                <button class="btn btn-sm btn-outline-primary" type="submit">Search</button>
            </form>
        </div>
        <div class="card-body">
            <?php if(empty($mock_buddies)): ?>
                <p class="text-muted text-center">No study buddies found matching your criteria. Try broadening your search!</p>
            <?php else: ?>
                <div class="list-group list-group-flush">
                <?php foreach($mock_buddies as $buddy): ?>
                    <div class="list-group-item p-3">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <img src="<?php echo htmlspecialchars($buddy['avatar']); ?>" alt="<?php echo htmlspecialchars($buddy['name']); ?>" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;" onerror="this.src='<?php echo BASE_URL; ?>assets/images/user_avatar_placeholder.png'">
                            </div>
                            <div class="col">
                                <h6 class="mb-0"><?php echo htmlspecialchars($buddy['name']); ?> 
                                    <?php if($buddy['status'] == 'Online'): ?>
                                        <span class="badge bg-success rounded-pill ms-1" style="font-size: 0.7em;">Online</span>
                                    <?php endif; ?>
                                </h6>
                                <small class="text-muted">Common Courses: <?php echo implode(', ', array_map('htmlspecialchars', $buddy['courses'])); ?></small>
                                <p class="small mb-0 mt-1 fst-italic">"<?php echo htmlspecialchars($buddy['bio']); ?>"</p>
                            </div>
                            <div class="col-md-auto mt-2 mt-md-0 text-md-end">
                                <button class="btn btn-sm btn-primary me-1"><i class="fas fa-user-plus me-1"></i> Connect</button>
                                <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye me-1"></i> View Profile</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h4 class="h5 mb-0">Study Groups</h4>
            <button class="btn btn-sm btn-success"><i class="fas fa-plus me-1"></i> Create Group</button>
        </div>
        <div class="card-body">
             <?php if(empty($mock_groups)): ?>
                <p class="text-muted text-center">No study groups available yet. Why not <a href="#">create one</a>?</p>
            <?php else: ?>
                <div class="list-group list-group-flush">
                <?php foreach($mock_groups as $group): ?>
                    <div class="list-group-item p-3">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.5rem;">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                            <div class="col">
                                <h6 class="mb-0"><?php echo htmlspecialchars($group['name']); ?></h6>
                                <small class="text-muted">Focus: <?php echo htmlspecialchars($group['course_focus']); ?> | <?php echo $group['members']; ?> members | <?php echo htmlspecialchars($group['activity']); ?></small>
                            </div>
                            <div class="col-md-auto mt-2 mt-md-0 text-md-end">
                                <button class="btn btn-sm btn-info"><i class="fas fa-sign-in-alt me-1"></i> Join Group</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
include '../includes/dashboard_footer.php';
?>
