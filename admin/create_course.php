<?php
define('BASE_URL', '../');
require_once BASE_URL . 'includes/instructor_helpers.php';
$instructor_id = (int) $_SESSION['user_id'];
$page_title = 'Create New Course';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!instructor_verify_csrf()) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    }

    $title = trim($_POST['course_title'] ?? '');
    $subtitle = trim($_POST['course_subtitle'] ?? '');
    $description = trim($_POST['course_description'] ?? '');
    $category_id = filter_var($_POST['course_category'] ?? null, FILTER_VALIDATE_INT);
    $skill_level = $_POST['course_level'] ?? '';
    $price_input = $_POST['course_price'] ?? '';
    $discount_input = $_POST['course_discount_price'] ?? '';
    $is_free = isset($_POST['is_free_course']);
    $save_as_draft = isset($_POST['save_draft']);

    if ($title === '' || strlen($title) > 255) {
        $errors[] = 'Enter a course title of no more than 255 characters.';
    }
    if ($description === '') {
        $errors[] = 'Enter a course description.';
    }
    if (!$category_id || $category_id < 1) {
        $errors[] = 'Choose a course category.';
    }
    if (!in_array($skill_level, ['beginner', 'intermediate', 'advanced', 'all_levels'], true)) {
        $errors[] = 'Choose a valid skill level.';
    }
    if (!is_numeric($price_input) || (float) $price_input < 0) {
        $errors[] = 'Enter a valid non-negative course price.';
    }
    $price = $is_free ? 0.0 : (float) $price_input;
    $discount = null;
    if (!$is_free && $discount_input !== '') {
        if (!is_numeric($discount_input) || (float) $discount_input < 0 || (float) $discount_input > $price) {
            $errors[] = 'The discounted price must be between zero and the course price.';
        } else {
            $discount = (float) $discount_input;
        }
    }

    if ($category_id) {
        $category_stmt = $mysqli->prepare('SELECT category_id FROM categories WHERE category_id = ?');
        $category_stmt->bind_param('i', $category_id);
        $category_stmt->execute();
        if (!$category_stmt->get_result()->fetch_assoc()) {
            $errors[] = 'The selected category is no longer available.';
        }
        $category_stmt->close();
    }

    $thumbnail_url = null;
    if (isset($_FILES['course_image']) && $_FILES['course_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['course_image']['error'] !== UPLOAD_ERR_OK || $_FILES['course_image']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'The course image upload failed or exceeds the 2 MB limit.';
        } else {
            $image_info = @getimagesize($_FILES['course_image']['tmp_name']);
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($_FILES['course_image']['tmp_name']);
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!$image_info || !isset($extensions[$mime_type])) {
                $errors[] = 'Upload a valid JPG, PNG, or WebP course image.';
            } else {
                $upload_directory = dirname(__DIR__) . '/assets/images/course_uploads';
                if (!is_dir($upload_directory) && !mkdir($upload_directory, 0755, true) && !is_dir($upload_directory)) {
                    $errors[] = 'The course image directory could not be created.';
                } else {
                    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime_type];
                    if (!move_uploaded_file($_FILES['course_image']['tmp_name'], $upload_directory . '/' . $filename)) {
                        $errors[] = 'The course image could not be saved.';
                    } else {
                        $thumbnail_url = 'assets/images/course_uploads/' . $filename;
                    }
                }
            }
        }
    }

    if (!$errors) {
        $slug_base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
        $slug_base = $slug_base !== '' ? substr($slug_base, 0, 240) : 'course';
        $slug = $slug_base;
        $slug_check = $mysqli->prepare('SELECT course_id FROM courses WHERE slug = ?');
        $slug_check->bind_param('s', $slug);
        $slug_check->execute();
        if ($slug_check->get_result()->fetch_assoc()) {
            $slug .= '-' . bin2hex(random_bytes(4));
        }
        $slug_check->close();

        $status = $save_as_draft ? 'draft' : 'pending_approval';
        $insert = $mysqli->prepare(
            'INSERT INTO courses
                (title, subtitle, slug, description, instructor_id, category_id, skill_level,
                 thumbnail_url, price, discounted_price, is_free, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->bind_param(
            'ssssiissddis',
            $title,
            $subtitle,
            $slug,
            $description,
            $instructor_id,
            $category_id,
            $skill_level,
            $thumbnail_url,
            $price,
            $discount,
            $is_free,
            $status
        );
        $insert->execute();
        $course_id = $insert->insert_id;
        $insert->close();

        instructor_flash('success', $save_as_draft ? 'Course draft saved.' : 'Course submitted for approval.');
        header('Location: course_content.php?course_id=' . $course_id);
        exit;
    }
}

$categories_result = $mysqli->query('SELECT category_id, name FROM categories ORDER BY name');
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);
include BASE_URL . 'includes/dashboard_header.php';
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">Create New Course</h1>
    <p class="lead mb-4">Create a draft or submit your course for approval, then add its modules and lessons.</p>
    <?php instructor_show_flash(); ?>
    <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="<?php echo BASE_URL; ?>create_course.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(instructor_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white"><h5 class="mb-0">Basic Information</h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="courseTitle" class="form-label">Course Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="courseTitle" name="course_title" maxlength="255" required value="<?php echo htmlspecialchars($_POST['course_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label for="courseSubtitle" class="form-label">Course Subtitle / Tagline</label>
                    <input type="text" class="form-control" id="courseSubtitle" name="course_subtitle" maxlength="255" value="<?php echo htmlspecialchars($_POST['course_subtitle'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-3">
                    <label for="courseDescription" class="form-label">Course Description <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="courseDescription" name="course_description" rows="5" required><?php echo htmlspecialchars($_POST['course_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="courseCategory" class="form-label">Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="courseCategory" name="course_category" required>
                            <option value="">Choose...</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo (int) $category['category_id']; ?>" <?php echo (string) ($category['category_id']) === (string) ($_POST['course_category'] ?? '') ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="courseLevel" class="form-label">Skill Level <span class="text-danger">*</span></label>
                        <select class="form-select" id="courseLevel" name="course_level" required>
                            <option value="">Choose...</option>
                            <?php foreach (['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced', 'all_levels' => 'All Levels'] as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo ($_POST['course_level'] ?? '') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="courseImage" class="form-label">Course Thumbnail Image</label>
                    <input class="form-control" type="file" id="courseImage" name="course_image" accept="image/jpeg,image/png,image/webp">
                    <small class="form-text text-muted">JPG, PNG, or WebP; maximum 2 MB.</small>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white"><h5 class="mb-0">Pricing</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="coursePrice" class="form-label">Price (USD) <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text">$</span><input type="number" class="form-control" id="coursePrice" name="course_price" step="0.01" min="0" required value="<?php echo htmlspecialchars($_POST['course_price'] ?? '0.00', ENT_QUOTES, 'UTF-8'); ?>"></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="courseDiscountPrice" class="form-label">Discounted Price (USD)</label>
                        <div class="input-group"><span class="input-group-text">$</span><input type="number" class="form-control" id="courseDiscountPrice" name="course_discount_price" step="0.01" min="0" value="<?php echo htmlspecialchars($_POST['course_discount_price'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
                    </div>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="isFreeCourse" name="is_free_course" <?php echo isset($_POST['is_free_course']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="isFreeCourse">This is a free course</label>
                </div>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" name="save_draft" class="btn btn-outline-secondary me-2">Save as Draft</button>
            <button type="submit" name="publish_course" class="btn btn-primary">Submit for Approval and Add Content</button>
        </div>
    </form>
</div>
<?php include BASE_URL . 'includes/dashboard_footer.php'; ?>
