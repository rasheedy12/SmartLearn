<?php
require_once __DIR__ . '/../includes/student_helpers.php';

define('BASE_URL', '../');
$page_title = 'Browse Courses';
$search = trim((string) ($_GET['q'] ?? ''));
$category_id = filter_var($_GET['category'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$page = max(1, filter_var($_GET['page'] ?? null, FILTER_VALIDATE_INT) ?: 1);
$per_page = 12;
$offset = ($page - 1) * $per_page;
$error_message = '';
$courses = [];
$categories = [];
$total_courses = 0;

try {
    $categories_result = $mysqli->query('SELECT category_id, name FROM categories ORDER BY name');
    $categories = $categories_result->fetch_all(MYSQLI_ASSOC);

    $where = "c.status = 'published'";
    $types = '';
    $params = [];
    if ($search !== '') {
        $where .= ' AND (c.title LIKE ? OR c.subtitle LIKE ? OR c.description LIKE ? OR CONCAT(u.first_name, " ", u.last_name) LIKE ?)';
        $term = '%' . $search . '%';
        $types .= 'ssss';
        array_push($params, $term, $term, $term, $term);
    }
    if ($category_id > 0) {
        $where .= ' AND c.category_id = ?';
        $types .= 'i';
        $params[] = $category_id;
    }

    $count_sql = "SELECT COUNT(*) AS total
        FROM courses c
        JOIN users u ON u.user_id = c.instructor_id
        WHERE $where";
    $count_stmt = $mysqli->prepare($count_sql);
    if ($types !== '') {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $total_courses = (int) $count_stmt->get_result()->fetch_assoc()['total'];

    $course_sql = "SELECT c.course_id, c.title, c.subtitle, c.thumbnail_url, c.price,
            c.discounted_price, c.is_free, c.average_rating, c.ratings_count,
            c.enrolled_students_count, cat.name AS category_name,
            CONCAT(u.first_name, ' ', u.last_name) AS instructor_name
        FROM courses c
        JOIN users u ON u.user_id = c.instructor_id
        LEFT JOIN categories cat ON cat.category_id = c.category_id
        WHERE $where
        ORDER BY c.published_at DESC, c.course_id DESC
        LIMIT ? OFFSET ?";
    $course_stmt = $mysqli->prepare($course_sql);
    $page_types = $types . 'ii';
    $page_params = array_merge($params, [$per_page, $offset]);
    $course_stmt->bind_param($page_types, ...$page_params);
    $course_stmt->execute();
    $courses = $course_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $exception) {
    error_log('Browse courses query failed: ' . $exception->getMessage());
    $error_message = 'Courses could not be loaded right now. Please try again later.';
}

$page_count = (int) ceil($total_courses / $per_page);
$page_title = 'Browse Courses';
include __DIR__ . '/../includes/dashboard_header.php';

function browse_courses_url($page_number)
{
    $query = $_GET;
    $query['page'] = $page_number;
    return 'browse_courses.php?' . http_build_query($query);
}
?>
<div class="container-fluid">
    <h1 class="mb-3">Browse Courses</h1>
    <p class="lead mb-4">Discover courses published by SmartLearn instructors.</p>

    <form class="row g-2 mb-4" method="get" action="browse_courses.php">
        <div class="col-md-8">
            <label class="visually-hidden" for="course-search">Search courses</label>
            <input id="course-search" class="form-control" type="search" name="q"
                   value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                   placeholder="Search courses, topics, or instructors">
        </div>
        <div class="col-md-3">
            <label class="visually-hidden" for="course-category">Filter by category</label>
            <select id="course-category" class="form-select" name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo (int) $category['category_id']; ?>"
                        <?php echo $category_id === (int) $category['category_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1 d-grid">
            <button class="btn btn-primary" type="submit" aria-label="Search courses">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </form>

    <?php if ($error_message !== ''): ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error_message); ?></div>
    <?php elseif ($courses === []): ?>
        <div class="alert alert-info text-center">
            <h2 class="h5">No published courses found</h2>
            <p class="mb-0">
                <?php echo $search !== '' || $category_id > 0
                    ? 'Try changing your search or category filter.'
                    : 'There are no courses available to browse yet. Please check back later.'; ?>
            </p>
        </div>
    <?php else: ?>
        <p class="text-muted"><?php echo number_format($total_courses); ?> course<?php echo $total_courses === 1 ? '' : 's'; ?> found</p>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">
            <?php foreach ($courses as $course): ?>
                <?php
                $thumbnail = trim((string) $course['thumbnail_url']);
                if ($thumbnail !== '' && !preg_match('~^(https?://|/)~i', $thumbnail)) {
                    $thumbnail = BASE_URL . ltrim($thumbnail, '/');
                }
                $course_price = (int) $course['is_free'] === 1
                    ? 0
                    : (float) ($course['discounted_price'] ?? $course['price']);
                ?>
                <div class="col">
                    <article class="card h-100 shadow-sm">
                        <?php if ($thumbnail !== ''): ?>
                            <img src="<?php echo htmlspecialchars($thumbnail, ENT_QUOTES, 'UTF-8'); ?>"
                                 class="card-img-top" alt="<?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                 style="height: 180px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-light d-flex align-items-center justify-content-center text-muted"
                                 style="height: 180px;">Course image unavailable</div>
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <h2 class="card-title fs-6"><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                            <p class="small text-muted mb-1">By <?php echo htmlspecialchars($course['instructor_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="small text-muted mb-2"><?php echo htmlspecialchars($course['category_name'] ?? 'Uncategorized', ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php if (!empty($course['subtitle'])): ?>
                                <p class="small"><?php echo htmlspecialchars($course['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between align-items-center mt-auto mb-3">
                                <strong class="text-primary"><?php echo $course_price <= 0 ? 'Free' : '$' . number_format($course_price, 2); ?></strong>
                                <span class="small" title="<?php echo (int) $course['ratings_count']; ?> ratings">
                                    <i class="fas fa-star text-warning"></i>
                                    <?php echo number_format((float) $course['average_rating'], 1); ?>
                                    (<?php echo (int) $course['ratings_count']; ?>)
                                </span>
                            </div>
                            <a href="course_detail.php?id=<?php echo (int) $course['course_id']; ?>"
                               class="btn btn-outline-primary w-100">View details</a>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($page_count > 1): ?>
            <nav aria-label="Course pages" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo htmlspecialchars(browse_courses_url(max(1, $page - 1)), ENT_QUOTES, 'UTF-8'); ?>">Previous</a>
                    </li>
                    <?php for ($page_number = 1; $page_number <= $page_count; $page_number++): ?>
                        <li class="page-item <?php echo $page_number === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars(browse_courses_url($page_number), ENT_QUOTES, 'UTF-8'); ?>"
                               <?php echo $page_number === $page ? 'aria-current="page"' : ''; ?>>
                                <?php echo $page_number; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $page_count ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo htmlspecialchars(browse_courses_url(min($page_count, $page + 1)), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/dashboard_footer.php'; ?>
