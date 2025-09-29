

/**
 * Recent Uploads JavaScript functionality
 * @package theme_remui_kids
 * @copyright 2024 Riyada Trainings
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Recent Uploads page functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Recent Uploads page loaded');
    
    // Initialize table interactions
    initializeTableInteractions();
    
    // Initialize upload actions
    initializeUploadActions();
    
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
    const tableRows = document.querySelectorAll('.uploads-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('click', function() {
            console.log('Row clicked:', this);
            // Add functionality to view upload details
        });
    });
}

/**
 * Initialize upload actions
 */
function initializeUploadActions() {
    // Handle view buttons
    const viewButtons = document.querySelectorAll('.view-upload-btn');
    viewButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const uploadId = this.getAttribute('data-upload-id');
            handleViewUpload(uploadId);
        });
    });

    // Handle download buttons
    const downloadButtons = document.querySelectorAll('.download-upload-btn');
    downloadButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const uploadId = this.getAttribute('data-upload-id');
            handleDownloadUpload(uploadId);
        });
    });

    // Handle delete buttons
    const deleteButtons = document.querySelectorAll('.delete-upload-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const uploadId = this.getAttribute('data-upload-id');
            handleDeleteUpload(uploadId);
        });
    });

    // Handle reprocess buttons
    const reprocessButtons = document.querySelectorAll('.reprocess-upload-btn');
    reprocessButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const uploadId = this.getAttribute('data-upload-id');
            handleReprocessUpload(uploadId);
        });
    });

    // Handle bulk actions
    const bulkDownloadBtn = document.querySelector('.bulk-download-btn');
    if (bulkDownloadBtn) {
        bulkDownloadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            handleBulkDownload();
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
 * Handle view upload
 */
function handleViewUpload(uploadId) {
    if (!uploadId) return;
    
    showNotification('Opening upload details...', 'info');
    
    // Open modal or redirect to view page
    console.log('Viewing upload:', uploadId);
}

/**
 * Handle download upload
 */
function handleDownloadUpload(uploadId) {
    if (!uploadId) return;
    
    showNotification('Preparing download...', 'info');
    
    // Add loading state
    const button = document.querySelector(`[data-upload-id="${uploadId}"]`);
    if (button) {
        button.disabled = true;
        button.innerHTML = '⏳ Downloading...';
    }
    
    // Simulate download (replace with actual implementation)
    setTimeout(() => {
        showNotification('Download started', 'success');
        button.disabled = false;
        button.innerHTML = '📥 Download';
    }, 1000);
}

/**
 * Handle delete upload
 */
function handleDeleteUpload(uploadId) {
    if (!uploadId) return;
    
    if (confirm('Are you sure you want to delete this upload?')) {
        showNotification('Deleting upload...', 'info');
        
        // Add loading state
        const button = document.querySelector(`[data-upload-id="${uploadId}"]`);
        if (button) {
            button.disabled = true;
            button.innerHTML = '⏳ Deleting...';
        }
        
        // Simulate API call (replace with actual implementation)
        setTimeout(() => {
            showNotification('Upload deleted successfully', 'success');
            // Remove row
            const row = button.closest('tr');
            if (row) {
                row.remove();
            }
        }, 1000);
    }
}

/**
 * Handle reprocess upload
 */
function handleReprocessUpload(uploadId) {
    if (!uploadId) return;
    
    showNotification('Reprocessing upload...', 'info');
    
    // Add loading state
    const button = document.querySelector(`[data-upload-id="${uploadId}"]`);
    if (button) {
        button.disabled = true;
        button.innerHTML = '⏳ Reprocessing...';
    }
    
    // Simulate API call (replace with actual implementation)
    setTimeout(() => {
        showNotification('Upload reprocessed successfully', 'success');
        button.disabled = false;
        button.innerHTML = '🔄 Reprocess';
    }, 2000);
}

/**
 * Handle bulk download
 */
function handleBulkDownload() {
    const selectedCheckboxes = document.querySelectorAll('.upload-checkbox:checked');
    if (selectedCheckboxes.length === 0) {
        showNotification('Please select at least one upload', 'warning');
        return;
    }
    
    showNotification(`Preparing download for ${selectedCheckboxes.length} upload(s)...`, 'info');
    
    // Process each selected upload
    selectedCheckboxes.forEach(checkbox => {
        const uploadId = checkbox.getAttribute('data-upload-id');
        if (uploadId) {
            handleDownloadUpload(uploadId);
        }
    });
}

/**
 * Handle bulk delete
 */
function handleBulkDelete() {
    const selectedCheckboxes = document.querySelectorAll('.upload-checkbox:checked');
    if (selectedCheckboxes.length === 0) {
        showNotification('Please select at least one upload', 'warning');
        return;
    }
    
    if (confirm(`Are you sure you want to delete ${selectedCheckboxes.length} upload(s)?`)) {
        showNotification(`Deleting ${selectedCheckboxes.length} upload(s)...`, 'info');
        
        // Process each selected upload
        selectedCheckboxes.forEach(checkbox => {
            const uploadId = checkbox.getAttribute('data-upload-id');
            if (uploadId) {
                handleDeleteUpload(uploadId);
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
        <div class="mobile-logo">Recent Uploads</div>
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
window.RecentUploads = {
    initializeTableInteractions,
    initializeUploadActions,
    initializeMobileResponsiveness,
    handleViewUpload,
    handleDownloadUpload,
    handleDeleteUpload,
    handleReprocessUpload,
    handleBulkDownload,
    handleBulkDelete,
    showNotification
};
