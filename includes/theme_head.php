<script>
    (() => {
        const root = document.documentElement;
        try {
            const savedTheme = localStorage.getItem('smartlearn-theme');
            const theme = savedTheme === 'dark' ? 'dark' : 'light';
            root.dataset.theme = theme;
            root.dataset.bsTheme = theme;
        } catch (_error) {
            root.dataset.theme = 'light';
            root.dataset.bsTheme = 'light';
        }
    })();
</script>
