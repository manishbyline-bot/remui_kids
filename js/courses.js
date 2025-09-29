/**
 * Courses & Programs JavaScript functionality
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Courses page functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Courses page loaded');
    
    // Initialize course cards interactions
    initializeCourseCards();
    
    // Initialize dashboard statistics
    initializeDashboardStats();
    
    // Initialize course management actions
    initializeCourseManagement();
});

/**
 * Initialize course cards functionality
 */
function initializeCourseCards() {
    const courseCards = document.querySelectorAll('.course-card');
    
    courseCards.forEach(card => {
        // Add hover effects
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
        });
    });
}

/**
 * Initialize dashboard statistics
 */
function initializeDashboardStats() {
    const statsCards = document.querySelectorAll('.dashboard-stat-card');
    
    statsCards.forEach(card => {
        // Add click animation
        card.addEventListener('click', function() {
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);
        });
    });
}

/**
 * Initialize course management actions
 */
function initializeCourseManagement() {
    // Handle course creation
    const createCourseBtn = document.querySelector('.create-course-btn');
    if (createCourseBtn) {
        createCourseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            // Add course creation logic here
            console.log('Create course clicked');
        });
    }
    
    // Handle course management
    const manageCourseBtn = document.querySelector('.manage-course-btn');
    if (manageCourseBtn) {
        manageCourseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            // Add course management logic here
            console.log('Manage course clicked');
        });
    }
}

/**
 * Show loading state
 */
function showLoading() {
    const loadingElement = document.createElement('div');
    loadingElement.className = 'loading-overlay';
    loadingElement.innerHTML = '<div class="spinner"></div>';
    document.body.appendChild(loadingElement);
}

/**
 * Hide loading state
 */
function hideLoading() {
    const loadingElement = document.querySelector('.loading-overlay');
    if (loadingElement) {
        loadingElement.remove();
    }
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Export functions for global use
window.CoursesPage = {
    showLoading,
    hideLoading,
    showNotification,
    initializeCourseCards,
    initializeDashboardStats,
    initializeCourseManagement
};
