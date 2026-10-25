
document.addEventListener('DOMContentLoaded', function () {
    // Check if Chart.js is loaded and chartData is available
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js is not loaded. Skipping chart initialization.');
        return;
    }
    if (typeof chartData === 'undefined') {
        console.warn('Global chartData object not available. Skipping chart initialization.');
        return;
    }

    let userGrowthChartInstance, learningActivityChartInstance, revenueChartInstance, userDemographicsChartInstance, studentEngagementChartInstance;

    function applyChartTheme(charts = []) {
        const styles = getComputedStyle(document.documentElement);
        const textColor = styles.getPropertyValue('--sl-text-muted').trim();
        const borderColor = styles.getPropertyValue('--sl-border').trim();
        const accentColor = styles.getPropertyValue('--sl-chart-accent').trim();
        const successColor = styles.getPropertyValue('--sl-chart-success').trim();
        const accentRgb = styles.getPropertyValue('--sl-primary-rgb').trim();
        const successRgb = styles.getPropertyValue('--sl-chart-success-rgb').trim();
        Chart.defaults.color = textColor;
        Chart.defaults.borderColor = borderColor;
        Chart.defaults.font.family = "'Inter', sans-serif";

        charts.forEach((chart) => {
            const isCategoricalChart = chart.canvas.id === 'userDemographicsChart';
            if (!isCategoricalChart) {
                chart.data.datasets.forEach((dataset, index) => {
                    const color = (chart.canvas.id === 'userGrowthChart' || chart.canvas.id === 'studentEngagementChart')
                        && index > 0
                        ? successColor
                        : accentColor;
                    dataset.borderColor = color;
                    const fillRgb = color === successColor ? successRgb : accentRgb;
                    dataset.backgroundColor = chart.config.type === 'line'
                        ? `rgba(${fillRgb}, 0.14)`
                        : color;
                });
            }
            chart.update('none');
        });
    }

    applyChartTheme();

    // Helper function to destroy existing chart if it exists
    function destroyChartIfExists(chartInstance) {
        if (chartInstance) {
            chartInstance.destroy();
        }
    }
    
    window.addEventListener('smartlearn:themechange', () => {
        applyChartTheme([userGrowthChartInstance, learningActivityChartInstance, revenueChartInstance,
            userDemographicsChartInstance, studentEngagementChartInstance].filter(Boolean));
    });

    // User Growth Chart (Admin Dashboard)
    const userGrowthCtx = document.getElementById('userGrowthChart');
    if (userGrowthCtx && chartData.userGrowth) {
        destroyChartIfExists(userGrowthChartInstance);
        userGrowthChartInstance = new Chart(userGrowthCtx, {
            type: 'line',
            data: chartData.userGrowth,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: chartData.userGrowth.datasets.length > 1 } }
            }
        });
        userGrowthChartInstance.data.datasets.forEach(function (_dataset, index) {
            userGrowthChartInstance.setDatasetVisibility(index, index === 0);
        });
        userGrowthChartInstance.update();

        document.querySelectorAll('[data-growth-role]').forEach(function (button) {
            button.addEventListener('click', function () {
                const datasetIndex = button.dataset.growthRole === 'instructor' ? 1 : 0;
                if (!userGrowthChartInstance.data.datasets[datasetIndex]) {
                    return;
                }

                userGrowthChartInstance.data.datasets.forEach(function (_dataset, index) {
                    userGrowthChartInstance.setDatasetVisibility(index, index === datasetIndex);
                });
                userGrowthChartInstance.update();

                document.querySelectorAll('[data-growth-role]').forEach(function (roleButton) {
                    const selected = roleButton === button;
                    roleButton.classList.toggle('active', selected);
                    roleButton.setAttribute('aria-pressed', selected ? 'true' : 'false');
                });
            });
        });
    }

    // Learning Activity Chart (Student Progress Page)
    const learningActivityCtx = document.getElementById('learningActivityChart');
    if (learningActivityCtx && chartData.learningActivity) {
        destroyChartIfExists(learningActivityChartInstance);
        learningActivityChartInstance = new Chart(learningActivityCtx, {
            type: 'bar', // Or 'line'
            data: chartData.learningActivity,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, title: { display: true, text: 'Lessons Completed' } } },
                plugins: { legend: { display: true } }
            }
        });
    }

    // Revenue Chart (Admin Reports Page)
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx && chartData.revenue) {
        destroyChartIfExists(revenueChartInstance);
        revenueChartInstance = new Chart(revenueCtx, {
            type: 'line',
            data: chartData.revenue,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, title: { display: true, text: 'Revenue ($)' } } },
                plugins: { legend: { display: true } }
            }
        });
    }

    // User Demographics Chart (Admin Reports Page)
    const userDemographicsCtx = document.getElementById('userDemographicsChart');
    if (userDemographicsCtx && chartData.userDemographics) {
        destroyChartIfExists(userDemographicsChartInstance);
        userDemographicsChartInstance = new Chart(userDemographicsCtx, {
            type: 'pie', // or 'doughnut'
            data: chartData.userDemographics,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } }
            }
        });
    }
    
    // Student Engagement Chart (Instructor Analytics Page)
    const studentEngagementCtx = document.getElementById('studentEngagementChart');
    if (studentEngagementCtx && chartData.studentEngagement) {
        destroyChartIfExists(studentEngagementChartInstance);
        studentEngagementChartInstance = new Chart(studentEngagementCtx, {
            type: 'line',
            data: chartData.studentEngagement,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Active Students' }
                    },
                    y1: { // Secondary Y-axis for time spent
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'Avg. Time Spent (hrs)' },
                        grid: { drawOnChartArea: false } // only want the grid lines for the first Y axis
                    }
                },
                plugins: { legend: { display: true } }
            }
        });
    }

    applyChartTheme([userGrowthChartInstance, learningActivityChartInstance, revenueChartInstance,
        userDemographicsChartInstance, studentEngagementChartInstance].filter(Boolean));

});
