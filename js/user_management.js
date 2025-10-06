/**
 * User Management Dashboard JavaScript functionality
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// User Management page functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('User Management page loaded');
    
    // Initialize tab functionality
    initializeTabs();
    
    // Initialize statistics cards
    initializeStatisticsCards();
    
    // Initialize quick actions
    initializeQuickActions();
    
    // Initialize mobile responsiveness
    initializeMobileResponsiveness();
});

/**
 * Initialize tab functionality
 */
function initializeTabs() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all tabs
            tabButtons.forEach(btn => btn.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Get the tab data
            const tabName = this.getAttribute('data-tab');
            console.log('Switched to tab:', tabName);
            
            // Handle tab-specific actions
            handleTabAction(tabName);
        });
    });
}

/**
 * Handle tab-specific actions
 */
function handleTabAction(tabName) {
    switch(tabName) {
        case 'overview':
            // Overview tab - stay on current page
            console.log('Overview tab selected - staying on current page');
            break;
            
        case 'create':
            // Redirect to create user form
            window.location.href = 'http://localhost/Kodeit-Iomad-local/iomad-test/blocks/iomad_company_admin/company_user_create_form.php';
            break;
            
        case 'edit':
            // Redirect to edit users page
            window.location.href = 'http://localhost/Kodeit-Iomad-local/iomad-test/blocks/iomad_company_admin/editusers.php';
            break;
            
        case 'department':
            // Redirect to department managers form
            window.location.href = 'http://localhost/Kodeit-Iomad-local/iomad-test/blocks/iomad_company_admin/company_managers_form.php';
            break;
            
        case 'assign':
            // Redirect to company users form
            window.location.href = 'http://localhost/Kodeit-Iomad-local/iomad-test/blocks/iomad_company_admin/company_users_form.php';
            break;
            
        case 'upload':
            // Redirect to upload user page
            window.location.href = 'http://localhost/Kodeit-Iomad-local/iomad-test/blocks/iomad_company_admin/uploaduser.php';
            break;
            
        case 'download':
            // Redirect to user bulk download page
            window.location.href = 'http://localhost/Kodeit-Iomad-local/iomad-test/blocks/iomad_company_admin/user_bulk_download.php';
            break;
            
        case 'training':
            // Redirect to approve page
            window.location.href = 'http://localhost/Kodeit-Iomad-local/iomad-test/blocks/iomad_approve_access/approve.php';
            break;
    }
}

/**
 * Initialize statistics cards
 */
function initializeStatisticsCards() {
    const statCards = document.querySelectorAll('.stat-card');
    
    statCards.forEach(card => {
        // Add hover effects
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
        });
        
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
 * Initialize quick actions
 */
function initializeQuickActions() {
    const actionButtons = document.querySelectorAll('.action-btn');
    
    actionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Add click animation
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);
            
            // Handle action based on class
            if (this.classList.contains('create')) {
                console.log('Create user action clicked');
            } else if (this.classList.contains('upload')) {
                console.log('Bulk upload action clicked');
            } else if (this.classList.contains('export')) {
                console.log('Export users action clicked');
            } else if (this.classList.contains('approve')) {
                console.log('Approve events action clicked');
            }
            
            // Force navigation using window.location
            e.preventDefault();
            window.location.href = this.href;
            return false;
        });
    });
}

/**
 * Initialize mobile responsiveness
 */
function initializeMobileResponsiveness() {
    // Create mobile header if needed
    if (window.innerWidth <= 768) {
        createMobileHeader();
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth <= 768) {
            createMobileHeader();
        } else {
            removeMobileHeader();
        }
    });
}

/**
 * Create mobile header
 */
function createMobileHeader() {
    if (document.querySelector('.mobile-header')) return;
    
    const mobileHeader = document.createElement('div');
    mobileHeader.className = 'mobile-header';
    mobileHeader.innerHTML = `
        <button class="hamburger-menu" onclick="toggleSidebar()">
            ☰
        </button>
        <div class="mobile-logo">User Management</div>
    `;
    
    document.body.insertBefore(mobileHeader, document.body.firstChild);
    
    // Create sidebar overlay
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    overlay.onclick = closeSidebar;
    document.body.appendChild(overlay);
}

/**
 * Remove mobile header
 */
function removeMobileHeader() {
    const mobileHeader = document.querySelector('.mobile-header');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (mobileHeader) mobileHeader.remove();
    if (overlay) overlay.remove();
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

// Global functions for sidebar
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar, #riyada-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar && overlay) {
        sidebar.classList.toggle('open');
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('show');
    }
}

function closeSidebar() {
    const sidebar = document.querySelector('.sidebar, #riyada-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar && overlay) {
        sidebar.classList.remove('open');
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('show');
    }
}

// Export functions for global use
window.UserManagement = {
    showLoading,
    hideLoading,
    showNotification,
    initializeTabs,
    initializeStatisticsCards,
    initializeQuickActions,
    initializeMobileResponsiveness
};
