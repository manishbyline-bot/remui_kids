/**
 * Department Managers JavaScript functionality
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Department Managers page functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Department Managers page loaded');
    
    // Initialize table interactions
    initializeTableInteractions();
    
    // Initialize manager actions
    initializeManagerActions();
    
    // Initialize mobile responsiveness
    initializeMobileResponsiveness();
});

/**
 * Initialize table interactions
 */
function initializeTableInteractions() {
    // Add sortable table headers functionality
    const sortableHeaders = document.querySelectorAll('.sortable');
    sortableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const sortField = this.getAttribute('data-sort');
            const currentSort = new URLSearchParams(window.location.search).get('sort') || 'id';
            const currentOrder = new URLSearchParams(window.location.search).get('order') || 'asc';
            
            // Determine new order
            let newOrder = 'asc';
            if (sortField === currentSort && currentOrder === 'asc') {
                newOrder = 'desc';
            }
            
            // Build new URL
            const url = new URL(window.location);
            url.searchParams.set('sort', sortField);
            url.searchParams.set('order', newOrder);
            
            // Navigate to new URL
            window.location.href = url.toString();
        });
    });

    // Add row click functionality
    const tableRows = document.querySelectorAll('.managers-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('click', function() {
            console.log('Row clicked:', this);
            // Add functionality to view manager details
        });
    });
}

/**
 * Initialize manager actions
 */
function initializeManagerActions() {
    // Handle edit buttons
    const editButtons = document.querySelectorAll('.edit-manager-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const managerId = this.getAttribute('data-manager-id');
            handleEditManager(managerId);
        });
    });

    // Handle delete buttons
    const deleteButtons = document.querySelectorAll('.delete-manager-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const managerId = this.getAttribute('data-manager-id');
            handleDeleteManager(managerId);
        });
    });

    // Handle role change buttons
    const roleButtons = document.querySelectorAll('.change-role-btn');
    roleButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const managerId = this.getAttribute('data-manager-id');
            const newRole = this.getAttribute('data-new-role');
            handleRoleChange(managerId, newRole);
        });
    });

    // Handle bulk actions
    const bulkEditBtn = document.querySelector('.bulk-edit-btn');
    if (bulkEditBtn) {
        bulkEditBtn.addEventListener('click', function(e) {
            e.preventDefault();
            handleBulkEdit();
        });
    }

    const bulkDeleteBtn = document.querySelector('.bulk-delete-btn');
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            handleBulkDelete();
        });
    }
}

/**
 * Handle edit manager
 */
function handleEditManager(managerId) {
    if (!managerId) return;
    
    showNotification('Opening manager edit form...', 'info');
    
    // Redirect to edit form or open modal
    // This would typically open a modal or redirect to an edit page
    console.log('Editing manager:', managerId);
}

/**
 * Handle delete manager
 */
function handleDeleteManager(managerId) {
    if (!managerId) return;
    
    if (confirm('Are you sure you want to delete this manager?')) {
        showNotification('Deleting manager...', 'info');
        
        // Add loading state
        const button = document.querySelector(`[data-manager-id="${managerId}"]`);
        if (button) {
            button.disabled = true;
            button.innerHTML = '⏳ Deleting...';
        }
        
        // Simulate API call (replace with actual implementation)
        setTimeout(() => {
            showNotification('Manager deleted successfully', 'success');
            // Remove row
            const row = button.closest('tr');
            if (row) {
                row.remove();
            }
        }, 1000);
    }
}

/**
 * Handle role change
 */
function handleRoleChange(managerId, newRole) {
    if (!managerId || !newRole) return;
    
    showNotification(`Changing role to ${newRole}...`, 'info');
    
    // Add loading state
    const button = document.querySelector(`[data-manager-id="${managerId}"]`);
    if (button) {
        button.disabled = true;
        button.innerHTML = '⏳ Changing...';
    }
    
    // Simulate API call (replace with actual implementation)
    setTimeout(() => {
        showNotification(`Role changed to ${newRole} successfully`, 'success');
        // Update role display
        const roleCell = button.closest('tr').querySelector('.role-cell');
        if (roleCell) {
            roleCell.textContent = newRole;
        }
        button.disabled = false;
        button.innerHTML = 'Change Role';
    }, 1000);
}

/**
 * Handle bulk edit
 */
function handleBulkEdit() {
    const selectedCheckboxes = document.querySelectorAll('.manager-checkbox:checked');
    if (selectedCheckboxes.length === 0) {
        showNotification('Please select at least one manager', 'warning');
        return;
    }
    
    showNotification(`Editing ${selectedCheckboxes.length} manager(s)...`, 'info');
    
    // Process each selected manager
    selectedCheckboxes.forEach(checkbox => {
        const managerId = checkbox.getAttribute('data-manager-id');
        if (managerId) {
            handleEditManager(managerId);
        }
    });
}

/**
 * Handle bulk delete
 */
function handleBulkDelete() {
    const selectedCheckboxes = document.querySelectorAll('.manager-checkbox:checked');
    if (selectedCheckboxes.length === 0) {
        showNotification('Please select at least one manager', 'warning');
        return;
    }
    
    if (confirm(`Are you sure you want to delete ${selectedCheckboxes.length} manager(s)?`)) {
        showNotification(`Deleting ${selectedCheckboxes.length} manager(s)...`, 'info');
        
        // Process each selected manager
        selectedCheckboxes.forEach(checkbox => {
            const managerId = checkbox.getAttribute('data-manager-id');
            if (managerId) {
                handleDeleteManager(managerId);
            }
        });
    }
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
        <div class="mobile-logo">Department Managers</div>
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
 * Show notification
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : type === 'warning' ? '#ffc107' : type === 'error' ? '#dc3545' : '#17a2b8'};
        color: white;
        padding: 10px 20px;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        z-index: 10000;
        font-size: 14px;
        opacity: 0;
        transition: opacity 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => {
        notification.style.opacity = '1';
    }, 100);
    
    // Hide notification after 3 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 300);
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
window.DepartmentManagers = {
    initializeTableInteractions,
    initializeManagerActions,
    initializeMobileResponsiveness,
    handleEditManager,
    handleDeleteManager,
    handleRoleChange,
    handleBulkEdit,
    handleBulkDelete,
    showNotification
};
