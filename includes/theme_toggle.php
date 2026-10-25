<?php
if (!function_exists('smartlearn_theme_toggle')) {
    function smartlearn_theme_toggle($extra_class = '')
    {
        $class = trim('theme-toggle ' . $extra_class);
        ?>
        <button class="<?php echo htmlspecialchars($class, ENT_QUOTES, 'UTF-8'); ?>"
                type="button"
                data-theme-toggle
                aria-label="Switch to dark theme"
                title="Switch to dark theme">
            <svg class="theme-toggle-icon theme-toggle-icon-sun" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <circle cx="12" cy="12" r="4"></circle>
                <path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"></path>
            </svg>
            <svg class="theme-toggle-icon theme-toggle-icon-moon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M20.2 15.1A8.5 8.5 0 0 1 8.9 3.8 8.5 8.5 0 1 0 20.2 15.1Z"></path>
            </svg>
            <span class="visually-hidden" data-theme-label>Switch to dark theme</span>
        </button>
        <?php
    }
}
