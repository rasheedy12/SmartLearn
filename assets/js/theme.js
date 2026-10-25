(() => {
    const storageKey = 'smartlearn-theme';
    const root = document.documentElement;
    const validThemes = ['light', 'dark'];

    function applyTheme(theme, persist = false) {
        if (!validThemes.includes(theme)) {
            return;
        }

        root.dataset.theme = theme;
        root.dataset.bsTheme = theme;

        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            const label = `Switch to ${theme === 'dark' ? 'light' : 'dark'} theme`;
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
            const hiddenLabel = button.querySelector('[data-theme-label]');
            if (hiddenLabel) {
                hiddenLabel.textContent = label;
            }
        });

        if (persist) {
            try {
                localStorage.setItem(storageKey, theme);
            } catch (_error) {
                // Theme still applies for this page when storage is unavailable.
            }
        }

        window.dispatchEvent(new CustomEvent('smartlearn:themechange', { detail: { theme } }));
    }

    applyTheme(root.dataset.theme === 'dark' ? 'dark' : 'light');

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark', true);
        });
    });

    window.addEventListener('storage', (event) => {
        if (event.key === storageKey && validThemes.includes(event.newValue)) {
            applyTheme(event.newValue);
        }
    });
})();
