
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

    // Helper function to destroy existing chart if it exists
    function destroyChartIfExists(chartInstance) {
        if (chartInstance) {
            chartInstance.destroy();
        }
    }
    
    let userGrowthChartInstance, learningActivityChartInstance, revenueChartInstance, userDemographicsChartInstance, studentEngagementChartInstance;

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

});

