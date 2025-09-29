/**
 * Pending Approvals JavaScript functionality
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Pending Approvals page functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Pending Approvals page loaded');
    
    // Initialize table interactions
    initializeTableInteractions();
    
    // Initialize approval actions
    initializeApprovalActions();
    
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
    const tableRows = document.querySelectorAll('.approvals-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('click', function() {
            console.log('Row clicked:', this);
            // Add functionality to view approval details
        });
    });
}

/**
 * Initialize approval actions
 */
function initializeApprovalActions() {
    // Handle approve buttons
    const approveButtons = document.querySelectorAll('.approve-btn');
    approveButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const approvalId = this.getAttribute('data-approval-id');
            handleApproval(approvalId, 'approve');
        });
    });

    // Handle reject buttons
    const rejectButtons = document.querySelectorAll('.reject-btn');
    rejectButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const approvalId = this.getAttribute('data-approval-id');
            handleApproval(approvalId, 'reject');
        });
    });

    // Handle bulk actions
    const bulkApproveBtn = document.querySelector('.bulk-approve-btn');
    if (bulkApproveBtn) {
        bulkApproveBtn.addEventListener('click', function(e) {
            e.preventDefault();
            handleBulkApproval('approve');
        });
    }

    const bulkRejectBtn = document.querySelector('.bulk-reject-btn');
    if (bulkRejectBtn) {
        bulkRejectBtn.addEventListener('click', function(e) {
            e.preventDefault();
            handleBulkApproval('reject');
        });
    }
}

/**
 * Handle individual approval
 */
function handleApproval(approvalId, action) {
    if (!approvalId) return;
    
    const actionText = action === 'approve' ? 'approving' : 'rejecting';
    showNotification(`${actionText} approval...`, 'info');
    
    // Add loading state
    const button = document.querySelector(`[data-approval-id="${approvalId}"]`);
    if (button) {
        button.disabled = true;
        button.innerHTML = action === 'approve' ? '⏳ Approving...' : '⏳ Rejecting...';
    }
    
    // Simulate API call (replace with actual implementation)
    setTimeout(() => {
        showNotification(`Approval ${action}d successfully`, 'success');
        // Remove row or update status
        if (button) {
            const row = button.closest('tr');
            if (row) {
                row.style.opacity = '0.5';
                row.style.textDecoration = 'line-through';
            }
        }
    }, 1000);
}

/**
 * Handle bulk approval
 */
function handleBulkApproval(action) {
    const selectedCheckboxes = document.querySelectorAll('.approval-checkbox:checked');
    if (selectedCheckboxes.length === 0) {
        showNotification('Please select at least one approval', 'warning');
        return;
    }
    
    const actionText = action === 'approve' ? 'approving' : 'rejecting';
    showNotification(`${actionText} ${selectedCheckboxes.length} approval(s)...`, 'info');
    
    // Process each selected approval
    selectedCheckboxes.forEach(checkbox => {
        const approvalId = checkbox.getAttribute('data-approval-id');
        if (approvalId) {
            handleApproval(approvalId, action);
        }
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
        <div class="mobile-logo">Pending Approvals</div>
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
window.PendingApprovals = {
    initializeTableInteractions,
    initializeApprovalActions,
    initializeMobileResponsiveness,
    handleApproval,
    handleBulkApproval,
    showNotification
};
