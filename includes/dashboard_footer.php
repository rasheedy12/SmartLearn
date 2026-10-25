<?php
// includes/dashboard_footer.php
?>
            </main> <?php // End of .page-content ?>
             <footer class="py-3 px-4 border-top bg-light mt-auto"> 
                <div class="container-fluid">
                    <small class="text-muted">&copy; <?php echo date("Y"); ?> SmartLearn. All rights reserved. | <a href="#" class="text-muted">Privacy Policy</a> | <a href="#" class="text-muted">Terms of Service</a></small>
                </div>
            </footer>
        </div> <?php // End of .main-content ?>
    </div> <?php // End of .dashboard-wrapper ?>

    <script src="<?php echo BASE_URL_INC; ?>assets/js/theme.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo BASE_URL_INC; ?>assets/js/action-feedback.js"></script>
    <?php if (isset($extra_js) && is_array($extra_js)): foreach ($extra_js as $js_file): ?>
        <script src="<?php echo $js_file; ?>"></script>
    <?php endforeach; endif; ?>
    <?php if (isset($use_charts) && $use_charts): ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="<?php echo BASE_URL_INC; ?>assets/js/dashboard_charts.js"></script>
    <?php endif;?>
</body>
</html>
