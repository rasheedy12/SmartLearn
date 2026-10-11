<?php
define('BASE_URL', '../');
// In real app: $course_id = $_GET['course_id']; $lesson_id = $_GET['lesson_id'];
// Fetch lesson and course data.
$mock_lesson_data = [
    'course_title' => 'JavaScript Fundamentals',
    'lesson_title' => 'Setting Up Your Environment',
    'video_url' => 'https://www.w3schools.com/html/mov_bbb.mp4', // Placeholder
    'progress' => 60, // Overall course progress
    'notes' => [
        ['title' => 'Key Takeaway 1', 'content' => 'Node.js is essential for server-side JS.', 'time' => '2 min ago'],
        ['title' => 'Remember This', 'content' => 'Use `npm init` to start a new project.', 'time' => '5 min ago'],
    ]
];
$page_title = htmlspecialchars($mock_lesson_data['lesson_title']);
$_SESSION['user_role'] = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'student';

// Custom header for lesson view (more focused)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SmartLearn</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/style.css">
    <style>
        body { background-color: #212529; color: #f8f9fa; display: flex; flex-direction: column; min-height: 100vh; }
        .lesson-header { background-color: #343a40; padding: 0.75rem 1.5rem; border-bottom: 1px solid #495057;}
        .lesson-header .navbar-brand, .lesson-header .nav-link { color: #f8f9fa; }
        .lesson-header .progress { height: 6px; background-color: #495057; }
        .lesson-main-content { flex-grow: 1; display: flex; overflow: hidden; } /* Key for layout */
        .video-container { flex-grow: 1; background-color: #000; display:flex; align-items:center; justify-content:center; padding: 1rem; }
        .video-container video { max-width: 100%; max-height: calc(100vh - 150px); /* Adjust based on header/footer */ }
        .lesson-sidebar { width: 320px; background-color: #2c3034; padding: 1rem; border-left: 1px solid #495057; overflow-y: auto; }
        .lesson-sidebar .nav-tabs .nav-link { color: #adb5bd; border-color: #495057 #495057 #2c3034; }
        .lesson-sidebar .nav-tabs .nav-link.active { color: #fff; background-color: #2c3034; border-color: #495057 #495057 #fff; }
        .lesson-sidebar .list-group-item { background-color: transparent; border-color: #495057; color: #adb5bd; }
        .lesson-footer { background-color: #343a40; padding: 1rem; border-top: 1px solid #495057; }
        .btn-lesson-nav { background-color: #495057; border-color: #495057; color: #fff; }
        .btn-lesson-nav:hover { background-color: #5a6268; border-color: #5a6268; }
        .btn-complete { background-color: #28a745; border-color: #28a745; color: #fff; }
        .btn-complete:hover { background-color: #218838; border-color: #1e7e34; }
    </style>
</head>
<body>
    <header class="lesson-header">
        <nav class="navbar navbar-expand navbar-dark">
            <div class="container-fluid">
                <a class="navbar-brand fs-6" href="<?php echo BASE_URL; ?>course_detail.php?id=1">
                    <i class="fas fa-arrow-left me-2"></i> <?php echo htmlspecialchars($mock_lesson_data['course_title']); ?>
                </a>
                <div class="ms-auto d-flex align-items-center" style="min-width: 200px;">
                    <small class="me-2">Progress: <?php echo $mock_lesson_data['progress']; ?>%</small>
                    <div class="progress flex-grow-1">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $mock_lesson_data['progress']; ?>%;" aria-valuenow="<?php echo $mock_lesson_data['progress']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <ul class="navbar-nav ms-3">
                    <li class="nav-item">
                        <a class="nav-link" href="#" title="User Profile">
                            <img src="<?php echo BASE_URL; ?>assets/images/user_avatar_placeholder.png" class="user-avatar" style="width:24px; height:24px;">
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
    </header>

    <div class="lesson-main-content">
        <div class="video-container">
            <video controls autoplay class="embed-responsive-item">
                <source src="<?php echo htmlspecialchars($mock_lesson_data['video_url']); ?>" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        </div>
        <aside class="lesson-sidebar">
            <h5 class="mb-3"><?php echo htmlspecialchars($mock_lesson_data['lesson_title']); ?></h5>
            <ul class="nav nav-tabs nav-fill mb-3" id="lessonTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes-panel" type="button" role="tab" aria-controls="notes-panel" aria-selected="true">Notes</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="discussion-tab" data-bs-toggle="tab" data-bs-target="#discussion-panel" type="button" role="tab" aria-controls="discussion-panel" aria-selected="false">Discussion</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="quiz-tab" data-bs-toggle="tab" data-bs-target="#quiz-panel" type="button" role="tab" aria-controls="quiz-panel" aria-selected="false">Quiz</button>
                </li>
            </ul>
            <div class="tab-content" id="lessonTabsContent">
                <div class="tab-pane fade show active" id="notes-panel" role="tabpanel" aria-labelledby="notes-tab">
                    <button class="btn btn-sm btn-outline-light w-100 mb-3">Add a New Note</button>
                    <?php if(empty($mock_lesson_data['notes'])): ?>
                        <p class="text-center text-muted">No notes yet for this lesson.</p>
                    <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach($mock_lesson_data['notes'] as $note): ?>
                        <li class="list-group-item">
                            <h6 class="mb-1 small"><?php echo htmlspecialchars($note['title']); ?></h6>
                            <p class="mb-1 small"><?php echo htmlspecialchars($note['content']); ?></p>
                            <small class="text-muted"><?php echo htmlspecialchars($note['time']); ?></small>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <div class="tab-pane fade" id="discussion-panel" role="tabpanel" aria-labelledby="discussion-tab">
                    <p class="text-center text-muted">Discussion forum coming soon.</p>
                </div>
                <div class="tab-pane fade" id="quiz-panel" role="tabpanel" aria-labelledby="quiz-tab">
                    <p class="text-center text-muted">Quiz will be available after completing the video.</p>
                    <button class="btn btn-sm btn-secondary w-100" disabled>Start Quiz</button>
                </div>
            </div>
        </aside>
    </div>

    <footer class="lesson-footer mt-auto">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <button class="btn btn-lesson-nav"><i class="fas fa-chevron-left me-1"></i> Previous</button>
            <button class="btn btn-complete"><i class="fas fa-check-circle me-1"></i> Mark as Complete</button>
            <button class="btn btn-lesson-nav">Next <i class="fas fa-chevron-right ms-1"></i></button>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
