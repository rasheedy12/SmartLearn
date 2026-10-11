<?php
define('BASE_URL', '../');
$page_title = "Create New Course";
$_SESSION['user_role'] = 'instructor';
include '../includes/dashboard_header.php';
?>
<div class="container-fluid">
    <h1 class="h2 mb-4">Create New Course</h1> 
    <p class="lead mb-4">Fill out the details below to create your new course. You can add content and modules later.</p>

    <form action="#" method="POST" enctype="multipart/form-data"> 
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Basic Information</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="courseTitle" class="form-label">Course Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="courseTitle" name="course_title" required>
                </div>
                <div class="mb-3">
                    <label for="courseSubtitle" class="form-label">Course Subtitle / Tagline</label>
                    <input type="text" class="form-control" id="courseSubtitle" name="course_subtitle">
                </div>
                <div class="mb-3">
                    <label for="courseDescription" class="form-label">Course Description <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="courseDescription" name="course_description" rows="5" required></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="courseCategory" class="form-label">Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="courseCategory" name="course_category" required>
                            <option selected disabled value="">Choose...</option>
                            <option value="web_development">Web Development</option>
                            <option value="data_science">Data Science</option>
                            <option value="design">Design</option>
                            <option value="marketing">Marketing</option>
                            <option value="business">Business</option>
                            {/* Add more categories */}
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="courseLevel" class="form-label">Skill Level <span class="text-danger">*</span></label>
                         <select class="form-select" id="courseLevel" name="course_level" required>
                            <option selected disabled value="">Choose...</option>
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                            <option value="all_levels">All Levels</option>
                        </select>
                    </div>
                </div>
                 <div class="mb-3">
                    <label for="courseImage" class="form-label">Course Thumbnail Image</label>
                    <input class="form-control" type="file" id="courseImage" name="course_image" accept="image/*">
                    <small class="form-text text-muted">Recommended size: 600x400 pixels.</small>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Pricing</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="coursePrice" class="form-label">Price (USD) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="coursePrice" name="course_price" placeholder="e.g., 49.99" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="courseDiscountPrice" class="form-label">Discounted Price (USD)</label>
                         <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="courseDiscountPrice" name="course_discount_price" placeholder="e.g., 29.99" step="0.01" min="0">
                        </div>
                    </div>
                </div>
                 <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="isFreeCourse" name="is_free_course">
                    <label class="form-check-label" for="isFreeCourse">
                        This is a free course
                    </label>
                </div>
            </div>
        </div>
        
        <div class="text-end">
            <button type="submit" name="save_draft" class="btn btn-outline-secondary me-2">Save as Draft</button>
            <button type="submit" name="publish_course" class="btn btn-primary">Save and Proceed to Add Content</button>
        </div>
    </form>
</div>
<?php include '../includes/dashboard_footer.php'; ?>

