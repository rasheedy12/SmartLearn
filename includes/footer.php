<?php
$current_page = basename($_SERVER['PHP_SELF']);
$is_auth_page = in_array($current_page, ['login.php', 'register.php']);
?>
    <?php if (!$is_auth_page): ?>
    <footer class="footer bg-dark text-light py-5">
        <div class="container">
            <div class="row g-4">
                <!-- Company Info -->
                <div class="col-lg-4 col-md-6">
                    <div class="footer-content">
                        <h5 class="fw-bold mb-3">
                            <i class="fas fa-graduation-cap me-2"></i>SmartLearn
                        </h5>
                        <p class="text-light-emphasis mb-3">Empowering learners and educators through innovative technology solutions. Join us in revolutionizing education.</p>
                        <div class="newsletter mb-3">
                            <h6 class="fw-semibold">Subscribe to Our Newsletter</h6>
                            <div class="input-group">
                                <input type="email" class="form-control" placeholder="Enter your email">
                                <button class="btn btn-primary" type="button">Subscribe</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="col-lg-2 col-md-6">
                    <h6 class="text-uppercase fw-semibold mb-3">Product</h6>
                    <ul class="list-unstyled footer-links">
                        <li><a href="<?php echo BASE_URL; ?>index.php#features">Features</a></li>
                        <li><a href="#">Pricing</a></li>
                        <li><a href="#">Testimonials</a></li>
                        <li><a href="#">Case Studies</a></li>
                    </ul>
                </div>

                <!-- Company -->
                <div class="col-lg-2 col-md-6">
                    <h6 class="text-uppercase fw-semibold mb-3">Company</h6>
                    <ul class="list-unstyled footer-links">
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Contact</a></li>
                        <li><a href="#">Careers</a></li>
                        <li><a href="#">Partners</a></li>
                    </ul>
                </div>

                <!-- Resources -->
                <div class="col-lg-2 col-md-6">
                    <h6 class="text-uppercase fw-semibold mb-3">Resources</h6>
                    <ul class="list-unstyled footer-links">
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                        <li><a href="#">Documentation</a></li>
                    </ul>
                </div>

                <!-- Social Links -->
                <div class="col-lg-2 col-md-6">
                    <h6 class="text-uppercase fw-semibold mb-3">Connect</h6>
                    <div class="social-links mb-3">
                        <a href="#" class="me-2 btn btn-outline-light btn-sm">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="me-2 btn btn-outline-light btn-sm">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="me-2 btn btn-outline-light btn-sm">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="me-2 btn btn-outline-light btn-sm">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Bottom Footer -->
            <div class="row mt-4 pt-3 border-top">
                <div class="col-md-6">
                    <p class="small mb-md-0">&copy; <?php echo date("Y"); ?> SmartLearn. All Rights Reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <ul class="list-inline small mb-0">
                        <li class="list-inline-item"><a href="#">Terms</a></li>
                        <li class="list-inline-item">·</li>
                        <li class="list-inline-item"><a href="#">Privacy</a></li>
                        <li class="list-inline-item">·</li>
                        <li class="list-inline-item"><a href="#">Cookies</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <!-- Add this CSS to your stylesheet -->
    <style>
        .footer {
            background-color: #1a1a1a;
        }
        .footer-links a {
            color: #a8a8a8;
            text-decoration: none;
            transition: color 0.3s;
            line-height: 2;
        }
        .footer-links a:hover {
            color: #ffffff;
        }
        .social-links a {
            width: 35px;
            height: 35px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        .social-links a:hover {
            transform: translateY(-3px);
        }
    </style>

    <?php endif; ?>

    <script src="<?php echo BASE_URL; ?>assets/js/theme.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/action-feedback.js"></script>
</body>
</html>
