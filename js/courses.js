/**
 * Courses List JavaScript functionality
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Global variables for enrolled users modal
let allUsers = [];
let filteredUsers = [];

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Courses List page loaded');
    
    // Initialize course cards
    initializeCourseCards();
    
    // Initialize page identification for sidebar
    initializePageIdentification();
    
    // Initialize search functionality
    initializeSearch();
    
    // Initialize modal functionality
    initializeModal();
});

/**
 * Initialize course cards functionality
 */
function initializeCourseCards() {
    const courseCards = document.querySelectorAll('.course-card');
    
    courseCards.forEach(card => {
        // Add hover effects
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px)';
            this.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.12)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 2px 8px rgba(0, 0, 0, 0.08)';
        });
        
        // Add click animation to action icons
        const actionIcons = card.querySelectorAll('.action-icon');
        actionIcons.forEach(icon => {
            icon.addEventListener('click', function(e) {
                // Add click animation
                this.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
        });
    });
}

/**
 * Initialize page identification for sidebar
 */
function initializePageIdentification() {
    const currentPath = window.location.pathname;
    console.log('Admin - Current path:', currentPath);
    
    // Extract page identifier from path
    let currentPage = null;
    if (currentPath.includes('courses_list.php')) {
        currentPage = 'coursesprogram';
    } else if (currentPath.includes('courses_program.php')) {
        currentPage = 'coursesprogram';
    } else if (currentPath.includes('categories_list.php')) {
        currentPage = 'coursesprogram';
    } else if (currentPath.includes('learning_paths.php')) {
        currentPage = 'coursesprogram';
    }
    
    console.log('Admin - Current page:', currentPage);
    
    if (currentPage) {
        // Find and activate the corresponding sidebar link
        const activeLink = document.querySelector(`[data-page="${currentPage}"]`);
        console.log('Admin - Active link found:', activeLink);
        
        if (activeLink) {
            // Remove active class from all links
            document.querySelectorAll('.riyada-nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            // Add active class to current link
            activeLink.classList.add('active');
            console.log('Admin - Added active class to:', activeLink);
        } else {
            console.log('Admin - No active link found for page:', currentPage);
            
            // Debug: List all available data-page attributes
            const allLinks = document.querySelectorAll('[data-page]');
            console.log('Admin - Available data-page attributes:', Array.from(allLinks).map(link => link.getAttribute('data-page')));
        }
    }
}

/**
 * Initialize search functionality
 */
function initializeSearch() {
    const searchInput = document.getElementById('userSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', performUserSearch);
    }
    
    const clearBtn = document.querySelector('.clear-search-btn');
    if (clearBtn) {
        clearBtn.addEventListener('click', clearUserSearch);
    }
}

/**
 * Initialize modal functionality
 */
function initializeModal() {
    // Modal close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEnrolledUsersModal();
        }
    });
    
    // Modal close on outside click
    const modal = document.getElementById('enrolledUsersModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeEnrolledUsersModal();
            }
        });
    }
}

/**
 * Show enrolled users modal
 */
function showEnrolledUsers(courseId, courseName) {
    const modal = document.getElementById('enrolledUsersModal');
    const modalTitle = document.getElementById('modalTitle');
    const usersList = document.getElementById('usersList');
    
    if (!modal || !modalTitle || !usersList) {
        console.error('Modal elements not found');
        return;
    }
    
    modalTitle.textContent = `Enrolled Users - ${courseName}`;
    modal.style.display = 'block';
    
    // Show loading spinner
    usersList.innerHTML = `
        <div class="loading-spinner">
            <i class="fa fa-spinner fa-spin"></i>
            <span>Loading users...</span>
        </div>
    `;
    
    // Fetch enrolled users
    const wwwroot = window.MOODLE_WWWROOT || '';
    const sesskey = document.querySelector('input[name="sesskey"]')?.value || '';
    
    fetch(`${wwwroot}/theme/remui_kids/get_enrolled_users.php?courseid=${courseId}&sesskey=${sesskey}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allUsers = data.users;
                filteredUsers = [...allUsers];
                renderUsers();
                updateResultsCount();
            } else {
                usersList.innerHTML = `
                    <div class="no-users">
                        <i class="fa fa-users"></i>
                        <p>No enrolled users found</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error fetching enrolled users:', error);
            usersList.innerHTML = `
                <div class="no-users">
                    <i class="fa fa-exclamation-triangle"></i>
                    <p>Error loading users</p>
                </div>
            `;
        });
}

/**
 * Close enrolled users modal
 */
function closeEnrolledUsersModal() {
    const modal = document.getElementById('enrolledUsersModal');
    if (modal) {
        modal.style.display = 'none';
        allUsers = [];
        filteredUsers = [];
        
        // Clear search input
        const searchInput = document.getElementById('userSearchInput');
        if (searchInput) {
            searchInput.value = '';
        }
        
        // Hide clear button
        const clearBtn = document.querySelector('.clear-search-btn');
        if (clearBtn) {
            clearBtn.style.display = 'none';
        }
    }
}

/**
 * Render users list
 */
function renderUsers() {
    const usersList = document.getElementById('usersList');
    if (!usersList) return;
    
    if (filteredUsers.length === 0) {
        usersList.innerHTML = `
            <div class="no-users">
                <i class="fa fa-search"></i>
                <p>No users found matching your search</p>
            </div>
        `;
        return;
    }
    
    usersList.innerHTML = filteredUsers.map(user => `
        <div class="user-item">
            <div class="user-avatar">
                <i class="fa fa-user"></i>
            </div>
            <div class="user-info">
                <div class="user-name">${escapeHtml(user.fullname)}</div>
                <div class="user-email">${escapeHtml(user.email)}</div>
                <div class="user-role">${escapeHtml(user.role)}</div>
            </div>
        </div>
    `).join('');
}

/**
 * Update results count
 */
function updateResultsCount() {
    const resultsCount = document.getElementById('resultsCount');
    if (resultsCount) {
        const count = filteredUsers.length;
        resultsCount.textContent = `${count} user${count !== 1 ? 's' : ''} found`;
    }
}

/**
 * Perform user search
 */
function performUserSearch() {
    const searchInput = document.getElementById('userSearchInput');
    const clearBtn = document.querySelector('.clear-search-btn');
    
    if (!searchInput) return;
    
    const query = searchInput.value.toLowerCase().trim();
    
    if (query === '') {
        filteredUsers = [...allUsers];
        if (clearBtn) clearBtn.style.display = 'none';
    } else {
        filteredUsers = allUsers.filter(user => 
            user.fullname.toLowerCase().includes(query) ||
            user.email.toLowerCase().includes(query) ||
            user.role.toLowerCase().includes(query)
        );
        if (clearBtn) clearBtn.style.display = 'block';
    }
    
    renderUsers();
    updateResultsCount();
}

/**
 * Clear user search
 */
function clearUserSearch() {
    const searchInput = document.getElementById('userSearchInput');
    const clearBtn = document.querySelector('.clear-search-btn');
    
    if (searchInput) {
        searchInput.value = '';
    }
    
    if (clearBtn) {
        clearBtn.style.display = 'none';
    }
    
    filteredUsers = [...allUsers];
    renderUsers();
    updateResultsCount();
}

/**
 * Delete course function
 */
function deleteCourse(courseId, courseName) {
    if (confirm(`Are you sure you want to delete the course "${courseName}"? This action cannot be undone.`)) {
        const wwwroot = window.MOODLE_WWWROOT || '';
        const sesskey = document.querySelector('input[name="sesskey"]')?.value || '';
        
        // Show loading state
        const courseCard = document.querySelector(`[data-course-id="${courseId}"]`);
        if (courseCard) {
            courseCard.classList.add('loading');
        }
        
        fetch(`${wwwroot}/theme/remui_kids/delete_course.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `courseid=${courseId}&sesskey=${sesskey}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove the course card from the DOM
                if (courseCard) {
                    courseCard.style.opacity = '0';
                    setTimeout(() => {
                        courseCard.remove();
                    }, 300);
                }
                
                // Show success message
                showNotification('Course deleted successfully!', 'success');
            } else {
                // Remove loading state
                if (courseCard) {
                    courseCard.classList.remove('loading');
                }
                
                showNotification('Error deleting course: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error deleting course:', error);
            
            // Remove loading state
            if (courseCard) {
                courseCard.classList.remove('loading');
            }
            
            showNotification('Error deleting course. Please try again.', 'error');
        });
    }
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Add styles if not already present
    if (!document.querySelector('#notification-styles')) {
        const styles = document.createElement('style');
        styles.id = 'notification-styles';
        styles.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                z-index: 10000;
                animation: slideIn 0.3s ease;
                max-width: 300px;
            }
            .notification-success {
                background: #28a745;
            }
            .notification-error {
                background: #dc3545;
            }
            .notification-info {
                background: #17a2b8;
            }
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(styles);
    }
    
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }, 3000);
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Initialize sidebar scroll functionality
 */
function initializeSidebarScroll() {
    const sidebar = document.querySelector('#riyada-sidebar-nav');
    if (sidebar) {
        const scrollHeight = sidebar.scrollHeight;
        const clientHeight = sidebar.clientHeight;
        const canScroll = scrollHeight > clientHeight;
        
        console.log('Sidebar scroll initialized:', {
            element: sidebar,
            scrollHeight: scrollHeight,
            clientHeight: clientHeight,
            canScroll: canScroll
        });
        
        // Add scroll behavior if needed
        if (canScroll) {
            sidebar.style.overflowY = 'auto';
        }
    }
}

// Initialize sidebar scroll when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeSidebarScroll();
});

// Export functions for global use
window.CoursesList = {
    showEnrolledUsers,
    closeEnrolledUsersModal,
    deleteCourse,
    showNotification,
    initializeCourseCards,
    initializePageIdentification,
    initializeSearch,
    initializeModal
};