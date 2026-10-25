<section id="testimonials" class="section-padding bg-light">
    <div class="container">
        <h2 class="text-center mb-5 fw-bold">What Our Users Say</h2>
        <div class="row">
            <?php
            $testimonials = [
                ["quote" => "This platform revolutionized how I study. The interactive tools are fantastic and so easy to use!", "user_img" => "https://placehold.co/50x50/A9A9A9/FFF?text=AP&font=montserrat", "name" => "Alex P.", "title" => "University Student"],
                ["quote" => "As an educator, SmartLearn has made it so much easier to create and manage my online courses effectively.", "user_img" => "https://placehold.co/50x50/A9A9A9/FFF?text=SK&font=montserrat", "name" => "Dr. Sarah K.", "title" => "Professor"],
                ["quote" => "Our institution has seen a significant improvement in student engagement since adopting SmartLearn.", "user_img" => "https://placehold.co/50x50/A9A9A9/FFF?text=JB&font=montserrat", "name" => "John B.", "title" => "School Administrator"]
            ];

            foreach ($testimonials as $testimonial):
            ?>
            <div class="col-md-4 mb-4">
                <div class="card testimonial-card p-3 h-100 shadow-sm">
                    <div class="card-body">
                        <p class="card-text fst-italic">"<?php echo htmlspecialchars($testimonial['quote']); ?>"</p>
                        <div class="d-flex align-items-center mt-4">
                            <img src="<?php echo htmlspecialchars($testimonial['user_img']); ?>" class="rounded-circle me-3" alt="<?php echo htmlspecialchars($testimonial['name']); ?>">
                            <div>
                                <h6 class="mb-0 fw-semibold"><?php echo htmlspecialchars($testimonial['name']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($testimonial['title']); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="row mt-5 text-center">
            <div class="col-md-4 mb-3">
                <div class="p-3">
                    <h3 class="fw-bold display-5 metric-accent-primary">92%</h3>
                    <p class="text-muted fs-5">Increased Engagement</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="p-3">
                    <h3 class="fw-bold display-5 metric-accent-success">85%</h3>
                    <p class="text-muted fs-5">Improved Grades</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="p-3">
                    <h3 class="fw-bold display-5 metric-accent-warning">95%</h3>
                    <p class="text-muted fs-5">User Satisfaction</p>
                </div>
            </div>
        </div>
    </div>
</section>
